<?php
ob_start();
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/clssVerificarSession.php';
require_once __DIR__ . '/tipoCambio.php';
require_once __DIR__ . '/auditoria.php';

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
        case 'DEVOLVERITEMDESPACHO': devolverItemDespacho(); break;
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
    $productos = executeQuery($db, 'SELECT p.id,p.codigo,p.descripcion,p.img_ruta AS imagen,p.unidad_venta_id,COALESCE(um.nombre_corto,\'UND\') AS unidad,COALESCE(p.stock,0) AS stock FROM producto p LEFT JOIN unidad_medida um ON um.id=p.unidad_venta_id WHERE p.activo IS TRUE ORDER BY p.descripcion');
    $conductores = executeQuery($db, "SELECT o.id,o.nombre_completo,c.nombre AS cargo FROM operario o JOIN cargo c ON c.id=o.cargo_id WHERE o.activo IS TRUE AND o.deleted_at IS NULL AND c.deleted_at IS NULL AND (LOWER(c.nombre) LIKE '%conductor%' OR LOWER(c.nombre) LIKE '%chofer%') ORDER BY o.nombre_completo");
    $vendedores = executeQuery($db, "SELECT id,nombre_completo FROM usuario WHERE deleted_at IS NULL AND operario_id IS NULL AND (UPPER(COALESCE(rol_y_perfiles->>'rol','')) IN ('ADMINISTRADOR','VENTAS') OR EXISTS (SELECT 1 FROM jsonb_array_elements(CASE WHEN jsonb_typeof(usuario.rol_y_perfiles->'catalogo_roles')='array' THEN usuario.rol_y_perfiles->'catalogo_roles' ELSE '[]'::jsonb END) AS catalogo(perfil) WHERE catalogo.perfil->>'codigo'=usuario.rol_y_perfiles->>'rol' AND catalogo.perfil->>'activo'='true' AND jsonb_exists(catalogo.perfil->'permisos','ventas'))) ORDER BY nombre_completo");
    $cuentas = executeQuery($db, 'SELECT id,nombre FROM cuenta_receptora WHERE activo IS TRUE ORDER BY nombre');
    responderTransporte(true, 'OK', ['transportes'=>$transportes,'clientes'=>$clientes,'monedas'=>$monedas,'productos'=>$productos,'conductores'=>$conductores,'vendedores'=>$vendedores,'cuentas'=>$cuentas]);
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
               COALESCE(um.equivalencia, 1) AS equivalencia_paquete,
               p.codigo, p.descripcion, p.unidad_venta_id, p.stock,
               COALESCE(uv.nombre_corto, 'UND') AS unidad_venta,
               p.cant_equivale, ue.equivalencia AS equivalencia_unidad_equivale
        FROM empaquetado emp
        JOIN producto p ON p.id = emp.producto_id
        LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
        LEFT JOIN unidad_medida uv ON uv.id = p.unidad_venta_id
        LEFT JOIN unidad_medida ue ON ue.id = p.unidad_equivale_id
        WHERE emp.id = :id AND emp.deleted_at IS NULL
    ", ['id' => (int)$qr[1]]);
    if (!$rows) responderTransporte(false, 'No se encontró un paquete activo con ese QR.');
    $p = $rows[0];
    $esperado = sprintf('A-%010d-%05d-%05d-%010d', (int)$p['empaquetado_id'], (int)$p['sucursal'], (int)$p['producto_id'], (int)($p['emsamblaje_id'] ?? 0));
    if (!hash_equals($esperado, $codigo)) responderTransporte(false, 'El QR no coincide con los datos del paquete.');
    if (in_array($p['pasado_venta'] ?? false, [true, 1, '1', 't', 'true'], true) || (float)$p['cantidad_disponible'] <= 0) {
        responderTransporte(false, 'Este paquete figura vendido o no tiene saldo disponible.');
    }
    $capacidadVentaBase = (float)($p['cant_equivale'] ?? 0) * (float)($p['equivalencia_unidad_equivale'] ?? 0);
    $equivalenciaPaquete = (float)($p['equivalencia_paquete'] ?? 0);
    if ($capacidadVentaBase <= 0 || $equivalenciaPaquete <= 0) {
        responderTransporte(false, 'No se puede convertir la cantidad del bulto a la unidad de venta del producto. Revisa sus equivalencias.');
    }
    responderTransporte(true, 'Paquete encontrado. Verifica cantidad y precio antes de guardar.', [
        'paquete' => [
            'codigo_qr' => $codigo, 'empaquetado_id' => (int)$p['empaquetado_id'],
            'producto_id' => (int)$p['producto_id'], 'producto' => trim(($p['codigo'] ?? '') . ' - ' . ($p['descripcion'] ?? '')),
            'unidad_venta' => $p['unidad_venta'], 'stock_producto' => (float)($p['stock'] ?? 0),
            'cantidad_disponible_paquete' => (float)$p['cantidad_disponible'], 'unidad_paquete' => $p['unidad_paquete'] ?? 'UND',
            'cantidad_venta_disponible' => round((float)$p['cantidad_disponible'] * $equivalenciaPaquete / $capacidadVentaBase, 2),
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
    $despachos = executeQuery(conectar_oll_BD(), "SELECT js_session->>'registro_uid' AS registro_uid,js_session->>'ubicacion_destino' AS ubicacion_destino,js_session->>'ubicacion_ruta_indice' AS ubicacion_ruta_indice,movimiento_transporte_id,cliente,cliente_ruc,fecha_venta,estado,monto,moneda,COALESCE(NULLIF(js_session #>> '{monto_total_pen}','')::numeric,CASE WHEN moneda='PEN' THEN monto ELSE monto*COALESCE(NULLIF(js_session #>> '{tipo_cambio,venta}','')::numeric,NULLIF(js_session #>> '{tipo_cambio,tasa_aplicada}','')::numeric,1) END) AS monto_total_pen,COALESCE(NULLIF(js_session #>> '{monto_total_cobro}','')::numeric,monto) AS monto_total_cobro,js_session #>> '{tipo_cambio,fuente}' AS tipo_cambio_fuente,COALESCE(NULLIF(js_session #>> '{tipo_cambio,venta}','')::numeric,NULLIF(js_session #>> '{tipo_cambio,tasa_aplicada}','')::numeric,1) AS tipo_cambio_aplicado,js_session #>> '{tipo_cambio,fecha}' AS fecha_tipo_cambio FROM movimiento_transporte_venta WHERE deleted_at IS NULL ORDER BY created_at DESC");
    $detalles = executeQuery(conectar_oll_BD(), "SELECT rv.js_session->>'registro_uid' AS registro_uid,rv.js_session->>'linea_uid' AS linea_uid,mv.estado AS despacho_estado,rv.producto,rv.producto_id,rv.empaquetado_id,rv.cantidad,rv.unidad_venta_corto,rv.precio_unitario_manual,rv.sub_total_calcular,rv.descuento FROM rel_venta_producto rv LEFT JOIN movimiento_transporte_venta mv ON mv.js_session->>'registro_uid'=rv.js_session->>'registro_uid' WHERE rv.deleted_at IS NULL ORDER BY rv.created_at");
    responderTransporte(true, 'OK', ['viajes'=>$rows,'despachos'=>$despachos,'detalles'=>$detalles]);
}

function guardarViaje(): void
{
    $id = (int)($_POST['id'] ?? 0); $transporteId = (int)($_POST['transporte_id'] ?? 0);
    $fecha = (string)($_POST['fecha_translado'] ?? '');
    $conductorId=(int)($_POST['operario_conductor_id']??0);$acompanantes=jsonArrayForm('js_acompanantes');$ruta = jsonArrayForm('js_ubicaciones_ruta');
    if ($transporteId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) responderTransporte(false, 'Selecciona vehículo y fecha del viaje.');
    if ($conductorId<=0) responderTransporte(false, 'Selecciona un operario registrado con cargo de conductor.');
    if (!$ruta) responderTransporte(false, 'Agrega al menos una ubicación de ruta.');
    $db = conectar_oll_BD();
    $v = executeQuery($db, 'SELECT id FROM transporte WHERE id=:id AND deleted_at IS NULL', ['id'=>$transporteId]);
    if (!$v) responderTransporte(false, 'El vehículo no existe o está inactivo.');
    $driver=executeQuery($db,"SELECT o.id,o.nombre_completo,c.nombre AS cargo FROM operario o JOIN cargo c ON c.id=o.cargo_id WHERE o.id=:id AND o.activo IS TRUE AND o.deleted_at IS NULL AND c.deleted_at IS NULL AND (LOWER(c.nombre) LIKE '%conductor%' OR LOWER(c.nombre) LIKE '%chofer%')",['id'=>$conductorId]);
    if(!$driver) responderTransporte(false,'El operario seleccionado ya no está activo como conductor.');
    $drivers=[['operario_id'=>(int)$driver[0]['id'],'nombre'=>$driver[0]['nombre_completo'],'cargo'=>$driver[0]['cargo']]];
    foreach($acompanantes as $nombre)if(is_string($nombre)&&trim($nombre)!=='')$drivers[]=trim($nombre);
    $params = ['transporte_id'=>$transporteId,'fecha'=>$fecha,'drivers'=>json_encode($drivers,JSON_UNESCAPED_UNICODE),'ruta'=>json_encode($ruta,JSON_UNESCAPED_UNICODE)];
    if ($id > 0) {
        $params['id']=$id;
        $existentes=executeQuery($db,'SELECT js_ubicaciones_ruta FROM movimiento_transporte WHERE id=:id AND deleted_at IS NULL FOR UPDATE',['id'=>$id]);
        if(!$existentes) responderTransporte(false,'No se encontró el viaje para actualizar.');
        $tieneDespachos=executeQuery($db,'SELECT 1 FROM movimiento_transporte_venta WHERE movimiento_transporte_id=:id AND deleted_at IS NULL LIMIT 1',['id'=>$id]);
        $rutaAnterior=json_decode((string)$existentes[0]['js_ubicaciones_ruta'],true)?:[];
        if($tieneDespachos && array_slice($ruta,0,count($rutaAnterior))!==$rutaAnterior) responderTransporte(false,'Este viaje ya tiene cargas; conserva el orden y los nombres de las paradas existentes. Puedes añadir nuevas paradas al final de la ruta.');
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
    if($ruc===''){$ruc='99999999';$cliente='Clientes Varios';}
    $vendedorId=(int)($_POST['vendedor_usuario_id']??0);$medioPago=strtolower(trim((string)($_POST['pago_medio']??'efectivo')));$cuentaId=(int)($_POST['pago_cuenta_id']??0);$referencia=trim((string)($_POST['pago_referencia']??''));
    $rutaIndice=filter_var($_POST['ubicacion_ruta_indice']??null,FILTER_VALIDATE_INT);
    $fecha=(string)($_POST['fecha_venta']??date('Y-m-d')); $tc=(float)($_POST['tipo_cambio']??1);
    $fuenteTc=strtolower(trim((string)($_POST['fuente_tipo_cambio']??'oficial')));
    $descuentoGeneral=(float)($_POST['descuento_general']??0); $items=json_decode((string)($_POST['items']??'[]'),true);
    if($viajeId<=0||$cliente===''||$ruc===''||$rutaIndice===false||$rutaIndice===null||$rutaIndice<0||$monedaId<=0||!is_array($items)||!count($items)) responderTransporte(false,'Completa viaje, cliente, parada de ruta, moneda y al menos un producto.');
    if($descuentoGeneral<0) responderTransporte(false,'El descuento general no puede ser negativo.');
    $db=conectar_oll_BD(); $db->beginTransaction();
    try {
        $viaje=executeQuery($db,"SELECT id,js_ubicaciones_ruta FROM movimiento_transporte WHERE id=:id AND deleted_at IS NULL FOR UPDATE",['id'=>$viajeId]);
        $moneda=executeQuery($db,"SELECT id,codigo,nombre,simbolo FROM moneda WHERE id=:id AND COALESCE(estado,TRUE)=TRUE AND deleted_at IS NULL",['id'=>$monedaId]);
        if($ruc==='99999999'){$actorCliente=obtenerMovimientoSesion('crear');executeNonQuery($db,"INSERT INTO proveedor(ruc,js_tipo,razon_social,nombre_comercial,telefonos_contacto,correo,ubigeo,ubicacion,js_consulta_api,created_at,js_session,js_historial) VALUES('99999999','[\"cliente\"]'::jsonb,'Clientes Varios',NULL,'[]'::jsonb,NULL,NULL,NULL,NULL,NOW(),CAST(:session AS jsonb),CAST(:historial AS jsonb)) ON CONFLICT(ruc) DO NOTHING",['session'=>json_encode($actorCliente,JSON_UNESCAPED_UNICODE),'historial'=>json_encode([$actorCliente],JSON_UNESCAPED_UNICODE)]);}
        $cli=executeQuery($db,"SELECT ruc,razon_social FROM proveedor WHERE ruc=:ruc AND deleted_at IS NULL AND js_tipo @> '[\"cliente\"]'::jsonb",['ruc'=>$ruc]);
        if(!$viaje||!$moneda||!$cli) throw new RuntimeException('El viaje, moneda o cliente ya no está disponible.');
        if(!in_array($medioPago,['efectivo','deposito','transferencia','yape','plin'],true))throw new RuntimeException('Selecciona una forma de pago válida.');
        $vendedor=executeQuery($db,"SELECT id,nombre_completo FROM usuario WHERE id=:id AND deleted_at IS NULL AND operario_id IS NULL AND (UPPER(COALESCE(rol_y_perfiles->>'rol','')) IN ('ADMINISTRADOR','VENTAS') OR EXISTS (SELECT 1 FROM jsonb_array_elements(CASE WHEN jsonb_typeof(usuario.rol_y_perfiles->'catalogo_roles')='array' THEN usuario.rol_y_perfiles->'catalogo_roles' ELSE '[]'::jsonb END) AS catalogo(perfil) WHERE catalogo.perfil->>'codigo'=usuario.rol_y_perfiles->>'rol' AND catalogo.perfil->>'activo'='true' AND jsonb_exists(catalogo.perfil->'permisos','ventas')))",['id'=>$vendedorId]);if(!$vendedor)throw new RuntimeException('Selecciona un vendedor activo para el ticket.');
        $cuentaNombre=null;if($cuentaId>0){$cuenta=executeQuery($db,'SELECT id,nombre FROM cuenta_receptora WHERE id=:id AND activo IS TRUE',['id'=>$cuentaId]);if(!$cuenta)throw new RuntimeException('La cuenta receptora seleccionada ya no está disponible.');$cuentaNombre=$cuenta[0]['nombre'];}elseif(in_array($medioPago,['deposito','transferencia'],true))throw new RuntimeException('Selecciona la cuenta receptora del pago.');
        $ruta=json_decode((string)$viaje[0]['js_ubicaciones_ruta'],true);
        if(!is_array($ruta)||!array_key_exists($rutaIndice,$ruta)) throw new RuntimeException('La parada seleccionada ya no pertenece a la ruta de este viaje.');
        $parada=$ruta[$rutaIndice];
        $destino=trim((string)(is_array($parada)?($parada['ubicacion']??$parada['nombre']??''):$parada));
        if($destino==='') throw new RuntimeException('La parada seleccionada no tiene nombre.');
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
        $subTotal=0; $discountLines=0; $normal=[]; $paquetesUsados=[];
        foreach(array_slice($items,0,100) as $it){
            $pid=(int)($it['producto_id']??0); $qty=(float)($it['cantidad']??0); $price=(float)($it['precio']??0); $discount=(float)($it['descuento']??0); $packId=(int)($it['empaquetado_id']??0);
            if($pid<=0||$qty<=0||$price<0||$discount<0) throw new RuntimeException('Revisa producto, cantidad, precio y descuento de cada línea.');
            $product=executeQuery($db,'SELECT p.id,p.codigo,p.descripcion,p.unidad_venta_id,p.cant_equivale,p.unidad_equivale_id,COALESCE(um.nombre_corto,\'UND\') AS unidad,COALESCE(ue.equivalencia,0) AS equivalencia_unidad_equivale FROM producto p LEFT JOIN unidad_medida um ON um.id=p.unidad_venta_id LEFT JOIN unidad_medida ue ON ue.id=p.unidad_equivale_id WHERE p.id=:id AND p.activo IS TRUE',['id'=>$pid]);
            if(!$product) throw new RuntimeException('Hay un producto inactivo o inexistente.');
            $consumoPaquete=null;
            if($packId>0){
                if(isset($paquetesUsados[$packId])) throw new RuntimeException('No repitas el mismo paquete en más de una línea.');
                $paquetesUsados[$packId]=true;
                $pack=executeQuery($db,'SELECT e.id,e.producto_id,e.pasado_venta,COALESCE(e.cantidad_disponible_tmp,e.cantidad_tota) AS disponible,e.unidad_medida,COALESCE(um.equivalencia,1) AS equivalencia_paquete FROM empaquetado e LEFT JOIN unidad_medida um ON um.id=e.unidad_medida WHERE e.id=:id AND e.deleted_at IS NULL FOR UPDATE OF e',['id'=>$packId]);
                if(!$pack||(int)$pack[0]['producto_id']!==$pid) throw new RuntimeException('El paquete indicado no corresponde al producto.');
                if(in_array($pack[0]['pasado_venta']??false,[true,1,'1','t','true'],true)||(float)$pack[0]['disponible']<=0) throw new RuntimeException('El paquete está vendido o agotado.');
                $factorVenta=(float)$product[0]['cant_equivale']*(float)$product[0]['equivalencia_unidad_equivale'];
                $factorPaquete=(float)$pack[0]['equivalencia_paquete'];
                if($factorVenta<=0||$factorPaquete<=0) throw new RuntimeException('Falta configurar la equivalencia de venta o del paquete.');
                $consumoPaquete=round($qty*$factorVenta/$factorPaquete,4);
                if($consumoPaquete>(float)$pack[0]['disponible']+0.0001) throw new RuntimeException('La cantidad solicitada supera el saldo de este paquete. Disponible en unidad de venta: '.number_format((float)$pack[0]['disponible']*$factorPaquete/$factorVenta,2,'.','').'.');
            }
            $lineSub=round($qty*$price,2); if($discount>$lineSub) throw new RuntimeException('El descuento de una línea supera su subtotal.');
            $subTotal+=$lineSub; $discountLines+=$discount;
            $normal[]=['producto'=>$product[0],'empaquetado_id'=>$packId?:null,'cantidad'=>$qty,'consumo_paquete'=>$consumoPaquete,'precio'=>$price,'subtotal'=>$lineSub,'descuento'=>$discount];
        }
        if($descuentoGeneral>$subTotal-$discountLines) throw new RuntimeException('El descuento general supera el total del despacho.');
        $total=round($subTotal-$discountLines-$descuentoGeneral,2);
        $montoCobro=$monedaCodigo==='PEN'?$total:round($total/$tc,2);
        $registroUid=bin2hex(random_bytes(16));
        $actor=['registro_uid'=>$registroUid,'usuario_id'=>(int)($_SESSION['usuario_id']??0),'nombre'=>(string)($_SESSION['nombre_usuario']??$_SESSION['usuario']??''),'fecha'=>date(DATE_ATOM),'tipo_cambio'=>$snapshotTc,'moneda_cobro'=>$monedaCodigo,'monto_total_pen'=>$total,'monto_total_cobro'=>$montoCobro,'ubicacion_ruta_indice'=>$rutaIndice,'ubicacion_destino'=>$destino,'stock_despachado'=>true,'vendedor'=>['id'=>$vendedorId,'nombre'=>$vendedor[0]['nombre_completo']],'pagos'=>[['medio_pago'=>$medioPago,'cuenta_receptora_id'=>$cuentaId?:null,'cuenta_nombre'=>$cuentaNombre,'monto'=>$montoCobro,'moneda'=>$monedaCodigo,'referencia'=>$referencia]]];
        executeNonQuery($db,"INSERT INTO movimiento_transporte_venta(movimiento_transporte_id,cliente_ruc,cliente,moneda_id,moneda,fecha_venta,estado,monto,descuento_general,tipo_cambio,js_session,created_at) VALUES(:viaje,:ruc,:cliente,:moneda_id,:moneda,:fecha,'C',:monto,:descuento,:tc,CAST(:session AS jsonb),NOW())",['viaje'=>$viajeId,'ruc'=>$cli[0]['ruc'],'cliente'=>$cli[0]['razon_social'],'moneda_id'=>$monedaId,'moneda'=>$monedaCodigo,'fecha'=>$fecha,'monto'=>$montoCobro,'descuento'=>$descuentoGeneral,'tc'=>$tc,'session'=>json_encode($actor,JSON_UNESCAPED_UNICODE)]);
        foreach($normal as $line){$p=$line['producto']; $lock=executeQuery($db,'SELECT stock FROM producto WHERE id=:id AND activo IS TRUE FOR UPDATE',['id'=>$p['id']]); if(!$lock) throw new RuntimeException('El producto ya no está disponible.'); $anterior=round((float)$lock[0]['stock'],2); if($line['cantidad']>$anterior+0.0001) throw new RuntimeException('Stock insuficiente de '.$p['descripcion'].'. Disponible: '.$anterior.' '.$p['unidad'].'.'); $posterior=round($anterior-$line['cantidad'],2); if($posterior<0&&$posterior>-0.0001)$posterior=0; executeNonQuery($db,'UPDATE producto SET stock=:stock,updated_at=NOW() WHERE id=:id',['stock'=>$posterior,'id'=>$p['id']]);
            $eventoStock=json_encode(['tipo'=>'despacho_transporte','registro_uid'=>$registroUid,'viaje_id'=>$viajeId,'cliente_ruc'=>$cli[0]['ruc'],'cliente'=>$cli[0]['razon_social'],'destino'=>$destino,'fecha'=>date(DATE_ATOM)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            executeNonQuery($db,"INSERT INTO movimiento_inventario(producto_id,empaquetado_id,fecha,tipo,es_traslado,cantidad,cantidad_anterior,cantidad_posterior,js_venta_id,created_at) VALUES(:producto_id,:empaquetado_id,:fecha,'SALIDA',FALSE,:cantidad,:anterior,:posterior,jsonb_build_array(CAST(:evento AS jsonb)),NOW())",['producto_id'=>$p['id'],'empaquetado_id'=>$line['empaquetado_id'],'fecha'=>$fecha,'cantidad'=>$line['cantidad'],'anterior'=>$anterior,'posterior'=>$posterior,'evento'=>$eventoStock]);
            if($line['empaquetado_id']){$disponible=executeQuery($db,'SELECT COALESCE(cantidad_disponible_tmp,cantidad_tota) AS cantidad FROM empaquetado WHERE id=:id FOR UPDATE',['id'=>$line['empaquetado_id']]);$cantidadPaquete=max(0,round((float)$disponible[0]['cantidad']-$line['consumo_paquete'],4));executeNonQuery($db,'UPDATE empaquetado SET cantidad_disponible_tmp=:cantidad,update_at=NOW() WHERE id=:id',['cantidad'=>$cantidadPaquete,'id'=>$line['empaquetado_id']]);}
            $lineSession=$actor; $lineSession['linea_uid']=bin2hex(random_bytes(12)); $lineSession['empaquetado_id']=$line['empaquetado_id']; $lineSession['stock_anterior']=$anterior; $lineSession['stock_posterior']=$posterior; if($line['empaquetado_id']){$lineSession['consumo_paquete']=$line['consumo_paquete'];$lineSession['factor_venta_base']=(float)$p['cant_equivale']*(float)$p['equivalencia_unidad_equivale'];$lineSession['factor_paquete_base']=(float)executeQuery($db,'SELECT COALESCE(um.equivalencia,1) AS equivalencia FROM empaquetado e LEFT JOIN unidad_medida um ON um.id=e.unidad_medida WHERE e.id=:id',['id'=>$line['empaquetado_id']])[0]['equivalencia'];} executeNonQuery($db,"INSERT INTO rel_venta_producto(empaquetado_id,producto_id,producto,unidad_medida_id,unidad_venta_corto,cantidad,precio_unitario_manual,sub_total_calcular,descuento,js_session,created_at) VALUES(:pack,:producto_id,:producto,:unidad_id,:unidad,:cantidad,:precio,:subtotal,:descuento,CAST(:session AS jsonb),NOW())",['pack'=>$line['empaquetado_id'],'producto_id'=>$p['id'],'producto'=>trim(($p['codigo']??'').' - '.($p['descripcion']??'')),'unidad_id'=>$p['unidad_venta_id'],'unidad'=>$p['unidad'],'cantidad'=>$line['cantidad'],'precio'=>$line['precio'],'subtotal'=>$line['subtotal'],'descuento'=>$line['descuento'],'session'=>json_encode($lineSession,JSON_UNESCAPED_UNICODE)]);
            if($line['empaquetado_id']){
                $evento=json_encode(['registro_uid'=>$registroUid,'fecha'=>date(DATE_ATOM)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                executeNonQuery($db,"UPDATE movimiento_inventario mi SET js_venta_id = (CASE WHEN jsonb_typeof(mi.js_venta_id)='array' THEN mi.js_venta_id WHEN mi.js_venta_id IS NULL THEN '[]'::jsonb ELSE jsonb_build_array(mi.js_venta_id) END) || jsonb_build_array(CAST(:evento AS jsonb)) WHERE mi.id=(SELECT m2.id FROM movimiento_inventario m2 WHERE m2.empaquetado_id=:pack_id ORDER BY m2.created_at DESC,m2.id DESC LIMIT 1)",['evento'=>$evento,'pack_id'=>$line['empaquetado_id']]);
            }
        }
        $db->commit(); responderTransporte(true,'Carga confirmada y ticket interno listo.',['registro_uid'=>$registroUid,'monto_total_pen'=>$total,'moneda_cobro'=>$monedaCodigo,'monto_total_cobro'=>$montoCobro,'tipo_cambio'=>$tc,'fuente_tipo_cambio'=>$fuenteTc,'ubicacion_destino'=>$destino]);
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack(); if($e instanceof RuntimeException)responderTransporte(false,$e->getMessage()); throw $e;}
}

function devolverItemDespacho(): void
{
    $uid=strtolower(trim((string)($_POST['registro_uid']??'')));$lineUid=strtolower(trim((string)($_POST['linea_uid']??'')));$cantidadDevolver=filter_var($_POST['cantidad_devolver']??null,FILTER_VALIDATE_FLOAT);
    if(!preg_match('/^[a-f0-9]{32}$/',$uid)||!preg_match('/^[a-f0-9]{24}$/',$lineUid))responderTransporte(false,'La carga seleccionada no es válida.');
    if($cantidadDevolver===false||$cantidadDevolver===null||!is_finite((float)$cantidadDevolver)||(float)$cantidadDevolver<=0)responderTransporte(false,'Ingresa una cantidad válida para devolver.');
    $db=conectar_oll_BD();$db->beginTransaction();
    try{
        $headers=executeQuery($db,"SELECT movimiento_transporte_id,cliente_ruc,cliente,moneda,descuento_general,monto,tipo_cambio,js_session,estado FROM movimiento_transporte_venta WHERE js_session->>'registro_uid'=:uid AND deleted_at IS NULL FOR UPDATE",['uid'=>$uid]);
        if(!$headers)throw new RuntimeException('No se encontró la carga del cliente.');$h=$headers[0];if(strtoupper(trim((string)$h['estado']))!=='C')throw new RuntimeException('Solo puedes corregir productos mientras el despacho está en ruta.');
        $rows=executeQuery($db,"SELECT producto_id,producto,empaquetado_id,cantidad,unidad_venta_corto,sub_total_calcular,descuento,js_session FROM rel_venta_producto WHERE js_session->>'registro_uid'=:uid AND js_session->>'linea_uid'=:linea_uid AND deleted_at IS NULL FOR UPDATE",['uid'=>$uid,'linea_uid'=>$lineUid]);
        if(!$rows)throw new RuntimeException('Ese producto ya fue devuelto del despacho o no existe.');$line=$rows[0];$cantidadOriginal=(float)$line['cantidad'];$cantidadDevolver=(float)$cantidadDevolver;if($cantidadDevolver>$cantidadOriginal+0.0001)throw new RuntimeException('La cantidad a devolver supera lo asignado a este cliente.');$cantidadDevolver=min($cantidadDevolver,$cantidadOriginal);$cantidadRestante=round($cantidadOriginal-$cantidadDevolver,4);$esDevolucionTotal=$cantidadRestante<=0.0001;if($esDevolucionTotal)$cantidadRestante=0;
        $factor=$cantidadDevolver/$cantidadOriginal;$subtotalDevuelto=round((float)$line['sub_total_calcular']*$factor,2);$descuentoDevuelto=round((float)$line['descuento']*$factor,2);$subtotalRestante=max(0,round((float)$line['sub_total_calcular']-$subtotalDevuelto,2));$descuentoRestante=max(0,round((float)$line['descuento']-$descuentoDevuelto,2));
        $stock=executeQuery($db,'SELECT stock FROM producto WHERE id=:id FOR UPDATE',['id'=>$line['producto_id']]);if(!$stock)throw new RuntimeException('No se encontró el producto para devolverlo al stock.');
        $antes=(float)$stock[0]['stock'];$despues=round($antes+$cantidadDevolver,2);executeNonQuery($db,'UPDATE producto SET stock=:stock,updated_at=NOW() WHERE id=:id',['stock'=>$despues,'id'=>$line['producto_id']]);
        $evento=json_encode(['tipo'=>'devolucion_carga_transporte','registro_uid'=>$uid,'linea_uid'=>$lineUid,'viaje_id'=>(int)$h['movimiento_transporte_id'],'cliente_ruc'=>$h['cliente_ruc'],'cliente'=>$h['cliente'],'cantidad_devuelta'=>$cantidadDevolver,'fecha'=>date(DATE_ATOM)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        executeNonQuery($db,"INSERT INTO movimiento_inventario(producto_id,empaquetado_id,fecha,tipo,es_traslado,cantidad,cantidad_anterior,cantidad_posterior,js_venta_id,created_at) VALUES(:producto_id,:empaquetado_id,CURRENT_DATE,'ENTRADA',FALSE,:cantidad,:anterior,:posterior,jsonb_build_array(CAST(:evento AS jsonb)),NOW())",['producto_id'=>$line['producto_id'],'empaquetado_id'=>$line['empaquetado_id'],'cantidad'=>$cantidadDevolver,'anterior'=>$antes,'posterior'=>$despues,'evento'=>$evento]);
        $info=json_decode((string)$line['js_session'],true)?:[];$consumoOriginal=(float)($info['consumo_paquete']??0);$consumoDevuelto=round($consumoOriginal*$factor,4);$consumoRestante=max(0,round($consumoOriginal-$consumoDevuelto,4));
        if(!empty($line['empaquetado_id'])&&$consumoDevuelto>0){$pack=executeQuery($db,'SELECT COALESCE(cantidad_disponible_tmp,cantidad_tota) AS cantidad FROM empaquetado WHERE id=:id AND deleted_at IS NULL FOR UPDATE',['id'=>$line['empaquetado_id']]);if(!$pack)throw new RuntimeException('El bulto vinculado ya no existe.');$restaurado=round((float)$pack[0]['cantidad']+$consumoDevuelto,4);executeNonQuery($db,'UPDATE empaquetado SET cantidad_disponible_tmp=:cantidad,update_at=NOW() WHERE id=:id',['cantidad'=>$restaurado,'id'=>$line['empaquetado_id']]);}
        if($esDevolucionTotal){executeNonQuery($db,"UPDATE rel_venta_producto SET deleted_at=NOW(),update_at=NOW() WHERE js_session->>'registro_uid'=:uid AND js_session->>'linea_uid'=:linea_uid AND deleted_at IS NULL",['uid'=>$uid,'linea_uid'=>$lineUid]);}else{$info['consumo_paquete']=$consumoRestante;executeNonQuery($db,"UPDATE rel_venta_producto SET cantidad=:cantidad,sub_total_calcular=:subtotal,descuento=:descuento,js_session=CAST(:session AS jsonb),update_at=NOW() WHERE js_session->>'registro_uid'=:uid AND js_session->>'linea_uid'=:linea_uid AND deleted_at IS NULL",['cantidad'=>$cantidadRestante,'subtotal'=>$subtotalRestante,'descuento'=>$descuentoRestante,'session'=>json_encode($info,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'uid'=>$uid,'linea_uid'=>$lineUid]);}
        $restantes=executeQuery($db,"SELECT COALESCE(SUM(sub_total_calcular),0) AS subtotal,COALESCE(SUM(descuento),0) AS descuentos FROM rel_venta_producto WHERE js_session->>'registro_uid'=:uid AND deleted_at IS NULL",['uid'=>$uid]);
        $baseRestante=max(0,(float)$restantes[0]['subtotal']-(float)$restantes[0]['descuentos']);$baseDevuelta=max(0,$subtotalDevuelto-$descuentoDevuelto);$baseAntesDevolucion=$baseRestante+$baseDevuelta;$descuentoGeneralNuevo=$baseAntesDevolucion>0?round((float)$h['descuento_general']*$baseRestante/$baseAntesDevolucion,2):0;$descuentoGeneralNuevo=min($descuentoGeneralNuevo,$baseRestante);
        $totalPen=max(0,round($baseRestante-$descuentoGeneralNuevo,2));$sesion=json_decode((string)$h['js_session'],true)?:[];$fx=$sesion['tipo_cambio']??[];$tasa=strtoupper((string)$h['moneda'])==='PEN'?1.0:(float)($fx['venta']??$fx['tasa_aplicada']??$h['tipo_cambio']);if($tasa<=0)$tasa=1.0;$totalCobro=strtoupper((string)$h['moneda'])==='PEN'?$totalPen:round($totalPen/$tasa,2);
        $ajustes=$sesion['ajustes_carga']??[];$ajustes[]=['tipo'=>$esDevolucionTotal?'producto_retirado_y_devuelto_a_stock':'devolucion_parcial_de_producto','linea_uid'=>$lineUid,'producto'=>$line['producto'],'cantidad'=>$cantidadDevolver,'fecha'=>date(DATE_ATOM)];$sesion['ajustes_carga']=$ajustes;$sesion['monto_total_pen']=$totalPen;$sesion['monto_total_cobro']=$totalCobro;if(!empty($sesion['pagos'][0]))$sesion['pagos'][0]['monto']=$totalCobro;
        executeNonQuery($db,"UPDATE movimiento_transporte_venta SET monto=:monto,descuento_general=:descuento_general,js_session=CAST(:session AS jsonb),update_at=NOW() WHERE js_session->>'registro_uid'=:uid AND deleted_at IS NULL",['monto'=>$totalCobro,'descuento_general'=>$descuentoGeneralNuevo,'session'=>json_encode($sesion,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'uid'=>$uid]);
        $db->commit();responderTransporte(true,'Se devolvió '.$cantidadDevolver.' '.$line['unidad_venta_corto'].' al stock y se ajustó la cantidad del despacho.',['monto_total_pen'=>$totalPen,'monto_total_cobro'=>$totalCobro,'cantidad_restante'=>$cantidadRestante]);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)responderTransporte(false,$e->getMessage());throw $e;}
}

function cambiarEstadoDespacho(): void
{
    $uid=strtolower(trim((string)($_POST['registro_uid']??''))); $estado=strtoupper(trim((string)($_POST['estado']??'')));
    if(!preg_match('/^[a-f0-9]{32}$/',$uid)||!in_array($estado,['C','E','R'],true)) responderTransporte(false,'Estado o despacho inválido.');
    $db=conectar_oll_BD();$db->beginTransaction();
    try{$cabecera=executeQuery($db,"SELECT movimiento_transporte_id,cliente_ruc,cliente,estado,COALESCE((js_session->>'stock_despachado')::boolean,FALSE) AS stock_despachado FROM movimiento_transporte_venta WHERE js_session->>'registro_uid'=:uid AND deleted_at IS NULL FOR UPDATE",['uid'=>$uid]);if(!$cabecera)throw new RuntimeException('No se encontró el despacho.');$actual=strtoupper(trim((string)$cabecera[0]['estado']));if($actual===$estado){$db->commit();responderTransporte(true,'El despacho ya tenía ese estado.');}if($actual!=='C')throw new RuntimeException('Un despacho entregado o rechazado es definitivo; no se puede cambiar nuevamente.');
        if($estado==='R'&&!empty($cabecera[0]['stock_despachado'])){$lineas=executeQuery($db,"SELECT producto_id,empaquetado_id,cantidad,js_session FROM rel_venta_producto WHERE js_session->>'registro_uid'=:uid AND deleted_at IS NULL FOR UPDATE",['uid'=>$uid]);foreach($lineas as $linea){$p=executeQuery($db,'SELECT stock FROM producto WHERE id=:id FOR UPDATE',['id'=>$linea['producto_id']]);if(!$p)continue;$antes=(float)$p[0]['stock'];$despues=round($antes+(float)$linea['cantidad'],2);executeNonQuery($db,'UPDATE producto SET stock=:stock,updated_at=NOW() WHERE id=:id',['stock'=>$despues,'id'=>$linea['producto_id']]);$evento=json_encode(['tipo'=>'rechazo_despacho','registro_uid'=>$uid,'viaje_id'=>(int)$cabecera[0]['movimiento_transporte_id'],'cliente_ruc'=>$cabecera[0]['cliente_ruc'],'cliente'=>$cabecera[0]['cliente'],'fecha'=>date(DATE_ATOM)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);executeNonQuery($db,"INSERT INTO movimiento_inventario(producto_id,empaquetado_id,fecha,tipo,es_traslado,cantidad,cantidad_anterior,cantidad_posterior,js_venta_id,created_at) VALUES(:producto_id,:empaquetado_id,CURRENT_DATE,'ENTRADA',FALSE,:cantidad,:anterior,:posterior,jsonb_build_array(CAST(:evento AS jsonb)),NOW())",['producto_id'=>$linea['producto_id'],'empaquetado_id'=>$linea['empaquetado_id'],'cantidad'=>$linea['cantidad'],'anterior'=>$antes,'posterior'=>$despues,'evento'=>$evento]);if(!empty($linea['empaquetado_id'])){$info=json_decode((string)$linea['js_session'],true)?:[];$consumo=(float)($info['consumo_paquete']??0);if($consumo>0){$actualPack=executeQuery($db,'SELECT COALESCE(cantidad_disponible_tmp,cantidad_tota) AS cantidad FROM empaquetado WHERE id=:id FOR UPDATE',['id'=>$linea['empaquetado_id']]);if($actualPack){$restore=round((float)$actualPack[0]['cantidad']+$consumo,4);executeNonQuery($db,'UPDATE empaquetado SET cantidad_disponible_tmp=:cantidad,update_at=NOW() WHERE id=:id',['cantidad'=>$restore,'id'=>$linea['empaquetado_id']]);}}}}}
        executeNonQuery($db,"UPDATE movimiento_transporte_venta SET estado=:estado,update_at=NOW() WHERE js_session->>'registro_uid'=:uid AND deleted_at IS NULL",['estado'=>$estado,'uid'=>$uid]);$db->commit();responderTransporte(true,$estado==='R'?'Despacho rechazado: stock y bultos devueltos al inventario.':'Entrega registrada.');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)responderTransporte(false,$e->getMessage());throw $e;}
}
