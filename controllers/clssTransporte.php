<?php
ob_start();
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/clssVerificarSession.php';
require_once __DIR__ . '/tipoCambio.php';

if (isset($_POST['accion'])) {
    try {
        $usuario = exigirSesion();
        exigirRol($usuario, ['administrador', 'ventas'], 'ventas');
        controladorTransporte((string)$_POST['accion']);
    } catch (PDOException $e) {
        error_log('Error DB transporte: ' . $e->getMessage());
        responderTransporte(false, 'Error de base de datos. Ejecuta primero sql/create_transporte_module.sql y revisa los permisos.');
    } catch (Throwable $e) {
        error_log('Error transporte: ' . $e->getMessage());
        responderTransporte(false, 'No se pudo completar la operación.');
    }
}

function responderTransporte(bool $success, string $message, array $extra = []): void
{
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function controladorTransporte(string $accion): void
{
    switch ($accion) {
        case 'LISTARTRANSPORTES': listarTransportes(); break;
        case 'GUARDARTRANSPORTE': guardarTransporte(); break;
        case 'ELIMINARTRANSPORTE': eliminarTransporte(); break;
        case 'LISTARDATOSDESPACHO': listarDatosDespacho(); break;
        case 'BUSCARPAQUETEQR': buscarPaqueteQrDespacho(); break;
        case 'CONSULTARTIPOCAMBIO': consultarTipoCambioDespacho(); break;
        case 'LISTARVIAJES': listarViajes(); break;
        case 'GUARDARVIAJE': guardarViaje(); break;
        case 'ELIMINARVIAJE': eliminarViaje(); break;
        case 'GUARDARDESPACHO': guardarDespacho(); break;
        case 'CAMBIARESTADODESPACHO': cambiarEstadoDespacho(); break;
        default: responderTransporte(false, 'Acción no reconocida.');
    }
}

function listarTransportes(): void
{
    $texto = trim((string)($_POST['texto'] ?? ''));
    $estado = (string)($_POST['estado'] ?? 'activos');
    $where = ['1=1']; $params = [];
    if ($texto !== '') { $where[] = '(LOWER(nombre) LIKE LOWER(:texto) OR LOWER(COALESCE(placa, \'\')) LIKE LOWER(:texto))'; $params['texto'] = '%' . $texto . '%'; }
    if ($estado === 'inactivos') $where[] = 'deleted_at IS NOT NULL';
    elseif ($estado !== 'todos') $where[] = 'deleted_at IS NULL';
    $rows = executeQuery(conectar_oll_BD(), 'SELECT id,nombre,placa,capacidad_carga,created_at,update_at,deleted_at FROM transporte WHERE ' . implode(' AND ', $where) . ' ORDER BY nombre', $params);
    responderTransporte(true, 'OK', ['transportes' => $rows]);
}

function guardarTransporte(): void
{
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $placa = strtoupper(trim((string)($_POST['placa'] ?? '')));
    $capacidad = trim((string)($_POST['capacidad_carga'] ?? ''));
    $longitud = static function (string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); };
    if ($nombre === '' || $longitud($nombre) > 30) responderTransporte(false, 'Ingresa un nombre de hasta 30 caracteres.');
    if ($longitud($placa) > 20) responderTransporte(false, 'La placa admite hasta 20 caracteres.');
    if ($capacidad !== '' && (!is_numeric($capacidad) || (float)$capacidad <= 0)) responderTransporte(false, 'La capacidad debe ser mayor que cero.');
    $db = conectar_oll_BD();
    $dupe = executeQuery($db, 'SELECT id FROM transporte WHERE LOWER(nombre)=LOWER(:nombre) AND id<>:id AND deleted_at IS NULL LIMIT 1', ['nombre'=>$nombre,'id'=>$id]);
    if ($dupe) responderTransporte(false, 'Ya existe un transporte activo con ese nombre.');
    if ($id > 0) {
        executeNonQuery($db, 'UPDATE transporte SET nombre=:nombre,placa=:placa,capacidad_carga=:capacidad,update_at=NOW(),deleted_at=NULL WHERE id=:id', ['nombre'=>$nombre,'placa'=>$placa ?: null,'capacidad'=>$capacidad !== '' ? $capacidad : null,'id'=>$id]);
    } else {
        executeNonQuery($db, 'INSERT INTO transporte(nombre,placa,capacidad_carga,created_at) VALUES(:nombre,:placa,:capacidad,NOW())', ['nombre'=>$nombre,'placa'=>$placa ?: null,'capacidad'=>$capacidad !== '' ? $capacidad : null]);
    }
    responderTransporte(true, $id ? 'Transporte actualizado.' : 'Transporte creado.');
}

function eliminarTransporte(): void
{
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) responderTransporte(false, 'Transporte inválido.');
    $uso = executeQuery(conectar_oll_BD(), 'SELECT 1 FROM movimiento_transporte WHERE transporte_id=:id AND deleted_at IS NULL LIMIT 1', ['id'=>$id]);
    if ($uso) responderTransporte(false, 'Este vehículo ya tiene viajes registrados; puedes editarlo, pero no eliminarlo.');
    executeNonQuery(conectar_oll_BD(), 'UPDATE transporte SET deleted_at=NOW(),update_at=NOW() WHERE id=:id', ['id'=>$id]);
    responderTransporte(true, 'Transporte desactivado.');
}

function jsonArrayForm(string $key, int $max = 30): array
{
    $raw = json_decode((string)($_POST[$key] ?? '[]'), true);
    if (!is_array($raw)) return [];
    $values = [];
    foreach (array_slice($raw, 0, $max) as $value) {
        if (is_string($value)) $value = trim($value);
        if ($value !== '' && $value !== null && $value !== []) $values[] = $value;
    }
    return $values;
}

function listarDatosDespacho(): void
{
    $db = conectar_oll_BD();
    $transportes = executeQuery($db, 'SELECT id,nombre,placa,capacidad_carga FROM transporte WHERE deleted_at IS NULL ORDER BY nombre');
    $clientes = executeQuery($db, "SELECT ruc,razon_social,nombre_comercial FROM proveedor WHERE deleted_at IS NULL AND js_tipo @> '[\"cliente\"]'::jsonb ORDER BY razon_social");
    $monedas = executeQuery($db, 'SELECT id,codigo,nombre,simbolo FROM moneda WHERE COALESCE(estado,TRUE)=TRUE AND deleted_at IS NULL ORDER BY codigo');
    $productos = executeQuery($db, 'SELECT p.id,p.codigo,p.descripcion,p.unidad_venta_id,COALESCE(um.nombre_corto,\'UND\') AS unidad,COALESCE(p.stock,0) AS stock FROM producto p LEFT JOIN unidad_medida um ON um.id=p.unidad_venta_id WHERE p.activo IS TRUE ORDER BY p.descripcion');
    responderTransporte(true, 'OK', ['transportes'=>$transportes,'clientes'=>$clientes,'monedas'=>$monedas,'productos'=>$productos]);
}

/** Consulta un paquete identificado por el QR SKU de empaquetado, sin mover stock. */
function buscarPaqueteQrDespacho(): void
{
    $codigo = strtoupper(trim((string)($_POST['codigo_qr'] ?? '')));
    if (!preg_match('/^A-(\d{10})-(\d{5})-(\d{5})-(\d{10})$/', $codigo, $qr)) {
        responderTransporte(false, 'El QR no tiene el formato de un sticker de paquete de Empaquetado.');
    }
    $rows = executeQuery(conectar_oll_BD(), "
        SELECT emp.id AS empaquetado_id, emp.sucursal, emp.producto_id, emp.emsamblaje_id,
               emp.cantidad_tota, COALESCE(emp.cantidad_disponible_tmp, emp.cantidad_tota) AS cantidad_disponible,
               emp.pasado_venta, emp.unidad_medida AS unidad_paquete_id,
               um.nombre_corto AS unidad_paquete,
               p.codigo, p.descripcion, p.unidad_venta_id, p.stock,
               COALESCE(uv.nombre_corto, 'UND') AS unidad_venta
        FROM empaquetado emp
        JOIN producto p ON p.id = emp.producto_id
        LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
        LEFT JOIN unidad_medida uv ON uv.id = p.unidad_venta_id
        WHERE emp.id = :id AND emp.deleted_at IS NULL
    ", ['id' => (int)$qr[1]]);
    if (!$rows) responderTransporte(false, 'No se encontró un paquete activo con ese QR.');
    $p = $rows[0];
    $esperado = sprintf('A-%010d-%05d-%05d-%010d', (int)$p['empaquetado_id'], (int)$p['sucursal'], (int)$p['producto_id'], (int)($p['emsamblaje_id'] ?? 0));
    if (!hash_equals($esperado, $codigo)) responderTransporte(false, 'El QR no coincide con los datos del paquete.');
    if (in_array($p['pasado_venta'] ?? false, [true, 1, '1', 't', 'true'], true) || (float)$p['cantidad_disponible'] <= 0) {
        responderTransporte(false, 'Este paquete figura vendido o no tiene saldo disponible.');
    }
    $asociado = executeQuery(conectar_oll_BD(), 'SELECT 1 FROM rel_venta_producto WHERE empaquetado_id=:id AND deleted_at IS NULL LIMIT 1', ['id' => (int)$p['empaquetado_id']]);
    if ($asociado) responderTransporte(false, 'Este paquete ya fue vinculado a un despacho.');
    responderTransporte(true, 'Paquete encontrado. Verifica cantidad y precio antes de guardar.', [
        'paquete' => [
            'codigo_qr' => $codigo, 'empaquetado_id' => (int)$p['empaquetado_id'],
            'producto_id' => (int)$p['producto_id'], 'producto' => trim(($p['codigo'] ?? '') . ' - ' . ($p['descripcion'] ?? '')),
            'unidad_venta' => $p['unidad_venta'], 'stock_producto' => (float)($p['stock'] ?? 0),
            'cantidad_disponible_paquete' => (float)$p['cantidad_disponible'], 'unidad_paquete' => $p['unidad_paquete'] ?? 'UND',
        ],
    ]);
}

function consultarTipoCambioDespacho(): void
{
    $fecha = trim((string)($_POST['fecha'] ?? ''));
    $moneda = strtoupper(trim((string)($_POST['moneda'] ?? '')));
    try {
        responderTransporte(true, 'OK', ['tipo_cambio' => consultarTipoCambio($fecha, $moneda)]);
    } catch (Throwable $e) {
        responderTransporte(false, $e->getMessage());
    }
}

function listarViajes(): void
{
    $rows = executeQuery(conectar_oll_BD(), "
        SELECT mt.id,mt.transporte_id,mt.fecha_translado,mt.js_transportistas,mt.js_ubicaciones_ruta,mt.created_at,
               t.nombre AS transporte_nombre,t.placa,t.capacidad_carga,
               COUNT(DISTINCT (mv.js_session->>'registro_uid')) FILTER (WHERE mv.deleted_at IS NULL) AS despachos,
               COALESCE(SUM(COALESCE(NULLIF(mv.js_session #>> '{monto_total_pen}','')::numeric,mv.monto)) FILTER (WHERE mv.deleted_at IS NULL),0) AS monto_total
        FROM movimiento_transporte mt
        LEFT JOIN transporte t ON t.id=mt.transporte_id
        LEFT JOIN movimiento_transporte_venta mv ON mv.movimiento_transporte_id=mt.id
        WHERE mt.deleted_at IS NULL
        GROUP BY mt.id,t.nombre,t.placa,t.capacidad_carga
        ORDER BY mt.fecha_translado DESC,mt.id DESC LIMIT 300
    ");
    $despachos = executeQuery(conectar_oll_BD(), "SELECT js_session->>'registro_uid' AS registro_uid,movimiento_transporte_id,cliente,cliente_ruc,fecha_venta,estado,monto,moneda,COALESCE(NULLIF(js_session #>> '{monto_total_pen}','')::numeric,CASE WHEN moneda='PEN' THEN monto ELSE monto*COALESCE(NULLIF(js_session #>> '{tipo_cambio,venta}','')::numeric,NULLIF(js_session #>> '{tipo_cambio,tasa_aplicada}','')::numeric,1) END) AS monto_total_pen,COALESCE(NULLIF(js_session #>> '{monto_total_cobro}','')::numeric,monto) AS monto_total_cobro,js_session #>> '{tipo_cambio,fuente}' AS tipo_cambio_fuente,COALESCE(NULLIF(js_session #>> '{tipo_cambio,venta}','')::numeric,NULLIF(js_session #>> '{tipo_cambio,tasa_aplicada}','')::numeric,1) AS tipo_cambio_aplicado,js_session #>> '{tipo_cambio,fecha}' AS fecha_tipo_cambio FROM movimiento_transporte_venta WHERE deleted_at IS NULL ORDER BY created_at DESC");
    $detalles = executeQuery(conectar_oll_BD(), "SELECT js_session->>'registro_uid' AS registro_uid,producto,producto_id,cantidad,unidad_venta_corto,precio_unitario_manual,sub_total_calcular,descuento FROM rel_venta_producto WHERE deleted_at IS NULL ORDER BY created_at");
    responderTransporte(true, 'OK', ['viajes'=>$rows,'despachos'=>$despachos,'detalles'=>$detalles]);
}

function guardarViaje(): void
{
    $id = (int)($_POST['id'] ?? 0); $transporteId = (int)($_POST['transporte_id'] ?? 0);
    $fecha = (string)($_POST['fecha_translado'] ?? '');
    $drivers = jsonArrayForm('js_transportistas'); $ruta = jsonArrayForm('js_ubicaciones_ruta');
    if ($transporteId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) responderTransporte(false, 'Selecciona vehículo y fecha del viaje.');
    if (!$drivers) responderTransporte(false, 'Agrega al menos un chofer o acompañante.');
    if (!$ruta) responderTransporte(false, 'Agrega al menos una ubicación de ruta.');
    $db = conectar_oll_BD();
    $v = executeQuery($db, 'SELECT id FROM transporte WHERE id=:id AND deleted_at IS NULL', ['id'=>$transporteId]);
    if (!$v) responderTransporte(false, 'El vehículo no existe o está inactivo.');
    $params = ['transporte_id'=>$transporteId,'fecha'=>$fecha,'drivers'=>json_encode($drivers,JSON_UNESCAPED_UNICODE),'ruta'=>json_encode($ruta,JSON_UNESCAPED_UNICODE)];
    if ($id > 0) {
        $params['id']=$id;
        executeNonQuery($db, "UPDATE movimiento_transporte SET transporte_id=:transporte_id,fecha_translado=:fecha,js_transportistas=CAST(:drivers AS jsonb),js_ubicaciones_ruta=CAST(:ruta AS jsonb),update_at=NOW() WHERE id=:id AND deleted_at IS NULL", $params);
    } else {
        executeNonQuery($db, "INSERT INTO movimiento_transporte(transporte_id,fecha_translado,js_transportistas,js_ubicaciones_ruta,created_at) VALUES(:transporte_id,:fecha,CAST(:drivers AS jsonb),CAST(:ruta AS jsonb),NOW())", $params);
    }
    responderTransporte(true, $id ? 'Viaje actualizado.' : 'Viaje registrado.');
}

function eliminarViaje(): void
{
    $id=(int)($_POST['id']??0); if($id<=0) responderTransporte(false,'Viaje inválido.');
    $hijos=executeQuery(conectar_oll_BD(),'SELECT 1 FROM movimiento_transporte_venta WHERE movimiento_transporte_id=:id AND deleted_at IS NULL LIMIT 1',['id'=>$id]);
    if($hijos) responderTransporte(false,'El viaje ya tiene despachos; no se puede eliminar.');
    executeNonQuery(conectar_oll_BD(),'UPDATE movimiento_transporte SET deleted_at=NOW(),update_at=NOW() WHERE id=:id',['id'=>$id]);
    responderTransporte(true,'Viaje eliminado.');
}

function guardarDespacho(): void
{
    $viajeId=(int)($_POST['movimiento_transporte_id']??0); $ruc=trim((string)($_POST['cliente_ruc']??''));
    $cliente=trim((string)($_POST['cliente']??'')); $monedaId=(int)($_POST['moneda_id']??0);
    $fecha=(string)($_POST['fecha_venta']??date('Y-m-d')); $tc=(float)($_POST['tipo_cambio']??1);
    $fuenteTc=strtolower(trim((string)($_POST['fuente_tipo_cambio']??'oficial')));
    $descuentoGeneral=(float)($_POST['descuento_general']??0); $items=json_decode((string)($_POST['items']??'[]'),true);
    if($viajeId<=0||$cliente===''||$monedaId<=0||!is_array($items)||!count($items)) responderTransporte(false,'Completa viaje, cliente, moneda y al menos un producto.');
    if($descuentoGeneral<0) responderTransporte(false,'El descuento general no puede ser negativo.');
    $db=conectar_oll_BD(); $db->beginTransaction();
    try {
        $viaje=executeQuery($db,"SELECT id FROM movimiento_transporte WHERE id=:id AND deleted_at IS NULL FOR UPDATE",['id'=>$viajeId]);
        $moneda=executeQuery($db,"SELECT id,codigo,nombre,simbolo FROM moneda WHERE id=:id AND COALESCE(estado,TRUE)=TRUE AND deleted_at IS NULL",['id'=>$monedaId]);
        $cli=executeQuery($db,"SELECT ruc,razon_social FROM proveedor WHERE ruc=:ruc AND deleted_at IS NULL AND js_tipo @> '[\"cliente\"]'::jsonb",['ruc'=>$ruc]);
        if(!$viaje||!$moneda||!$cli) throw new RuntimeException('El viaje, moneda o cliente ya no está disponible.');
        $monedaCodigo=strtoupper((string)$moneda[0]['codigo']);
        if($monedaCodigo==='PEN'){
            $tc=1.0; $fuenteTc='local';
            $snapshotTc=['fuente'=>'local','moneda'=>'PEN','fecha'=>$fecha,'compra'=>1,'venta'=>1];
        }elseif($fuenteTc==='oficial'){
            $snapshotTc=consultarTipoCambio($fecha,$monedaCodigo);
            $tc=(float)($snapshotTc['venta']??0);
            if(!is_finite($tc)||$tc<=0) throw new RuntimeException('La API no devolvió una tasa oficial de venta válida.');
            $snapshotTc['fuente']='oficial'; $snapshotTc['moneda']=$monedaCodigo; $snapshotTc['fecha']=$snapshotTc['fecha']??$fecha;
        }elseif($fuenteTc==='calle'){
            $tc=(float)($_POST['tipo_cambio']??0);
            if(!is_finite($tc)||$tc<=0||$tc>10000) throw new RuntimeException('Ingresa un tipo de cambio de calle válido.');
            $snapshotTc=['fuente'=>'calle','moneda'=>$monedaCodigo,'fecha'=>$fecha,'compra'=>null,'venta'=>null,'tasa_aplicada'=>$tc];
        }else{
            throw new RuntimeException('Selecciona tipo de cambio oficial o de calle.');
        }
        $subTotal=0; $discountLines=0; $normal=[];
        foreach(array_slice($items,0,100) as $it){
            $pid=(int)($it['producto_id']??0); $qty=(float)($it['cantidad']??0); $price=(float)($it['precio']??0); $discount=(float)($it['descuento']??0); $packId=(int)($it['empaquetado_id']??0);
            if($pid<=0||$qty<=0||$price<0||$discount<0) throw new RuntimeException('Revisa producto, cantidad, precio y descuento de cada línea.');
            $product=executeQuery($db,'SELECT p.id,p.codigo,p.descripcion,p.unidad_venta_id,COALESCE(um.nombre_corto,\'UND\') AS unidad FROM producto p LEFT JOIN unidad_medida um ON um.id=p.unidad_venta_id WHERE p.id=:id AND p.activo IS TRUE',['id'=>$pid]);
            if(!$product) throw new RuntimeException('Hay un producto inactivo o inexistente.');
            if($packId>0){$pack=executeQuery($db,'SELECT id,producto_id,pasado_venta,COALESCE(cantidad_disponible_tmp,cantidad_tota) AS disponible FROM empaquetado WHERE id=:id AND deleted_at IS NULL FOR UPDATE',['id'=>$packId]); if(!$pack||(int)$pack[0]['producto_id']!==$pid) throw new RuntimeException('El paquete indicado no corresponde al producto.'); if(in_array($pack[0]['pasado_venta']??false,[true,1,'1','t','true'],true)||(float)$pack[0]['disponible']<=0) throw new RuntimeException('El paquete está vendido o agotado.'); $asociado=executeQuery($db,'SELECT 1 FROM rel_venta_producto WHERE empaquetado_id=:id AND deleted_at IS NULL LIMIT 1',['id'=>$packId]); if($asociado) throw new RuntimeException('El paquete ya fue vinculado a un despacho.');}
            $lineSub=round($qty*$price,2); if($discount>$lineSub) throw new RuntimeException('El descuento de una línea supera su subtotal.');
            $subTotal+=$lineSub; $discountLines+=$discount;
            $normal[]=['producto'=>$product[0],'empaquetado_id'=>$packId?:null,'cantidad'=>$qty,'precio'=>$price,'subtotal'=>$lineSub,'descuento'=>$discount];
        }
        if($descuentoGeneral>$subTotal-$discountLines) throw new RuntimeException('El descuento general supera el total del despacho.');
        $total=round($subTotal-$discountLines-$descuentoGeneral,2);
        $montoCobro=$monedaCodigo==='PEN'?$total:round($total/$tc,2);
        $registroUid=bin2hex(random_bytes(16));
        $actor=['registro_uid'=>$registroUid,'usuario_id'=>(int)($_SESSION['usuario_id']??0),'nombre'=>(string)($_SESSION['nombre_usuario']??$_SESSION['usuario']??''),'fecha'=>date(DATE_ATOM),'tipo_cambio'=>$snapshotTc,'moneda_cobro'=>$monedaCodigo,'monto_total_pen'=>$total,'monto_total_cobro'=>$montoCobro];
        executeNonQuery($db,"INSERT INTO movimiento_transporte_venta(movimiento_transporte_id,cliente_ruc,cliente,moneda_id,moneda,fecha_venta,estado,monto,descuento_general,tipo_cambio,js_session,created_at) VALUES(:viaje,:ruc,:cliente,:moneda_id,:moneda,:fecha,'C',:monto,:descuento,:tc,CAST(:session AS jsonb),NOW())",['viaje'=>$viajeId,'ruc'=>$cli[0]['ruc'],'cliente'=>$cli[0]['razon_social'],'moneda_id'=>$monedaId,'moneda'=>$monedaCodigo,'fecha'=>$fecha,'monto'=>$montoCobro,'descuento'=>$descuentoGeneral,'tc'=>$tc,'session'=>json_encode($actor,JSON_UNESCAPED_UNICODE)]);
        foreach($normal as $line){$p=$line['producto']; $lineSession=$actor; $lineSession['empaquetado_id']=$line['empaquetado_id']; executeNonQuery($db,"INSERT INTO rel_venta_producto(empaquetado_id,producto_id,producto,unidad_medida_id,unidad_venta_corto,cantidad,precio_unitario_manual,sub_total_calcular,descuento,js_session,created_at) VALUES(:pack,:producto_id,:producto,:unidad_id,:unidad,:cantidad,:precio,:subtotal,:descuento,CAST(:session AS jsonb),NOW())",['pack'=>$line['empaquetado_id'],'producto_id'=>$p['id'],'producto'=>trim(($p['codigo']??'').' - '.($p['descripcion']??'')),'unidad_id'=>$p['unidad_venta_id'],'unidad'=>$p['unidad'],'cantidad'=>$line['cantidad'],'precio'=>$line['precio'],'subtotal'=>$line['subtotal'],'descuento'=>$line['descuento'],'session'=>json_encode($lineSession,JSON_UNESCAPED_UNICODE)]);
            if($line['empaquetado_id']){
                $evento=json_encode(['registro_uid'=>$registroUid,'fecha'=>date(DATE_ATOM)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                executeNonQuery($db,"UPDATE movimiento_inventario mi SET js_venta_id = (CASE WHEN jsonb_typeof(mi.js_venta_id)='array' THEN mi.js_venta_id WHEN mi.js_venta_id IS NULL THEN '[]'::jsonb ELSE jsonb_build_array(mi.js_venta_id) END) || jsonb_build_array(CAST(:evento AS jsonb)) WHERE mi.id=(SELECT m2.id FROM movimiento_inventario m2 WHERE m2.empaquetado_id=:pack_id ORDER BY m2.created_at DESC,m2.id DESC LIMIT 1)",['evento'=>$evento,'pack_id'=>$line['empaquetado_id']]);
            }
        }
        $db->commit(); responderTransporte(true,'Despacho registrado en el viaje. No se modificó el módulo de ventas ni el stock.',['registro_uid'=>$registroUid,'monto_total_pen'=>$total,'moneda_cobro'=>$monedaCodigo,'monto_total_cobro'=>$montoCobro,'tipo_cambio'=>$tc,'fuente_tipo_cambio'=>$fuenteTc]);
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack(); if($e instanceof RuntimeException)responderTransporte(false,$e->getMessage()); throw $e;}
}

function cambiarEstadoDespacho(): void
{
    $uid=strtolower(trim((string)($_POST['registro_uid']??''))); $estado=strtoupper(trim((string)($_POST['estado']??'')));
    if(!preg_match('/^[a-f0-9]{32}$/',$uid)||!in_array($estado,['C','E','R'],true)) responderTransporte(false,'Estado o despacho inválido.');
    executeNonQuery(conectar_oll_BD(),"UPDATE movimiento_transporte_venta SET estado=:estado,update_at=NOW() WHERE js_session->>'registro_uid'=:uid AND deleted_at IS NULL",['estado'=>$estado,'uid'=>$uid]);
    responderTransporte(true,'Estado del despacho actualizado.');
}
