<?php
/**
 * controllers_tablet/clssCompraTablet.php
 * Versión tablet (conductor) del módulo de Compras. Sesión propia
 * ($_SESSION['operario_id'] + operario_rol === 'conductor'), NO la sesión
 * de administrador (usuario_id) que usa controllers/clssCompra.php.
 *
 * Diferencias respecto a clssCompra.php (panel admin):
 *   - Un conductor solo ve y edita SUS PROPIAS compras: se agregó la
 *     columna compra.operario_id (FK a operario, NULL para compras creadas
 *     desde el panel admin) que se setea al crear desde esta pantalla.
 *   - No existe ELIMINARCOMPRA ni REACTIVARCOMPRA aquí: el conductor solo
 *     registra y edita, nunca desactiva.
 *   - GUARDARCOMPRA en modo edición valida que la compra pertenezca al
 *     conductor logueado antes de tocar nada.
 *   - Duplica (intencionalmente, para no acoplar ambas pantallas a la misma
 *     sesión) la lógica de conversión de unidades / cantidad_base /
 *     validación de familia / diff de líneas de clssCompra.php.
 *   - Simplificado respecto al admin: sin alta rápida de proveedor/material
 *     (el conductor elige de lo ya existente) y el comprobante se muestra
 *     con la URL directa de Cloudinary en vez de un proxy con sesión.
 *
 * Requiere migración previa:
 *   ALTER TABLE compra ADD COLUMN operario_id bigint REFERENCES operario(id);
 */

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/clssAuthOperario.php';
require_once __DIR__ . '/../controllers/bd.php';
require_once __DIR__ . '/../controllers/executeQuery.php';
require_once __DIR__ . '/../controllers/auditoria.php';
require_once __DIR__ . '/../controllers/cloudinaryHelper.php';

if (isset($_POST['accion'])) {
    try {
        controladorCompraTablet((string) $_POST['accion']);
    } catch (PDOException $e) {
        error_log('Error de base de datos en clssCompra.php: ' . $e->getMessage());
        responder(false, 'Error de base de datos.');
    } catch (Throwable $e) {
        error_log('Error inesperado en clssCompra.php: ' . $e->getMessage());
        responder(false, 'Error inesperado en el servidor.');
    }
}

function controladorCompraTablet(string $accion): void
{
    $operarioId = exigirSesionConductorApi();

    $accionesLectura   = ['LISTARMISCOMPRAS', 'OBTENERCOMPRA', 'BUSCARPROVEEDORES', 'BUSCARMATERIALES', 'BUSCARUNIDADES'];
    $accionesEscritura = ['GUARDARCOMPRA'];

    if (!in_array($accion, array_merge($accionesLectura, $accionesEscritura), true)) {
        responder(false, 'Acción no reconocida.');
    }

    switch ($accion) {
        case 'LISTARMISCOMPRAS':
            listarMisCompras($operarioId);
            break;
        case 'OBTENERCOMPRA':
            obtenerCompraPropia((int) ($_POST['id'] ?? 0), $operarioId);
            break;
        case 'GUARDARCOMPRA':
            guardarCompraTablet($operarioId);
            break;
        case 'BUSCARPROVEEDORES':
            buscarProveedores();
            break;
        case 'BUSCARMATERIALES':
            buscarMateriales();
            break;
        case 'BUSCARUNIDADES':
            buscarUnidades();
            break;
    }
}

/**
 * Guard de sesión para AJAX: a diferencia de exigirRolConductor() (pensado
 * para el guard de página completa, imprime un modal HTML y hace exit),
 * aquí respondemos JSON limpio si falla, porque esto lo consume fetch().
 */
function exigirSesionConductorApi(): int
{
    if (empty($_SESSION['operario_id'])) {
        responder(false, 'Debes iniciar sesión para acceder a este recurso.');
    }
    if (($_SESSION['operario_rol'] ?? '') !== 'conductor') {
        responder(false, 'No tienes permiso para realizar esta acción.');
    }
    return (int) $_SESSION['operario_id'];
}

// =============================================================================
// LISTADOS AUXILIARES (idénticos a clssCompra.php, no dependen de sesión)
// =============================================================================

function buscarProveedores()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["deleted_at IS NULL", "js_tipo @> '[\"proveedor\"]'::jsonb"];
    $params = [];
    if ($texto !== '') {
        $where[] = "(LOWER(razon_social) LIKE LOWER(:texto) OR ruc LIKE :texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT ruc, razon_social, nombre_comercial FROM proveedor
            WHERE " . implode(' AND ', $where) . " ORDER BY razon_social LIMIT 500";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['proveedores' => $result]);
}

function buscarMateriales()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["m.deleted_at IS NULL"];
    $params = [];
    if ($texto !== '') {
        $where[] = "LOWER(m.nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT m.id, m.nombre, m.stock_actual, m.unidad_medida_id, m.derivado,
                   u.nombre_corto AS unidad_corto, u.equivalencia AS unidad_equivalencia
            FROM material m
            LEFT JOIN unidad_medida u ON u.id = m.unidad_medida_id
            WHERE " . implode(' AND ', $where) . " ORDER BY m.nombre LIMIT 50";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['materiales' => $result]);
}

function buscarUnidades()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre, nombre_corto, equivalencia FROM unidad_medida ORDER BY nombre";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['unidades' => $result]);
}

// =============================================================================
// COMPRAS (solo las del conductor logueado)
// =============================================================================

function listarMisCompras(int $operarioId)
{
    $conectar = conectar_oll_BD();

    $texto       = trim($_POST['texto'] ?? '');
    $fecha_desde = trim($_POST['fecha_desde'] ?? '');
    $fecha_hasta = trim($_POST['fecha_hasta'] ?? '');

    $where  = ["c.operario_id = :operario_id", "c.deleted_at IS NULL"];
    $params = ['operario_id' => $operarioId];

    if ($texto !== '') {
        $where[] = "(LOWER(p.razon_social) LIKE LOWER(:texto) OR LOWER(c.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($fecha_desde !== '') {
        $where[] = "c.fecha_compra >= :fecha_desde";
        $params['fecha_desde'] = $fecha_desde;
    }
    if ($fecha_hasta !== '') {
        $where[] = "c.fecha_compra <= :fecha_hasta";
        $params['fecha_hasta'] = $fecha_hasta;
    }

    $sql = "
        SELECT c.*, p.razon_social, p.nombre_comercial,
               COALESCE(jsonb_array_length(c.js_detalle), 0) AS items_count
        FROM compra c
        JOIN proveedor p ON p.ruc = c.proveedor_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.fecha_compra DESC, c.id DESC
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['compras' => $result]);
}

function obtenerCompraPropia(int $id, int $operarioId)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $compra = executeQuery(
        $conectar,
        "SELECT c.*, p.razon_social, p.nombre_comercial
         FROM compra c JOIN proveedor p ON p.ruc = c.proveedor_id
         WHERE c.id = :id AND c.operario_id = :operario_id",
        ['id' => $id, 'operario_id' => $operarioId]
    );
    if (empty($compra)) responder(false, 'Compra no encontrada o no te pertenece.');

    $detalle = executeQuery(
        $conectar,
        "SELECT rcm.*, m.nombre AS material_nombre, m.unidad_medida_id AS material_unidad_base_id,
                m.derivado AS material_derivado,
                um.nombre AS unidad_nombre, um.nombre_corto AS unidad_corto, um.equivalencia AS unidad_equivalencia,
                ub.nombre_corto AS material_unidad_base_corto,
                EXISTS (
                    SELECT 1 FROM rel_produccion_material rpm WHERE rpm.rel_compra_material_id = rcm.id
                ) AS usado_en_produccion
         FROM rel_compra_material rcm
         JOIN material m ON m.id = rcm.material_id
         LEFT JOIN unidad_medida um ON um.id = rcm.unidad_medida_id
         LEFT JOIN unidad_medida ub ON ub.id = m.unidad_medida_id
         WHERE rcm.compra_id = :id AND rcm.deleted_at IS NULL
         ORDER BY rcm.id",
        ['id' => $id]
    );

    responder(true, 'OK', ['compra' => $compra[0], 'detalle' => $detalle]);
}

function subirComprobante(): ?string
{
    if (empty($_FILES['img_comprobante']) || $_FILES['img_comprobante']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $archivo = $_FILES['img_comprobante'];

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        responder(false, 'Error al subir el comprobante (código ' . $archivo['error'] . ').');
    }
    if ($archivo['size'] > 5 * 1024 * 1024) {
        responder(false, 'El comprobante no puede pesar más de 5MB.');
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($extension, $permitidas, true)) {
        responder(false, 'Formato de comprobante no permitido. Usa JPG, PNG, WEBP o PDF.');
    }

    $subida = subirImagenACloudinary($archivo['tmp_name'], $archivo['name'], 'comprobantes');
    return $subida['url'];
}

function borrarArchivoComprobante(?string $url): void
{
    borrarImagenCloudinary($url);
}

function guardarCompraTablet(int $operarioId)
{
    $conectar = conectar_oll_BD();

    $id           = intval($_POST['id'] ?? 0);
    $proveedor_id = trim($_POST['proveedor_id'] ?? '');
    $fecha_compra = trim($_POST['fecha_compra'] ?? '');
    $descripcion  = trim($_POST['descripcion'] ?? '');
    $detalleJson  = trim($_POST['detalle'] ?? '[]');
    $eliminarComprobante = ($_POST['eliminar_comprobante'] ?? '') === '1';

    $totalImgCargadoRaw = trim($_POST['total_img_cargado'] ?? '');
    $totalImgCargado = ($totalImgCargadoRaw !== '') ? floatval($totalImgCargadoRaw) : null;

    if (empty($proveedor_id)) responder(false, 'Debes seleccionar un proveedor.');
    if (empty($fecha_compra)) responder(false, 'La fecha de compra es obligatoria.');
    if ($totalImgCargado !== null && $totalImgCargado < 0) {
        responder(false, 'El monto del comprobante no puede ser negativo.');
    }

    $proveedor = executeQuery($conectar, "SELECT ruc FROM proveedor WHERE ruc = :ruc", ['ruc' => $proveedor_id]);
    if (empty($proveedor)) responder(false, 'El proveedor seleccionado no existe.');

    // Si es edición, la compra debe pertenecer al conductor logueado.
    $compraAnterior = null;
    if ($id !== 0) {
        $actual = executeQuery($conectar, "SELECT * FROM compra WHERE id = :id AND operario_id = :operario_id", [
            'id' => $id, 'operario_id' => $operarioId,
        ]);
        if (empty($actual)) responder(false, 'Esa compra no existe o no te pertenece.');
        if (!empty($actual[0]['deleted_at'])) responder(false, 'No puedes editar una compra inactiva.');
        $compraAnterior = $actual[0];
    }

    $detalleEntrada = json_decode($detalleJson, true);
    if (!is_array($detalleEntrada)) $detalleEntrada = [];

    $detalle = [];
    foreach ($detalleEntrada as $linea) {
        $lineaId        = intval($linea['id'] ?? 0);
        $materialId     = intval($linea['material_id'] ?? 0);
        $unidadMedidaId = intval($linea['unidad_medida_id'] ?? 0);
        $cantidad       = floatval($linea['cantidad'] ?? 0);
        $subTotal       = floatval($linea['sub_total'] ?? 0);
        $total          = isset($linea['total']) && $linea['total'] !== '' ? floatval($linea['total']) : $subTotal;
        $comentario     = trim($linea['comentario'] ?? '');

        if ($materialId <= 0 || $unidadMedidaId <= 0 || $cantidad <= 0) continue;

        $detalle[] = [
            'id'               => $lineaId ?: null,
            'material_id'      => $materialId,
            'unidad_medida_id' => $unidadMedidaId,
            'cantidad'         => $cantidad,
            'sub_total'        => $subTotal,
            'total'            => $total,
            'comentario'       => $comentario ?: null,
        ];
    }

    if (empty($detalle)) {
        responder(false, 'Debes agregar al menos un material con cantidad y unidad de medida válidas.');
    }

    $materialesIds = array_column($detalle, 'material_id');
    $placeholders  = [];
    $paramsIn      = [];
    foreach (array_unique($materialesIds) as $i => $mid) {
        $key = "mid$i";
        $placeholders[] = ":$key";
        $paramsIn[$key] = $mid;
    }
    $materialesInfo = executeQuery(
        $conectar,
        "SELECT m.id, m.nombre, m.unidad_medida_id, um.equivalencia AS unidad_equivalencia
        FROM material m
        LEFT JOIN unidad_medida um ON um.id = m.unidad_medida_id
        WHERE m.id IN (" . implode(',', $placeholders) . ")",
        $paramsIn
    );
    $infoMaterial = [];
    foreach ($materialesInfo as $m) $infoMaterial[$m['id']] = $m;

    $unidadesIds   = array_column($detalle, 'unidad_medida_id');
    $placeholdersU = [];
    $paramsInU     = [];
    foreach (array_unique($unidadesIds) as $i => $uid) {
        $key = "uid$i";
        $placeholdersU[] = ":$key";
        $paramsInU[$key] = $uid;
    }
    $unidadesInfo = executeQuery(
        $conectar,
        "SELECT id, nombre, nombre_corto, equivalencia, unidad_base_id FROM unidad_medida WHERE id IN (" . implode(',', $placeholdersU) . ")",
        $paramsInU
    );
    $infoUnidad = [];
    foreach ($unidadesInfo as $u) $infoUnidad[$u['id']] = $u;

    foreach ($detalle as &$linea) {
        if (!isset($infoMaterial[$linea['material_id']])) {
            responder(false, 'Uno de los materiales seleccionados ya no existe.');
        }
        if (!isset($infoUnidad[$linea['unidad_medida_id']])) {
            responder(false, 'Una de las unidades de medida seleccionadas ya no existe.');
        }

        $materialActual = $infoMaterial[$linea['material_id']];
        $unidadElegida  = $infoUnidad[$linea['unidad_medida_id']];
        $raizMaterialId = $materialActual['unidad_medida_id'];

        if ($raizMaterialId !== null) {
            $esLaMismaRaiz           = ((int)$linea['unidad_medida_id'] === (int)$raizMaterialId);
            $esCompuestaDeEsaFamilia = ((int)($unidadElegida['unidad_base_id'] ?? 0) === (int)$raizMaterialId);

            if (!$esLaMismaRaiz && !$esCompuestaDeEsaFamilia) {
                responder(
                    false,
                    'La unidad "' . $unidadElegida['nombre'] . '" no es compatible con el material "'
                    . $materialActual['nombre'] . '". Elige la unidad base del material o una unidad '
                    . 'compuesta de su misma familia.'
                );
            }
        }

        $equivalencia         = floatval($unidadElegida['equivalencia'] ?? 1);
        $equivalenciaMaterial = floatval($materialActual['unidad_equivalencia'] ?? 1) ?: 1;
        $linea['cantidad_base'] = $linea['cantidad'] * ($equivalencia / $equivalenciaMaterial);
    }
    unset($linea);

    $totalCompra = array_sum(array_column($detalle, 'total'));

    $jsDetalleSnapshot = array_map(function ($linea) use ($infoMaterial, $infoUnidad) {
        return [
            'material_id'      => $linea['material_id'],
            'material_nombre'  => $infoMaterial[$linea['material_id']]['nombre'],
            'unidad_medida_id' => $linea['unidad_medida_id'],
            'unidad_nombre'    => $infoUnidad[$linea['unidad_medida_id']]['nombre'] ?? null,
            'cantidad'         => $linea['cantidad'],
            'cantidad_base'    => $linea['cantidad_base'],
            'sub_total'        => $linea['sub_total'],
            'total'            => $linea['total'],
            'comentario'       => $linea['comentario'],
        ];
    }, $detalle);
    $jsDetalleJson = json_encode($jsDetalleSnapshot, JSON_UNESCAPED_UNICODE);

    $rutaNuevoComprobante = subirComprobante();

    $conectar->beginTransaction();
    try {
        if ($id === 0) {
            $movimiento   = obtenerMovimientoSesion('crear', [[
                'campo' => 'Compra', 'valor_antes' => '(nueva)',
                'valor_despues' => count($detalle) . ' material(es), total S/ ' . number_format($totalCompra, 2),
            ]]);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            $nuevaCompra = executeQuery($conectar, "
                INSERT INTO compra (
                    proveedor_id, fecha_compra, img_comprobante, descripcion,
                    total, total_img_cargado, js_detalle, operario_id,
                    created_at, js_session, js_historial
                ) VALUES (
                    :proveedor_id, :fecha_compra, :img_comprobante, :descripcion,
                    :total, :total_img_cargado, :js_detalle::jsonb, :operario_id,
                    NOW(), :js_session, :js_historial
                ) RETURNING id
            ", [
                'proveedor_id'      => $proveedor_id,
                'fecha_compra'      => $fecha_compra,
                'img_comprobante'   => $rutaNuevoComprobante,
                'descripcion'       => $descripcion ?: null,
                'total'             => $totalCompra,
                'total_img_cargado' => $totalImgCargado,
                'js_detalle'        => $jsDetalleJson,
                'operario_id'       => $operarioId,
                'js_session'        => $js_session,
                'js_historial'      => $js_historial,
            ]);
            $compraId = $nuevaCompra[0]['id'] ?? null;
            if (!$compraId) throw new Exception('No se pudo crear la cabecera de la compra.');

            insertarLineasYSumarStockTablet($conectar, $compraId, $detalle);

            $conectar->commit();
            responder(true, 'Compra registrada correctamente.', ['id' => $compraId, 'modo' => 'crear']);
        } else {
            $lineasAnteriores = executeQuery(
                $conectar,
                "SELECT * FROM rel_compra_material WHERE compra_id = :id AND deleted_at IS NULL",
                ['id' => $id]
            );
            $lineasAnterioresPorId = [];
            foreach ($lineasAnteriores as $la) {
                $lineasAnterioresPorId[(int)$la['id']] = $la;
            }

            $idsEnviados = [];
            foreach ($detalle as $linea) {
                if (!empty($linea['id'])) $idsEnviados[] = (int)$linea['id'];
            }

            $idsAEliminar = array_diff(array_keys($lineasAnterioresPorId), $idsEnviados);

            foreach ($idsAEliminar as $lineaIdEliminar) {
                $usoProduccion = executeQuery(
                    $conectar,
                    "SELECT id FROM rel_produccion_material WHERE rel_compra_material_id = :lote_id LIMIT 1",
                    ['lote_id' => $lineaIdEliminar]
                );
                if (!empty($usoProduccion)) {
                    $lineaVieja = $lineasAnterioresPorId[$lineaIdEliminar];
                    $nombreMat  = $infoMaterial[$lineaVieja['material_id']]['nombre']
                                  ?? ('material #' . $lineaVieja['material_id']);
                    throw new Exception(
                        'No puedes quitar "' . $nombreMat . '" de esta compra porque ese lote ya '
                        . 'fue usado en un registro de producción. Puedes editar su cantidad, pero '
                        . 'no eliminarlo de la compra.'
                    );
                }

                $lineaVieja       = $lineasAnterioresPorId[$lineaIdEliminar];
                $cantidadRevertir = $lineaVieja['cantidad_base'] ?? $lineaVieja['cantidad'];
                executeNonQuery(
                    $conectar,
                    "UPDATE material SET stock_actual = stock_actual - :cantidad WHERE id = :mid",
                    ['cantidad' => $cantidadRevertir, 'mid' => $lineaVieja['material_id']]
                );
                executeNonQuery($conectar, "DELETE FROM rel_compra_material WHERE id = :id", ['id' => $lineaIdEliminar]);
            }

            $detalleNuevas = [];
            foreach ($detalle as $linea) {
                $lineaId = $linea['id'] ? (int)$linea['id'] : null;

                if ($lineaId && isset($lineasAnterioresPorId[$lineaId])) {
                    $anterior         = $lineasAnterioresPorId[$lineaId];
                    $cantidadRevertir = $anterior['cantidad_base'] ?? $anterior['cantidad'];

                    executeNonQuery(
                        $conectar,
                        "UPDATE material SET stock_actual = stock_actual - :cantidad WHERE id = :mid",
                        ['cantidad' => $cantidadRevertir, 'mid' => $anterior['material_id']]
                    );
                    executeNonQuery(
                        $conectar,
                        "UPDATE material SET stock_actual = stock_actual + :cantidad WHERE id = :mid",
                        ['cantidad' => $linea['cantidad_base'], 'mid' => $linea['material_id']]
                    );

                    executeNonQuery($conectar, "
                        UPDATE rel_compra_material SET
                            material_id      = :material_id,
                            cantidad         = :cantidad,
                            unidad_medida_id = :unidad_medida_id,
                            cantidad_base    = :cantidad_base,
                            sub_total        = :sub_total,
                            total            = :total,
                            comentario       = :comentario,
                            update_at        = NOW()
                        WHERE id = :id
                    ", [
                        'material_id'      => $linea['material_id'],
                        'cantidad'         => $linea['cantidad'],
                        'unidad_medida_id' => $linea['unidad_medida_id'],
                        'cantidad_base'    => $linea['cantidad_base'],
                        'sub_total'        => $linea['sub_total'],
                        'total'            => $linea['total'],
                        'comentario'       => $linea['comentario'],
                        'id'               => $lineaId,
                    ]);
                } else {
                    $detalleNuevas[] = $linea;
                }
            }

            if (!empty($detalleNuevas)) {
                insertarLineasYSumarStockTablet($conectar, $id, $detalleNuevas);
            }

            $rutaFinalComprobante = $compraAnterior['img_comprobante'];
            if ($rutaNuevoComprobante !== null) {
                borrarArchivoComprobante($compraAnterior['img_comprobante']);
                $rutaFinalComprobante = $rutaNuevoComprobante;
            } elseif ($eliminarComprobante) {
                borrarArchivoComprobante($compraAnterior['img_comprobante']);
                $rutaFinalComprobante = null;
            }

            $movimiento   = obtenerMovimientoSesion('editar', [[
                'campo' => 'Compra', 'valor_antes' => 'total S/ ' . number_format($compraAnterior['total'], 2),
                'valor_despues' => 'total S/ ' . number_format($totalCompra, 2) . ' (' . count($detalle) . ' material(es))',
            ]]);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            executeNonQuery($conectar, "
                UPDATE compra SET
                    proveedor_id       = :proveedor_id,
                    fecha_compra       = :fecha_compra,
                    img_comprobante    = :img_comprobante,
                    descripcion        = :descripcion,
                    total              = :total,
                    total_img_cargado  = :total_img_cargado,
                    js_detalle         = :js_detalle::jsonb,
                    update_at          = NOW(),
                    js_session         = :js_session,
                    js_historial       = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
                WHERE id = :id
            ", [
                'proveedor_id'      => $proveedor_id,
                'fecha_compra'      => $fecha_compra,
                'img_comprobante'   => $rutaFinalComprobante,
                'descripcion'       => $descripcion ?: null,
                'total'             => $totalCompra,
                'total_img_cargado' => $totalImgCargado,
                'js_detalle'        => $jsDetalleJson,
                'js_session'        => $js_session,
                'js_historial'      => $js_historial,
                'id'                => $id,
            ]);

            $conectar->commit();
            responder(true, 'Compra actualizada correctamente.', ['id' => $id, 'modo' => 'editar']);
        }
    } catch (Throwable $e) {
        $conectar->rollBack();
        if ($rutaNuevoComprobante !== null) borrarArchivoComprobante($rutaNuevoComprobante);
        error_log("Error guardando compra (tablet): " . $e->getMessage());
        responder(false, 'No se pudo guardar la compra: ' . $e->getMessage());
    }
}

function insertarLineasYSumarStockTablet($conectar, int $compraId, array $detalle): void
{
    foreach ($detalle as $linea) {
        $movimiento   = obtenerMovimientoSesion('crear_linea');
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery($conectar, "
            INSERT INTO rel_compra_material (
                compra_id, material_id, cantidad, unidad_medida_id, cantidad_base,
                sub_total, total, comentario,
                created_at, js_session, js_historial
            ) VALUES (
                :compra_id, :material_id, :cantidad, :unidad_medida_id, :cantidad_base,
                :sub_total, :total, :comentario,
                NOW(), :js_session, :js_historial
            )
        ", [
            'compra_id'        => $compraId,
            'material_id'      => $linea['material_id'],
            'cantidad'         => $linea['cantidad'],
            'unidad_medida_id' => $linea['unidad_medida_id'],
            'cantidad_base'    => $linea['cantidad_base'],
            'sub_total'        => $linea['sub_total'],
            'total'            => $linea['total'],
            'comentario'       => $linea['comentario'],
            'js_session'       => $js_session,
            'js_historial'     => $js_historial,
        ]);

        executeNonQuery(
            $conectar,
            "UPDATE material SET stock_actual = stock_actual + :cantidad WHERE id = :mid",
            ['cantidad' => $linea['cantidad_base'], 'mid' => $linea['material_id']]
        );
    }
}

// =============================================================================
// HELPER
// =============================================================================

function responder(bool $ok, string $msg, array $extra = []): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}