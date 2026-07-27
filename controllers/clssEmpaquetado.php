<?php

/**
 * controllers/clssEmpaquetado.php
 * Controlador del módulo de Empaquetado
 *
 * ACTUALIZADO (2026-07-27 v2): se adapta a la tabla `empaquetado` definitiva
 * creada por el usuario, que reemplaza la columna `cantidad` (numeric) por:
 *
 *   cantidad_tota numeric  -- total, según la unidad de medida seleccionada
 *   js_cantidades jsonb    -- detalle de los "bultos" individuales de ESTA
 *                              operación de empaquetado
 *
 * SUPUESTO (sin confirmar): js_cantidades es un arreglo de objetos, uno por
 * cada bulto registrado en la misma operación, con la forma:
 *     [{"cantidad": 25}, {"cantidad": 25}, {"cantidad": 18.5}]
 * cantidad_tota = suma de todos los "cantidad" del arreglo. Esto permite
 * registrar, por ejemplo, 3 sacos de una sola vez (misma unidad de medida,
 * mismo operario) sin crear 3 filas separadas, conservando el detalle de
 * cuánto pesó cada saco. Si la idea era otra estructura, avísame y ajusto
 * tanto este controlador como el parseo en el frontend.
 *
 * Se elimina la acción BUSCARPRODUCTOS: producto_id siempre se deriva del
 * ensamblaje, nunca se elige aparte en ningún formulario, así que no hace
 * falta un buscador de productos en este módulo.
 *
 * Tabla real:
 *   empaquetado (id, producto_id -> producto, emsamblaje_id -> ensamblaje,
 *                unidad_medida -> unidad_medida, operario_id -> operario,
 *                pasado_venta [timestamp, NULL = aún no pasó a venta],
 *                venta_id_ref [FUTURO: se llenará cuando exista el módulo de
 *                ventas; por ahora esta columna no se toca desde aquí],
 *                cantidad_tota, js_cantidades,
 *                js_session, js_historial, created_at, update_at, deleted_at)
 *
 * MODELO (igual que antes, sin cambios):
 *   producto_id se DERIVA siempre de emsamblaje_id (producto_id del
 *   ensamblaje asociado), nunca se elige aparte en el formulario.
 *
 *   Un ensamblaje solo puede empaquetarse una vez finalizado (fin IS NOT
 *   NULL). Un mismo ensamblaje puede tener VARIAS filas de empaquetado
 *   (cada fila = una operación de empaquetado con uno o más bultos, en UNA
 *   sola unidad de medida y UN solo operario).
 *
 *   pasado_venta / venta_id_ref: columnas FUTURAS para cuando exista el
 *   módulo de ventas. Por ahora solo se muestran (de solo lectura) en el
 *   listado; este controlador no las modifica. No se permite eliminar ni
 *   editar una fila que ya tenga pasado_venta con valor (se considera
 *   "cerrada" una vez que salió a venta).
 *
 * SUPUESTO (sin confirmar): unidad_medida(id, nombre, nombre_corto, activo),
 * mismo patrón usado en clssEnsamblaje.php (u.nombre_corto).
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    try {
        controladorEmpaquetado($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssEmpaquetado.php: " . $e->getMessage());
        responder(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssEmpaquetado.php: " . $e->getMessage());
        responder(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorEmpaquetado($accion)
{
    switch ($accion) {
        case 'LISTARENSAMBLAJESPARAEMPAQUETADO':
            listarEnsamblajesParaEmpaquetado();
            break;
        case 'LISTAREMPAQUETADOS':
            listarEmpaquetados(intval($_POST['ensamblaje_id'] ?? 0));
            break;
        case 'OBTENEREMPAQUETADO':
            obtenerEmpaquetado(intval($_POST['id'] ?? 0));
            break;
        case 'CREAREMPAQUETADO':
            crearEmpaquetado();
            break;
        case 'EDITAREMPAQUETADO':
            editarEmpaquetado();
            break;
        case 'ELIMINAREMPAQUETADO':
            eliminarEmpaquetado(intval($_POST['id'] ?? 0));
            break;
        case 'REACTIVAREMPAQUETADO':
            reactivarEmpaquetado(intval($_POST['id'] ?? 0));
            break;
        case 'BUSCARUNIDADESMEDIDA':
            buscarUnidadesMedida();
            break;
        case 'BUSCAROPERARIOS':
            buscarOperarios();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// LISTADOS AUXILIARES
// =============================================================================

function buscarOperarios()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre_completo, cargo FROM operario WHERE activo = true ORDER BY nombre_completo";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['operario' => $result]);
}

// SUPUESTO: unidad_medida(id, nombre, nombre_corto, activo). Ajustar si la
// tabla real tiene otras columnas (mismo patrón usado en clssEnsamblaje.php).
function buscarUnidadesMedida()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["deleted_at IS NULL"];
    $params = [];
    if ($texto !== '') {
        $where[] = "LOWER(nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT id, nombre, nombre_corto, unidad_base_id, equivalencia
            FROM unidad_medida
            WHERE " . implode(' AND ', $where) . " ORDER BY nombre LIMIT 100";
    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['unidades' => $result]);
}
// Ensamblajes finalizados disponibles para empaquetar (grid principal).
// Incluye conteo Y suma de cantidad_tota ya empaquetada, solo informativo
// (ya no hay "saldo pendiente" que calcular: un ensamblaje puede tener
// cuantas filas de empaquetado se necesiten).
function listarEnsamblajesParaEmpaquetado()
{
    $conectar          = conectar_oll_BD();
    $texto             = trim($_POST['texto'] ?? '');
    $productoId        = trim($_POST['producto_id'] ?? '');
    $soloSinEmpaquetar = ($_POST['solo_sin_empaquetar'] ?? '0') === '1';

    $where  = ["e.deleted_at IS NULL", "e.fin IS NOT NULL", "e.ensamblaje_id_referido IS NULL"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(p.codigo) LIKE LOWER(:texto) OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($productoId !== '') {
        $where[] = "e.producto_id = :producto_id";
        $params['producto_id'] = $productoId;
    }
    if ($soloSinEmpaquetar) {
        $where[] = "NOT EXISTS (
            SELECT 1 FROM empaquetado emp
            WHERE emp.emsamblaje_id = e.id AND emp.deleted_at IS NULL
        )";
    }

    $sql = "SELECT
                e.id AS ensamblaje_id,
                e.producto_id,
                p.codigo AS producto_codigo,
                p.descripcion AS producto_descripcion,
                e.cantidad_peso_kg,
                e.fin,
                o.nombre_completo AS operario_ensamblaje_nombre,
                (
                    SELECT COUNT(*) FROM empaquetado emp
                    WHERE emp.emsamblaje_id = e.id AND emp.deleted_at IS NULL
                ) AS empaquetados_count,
                (
                    SELECT COALESCE(SUM(emp.cantidad_tota), 0) FROM empaquetado emp
                    WHERE emp.emsamblaje_id = e.id AND emp.deleted_at IS NULL
                ) AS cantidad_total_empaquetada
            FROM ensamblaje e
            LEFT JOIN producto p ON p.id = e.producto_id
            LEFT JOIN operario o ON o.id = e.operario_ortorgado
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.fin DESC";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['ensamblajes' => $result]);
}

// =============================================================================
// EMPAQUETADO (registro plano, con bultos internos en js_cantidades)
// =============================================================================

function listarEmpaquetados(int $ensamblajeId)
{
    if (!$ensamblajeId) responder(false, 'ID de ensamblaje inválido.');
    $conectar = conectar_oll_BD();

    $result = executeQuery($conectar, "
        SELECT
            emp.id, emp.producto_id, emp.emsamblaje_id, emp.unidad_medida,
            emp.cantidad_tota, emp.js_cantidades,
            emp.operario_id, emp.pasado_venta, emp.venta_id_ref,
            emp.created_at, emp.update_at,
            um.nombre AS unidad_nombre, um.nombre_corto AS unidad_corto,
            um.equivalencia, um.unidad_base_id,
            ub.nombre_corto AS unidad_base_corto,
            CASE WHEN um.unidad_base_id IS NOT NULL
                 THEN emp.cantidad_tota * um.equivalencia
                 ELSE NULL END AS cantidad_tota_en_base,
            op.nombre_completo AS operario_nombre
        FROM empaquetado emp
        LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
        LEFT JOIN unidad_medida ub ON ub.id = um.unidad_base_id
        LEFT JOIN operario op ON op.id = emp.operario_id
        WHERE emp.emsamblaje_id = :ensamblaje_id AND emp.deleted_at IS NULL
        ORDER BY emp.created_at DESC
    ", ['ensamblaje_id' => $ensamblajeId]);

    responder(true, 'OK', ['empaquetados' => $result]);
}
function obtenerEmpaquetado(int $id)
{
    if (!$id) responder(false, 'ID inválido.');
    $conectar = conectar_oll_BD();

    $result = executeQuery($conectar, "
        SELECT emp.*, um.nombre_corto AS unidad_corto
        FROM empaquetado emp
        LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
        WHERE emp.id = :id AND emp.deleted_at IS NULL
    ", ['id' => $id]);

    if (empty($result)) responder(false, 'Registro de empaquetado no encontrado o inactivo.');
    responder(true, 'OK', ['empaquetado' => $result[0]]);
}

// Normaliza el arreglo de bultos que llega del frontend (JSON de números o
// de objetos {cantidad: n}) a un arreglo limpio [{"cantidad": n}, ...],
// descartando cualquier valor <= 0. Devuelve también el total ya sumado.
function normalizarBultos($bultosEntrada): array
{
    if (!is_array($bultosEntrada)) return [[], 0.0];

    $bultos = [];
    foreach ($bultosEntrada as $b) {
        $cantidad = is_array($b) ? floatval($b['cantidad'] ?? 0) : floatval($b);
        if ($cantidad > 0) {
            $bultos[] = ['cantidad' => $cantidad];
        }
    }
    $total = array_sum(array_column($bultos, 'cantidad'));
    return [$bultos, $total];
}

function crearEmpaquetado()
{
    $conectar = conectar_oll_BD();

    $ensamblajeId = intval($_POST['ensamblaje_id'] ?? 0);
    $unidadMedida = intval($_POST['unidad_medida'] ?? 0);
    $operarioId   = intval($_POST['operario_id'] ?? 0);
    $bultosJson   = trim($_POST['bultos'] ?? '[]');

    if (!$ensamblajeId) responder(false, 'ID de ensamblaje inválido.');
    if (!$unidadMedida) responder(false, 'Debes indicar la unidad de medida.');
    if (!$operarioId) responder(false, 'Debes indicar el operario.');

    [$bultos, $cantidadTotal] = normalizarBultos(json_decode($bultosJson, true));
    if (empty($bultos)) responder(false, 'Debes registrar al menos un bulto con cantidad mayor a 0.');

    $ensamblaje = executeQuery(
        $conectar,
        "SELECT id, producto_id, fin, deleted_at FROM ensamblaje WHERE id = :id",
        ['id' => $ensamblajeId]
    );
    if (empty($ensamblaje)) responder(false, 'El ensamblaje indicado no existe.');
    if (!empty($ensamblaje[0]['deleted_at'])) responder(false, 'Este ensamblaje está inactivo.');
    if (empty($ensamblaje[0]['fin'])) responder(false, 'Este ensamblaje aún no ha finalizado; no se puede empaquetar todavía.');
    $productoId = intval($ensamblaje[0]['producto_id']);

    $unidad = executeQuery($conectar, "SELECT id FROM unidad_medida WHERE id = :id AND deleted_at IS NULL", ['id' => $unidadMedida]);    if (empty($unidad)) responder(false, 'La unidad de medida indicada no existe o está inactiva.');

    $operario = executeQuery($conectar, "SELECT id FROM operario WHERE id = :id AND activo = true", ['id' => $operarioId]);
    if (empty($operario)) responder(false, 'El operario indicado no existe o está inactivo.');

    $cambios = [[
        'campo' => 'Empaquetado', 'valor_antes' => '(nuevo)',
        'valor_despues' => count($bultos) . " bulto(s), total $cantidadTotal",
    ]];
    $movimiento   = obtenerMovimientoSesionEmp('crear', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);
    $js_cantidades = json_encode($bultos, JSON_UNESCAPED_UNICODE);

    $nuevo = executeQuery($conectar, "
        INSERT INTO empaquetado (
            producto_id, emsamblaje_id, unidad_medida, operario_id,
            cantidad_tota, js_cantidades,
            created_at, js_session, js_historial
        ) VALUES (
            :producto_id, :emsamblaje_id, :unidad_medida, :operario_id,
            :cantidad_tota, :js_cantidades,
            NOW(), :js_session, :js_historial
        ) RETURNING id
    ", [
        'producto_id'    => $productoId,
        'emsamblaje_id'  => $ensamblajeId,
        'unidad_medida'  => $unidadMedida,
        'operario_id'    => $operarioId,
        'cantidad_tota'  => $cantidadTotal,
        'js_cantidades'  => $js_cantidades,
        'js_session'     => $js_session,
        'js_historial'   => $js_historial,
    ]);

    responder(true, 'Empaquetado registrado correctamente.', ['id' => $nuevo[0]['id'] ?? null]);
}

function editarEmpaquetado()
{
    $conectar = conectar_oll_BD();

    $id           = intval($_POST['id'] ?? 0);
    $unidadMedida = intval($_POST['unidad_medida'] ?? 0);
    $operarioId   = intval($_POST['operario_id'] ?? 0);
    $bultosJson   = trim($_POST['bultos'] ?? '[]');

    if (!$id) responder(false, 'ID inválido.');
    if (!$unidadMedida) responder(false, 'Debes indicar la unidad de medida.');
    if (!$operarioId) responder(false, 'Debes indicar el operario.');

    [$bultos, $cantidadTotal] = normalizarBultos(json_decode($bultosJson, true));
    if (empty($bultos)) responder(false, 'Debes registrar al menos un bulto con cantidad mayor a 0.');

    $actual = executeQuery($conectar, "SELECT * FROM empaquetado WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    if (empty($actual)) responder(false, 'Registro de empaquetado no encontrado o inactivo.');
    if (!empty($actual[0]['pasado_venta'])) {
        responder(false, 'Este registro ya pasó a venta y no se puede editar.');
    }

    $unidad = executeQuery($conectar, "SELECT id FROM unidad_medida WHERE id = :id AND deleted_at IS NULL", ['id' => $unidadMedida]);
    if (empty($unidad)) responder(false, 'La unidad de medida indicada no existe o está inactiva.');

    $operario = executeQuery($conectar, "SELECT id FROM operario WHERE id = :id AND activo = true", ['id' => $operarioId]);
    if (empty($operario)) responder(false, 'El operario indicado no existe o está inactivo.');

    $cambios = [[
        'campo' => 'Empaquetado',
        'valor_antes' => "{$actual[0]['cantidad_tota']} / unidad #{$actual[0]['unidad_medida']}",
        'valor_despues' => "$cantidadTotal / unidad #$unidadMedida",
    ]];
    $movimiento   = obtenerMovimientoSesionEmp('editar', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);
    $js_cantidades = json_encode($bultos, JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE empaquetado SET
            unidad_medida  = :unidad_medida,
            operario_id    = :operario_id,
            cantidad_tota  = :cantidad_tota,
            js_cantidades  = :js_cantidades,
            update_at      = NOW(),
            js_session     = :js_session,
            js_historial   = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", [
        'unidad_medida' => $unidadMedida,
        'operario_id'   => $operarioId,
        'cantidad_tota' => $cantidadTotal,
        'js_cantidades' => $js_cantidades,
        'js_session'    => $js_session,
        'js_historial'  => $js_historial,
        'id'            => $id,
    ]);

    responder(true, 'Empaquetado actualizado correctamente.', ['id' => $id]);
}

// Soft-delete. No se permite si ya pasó a venta (pasado_venta con valor).
function eliminarEmpaquetado(int $id)
{
    if (!$id) responder(false, 'ID inválido.');
    $conectar = conectar_oll_BD();

    $existe = executeQuery($conectar, "SELECT id, pasado_venta FROM empaquetado WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de empaquetado no encontrado o ya inactivo.');
    if (!empty($existe[0]['pasado_venta'])) {
        responder(false, 'Este registro ya pasó a venta y no se puede eliminar.');
    }

    $cambios = [['campo' => 'Estado', 'valor_antes' => 'Activo', 'valor_despues' => 'Inactivo']];
    $movimiento   = obtenerMovimientoSesionEmp('desactivar', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE empaquetado SET
            deleted_at   = NOW(),
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]);

    responder(true, 'Empaquetado eliminado correctamente.');
}

function reactivarEmpaquetado(int $id)
{
    if (!$id) responder(false, 'ID inválido.');
    $conectar = conectar_oll_BD();

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM empaquetado WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de empaquetado no encontrado.');
    if (empty($existe[0]['deleted_at'])) responder(false, 'Este registro ya estaba activo.');

    $cambios = [['campo' => 'Estado', 'valor_antes' => 'Inactivo', 'valor_despues' => 'Activo']];
    $movimiento   = obtenerMovimientoSesionEmp('reactivar', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE empaquetado SET
            deleted_at   = NULL,
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]);

    responder(true, 'Empaquetado reactivado correctamente.');
}

// =============================================================================
// AUDITORÍA (idéntico patrón al resto de controladores)
// =============================================================================

function obtenerIpClienteEmp(): string
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

function obtenerMovimientoSesionEmp(string $accion, array $cambios = []): array
{
    return [
        'usuario'   => $_SESSION['usuario_id'] ?? 'Sistema',
        'nombre'    => $_SESSION['nombre_usuario'] ?? 'Usuario Desconocido',
        'user'      => $_SESSION['user_usuario'] ?? 'N/A',
        'perfiles'  => $_SESSION['perfiles'] ?? 'N/A',
        'rol'       => $_SESSION['rol_usuario'] ?? 'N/A',
        'accion'    => $accion,
        'ip'        => obtenerIpClienteEmp(),
        'cambios'   => $cambios,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
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