<?php

/**
 * controllers/clssCompra.php
 * Controlador del módulo de Compras
 *
 * Tablas reales:
 *   compra (id, proveedor_id -> proveedor.ruc, fecha_compra, img_comprobante,
 *           descripcion, total, total_img_cargado, js_detalle jsonb, js_session,
 *           js_historial, created_at, update_at, deleted_at)
 *   rel_compra_material (id, compra_id, material_id, cantidad, unidad_medida_id,
 *           cantidad_base, sub_total, total, comentario, js_session, js_historial,
 *           created_at, update_at, deleted_at)
 *   unidad_medida (id, nombre, nombre_corto, unidad_base_id, equivalencia, ...)
 *
 * UNIDAD DE MEDIDA POR LÍNEA:
 *   - Cada línea de compra guarda la unidad en la que se compró
 *     (unidad_medida_id) y la cantidad tal cual la escribió el usuario
 *     en esa unidad (cantidad).
 *   - cantidad_base = cantidad * unidad_medida.equivalencia -> es la
 *     cantidad ya convertida a la unidad base del material (la unidad
 *     en la que se lleva material.stock_actual). Se calcula una sola
 *     vez al guardar y se persiste, no se recalcula después.
 *   - TODO movimiento de stock (crear, editar, desactivar, reactivar)
 *     usa cantidad_base, NUNCA cantidad.
 *   - `sub_total` en rel_compra_material representa el PRECIO UNITARIO
 *     (P.U) de la línea; `total` = cantidad * sub_total. El nombre de
 *     columna se mantiene por compatibilidad, pero su significado real
 *     es P.U, no un subtotal de línea.
 *
 * VALIDACIÓN DE FAMILIA (unidad ↔ material):
 *   - La unidad elegida en cada línea debe pertenecer a la misma familia
 *     que la unidad base del material: o ES esa unidad raíz, o es una
 *     compuesta cuyo unidad_base_id apunta exactamente a esa raíz.
 *     Se valida en el backend además del frontend, para que nadie
 *     pueda colar una unidad incompatible manipulando el request.
 *
 * MATERIALES DERIVADOS (material.derivado):
 *   - `derivado` (boolean) distingue materiales COMPUESTOS (se compran
 *     normalmente a proveedores) de materiales DERIVADOS (suelen salir
 *     como subproducto de un proceso interno, ej. "CLICK DE GANCHO").
 *   - Esta distinción es SOLO informativa en Compras: un material
 *     derivado (ej. clips de gancho) puede comprarse a un proveedor con
 *     total normalidad, así que NO se filtra ni se bloquea en
 *     BUSCARMATERIALES ni en guardarCompra(). Se expone el campo para
 *     que el frontend lo muestre como referencia visual, nada más.
 *
 * total_img_cargado:
 *   - Campo opcional (nullable) en `compra`. Guarda el monto REAL que
 *     figura en el comprobante subido (img_comprobante), que puede no
 *     coincidir con `total` (suma calculada de las líneas de material)
 *     por fletes, descuentos, redondeos, etc. Pensado para alimentar
 *     el futuro módulo de egresos: el egreso real de caja usa este
 *     campo (o `total` como fallback si no se cargó monto de comprobante).
 *
 * LÍNEAS DE COMPRA COMO "LOTE" EN PRODUCCIÓN:
 *   - El módulo de producción referencia filas de rel_compra_material
 *     por su id (rel_produccion_material.rel_compra_material_id ->
 *     rel_compra_material.id), tratando cada línea de compra como un
 *     lote específico.
 *   - Por eso, al EDITAR una compra, YA NO se puede borrar físicamente
 *     todas las líneas viejas y reinsertar nuevas (eso les cambiaría el
 *     id y rompería el FK, o directamente el DELETE fallaría con
 *     "violates foreign key constraint rel_produccion_material_lote_fkey").
 *   - En vez de eso, guardarCompra() hace un DIFF entre las líneas
 *     anteriores y las nuevas:
 *       - Línea que sigue viniendo (trae su `id`) -> UPDATE en el sitio,
 *         conserva su id, ajusta el stock por delta (revierte lo viejo,
 *         suma lo nuevo).
 *       - Línea nueva (sin `id`) -> INSERT normal, suma stock.
 *       - Línea que existía y ya NO viene en el detalle nuevo -> se
 *         intenta eliminar. Si está referenciada como lote en
 *         rel_produccion_material, se rechaza toda la edición con un
 *         mensaje explicando que ese material ya se usó en producción
 *         y no se puede quitar (sí se puede editar su cantidad).
 *
 * REGLAS DE STOCK (material.stock_actual):
 *   - Crear compra           -> SUMA cantidad_base de cada línea al stock del material.
 *   - Editar compra          -> ver diff explicado arriba. El resultado neto en
 *                                stock es el mismo que antes (revertir lo viejo,
 *                                aplicar lo nuevo), solo cambia CÓMO se hace a
 *                                nivel de filas para no romper el FK de producción.
 *   - Desactivar compra      -> RESTA cantidad_base de cada línea activa del stock y hace soft-delete
 *                                de esas líneas (rel_compra_material.deleted_at) y de la compra.
 *   - Reactivar compra       -> restaura (deleted_at = NULL) esas mismas líneas y vuelve a SUMAR
 *                                su cantidad_base al stock.
 *
 * Todo movimiento de varias tablas (compra + rel_compra_material + material.stock_actual)
 * va envuelto en una transacción para no dejar datos a medias.
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

// Carpeta donde se guardan los comprobantes subidos (sube 1 nivel desde controllers/)
define('CARPETA_COMPROBANTES', __DIR__ . '/../uploads/comprobantes/');
define('RUTA_WEB_COMPROBANTES', 'uploads/comprobantes/');

if (isset($_POST["accion"])) {
    try {
        controladorCompra($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssCompra.php: " . $e->getMessage());
        responder(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssCompra.php: " . $e->getMessage());
        responder(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorCompra($accion)
{
    switch ($accion) {
        case 'LISTARCOMPRAS':
            listarCompras();
            break;
        case 'OBTENERCOMPRA':
            obtenerCompra(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARCOMPRA':
            guardarCompra();
            break;
        case 'ELIMINARCOMPRA':
            eliminarCompra();
            break;
        case 'REACTIVARCOMPRA':
            reactivarCompra();
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
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// LISTADOS AUXILIARES (para los <select> del modal)
// =============================================================================

function buscarProveedores()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["deleted_at IS NULL"];
    $params = [];
    if ($texto !== '') {
        $where[] = "(LOWER(razon_social) LIKE LOWER(:texto) OR ruc LIKE :texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT ruc, razon_social, nombre_comercial FROM proveedor
            WHERE " . implode(' AND ', $where) . " ORDER BY razon_social LIMIT 50";

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

    // Se agrega m.unidad_medida_id y u.equivalencia para que el frontend pueda
    // preseleccionar la unidad base del material y mostrar la conversión.
    // m.derivado viaja también, pero SOLO como dato informativo para el
    // frontend (ver nota "MATERIALES DERIVADOS" al inicio del archivo):
    // un material derivado (ej. clips de gancho) sí puede comprarse a
    // proveedores con normalidad, así que aquí no se filtra ni se excluye.
    $sql = "SELECT m.id, m.nombre, m.stock_actual, m.unidad_medida_id, m.derivado,
                   u.nombre_corto AS unidad_corto, u.equivalencia AS unidad_equivalencia
            FROM material m
            LEFT JOIN unidad_medida u ON u.id = m.unidad_medida_id
            WHERE " . implode(' AND ', $where) . " ORDER BY m.nombre LIMIT 50";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['materiales' => $result]);
}

// Lista general de unidades de medida (se mantiene por compatibilidad; el
// selector de unidad por línea en el modal ya NO usa esta acción, usa
// LISTARUNIDADESCOMPATIBLES de clssUnidadMedida.php para filtrar por familia).
function buscarUnidades()
{
    $conectar = conectar_oll_BD();

    $sql = "SELECT id, nombre, nombre_corto, equivalencia
            FROM unidad_medida
            ORDER BY nombre";

    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['unidades' => $result]);
}

// =============================================================================
// COMPRAS
// =============================================================================

function listarCompras()
{
    $conectar = conectar_oll_BD();

    $texto        = trim($_POST['texto'] ?? '');
    $proveedor_id = trim($_POST['proveedor_id'] ?? '');
    $estado       = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'
    $fecha_desde  = trim($_POST['fecha_desde'] ?? '');
    $fecha_hasta  = trim($_POST['fecha_hasta'] ?? '');

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(p.razon_social) LIKE LOWER(:texto)
                     OR LOWER(c.descripcion) LIKE LOWER(:texto)
                     OR p.ruc LIKE :texto)";
        $params['texto'] = "%$texto%";
    }
    if ($proveedor_id !== '') {
        $where[] = "c.proveedor_id = :proveedor_id";
        $params['proveedor_id'] = $proveedor_id;
    }
    if ($estado === 'activa') {
        $where[] = "c.deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "c.deleted_at IS NOT NULL";
    }
    if ($fecha_desde !== '') {
        $where[] = "c.fecha_compra >= :fecha_desde";
        $params['fecha_desde'] = $fecha_desde;
    }
    if ($fecha_hasta !== '') {
        $where[] = "c.fecha_compra <= :fecha_hasta";
        $params['fecha_hasta'] = $fecha_hasta;
    }

    // c.* ya incluye total_img_cargado automáticamente, no se requiere tocar
    // esta consulta para exponer el nuevo campo al frontend.
    $sql = "
        SELECT
            c.*,
            p.razon_social,
            p.nombre_comercial,
            COALESCE(jsonb_array_length(c.js_detalle), 0) AS items_count
        FROM compra c
        JOIN proveedor p ON p.ruc = c.proveedor_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.fecha_compra DESC, c.id DESC
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['compras' => $result]);
}

function obtenerCompra($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $compra = executeQuery(
        $conectar,
        "SELECT c.*, p.razon_social, p.nombre_comercial
         FROM compra c JOIN proveedor p ON p.ruc = c.proveedor_id
         WHERE c.id = :id",
        ['id' => $id]
    );
    if (empty($compra)) responder(false, 'Compra no encontrada.');

    // Se agrega el join a unidad_medida para traer la unidad EN LA QUE SE COMPRÓ
    // esa línea (rcm.unidad_medida_id), que puede ser distinta a la unidad base
    // del material. rcm.id viaja en rcm.* -> el frontend lo necesita para poder
    // editar la línea en el sitio en vez de recrearla (ver guardarCompra()).
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

/**
 * Auditoría (idéntico patrón al resto de controladores).
 */
function obtenerIpCliente(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return trim($_SERVER['HTTP_X_REAL_IP']);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'N/A';
}

function obtenerMovimientoSesion(string $accion, array $cambios = []): array
{
    return [
        'usuario'   => $_SESSION['usuario_id'] ?? 'Sistema',
        'nombre'    => $_SESSION['nombre_usuario'] ?? 'Usuario Desconocido',
        'user'      => $_SESSION['user_usuario'] ?? 'N/A',
        'perfiles'  => $_SESSION['perfiles'] ?? 'N/A',
        'rol'       => $_SESSION['rol_usuario'] ?? 'N/A',
        'accion'    => $accion,
        'ip'        => obtenerIpCliente(),
        'cambios'   => $cambios,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
}

/**
 * Sube el comprobante (si vino uno en $_FILES) y devuelve la ruta relativa a guardar en BD.
 * Devuelve null si no vino archivo.
 */
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

    if (!is_dir(CARPETA_COMPROBANTES)) {
        mkdir(CARPETA_COMPROBANTES, 0755, true);
    }

    $nombreArchivo = 'comprobante_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $rutaDestino   = CARPETA_COMPROBANTES . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        responder(false, 'No se pudo guardar el archivo del comprobante en el servidor.');
    }

    return RUTA_WEB_COMPROBANTES . $nombreArchivo;
}

function borrarArchivoComprobante(?string $rutaRelativa): void
{
    if (empty($rutaRelativa)) return;
    $rutaFisica = __DIR__ . '/../' . $rutaRelativa;
    if (is_file($rutaFisica)) {
        @unlink($rutaFisica);
    }
}

function guardarCompra()
{
    $conectar = conectar_oll_BD();

    $id           = intval($_POST['id'] ?? 0);
    $proveedor_id = trim($_POST['proveedor_id'] ?? '');
    $fecha_compra = trim($_POST['fecha_compra'] ?? '');
    $descripcion  = trim($_POST['descripcion'] ?? '');
    $detalleJson  = trim($_POST['detalle'] ?? '[]');
    $eliminarComprobante = ($_POST['eliminar_comprobante'] ?? '') === '1';

    // Monto real del comprobante subido (para el futuro módulo de egresos).
    // Opcional: si no lo mandan o mandan vacío, queda NULL.
    $totalImgCargadoRaw = trim($_POST['total_img_cargado'] ?? '');
    $totalImgCargado = ($totalImgCargadoRaw !== '') ? floatval($totalImgCargadoRaw) : null;

    // ── Validaciones básicas ─────────────────────────────────────────────────
    if (empty($proveedor_id)) responder(false, 'Debes seleccionar un proveedor.');
    if (empty($fecha_compra)) responder(false, 'La fecha de compra es obligatoria.');
    if ($totalImgCargado !== null && $totalImgCargado < 0) {
        responder(false, 'El monto del comprobante no puede ser negativo.');
    }

    $proveedor = executeQuery($conectar, "SELECT ruc FROM proveedor WHERE ruc = :ruc", ['ruc' => $proveedor_id]);
    if (empty($proveedor)) responder(false, 'El proveedor seleccionado no existe.');

    $detalleEntrada = json_decode($detalleJson, true);
    if (!is_array($detalleEntrada)) $detalleEntrada = [];

    $detalle = [];
    foreach ($detalleEntrada as $linea) {
        // id de la línea existente (rel_compra_material.id), si venía de una
        // compra que se está editando. Vacío/0 = línea nueva.
        $lineaId          = intval($linea['id'] ?? 0);
        $materialId       = intval($linea['material_id'] ?? 0);
        $unidadMedidaId   = intval($linea['unidad_medida_id'] ?? 0);
        $cantidad         = floatval($linea['cantidad'] ?? 0);
        $subTotal         = floatval($linea['sub_total'] ?? 0); // P.U (precio unitario) de la línea
        $total            = isset($linea['total']) && $linea['total'] !== '' ? floatval($linea['total']) : $subTotal;
        $comentario       = trim($linea['comentario'] ?? '');

        if ($materialId <= 0 || $unidadMedidaId <= 0 || $cantidad <= 0) continue; // fila incompleta, se ignora

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

    // Traemos los materiales usados junto con su unidad_medida_id (la unidad
    // RAÍZ del material, en la que se lleva el stock). La necesitamos para
    // el snapshot y para validar que la unidad elegida sea de su misma familia.
    // NOTA: aquí NO se filtra ni se rechaza nada por m.derivado — un material
    // derivado (ej. clips de gancho) se compra igual que cualquier otro.
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
        "SELECT id, nombre, unidad_medida_id FROM material WHERE id IN (" . implode(',', $placeholders) . ")",
        $paramsIn
    );
    $infoMaterial = [];
    foreach ($materialesInfo as $m) $infoMaterial[$m['id']] = $m; // id, nombre, unidad_medida_id

    // Traemos las unidades usadas junto con unidad_base_id, para saber a qué
    // familia pertenece cada una (si unidad_base_id es NULL, es ella misma la raíz).
    $unidadesIds     = array_column($detalle, 'unidad_medida_id');
    $placeholdersU   = [];
    $paramsInU       = [];
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
        $raizMaterialId = $materialActual['unidad_medida_id']; // puede ser NULL

        // ── Validación de familia ────────────────────────────────────────────
        // La unidad elegida en la línea debe pertenecer a la misma familia que
        // la unidad base del material: o ES esa raíz, o es una compuesta cuyo
        // unidad_base_id apunta exactamente a esa raíz. Si el material no tiene
        // unidad base asignada (caso legado/opcional), no hay forma de validar,
        // así que lo dejamos pasar tal cual estaba antes.
        if ($raizMaterialId !== null) {
            $esLaMismaRaiz           = ((int)$linea['unidad_medida_id'] === (int)$raizMaterialId);
            $esCompuestaDeEsaFamilia = ((int)($unidadElegida['unidad_base_id'] ?? 0) === (int)$raizMaterialId);

            if (!$esLaMismaRaiz && !$esCompuestaDeEsaFamilia) {
                responder(
                    false,
                    'La unidad "' . $unidadElegida['nombre'] . '" no es compatible con el material "'
                    . $materialActual['nombre'] . '". Elige la unidad base del material o una unidad '
                    . 'compuesta de su misma familia (ej: si el material es por kg, usa Kilogramo, '
                    . 'Saco 25kg, Bolsa 25kg, etc.).'
                );
            }
        }

        $equivalencia = floatval($unidadElegida['equivalencia'] ?? 1);
        $linea['cantidad_base'] = $linea['cantidad'] * $equivalencia;
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
            'sub_total'        => $linea['sub_total'], // P.U de la línea
            'total'            => $linea['total'],
            'comentario'       => $linea['comentario'],
        ];
    }, $detalle);
    $jsDetalleJson = json_encode($jsDetalleSnapshot, JSON_UNESCAPED_UNICODE);

    // ── Comprobante ──────────────────────────────────────────────────────────
    $rutaNuevoComprobante = subirComprobante(); // null si no mandaron archivo nuevo

    $conectar->beginTransaction();
    try {
        if ($id === 0) {
            // ── CREACIÓN ─────────────────────────────────────────────────────
            $cambios = [[
                'campo' => 'Compra', 'valor_antes' => '(nueva)',
                'valor_despues' => count($detalle) . ' material(es), total S/ ' . number_format($totalCompra, 2),
            ]];
            $movimiento   = obtenerMovimientoSesion('crear', $cambios);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            $nuevaCompra = executeQuery($conectar, "
                INSERT INTO compra (
                    proveedor_id, fecha_compra, img_comprobante, descripcion,
                    total, total_img_cargado, js_detalle, created_at, js_session, js_historial
                ) VALUES (
                    :proveedor_id, :fecha_compra, :img_comprobante, :descripcion,
                    :total, :total_img_cargado, :js_detalle::jsonb, NOW(), :js_session, :js_historial
                ) RETURNING id
            ", [
                'proveedor_id'      => $proveedor_id,
                'fecha_compra'      => $fecha_compra,
                'img_comprobante'   => $rutaNuevoComprobante,
                'descripcion'       => $descripcion ?: null,
                'total'             => $totalCompra,
                'total_img_cargado' => $totalImgCargado,
                'js_detalle'        => $jsDetalleJson,
                'js_session'        => $js_session,
                'js_historial'      => $js_historial,
            ]);
            $compraId = $nuevaCompra[0]['id'] ?? null;
            if (!$compraId) throw new Exception('No se pudo crear la cabecera de la compra.');

            // Compra nueva: todas las líneas son nuevas, se insertan tal cual.
            insertarLineasYSumarStock($conectar, $compraId, $detalle);

            $conectar->commit();
            responder(true, 'Compra registrada correctamente.', ['id' => $compraId, 'modo' => 'crear']);
        } else {
            // ── EDICIÓN ──────────────────────────────────────────────────────
            $actual = executeQuery($conectar, "SELECT * FROM compra WHERE id = :id", ['id' => $id]);
            if (empty($actual)) throw new Exception('Compra no encontrada.');
            if (!empty($actual[0]['deleted_at'])) {
                throw new Exception('No puedes editar una compra inactiva. Reactívala primero.');
            }
            $compraAnterior = $actual[0];

            // Líneas activas actuales, indexadas por id, para poder diffearlas
            // contra el detalle nuevo que mandó el frontend.
            $lineasAnteriores = executeQuery(
                $conectar,
                "SELECT * FROM rel_compra_material WHERE compra_id = :id AND deleted_at IS NULL",
                ['id' => $id]
            );
            $lineasAnterioresPorId = [];
            foreach ($lineasAnteriores as $la) {
                $lineasAnterioresPorId[(int)$la['id']] = $la;
            }

            // IDs de líneas existentes que el frontend siguió enviando (se
            // mantienen, hayan cambiado o no sus datos).
            $idsEnviados = [];
            foreach ($detalle as $linea) {
                if (!empty($linea['id'])) $idsEnviados[] = (int)$linea['id'];
            }

            // Líneas que existían y YA NO vienen en el detalle nuevo = el
            // usuario las quitó del formulario.
            $idsAEliminar = array_diff(array_keys($lineasAnterioresPorId), $idsEnviados);

            foreach ($idsAEliminar as $lineaIdEliminar) {
                // No se puede quitar una línea de compra que ya fue usada como
                // lote en producción (rel_produccion_material.lote_id la
                // referencia por foreign key) - por eso el error original.
                $usoProduccion = executeQuery(
                    $conectar,
                    "SELECT id FROM rel_produccion_material WHERE rel_compra_material_id = :lote_id LIMIT 1",
                    ['lote_id' => $lineaIdEliminar]
                );
                if (!empty($usoProduccion)) {
                    $lineaVieja  = $lineasAnterioresPorId[$lineaIdEliminar];
                    $nombreMat   = $infoMaterial[$lineaVieja['material_id']]['nombre']
                                   ?? ('material #' . $lineaVieja['material_id']);
                    throw new Exception(
                        'No puedes quitar "' . $nombreMat . '" de esta compra porque ese lote ya '
                        . 'fue usado en un registro de producción. Puedes editar su cantidad, pero '
                        . 'no eliminarlo de la compra.'
                    );
                }

                // Es seguro eliminarla: revertimos su cantidad_base del stock y
                // la borramos físicamente (igual que el comportamiento anterior).
                $lineaVieja       = $lineasAnterioresPorId[$lineaIdEliminar];
                $cantidadRevertir = $lineaVieja['cantidad_base'] ?? $lineaVieja['cantidad'];
                executeNonQuery(
                    $conectar,
                    "UPDATE material SET stock_actual = stock_actual - :cantidad WHERE id = :mid",
                    ['cantidad' => $cantidadRevertir, 'mid' => $lineaVieja['material_id']]
                );
                executeNonQuery(
                    $conectar,
                    "DELETE FROM rel_compra_material WHERE id = :id",
                    ['id' => $lineaIdEliminar]
                );
            }

            // Líneas que siguen viniendo con id -> UPDATE en el sitio (conserva
            // el id, así el lote_id de producción sigue siendo válido). Las que
            // no traen id son líneas nuevas -> se insertan al final.
            $detalleNuevas = [];
            foreach ($detalle as $linea) {
                $lineaId = $linea['id'] ? (int)$linea['id'] : null;

                if ($lineaId && isset($lineasAnterioresPorId[$lineaId])) {
                    $anterior         = $lineasAnterioresPorId[$lineaId];
                    $cantidadRevertir = $anterior['cantidad_base'] ?? $anterior['cantidad'];

                    // Ajuste de stock por delta: revierte lo que esta línea
                    // aportaba antes y aplica lo que aporta ahora. Si no cambió
                    // nada, el neto es cero (resta y luego suma lo mismo).
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
                    // Sin id (o con un id que ya no existe/no es de esta compra) = línea nueva.
                    $detalleNuevas[] = $linea;
                }
            }

            if (!empty($detalleNuevas)) {
                insertarLineasYSumarStock($conectar, $id, $detalleNuevas);
            }

            // Comprobante: si subieron uno nuevo, reemplaza; si marcaron "eliminar", lo quitamos;
            // si no, se mantiene el que ya había.
            $rutaFinalComprobante = $compraAnterior['img_comprobante'];
            if ($rutaNuevoComprobante !== null) {
                borrarArchivoComprobante($compraAnterior['img_comprobante']);
                $rutaFinalComprobante = $rutaNuevoComprobante;
            } elseif ($eliminarComprobante) {
                borrarArchivoComprobante($compraAnterior['img_comprobante']);
                $rutaFinalComprobante = null;
            }

            $cambios = [[
                'campo' => 'Compra', 'valor_antes' => 'total S/ ' . number_format($compraAnterior['total'], 2),
                'valor_despues' => 'total S/ ' . number_format($totalCompra, 2) . ' (' . count($detalle) . ' material(es))',
            ]];
            $movimiento   = obtenerMovimientoSesion('editar', $cambios);
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
        // Si habíamos subido un archivo nuevo y la transacción falló, lo borramos para no dejar huérfanos
        if ($rutaNuevoComprobante !== null) borrarArchivoComprobante($rutaNuevoComprobante);
        error_log("Error guardando compra: " . $e->getMessage());
        responder(false, 'No se pudo guardar la compra: ' . $e->getMessage());
    }
}

/**
 * Inserta las líneas de detalle de una compra y suma cantidad_base al stock
 * del material correspondiente (NUNCA cantidad, que está en la unidad que
 * eligió el usuario y puede no coincidir con la unidad base del material).
 * Solo se usa para líneas realmente NUEVAS (sin id previo).
 */
function insertarLineasYSumarStock($conectar, int $compraId, array $detalle): void
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

// Soft delete: revierte el stock (cantidad_base) de las líneas activas y desactiva compra + líneas.
function eliminarCompra()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM compra WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Compra no encontrada.');
    if (!empty($existe[0]['deleted_at'])) responder(false, 'Esta compra ya estaba inactiva.');

    $conectar->beginTransaction();
    try {
        $lineas = executeQuery(
            $conectar,
            "SELECT * FROM rel_compra_material WHERE compra_id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );

        foreach ($lineas as $linea) {
            $cantidadRevertir = $linea['cantidad_base'] ?? $linea['cantidad'];
            executeNonQuery(
                $conectar,
                "UPDATE material SET stock_actual = stock_actual - :cantidad WHERE id = :mid",
                ['cantidad' => $cantidadRevertir, 'mid' => $linea['material_id']]
            );
        }

        executeNonQuery(
            $conectar,
            "UPDATE rel_compra_material SET deleted_at = NOW(), update_at = NOW() WHERE compra_id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );

        $cambios = [[
            'campo' => 'Estado', 'valor_antes' => 'Activa', 'valor_despues' => 'Inactiva (stock revertido)',
        ]];
        $movimiento   = obtenerMovimientoSesion('desactivar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery(
            $conectar,
            "UPDATE compra SET
                deleted_at   = NOW(),
                update_at    = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id",
            ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]
        );

        $conectar->commit();
        responder(true, 'Compra desactivada y stock revertido correctamente.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error desactivando compra: " . $e->getMessage());
        responder(false, 'No se pudo desactivar la compra: ' . $e->getMessage());
    }
}

// Restaura las líneas que fueron desactivadas junto con la compra y vuelve a sumar cantidad_base al stock.
function reactivarCompra()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM compra WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Compra no encontrada.');
    if (empty($existe[0]['deleted_at'])) responder(false, 'Esta compra ya estaba activa.');

    $conectar->beginTransaction();
    try {
        $lineas = executeQuery(
            $conectar,
            "SELECT * FROM rel_compra_material WHERE compra_id = :id AND deleted_at IS NOT NULL",
            ['id' => $id]
        );

        foreach ($lineas as $linea) {
            $cantidadRestaurar = $linea['cantidad_base'] ?? $linea['cantidad'];
            executeNonQuery(
                $conectar,
                "UPDATE material SET stock_actual = stock_actual + :cantidad WHERE id = :mid",
                ['cantidad' => $cantidadRestaurar, 'mid' => $linea['material_id']]
            );
        }

        executeNonQuery(
            $conectar,
            "UPDATE rel_compra_material SET deleted_at = NULL, update_at = NOW() WHERE compra_id = :id AND deleted_at IS NOT NULL",
            ['id' => $id]
        );

        $cambios = [[
            'campo' => 'Estado', 'valor_antes' => 'Inactiva', 'valor_despues' => 'Activa (stock restaurado)',
        ]];
        $movimiento   = obtenerMovimientoSesion('reactivar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery(
            $conectar,
            "UPDATE compra SET
                deleted_at   = NULL,
                update_at    = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id",
            ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]
        );

        $conectar->commit();
        responder(true, 'Compra reactivada y stock restaurado correctamente.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error reactivando compra: " . $e->getMessage());
        responder(false, 'No se pudo reactivar la compra: ' . $e->getMessage());
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