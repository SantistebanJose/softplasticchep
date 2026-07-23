<?php

/**
 * controllers/clssProduccion.php
 * Controlador del módulo de Producción (avances de producción)
 *
 * Tablas reales:
 *   operarios (id, nombre_completo, cargo, activo, created_at, updated_at)
 *   molde (id, nombre, forma, producto_id, deleted_at, ...)
 *   color (id, nombre, descripcion, rgb, deleted_at, ...)
 *   producto (id, codigo, descripcion, peso_unitario_g, ...)
 *   produccion (id, orden_id -> orden_produccion [ya no se usa, siempre NULL],
 *               operario_id -> operarios, maquina_id -> maquina,
 *               molde_id -> molde, color_id -> color,
 *               cantidad, fecha, fecha_hora_inicio, fecha_hora_fin,
 *               observaciones, es_emergencia [ya no se usa, siempre false],
 *               categoria_material_id -> categoria_material,
 *               created_at, updated_at, deleted_at, js_session, js_historial)
 *   rel_produccion_material (id, produccion_id, material_id,
 *               rel_compra_material_id, cantidad, comentario,
 *               created_at, updated_at, deleted_at, js_session, js_historial)
 *   categoria_material (id, nombre, descripcion, created_at, update_at,
 *               deleted_at, js_session, js_historial)
 *   view_lotes_material_disponible (ver produccion_ddl_ajustado.sql)
 *
 * MODELO:
 *   Cada fila de `produccion` es un AVANCE puntual: quién, cuándo, en qué
 *   máquina, con qué molde y color. Ya NO se registra contra una orden de
 *   producción (la empresa no trabaja con ese flujo), por lo que los
 *   campos `orden_id` y `es_emergencia` se mantienen en la tabla por
 *   compatibilidad pero siempre se guardan como NULL / false.
 *
 *   IMPORTANTE (cambio de significado de `cantidad`): `cantidad` ya NO es
 *   el número de piezas (ganchos) producidas. Ahora representa los
 *   KILOGRAMOS de material insertados en la máquina en este avance. El
 *   número real de piezas se deriva después a partir de esos kg (por
 *   ahora fuera del alcance de este controlador).
 *
 *   CATEGORÍA DE MATERIAL: `categoria_material_id` es opcional y clasifica
 *   el avance (ej. "virgen", "reciclado", "mezcla"), independientemente de
 *   los materiales/lotes puntuales que se consuman en el detalle.
 *
 *   MERMA: se descartó por ahora — el campo `merma_total` ya no se pide
 *   ni se guarda desde este formulario.
 *
 *   Cada avance puede consumir uno o varios materiales, y CADA línea de
 *   consumo apunta a un LOTE puntual (trazabilidad FIFO por compra).
 *
 * DISPONIBILIDAD DE UN LOTE:
 *   disponible = lote.cantidad_base - SUM(cantidad consumida en avances
 *   activos de ese lote). Se calcula en vivo vía view_lotes_material_disponible.
 *
 * REGLAS DE STOCK (material.stock_actual):
 *   - Crear avance      -> RESTA cantidad de cada línea del stock del material.
 *   - Editar avance      -> se eliminan físicamente las líneas anteriores
 *                           (revirtiendo/sumando su cantidad de vuelta al stock)
 *                           y se insertan las líneas nuevas (restando de nuevo).
 *   - Desactivar avance  -> SUMA de vuelta cantidad de cada línea activa al
 *                           stock (revierte el consumo) y hace soft-delete
 *                           de esas líneas y del avance.
 *   - Reactivar avance   -> restaura las líneas y vuelve a RESTAR su
 *                           cantidad del stock.
 *
 * Este controlador NO crea/edita molde, color, producto ni categoria_material
 * (cada uno tiene su propio CRUD en su respectivo clss*.php); aquí solo se
 * listan/consultan para elegir contra qué avance se registra.
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    try {
        controladorProduccion($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssProduccion.php: " . $e->getMessage());
        responder(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssProduccion.php: " . $e->getMessage());
        responder(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorProduccion($accion)
{
    switch ($accion) {
        case 'LISTARPRODUCCIONES':
            listarProducciones();
            break;
        case 'OBTENERPRODUCCION':
            obtenerProduccion(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARPRODUCCION':
            guardarProduccion();
            break;
        case 'ELIMINARPRODUCCION':
            eliminarProduccion();
            break;
        case 'REACTIVARPRODUCCION':
            reactivarProduccion();
            break;
        case 'BUSCAROPERARIOS':
            buscarOperarios();
            break;
        case 'BUSCARMAQUINAS':
            buscarMaquinas();
            break;
        case 'BUSCARMATERIALESPRODUCCION':
            buscarMaterialesProduccion();
            break;
        case 'BUSCARCATEGORIASMATERIAL':
            buscarCategoriasMaterial();
            break;
        case 'ENVIARAENSAMBLAJE':
            enviarAEnsamblaje();
            break;
        case 'INICIARCORRIDA':
            iniciarCorrida(intval($_POST['id'] ?? 0));
            break;
        case 'FINALIZARCORRIDA':
            finalizarCorrida(intval($_POST['id'] ?? 0));
            break;
        case 'BUSCARLOTESMATERIAL':
            buscarLotesMaterial();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// LISTADOS AUXILIARES (para los <select> / cards del modal)
// =============================================================================

function buscarOperarios()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre_completo, cargo FROM operario WHERE activo = true ORDER BY nombre_completo";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['operario' => $result]);
}

function buscarMaquinas()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre FROM maquina WHERE deleted_at IS NULL ORDER BY nombre";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['maquinas' => $result]);
}

// Catálogo de materiales para el "menú" de tarjetas del modal. Trae también
// stock actual y unidad, para que cada card muestre de un vistazo cuánto
// hay en total (la disponibilidad real por lote se pide aparte).
function buscarMaterialesProduccion()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["m.deleted_at IS NULL"];
    $params = [];
    if ($texto !== '') {
        $where[] = "LOWER(m.nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT m.id, m.nombre, m.stock_actual, m.unidad_medida_id,
                   u.nombre_corto AS unidad_corto
            FROM material m
            LEFT JOIN unidad_medida u ON u.id = m.unidad_medida_id
            WHERE " . implode(' AND ', $where) . " ORDER BY m.nombre LIMIT 100";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['materiales' => $result]);
}

// El corazón del "¿de dónde saco el material?": lista los lotes (compras)
// de un material específico con su disponible calculado en vivo. Ordenado
// del más antiguo al más nuevo (sugerencia FIFO), el usuario elige libremente.
function buscarLotesMaterial()
{
    $conectar = conectar_oll_BD();
    $materialId = intval($_POST['material_id'] ?? 0);
    if (!$materialId) responder(false, 'Debes indicar un material.');

    $sql = "SELECT lote_id, compra_id, fecha_compra, proveedor_ruc, proveedor,
                   cantidad_base, consumido, disponible, unidad_base_corto, unidad_base_nombre
            FROM view_lotes_material_disponible
            WHERE material_id = :material_id
              AND disponible > 0.0001
            ORDER BY fecha_compra ASC, lote_id ASC";

    $result = executeQuery($conectar, $sql, ['material_id' => $materialId]);
    responder(true, 'OK', ['lotes' => $result]);
}

// =============================================================================
// PRODUCCIÓN (avances)
// =============================================================================

function listarProducciones()
{
    $conectar = conectar_oll_BD();

    $texto        = trim($_POST['texto'] ?? '');
    $operario_id  = trim($_POST['operario_id'] ?? '');
    $maquina_id   = trim($_POST['maquina_id'] ?? '');
    $molde_id     = trim($_POST['molde_id'] ?? '');
    $color_id     = trim($_POST['color_id'] ?? '');
    $estado       = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'
    $fecha_desde  = trim($_POST['fecha_desde'] ?? '');
    $fecha_hasta  = trim($_POST['fecha_hasta'] ?? '');

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(pd.observaciones) LIKE LOWER(:texto) OR LOWER(mo.nombre) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($operario_id !== '') {
        $where[] = "pd.operario_id = :operario_id";
        $params['operario_id'] = $operario_id;
    }
    if ($maquina_id !== '') {
        $where[] = "pd.maquina_id = :maquina_id";
        $params['maquina_id'] = $maquina_id;
    }
    if ($molde_id !== '') {
        $where[] = "pd.molde_id = :molde_id";
        $params['molde_id'] = $molde_id;
    }
    if ($color_id !== '') {
        $where[] = "pd.color_id = :color_id";
        $params['color_id'] = $color_id;
    }
    if ($estado === 'activa') {
        $where[] = "pd.deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "pd.deleted_at IS NOT NULL";
    }
    if ($fecha_desde !== '') {
        $where[] = "pd.fecha >= :fecha_desde";
        $params['fecha_desde'] = $fecha_desde;
    }
    if ($fecha_hasta !== '') {
        $where[] = "pd.fecha <= :fecha_hasta";
        $params['fecha_hasta'] = $fecha_hasta . ' 23:59:59';
    }

    $sql = "
        SELECT
            pd.*,
            op.nombre_completo AS operario_nombre,
            ma.nombre AS maquina_nombre,
            mo.nombre AS molde_nombre,
            co.nombre AS color_nombre,
            co.rgb AS color_rgb,
            cm.nombre AS categoria_material_nombre,
            COALESCE((
                SELECT COUNT(*) FROM rel_produccion_material rpm
                WHERE rpm.produccion_id = pd.id AND rpm.deleted_at IS NULL
            ), 0) AS items_count
        FROM produccion pd
        LEFT JOIN operario op ON op.id = pd.operario_id
        LEFT JOIN maquina ma ON ma.id = pd.maquina_id
        LEFT JOIN molde mo ON mo.id = pd.molde_id
        LEFT JOIN color co ON co.id = pd.color_id
        LEFT JOIN categoria_material cm ON cm.id = pd.categoria_material_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY pd.enviado_ensamblaje ASC, pd.id DESC
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['producciones' => $result]);
}
function buscarCategoriasMaterial()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre FROM categoria_material WHERE deleted_at IS NULL ORDER BY nombre";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['categorias' => $result]);
}

function obtenerProduccion($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $produccion = executeQuery(
        $conectar,
        "SELECT pd.*,
                op.nombre_completo AS operario_nombre, ma.nombre AS maquina_nombre,
                mo.nombre AS molde_nombre,
                co.nombre AS color_nombre, co.rgb AS color_rgb,
                cm.nombre AS categoria_material_nombre
         FROM produccion pd
         LEFT JOIN operario op ON op.id = pd.operario_id
         LEFT JOIN maquina ma ON ma.id = pd.maquina_id
         LEFT JOIN molde mo ON mo.id = pd.molde_id
         LEFT JOIN color co ON co.id = pd.color_id
         LEFT JOIN categoria_material cm ON cm.id = pd.categoria_material_id
         WHERE pd.id = :id",
        ['id' => $id]
    );
    if (empty($produccion)) responder(false, 'Registro de producción no encontrado.');

    // Detalle con toda la info del lote de origen, para ver de inmediato
    // "de dónde" salió cada línea al editar.
    $detalle = executeQuery(
        $conectar,
        "SELECT rpm.*, m.nombre AS material_nombre,
                rcm.compra_id, c.fecha_compra, p.razon_social AS proveedor,
                rcm.cantidad_base AS lote_cantidad_base,
                ub.nombre_corto AS unidad_base_corto
         FROM rel_produccion_material rpm
         JOIN material m ON m.id = rpm.material_id
         JOIN rel_compra_material rcm ON rcm.id = rpm.rel_compra_material_id
         JOIN compra c ON c.id = rcm.compra_id
         JOIN proveedor p ON p.ruc = c.proveedor_id
         LEFT JOIN unidad_medida ub ON ub.id = m.unidad_medida_id
         WHERE rpm.produccion_id = :id AND rpm.deleted_at IS NULL
         ORDER BY rpm.id",
        ['id' => $id]
    );

    responder(true, 'OK', ['produccion' => $produccion[0], 'detalle' => $detalle]);
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

function guardarProduccion()
{
    $conectar = conectar_oll_BD();

    $id                 = intval($_POST['id'] ?? 0);
    $operario_id        = !empty($_POST['operario_id']) ? intval($_POST['operario_id']) : null;
    $maquina_id         = !empty($_POST['maquina_id']) ? intval($_POST['maquina_id']) : null;
    $categoria_material_id = !empty($_POST['categoria_material_id']) ? intval($_POST['categoria_material_id']) : null;
    $molde_id           = intval($_POST['molde_id'] ?? 0);
    $color_id           = intval($_POST['color_id'] ?? 0);
    $unico_molde        = trim($_POST['unico_molde'] ?? '');    // 7-2
    $molde_producto     = trim($_POST['molde_producto'] ?? '');
    $cantidad           = intval($_POST['cantidad'] ?? 0); // kg insertados en máquina en este avance
    $fecha              = trim($_POST['fecha'] ?? '');
    $observaciones      = trim($_POST['observaciones'] ?? '');
    $detalleJson        = trim($_POST['detalle'] ?? '[]');

    // La empresa ya no maneja órdenes de producción ni el concepto de
    // "emergencia" (que solo existía para justificar romper el orden de
    // una orden). Ambos campos se conservan en la tabla por compatibilidad
    // pero siempre se guardan vacíos/false.
    $orden_id     = null;
    $esEmergencia = false;

    // ── Validaciones básicas ─────────────────────────────────────────────────
    if ($categoria_material_id !== null) {
    $cat = executeQuery($conectar, "SELECT id FROM categoria_material WHERE id = :id AND deleted_at IS NULL", ['id' => $categoria_material_id]);
    if (empty($cat)) responder(false, 'La categoría de material seleccionada no existe o está inactiva.');
}
    if ($cantidad <= 0) responder(false, 'La cantidad de kg insertados debe ser mayor a 0.');
    if ($molde_id <= 0) responder(false, 'Debes seleccionar el molde usado en este avance.');
    if ($color_id <= 0) responder(false, 'Debes seleccionar el color usado en este avance.');
    if (empty($unico_molde) || empty($molde_producto)) {
        responder(false, 'Debes seleccionar un molde con su producto asociado.');
    }
    if (empty($fecha)) $fecha = date('Y-m-d H:i:s');

    $molde = executeQuery($conectar, "SELECT id FROM molde WHERE id = :id AND deleted_at IS NULL", ['id' => $molde_id]);
    if (empty($molde)) responder(false, 'El molde seleccionado no existe o está inactivo.');

    $color = executeQuery($conectar, "SELECT id FROM color WHERE id = :id AND deleted_at IS NULL", ['id' => $color_id]);
    if (empty($color)) responder(false, 'El color seleccionado no existe o está inactivo.');

    $detalleEntrada = json_decode($detalleJson, true);
    if (!is_array($detalleEntrada)) $detalleEntrada = [];

    $detalle = [];
    foreach ($detalleEntrada as $linea) {
        $materialId = intval($linea['material_id'] ?? 0);
        $loteId     = intval($linea['rel_compra_material_id'] ?? 0);
        $cant       = floatval($linea['cantidad'] ?? 0);
        $comentario = trim($linea['comentario'] ?? '');

        if ($materialId <= 0 || $loteId <= 0 || $cant <= 0) continue; // fila incompleta, se ignora

        $detalle[] = [
            'material_id'            => $materialId,
            'rel_compra_material_id' => $loteId,
            'cantidad'               => $cant,
            'comentario'             => $comentario ?: null,
        ];
    }

    // El detalle de materiales es opcional: puede haber avances de producción
    // (ej. reproceso, control de calidad) que no consuman material nuevo.
    // Si quieres forzarlo obligatorio, descomenta la validación siguiente:
    // if (empty($detalle)) responder(false, 'Debes agregar al menos un material con su lote de origen.');

    $conectar->beginTransaction();
    try {
        if ($id === 0) {
            // ── CREACIÓN ─────────────────────────────────────────────────────
            $cambios = [[
                'campo' => 'Producción', 'valor_antes' => '(nuevo)',
                'valor_despues' => "Avance de $cantidad kg, " . count($detalle) . ' material(es) consumido(s)',
            ]];
            $movimiento   = obtenerMovimientoSesion('crear', $cambios);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            // DESPUÉS
            $nuevaProduccion = executeQuery($conectar, "
                INSERT INTO produccion (
                    operario_id, maquina_id, molde_id, color_id, cantidad,
                    categoria_material_id, unico_molde_producto, molde_producto,
                    fecha, observaciones,
                    created_at, updated_at, js_session, js_historial
                ) VALUES (
                    :operario_id, :maquina_id, :molde_id, :color_id, :cantidad,
                    :categoria_material_id, :unico_molde, :molde_producto,
                    :fecha, :observaciones,
                    NOW(), NOW(), :js_session, :js_historial
                ) RETURNING id
            ", [
                'operario_id'            => $operario_id,
                'maquina_id'             => $maquina_id,
                'molde_id'               => $molde_id,
                'color_id'               => $color_id,
                'cantidad'               => $cantidad,
                'categoria_material_id'  => $categoria_material_id,
                'unico_molde'            => $unico_molde,
                'molde_producto'         => $molde_producto,
                'fecha'                  => $fecha,
                'observaciones'          => $observaciones ?: null,
                'js_session'             => $js_session,
                'js_historial'           => $js_historial,
            ]);
            $produccionId = $nuevaProduccion[0]['id'] ?? null;
            if (!$produccionId) throw new Exception('No se pudo crear el registro de producción.');

            if (!empty($detalle)) {
                insertarLineasYRestarStock($conectar, $produccionId, $detalle);
            }

            $conectar->commit();
            responder(true, 'Producción registrada correctamente.', [
                'id' => $produccionId, 'modo' => 'crear',
            ]);
        } else {
            // ── EDICIÓN ──────────────────────────────────────────────────────
            $actual = executeQuery($conectar, "SELECT * FROM produccion WHERE id = :id", ['id' => $id]);
            if (empty($actual)) throw new Exception('Registro de producción no encontrado.');
            if (!empty($actual[0]['deleted_at'])) {
                throw new Exception('No puedes editar un registro inactivo. Reactívalo primero.');
            }
            $produccionAnterior = $actual[0];

            // Revertimos el consumo de las líneas activas actuales (devolvemos
            // stock) y las eliminamos físicamente
            $lineasAnteriores = executeQuery(
                $conectar,
                "SELECT * FROM rel_produccion_material WHERE produccion_id = :id AND deleted_at IS NULL",
                ['id' => $id]
            );
            foreach ($lineasAnteriores as $linea) {
                executeNonQuery(
                    $conectar,
                    "UPDATE material SET stock_actual = stock_actual + :cantidad WHERE id = :mid",
                    ['cantidad' => $linea['cantidad'], 'mid' => $linea['material_id']]
                );
            }
            executeNonQuery($conectar, "DELETE FROM rel_produccion_material WHERE produccion_id = :id", ['id' => $id]);

            // Insertamos las líneas nuevas (valida disponible por lote y resta stock)
            if (!empty($detalle)) {
                insertarLineasYRestarStock($conectar, $id, $detalle);
            }

            $cambios = [[
                'campo' => 'Producción',
                'valor_antes' => $produccionAnterior['cantidad'] . ' kg',
                'valor_despues' => "$cantidad kg, " . count($detalle) . ' material(es)',
            ]];
            $movimiento   = obtenerMovimientoSesion('editar', $cambios);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            // DESPUÉS
            executeNonQuery($conectar, "
                UPDATE produccion SET
                    operario_id            = :operario_id,
                    maquina_id             = :maquina_id,
                    molde_id               = :molde_id,
                    color_id               = :color_id,
                    cantidad               = :cantidad,
                    categoria_material_id  = :categoria_material_id,
                    unico_molde_producto   = :unico_molde,
                    molde_producto         = :molde_producto,
                    fecha                  = :fecha,
                    observaciones          = :observaciones,
                    updated_at             = NOW(),
                    js_session             = :js_session,
                    js_historial           = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
                WHERE id = :id
            ", [
                'operario_id'            => $operario_id,
                'maquina_id'             => $maquina_id,
                'molde_id'               => $molde_id,
                'color_id'               => $color_id,
                'cantidad'               => $cantidad,
                'categoria_material_id'  => $categoria_material_id,
                'unico_molde'            => $unico_molde,
                'molde_producto'         => $molde_producto,
                'fecha'                  => $fecha,
                'observaciones'          => $observaciones ?: null,
                'js_session'             => $js_session,
                'js_historial'           => $js_historial,
                'id'                     => $id,
            ]);
            $conectar->commit();
            responder(true, 'Producción actualizada correctamente.', [
                'id' => $id, 'modo' => 'editar',
            ]);
        }
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error guardando producción: " . $e->getMessage());
        responder(false, 'No se pudo guardar la producción: ' . $e->getMessage());
    }
}

/**
 * Valida (dentro de la transacción) que el lote elegido pertenezca al
 * material indicado y tenga disponible suficiente, luego inserta la línea
 * de rel_produccion_material y resta la cantidad del stock del material.
 */
function insertarLineasYRestarStock($conectar, int $produccionId, array $detalle): void
{
    foreach ($detalle as $linea) {
        $lote = executeQuery(
            $conectar,
            "SELECT lote_id, material_id, proveedor, fecha_compra, disponible, unidad_base_corto
             FROM view_lotes_material_disponible
             WHERE lote_id = :lote_id",
            ['lote_id' => $linea['rel_compra_material_id']]
        );

        if (empty($lote)) {
            throw new Exception('El lote de material seleccionado ya no existe o fue desactivado.');
        }
        $lote = $lote[0];

        if ((int) $lote['material_id'] !== (int) $linea['material_id']) {
            throw new Exception('El lote seleccionado no corresponde al material indicado.');
        }
        if ($linea['cantidad'] > (float) $lote['disponible'] + 0.0001) {
            throw new Exception(
                'No hay suficiente disponible en el lote de "' . $lote['proveedor'] . '" (' . $lote['fecha_compra'] . '). '
                . 'Disponible: ' . number_format((float) $lote['disponible'], 4) . ' ' . $lote['unidad_base_corto']
                . ', solicitado: ' . number_format($linea['cantidad'], 4) . ' ' . $lote['unidad_base_corto'] . '.'
            );
        }

        $movimiento   = obtenerMovimientoSesion('crear_linea');
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery($conectar, "
            INSERT INTO rel_produccion_material (
                produccion_id, material_id, rel_compra_material_id, cantidad, comentario,
                created_at, updated_at, js_session, js_historial
            ) VALUES (
                :produccion_id, :material_id, :rel_compra_material_id, :cantidad, :comentario,
                NOW(), NOW(), :js_session, :js_historial
            )
        ", [
            'produccion_id'           => $produccionId,
            'material_id'             => $linea['material_id'],
            'rel_compra_material_id'  => $linea['rel_compra_material_id'],
            'cantidad'                => $linea['cantidad'],
            'comentario'              => $linea['comentario'],
            'js_session'              => $js_session,
            'js_historial'            => $js_historial,
        ]);

        executeNonQuery(
            $conectar,
            "UPDATE material SET stock_actual = stock_actual - :cantidad WHERE id = :mid",
            ['cantidad' => $linea['cantidad'], 'mid' => $linea['material_id']]
        );
    }
}

// Soft delete: revierte el stock (devuelve la cantidad consumida) de las
// líneas activas y desactiva el avance + sus líneas.
function eliminarProduccion()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM produccion WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de producción no encontrado.');
    if (!empty($existe[0]['deleted_at'])) responder(false, 'Este registro ya estaba inactivo.');

    $conectar->beginTransaction();
    try {
        $lineas = executeQuery(
            $conectar,
            "SELECT * FROM rel_produccion_material WHERE produccion_id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );

        foreach ($lineas as $linea) {
            executeNonQuery(
                $conectar,
                "UPDATE material SET stock_actual = stock_actual + :cantidad WHERE id = :mid",
                ['cantidad' => $linea['cantidad'], 'mid' => $linea['material_id']]
            );
        }

        executeNonQuery(
            $conectar,
            "UPDATE rel_produccion_material SET deleted_at = NOW(), updated_at = NOW() WHERE produccion_id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );

        $cambios = [[
            'campo' => 'Estado', 'valor_antes' => 'Activo', 'valor_despues' => 'Inactivo (stock revertido)',
        ]];
        $movimiento   = obtenerMovimientoSesion('desactivar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery(
            $conectar,
            "UPDATE produccion SET
                deleted_at   = NOW(),
                updated_at   = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id",
            ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]
        );

        $conectar->commit();
        responder(true, 'Producción desactivada y stock revertido correctamente.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error desactivando producción: " . $e->getMessage());
        responder(false, 'No se pudo desactivar la producción: ' . $e->getMessage());
    }
}

// Restaura las líneas que fueron desactivadas junto con el avance y vuelve
// a restar su cantidad del stock.
function reactivarProduccion()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM produccion WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de producción no encontrado.');
    if (empty($existe[0]['deleted_at'])) responder(false, 'Este registro ya estaba activo.');

    $conectar->beginTransaction();
    try {
        $lineas = executeQuery(
            $conectar,
            "SELECT * FROM rel_produccion_material WHERE produccion_id = :id AND deleted_at IS NOT NULL",
            ['id' => $id]
        );

        foreach ($lineas as $linea) {
            executeNonQuery(
                $conectar,
                "UPDATE material SET stock_actual = stock_actual - :cantidad WHERE id = :mid",
                ['cantidad' => $linea['cantidad'], 'mid' => $linea['material_id']]
            );
        }

        executeNonQuery(
            $conectar,
            "UPDATE rel_produccion_material SET deleted_at = NULL, updated_at = NOW() WHERE produccion_id = :id AND deleted_at IS NOT NULL",
            ['id' => $id]
        );

        $cambios = [[
            'campo' => 'Estado', 'valor_antes' => 'Inactivo', 'valor_despues' => 'Activo (stock vuelto a descontar)',
        ]];
        $movimiento   = obtenerMovimientoSesion('reactivar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery(
            $conectar,
            "UPDATE produccion SET
                deleted_at   = NULL,
                updated_at   = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id",
            ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]
        );

        $conectar->commit();
        responder(true, 'Producción reactivada y stock actualizado correctamente.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error reactivando producción: " . $e->getMessage());
        responder(false, 'No se pudo reactivar la producción: ' . $e->getMessage());
    }
}
// Marca el avance como enviado a ensamblaje: guarda la cantidad producida
// (kg reales de salida, distinto de `cantidad` que son los kg insertados
// en máquina) y sella fecha_envio_ensamblaje/enviado_ensamblaje.
function enviarAEnsamblaje()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    $cantidadProducida = floatval($_POST['cantidad_producida'] ?? 0);

    if (!$id) responder(false, 'ID inválido.');
    if ($cantidadProducida <= 0) responder(false, 'La cantidad producida debe ser mayor a 0.');

    $existe = executeQuery(
        $conectar,
        "SELECT id, deleted_at, fecha_hora_fin, enviado_ensamblaje FROM produccion WHERE id = :id",
        ['id' => $id]
    );
    if (empty($existe)) responder(false, 'Registro de producción no encontrado.');
    if (!empty($existe[0]['deleted_at'])) responder(false, 'No puedes enviar a ensamblaje un registro inactivo.');
    if (empty($existe[0]['fecha_hora_fin'])) responder(false, 'Primero debes finalizar la corrida.');
    if (!empty($existe[0]['enviado_ensamblaje'])) responder(false, 'Este avance ya fue enviado a ensamblaje.');

    $cambios = [[
        'campo' => 'Envío a ensamblaje',
        'valor_antes' => '(no enviado)',
        'valor_despues' => "Enviado, {$cantidadProducida} kg producidos",
    ]];
    $movimiento   = obtenerMovimientoSesion('enviar_ensamblaje', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE produccion SET
            cantidad_producida_kg   = :cantidad_producida,
            fecha_envio_ensamblaje  = NOW(),
            enviado_ensamblaje      = true,
            updated_at              = NOW(),
            js_session              = :js_session,
            js_historial            = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", [
        'id'                  => $id,
        'cantidad_producida'  => $cantidadProducida,
        'js_session'          => $js_session,
        'js_historial'        => $js_historial,
    ]);

    responder(true, 'Avance enviado a ensamblaje correctamente.', ['id' => $id]);
}
// Marca el inicio real de la corrida con la hora del servidor (no la del
// navegador, para que todos los operarios queden sincronizados igual).
function iniciarCorrida(int $id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at, fecha_hora_inicio FROM produccion WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de producción no encontrado.');
    if (!empty($existe[0]['deleted_at'])) responder(false, 'No puedes iniciar la corrida de un registro inactivo.');
    if (!empty($existe[0]['fecha_hora_inicio'])) responder(false, 'Esta corrida ya fue iniciada.');

    $cambios = [[
        'campo' => 'Inicio de corrida', 'valor_antes' => '(sin iniciar)', 'valor_despues' => 'Iniciada ahora',
    ]];
    $movimiento   = obtenerMovimientoSesion('iniciar_corrida', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE produccion SET
            fecha_hora_inicio = NOW(),
            updated_at        = NOW(),
            js_session        = :js_session,
            js_historial      = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]);

    responder(true, 'Corrida iniciada.');
}

// Marca el fin real de la corrida con la hora del servidor.
function finalizarCorrida(int $id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at, fecha_hora_inicio, fecha_hora_fin FROM produccion WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de producción no encontrado.');
    if (!empty($existe[0]['deleted_at'])) responder(false, 'No puedes finalizar la corrida de un registro inactivo.');
    if (empty($existe[0]['fecha_hora_inicio'])) responder(false, 'Primero debes iniciar la corrida.');
    if (!empty($existe[0]['fecha_hora_fin'])) responder(false, 'Esta corrida ya fue finalizada.');

    $cambios = [[
        'campo' => 'Fin de corrida', 'valor_antes' => '(en curso)', 'valor_despues' => 'Finalizada ahora',
    ]];
    $movimiento   = obtenerMovimientoSesion('finalizar_corrida', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE produccion SET
            fecha_hora_fin = NOW(),
            updated_at     = NOW(),
            js_session     = :js_session,
            js_historial   = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]);

    responder(true, 'Corrida finalizada.');
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