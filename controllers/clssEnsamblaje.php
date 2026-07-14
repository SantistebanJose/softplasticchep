<?php

/**
 * controllers/clssEnsamblaje.php
 * Controlador del módulo de Ensamblaje
 *
 * Tablas reales:
 *   ensamblaje (id, producto_id -> producto, operario_ortorgado -> operario,
 *               js_derivados_utilizados, js_moldes_utilizados [resúmenes,
 *               recalculados en cada guardado a partir del detalle real],
 *               inicio, fin, cantidad_peso_kg,
 *               js_usuario, js_historial, created_at, update_at, deleted_at)
 *   rel_ensamblaje_producto (id, ensamblaje_id -> ensamblaje,
 *               molde_produccion_id [referencia "suave" a produccion.id, SIN FK],
 *               js_query_consulta_produccion [snapshot jsonb de la fila de
 *               produccion al momento de vincularla],
 *               derivado_id -> derivado, created_at, update_at, deleted_at)
 *
 * SUPUESTO (sin confirmar): la tabla `derivado` no se me compartió. Se asume
 * el patrón estándar del sistema: derivado(id, nombre, ..., deleted_at). Si
 * las columnas reales son distintas, ajustar buscarDerivados() y los JOIN
 * de la vista view_ensamblaje_detalle.
 *
 * MODELO:
 *   Cada fila de `ensamblaje` es un armado de un `producto` final. Ese
 *   armado consume, línea por línea (rel_ensamblaje_producto), o bien un
 *   AVANCE DE PRODUCCIÓN finalizado (molde_produccion_id -> produccion.id)
 *   o bien un DERIVADO comprado/preexistente (derivado_id). Cada línea es
 *   de un tipo u otro, nunca ambos.
 *
 * REGLA DE UNICIDAD:
 *   Un mismo avance de producción (molde_produccion_id) solo puede estar
 *   vinculado a UN ensamblaje activo a la vez (ver
 *   view_producciones_disponibles_ensamblaje, que ya filtra los que siguen
 *   libres). Esto evita "gastar" el mismo avance en dos armados distintos.
 *
 * EDICIÓN (diff-based, NO borrar-y-reinsertar):
 *   Se compara el detalle activo actual contra el nuevo: las líneas que se
 *   mantienen no se tocan, las que ya no están se desactivan (soft-delete),
 *   las nuevas se insertan. Mismo patrón ya aplicado en compras para evitar
 *   romper referencias de auditoría o futuras integraciones.
 *
 * Este controlador NO crea/edita producto, operario, derivado ni produccion
 * (cada uno tiene su propio CRUD); aquí solo se listan/consultan para elegir
 * contra qué se arma el ensamblaje.
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    try {
        controladorEnsamblaje($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssEnsamblaje.php: " . $e->getMessage());
        responder(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssEnsamblaje.php: " . $e->getMessage());
        responder(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorEnsamblaje($accion)
{
    switch ($accion) {
        case 'LISTARENSAMBLAJES':
            listarEnsamblajes();
            break;
        case 'OBTENERENSAMBLAJE':
            obtenerEnsamblaje(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARENSAMBLAJE':
            guardarEnsamblaje();
            break;
        case 'ELIMINARENSAMBLAJE':
            eliminarEnsamblaje();
            break;
        case 'REACTIVARENSAMBLAJE':
            reactivarEnsamblaje();
            break;
        case 'INICIARENSAMBLAJE':
            iniciarEnsamblaje(intval($_POST['id'] ?? 0));
            break;
        case 'FINALIZARENSAMBLAJE':
            finalizarEnsamblaje(intval($_POST['id'] ?? 0));
            break;
        case 'BUSCARPRODUCTOS':
            buscarProductos();
            break;
        case 'BUSCAROPERARIOS':
            buscarOperarios();
            break;
        case 'BUSCARDERIVADOS':
            buscarDerivados();
            break;
        case 'BUSCARPRODUCCIONESDISPONIBLES':
            buscarProduccionesDisponibles();
            break;
        case 'OBTENERDATOSPRODUCCIONPARAENSAMBLAJE':
            obtenerDatosProduccionParaEnsamblaje(intval($_POST['produccion_id'] ?? 0));
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// LISTADOS AUXILIARES (para los <select> / cards del modal)
// =============================================================================

function buscarProductos()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["activo = true"];
    $params = [];
    if ($texto !== '') {
        $where[] = "(LOWER(codigo) LIKE LOWER(:texto) OR LOWER(descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT id, codigo, descripcion, peso_unitario_g FROM producto
            WHERE " . implode(' AND ', $where) . " ORDER BY descripcion LIMIT 100";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['productos' => $result]);
}
function buscarOperarios()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre_completo, cargo FROM operario WHERE activo = true ORDER BY nombre_completo";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['operario' => $result]);
}

// SUPUESTO: derivado(id, nombre, deleted_at). Ajustar si la tabla real
// tiene otras columnas (ej. producto_id, stock, etc).
function buscarDerivados()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["deleted_at IS NULL"];
    $params = [];
    if ($texto !== '') {
        $where[] = "LOWER(nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT id, nombre FROM derivado
            WHERE " . implode(' AND ', $where) . " ORDER BY nombre LIMIT 100";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['derivados' => $result]);
}

// Avances de producción finalizados y aún no consumidos por ningún
// ensamblaje activo (ver view_producciones_disponibles_ensamblaje).
// Si se pasa producto_id, se filtra solo a los avances cuyo molde
// pertenece a ese producto.
function buscarProduccionesDisponibles()
{
    $conectar = conectar_oll_BD();
    $productoId = intval($_POST['producto_id'] ?? 0);
    $texto      = trim($_POST['texto'] ?? '');

    $where  = ["1=1"];
    $params = [];
    if ($productoId > 0) {
        $where[] = "producto_id = :producto_id";
        $params['producto_id'] = $productoId;
    }
    if ($texto !== '') {
        $where[] = "LOWER(molde_nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT * FROM view_producciones_disponibles_ensamblaje
            WHERE " . implode(' AND ', $where) . "
            ORDER BY fecha_hora_fin DESC";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['producciones' => $result]);
}

// Usado por el botón "Pasar a ensamblaje" desde la card de producción:
// trae los datos necesarios para prellenar el modal (producto sugerido
// según el molde usado, kg del avance, etc), sin crear nada todavía.
function obtenerDatosProduccionParaEnsamblaje(int $produccionId)
{
    $conectar = conectar_oll_BD();
    if (!$produccionId) responder(false, 'ID de producción inválido.');

    $data = executeQuery(
        $conectar,
        "SELECT * FROM view_producciones_disponibles_ensamblaje WHERE produccion_id = :id",
        ['id' => $produccionId]
    );

    if (empty($data)) {
        responder(false, 'Esta producción no está disponible para ensamblaje (no existe, no está finalizada, o ya fue usada en otro ensamblaje).');
    }

    responder(true, 'OK', ['produccion' => $data[0]]);
}

// =============================================================================
// AUDITORÍA (idéntico patrón al resto de controladores)
// =============================================================================

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

// =============================================================================
// ENSAMBLAJE
// =============================================================================

function listarEnsamblajes()
{
    $conectar = conectar_oll_BD();

    $texto        = trim($_POST['texto'] ?? '');
    $producto_id  = trim($_POST['producto_id'] ?? '');
    $operario_id  = trim($_POST['operario_id'] ?? '');
    $estado       = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'
    $fecha_desde  = trim($_POST['fecha_desde'] ?? '');
    $fecha_hasta  = trim($_POST['fecha_hasta'] ?? '');

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(producto_codigo) LIKE LOWER(:texto) OR LOWER(producto_descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($producto_id !== '') {
        $where[] = "producto_id = :producto_id";
        $params['producto_id'] = $producto_id;
    }
    if ($operario_id !== '') {
        $where[] = "operario_id = :operario_id";
        $params['operario_id'] = $operario_id;
    }
    if ($estado === 'activa') {
        $where[] = "deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "deleted_at IS NOT NULL";
    }
    if ($fecha_desde !== '') {
        $where[] = "inicio >= :fecha_desde";
        $params['fecha_desde'] = $fecha_desde;
    }
    if ($fecha_hasta !== '') {
        $where[] = "inicio <= :fecha_hasta";
        $params['fecha_hasta'] = $fecha_hasta . ' 23:59:59';
    }

    $sql = "SELECT * FROM view_ensamblaje_detalle
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ensamblaje_id DESC";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['ensamblajes' => $result]);
}

function obtenerEnsamblaje($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $ensamblaje = executeQuery(
        $conectar,
        "SELECT * FROM view_ensamblaje_detalle WHERE ensamblaje_id = :id",
        ['id' => $id]
    );
    if (empty($ensamblaje)) responder(false, 'Registro de ensamblaje no encontrado.');

    responder(true, 'OK', ['ensamblaje' => $ensamblaje[0]]);
}

function guardarEnsamblaje()
{
    $conectar = conectar_oll_BD();

    $id                  = intval($_POST['id'] ?? 0);
    $producto_id         = intval($_POST['producto_id'] ?? 0);
    $operario_ortorgado  = !empty($_POST['operario_ortorgado']) ? intval($_POST['operario_ortorgado']) : null;
    $inicio              = trim($_POST['inicio'] ?? '');
    $fin                 = trim($_POST['fin'] ?? '');
    $cantidad_peso_kg    = $_POST['cantidad_peso_kg'] ?? null;
    $detalleJson         = trim($_POST['detalle'] ?? '[]');

    // ── Validaciones básicas ─────────────────────────────────────────────────
    if ($producto_id <= 0) responder(false, 'Debes seleccionar el producto a ensamblar.');

    $producto = executeQuery($conectar, "SELECT id FROM producto WHERE id = :id AND activo = true", ['id' => $producto_id]);
    if (empty($producto)) responder(false, 'El producto seleccionado no existe o está inactivo.');

    if ($operario_ortorgado !== null) {
        $operario = executeQuery($conectar, "SELECT id FROM operario WHERE id = :id AND activo = true", ['id' => $operario_ortorgado]);
        if (empty($operario)) responder(false, 'El operario seleccionado no existe o está inactivo.');
    }

    if ($cantidad_peso_kg !== null && $cantidad_peso_kg !== '') {
        $cantidad_peso_kg = floatval($cantidad_peso_kg);
        if ($cantidad_peso_kg < 0) responder(false, 'El peso del ensamblaje no puede ser negativo.');
    } else {
        $cantidad_peso_kg = null;
    }

    if ($fin !== '' && $inicio === '') {
        responder(false, 'No puedes registrar un fin sin un inicio.');
    }
    if ($inicio !== '' && $fin !== '' && strtotime($fin) < strtotime($inicio)) {
        responder(false, 'La fecha de fin no puede ser anterior a la de inicio.');
    }

    $detalleEntrada = json_decode($detalleJson, true);
    if (!is_array($detalleEntrada)) $detalleEntrada = [];

    // Normaliza y valida cada línea (tipo produccion XOR derivado).
    $detalle = [];
    foreach ($detalleEntrada as $linea) {
        $tipo = trim($linea['tipo'] ?? '');
        if ($tipo === 'produccion') {
            $prodId = intval($linea['molde_produccion_id'] ?? 0);
            if ($prodId <= 0) continue;
            $detalle[] = ['tipo' => 'produccion', 'molde_produccion_id' => $prodId, 'derivado_id' => null];
        } elseif ($tipo === 'derivado') {
            $derId = intval($linea['derivado_id'] ?? 0);
            if ($derId <= 0) continue;
            $detalle[] = ['tipo' => 'derivado', 'molde_produccion_id' => null, 'derivado_id' => $derId];
        }
        // tipos desconocidos se ignoran silenciosamente
    }

    if (empty($detalle)) {
        responder(false, 'Debes vincular al menos una producción finalizada o un derivado a este ensamblaje.');
    }

    $conectar->beginTransaction();
    try {
        if ($id === 0) {
            // ── CREACIÓN ─────────────────────────────────────────────────────
            $cambios = [[
                'campo' => 'Ensamblaje', 'valor_antes' => '(nuevo)',
                'valor_despues' => count($detalle) . ' ítem(s) vinculado(s)',
            ]];
            $movimiento   = obtenerMovimientoSesion('crear', $cambios);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            $nuevoEnsamblaje = executeQuery($conectar, "
                INSERT INTO ensamblaje (
                    producto_id, operario_ortorgado, inicio, fin, cantidad_peso_kg,
                    js_derivados_utilizados, js_moldes_utilizados,
                    created_at, js_usuario, js_historial
                ) VALUES (
                    :producto_id, :operario_ortorgado, :inicio, :fin, :cantidad_peso_kg,
                    '[]'::jsonb, '[]'::jsonb,
                    NOW(), :js_usuario, :js_historial
                ) RETURNING id
            ", [
                'producto_id'        => $producto_id,
                'operario_ortorgado' => $operario_ortorgado,
                'inicio'             => $inicio ?: null,
                'fin'                => $fin ?: null,
                'cantidad_peso_kg'   => $cantidad_peso_kg,
                'js_usuario'         => $js_session,
                'js_historial'       => $js_historial,
            ]);
            $ensamblajeId = $nuevoEnsamblaje[0]['id'] ?? null;
            if (!$ensamblajeId) throw new Exception('No se pudo crear el registro de ensamblaje.');

            insertarLineasEnsamblaje($conectar, $ensamblajeId, $detalle);
            recalcularResumenesEnsamblaje($conectar, $ensamblajeId);

            $conectar->commit();
            responder(true, 'Ensamblaje registrado correctamente.', [
                'id' => $ensamblajeId, 'modo' => 'crear',
            ]);
        } else {
            // ── EDICIÓN (diff-based) ────────────────────────────────────────
            $actual = executeQuery($conectar, "SELECT * FROM ensamblaje WHERE id = :id", ['id' => $id]);
            if (empty($actual)) throw new Exception('Registro de ensamblaje no encontrado.');
            if (!empty($actual[0]['deleted_at'])) {
                throw new Exception('No puedes editar un ensamblaje inactivo. Reactívalo primero.');
            }

            $lineasActuales = executeQuery(
                $conectar,
                "SELECT * FROM rel_ensamblaje_producto WHERE ensamblaje_id = :id AND deleted_at IS NULL",
                ['id' => $id]
            );

            // Claves de comparación: "p:123" para producción, "d:45" para derivado.
            $clave = function ($tipo, $prodId, $derId) {
                return $tipo === 'produccion' ? "p:$prodId" : "d:$derId";
            };

            $actualesPorClave = [];
            foreach ($lineasActuales as $l) {
                $tipo = $l['molde_produccion_id'] !== null ? 'produccion' : 'derivado';
                $k = $clave($tipo, $l['molde_produccion_id'], $l['derivado_id']);
                $actualesPorClave[$k] = $l;
            }

            $nuevasPorClave = [];
            foreach ($detalle as $d) {
                $k = $clave($d['tipo'], $d['molde_produccion_id'], $d['derivado_id']);
                $nuevasPorClave[$k] = $d;
            }

            // Líneas que ya no están -> soft delete.
            $clavesAEliminar = array_diff(array_keys($actualesPorClave), array_keys($nuevasPorClave));
            foreach ($clavesAEliminar as $k) {
                executeNonQuery(
                    $conectar,
                    "UPDATE rel_ensamblaje_producto SET deleted_at = NOW(), update_at = NOW() WHERE id = :id",
                    ['id' => $actualesPorClave[$k]['id']]
                );
            }

            // Líneas nuevas -> insertar (con su validación de disponibilidad/existencia).
            $clavesAInsertar = array_diff(array_keys($nuevasPorClave), array_keys($actualesPorClave));
            $detalleNuevo = array_values(array_filter(
                $detalle,
                fn($d) => in_array($clave($d['tipo'], $d['molde_produccion_id'], $d['derivado_id']), $clavesAInsertar)
            ));
            if (!empty($detalleNuevo)) {
                insertarLineasEnsamblaje($conectar, $id, $detalleNuevo, $id);
            }
            // Las líneas que se mantienen (intersección) no se tocan.

            $cambios = [[
                'campo' => 'Ensamblaje',
                'valor_antes' => count($lineasActuales) . ' ítem(s)',
                'valor_despues' => count($detalle) . ' ítem(s)',
            ]];
            $movimiento   = obtenerMovimientoSesion('editar', $cambios);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            executeNonQuery($conectar, "
                UPDATE ensamblaje SET
                    producto_id         = :producto_id,
                    operario_ortorgado  = :operario_ortorgado,
                    inicio              = :inicio,
                    fin                 = :fin,
                    cantidad_peso_kg    = :cantidad_peso_kg,
                    update_at           = NOW(),
                    js_usuario          = :js_usuario,
                    js_historial        = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
                WHERE id = :id
            ", [
                'producto_id'        => $producto_id,
                'operario_ortorgado' => $operario_ortorgado,
                'inicio'             => $inicio ?: null,
                'fin'                => $fin ?: null,
                'cantidad_peso_kg'   => $cantidad_peso_kg,
                'js_usuario'         => $js_session,
                'js_historial'       => $js_historial,
                'id'                 => $id,
            ]);

            recalcularResumenesEnsamblaje($conectar, $id);

            $conectar->commit();
            responder(true, 'Ensamblaje actualizado correctamente.', [
                'id' => $id, 'modo' => 'editar',
            ]);
        }
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error guardando ensamblaje: " . $e->getMessage());
        responder(false, 'No se pudo guardar el ensamblaje: ' . $e->getMessage());
    }
}

/**
 * Valida cada línea nueva (que la producción exista, esté finalizada y
 * libre; o que el derivado exista) e inserta en rel_ensamblaje_producto.
 * $excluirEnsamblajeId permite, en edición, no chocar contra las propias
 * líneas del ensamblaje que se está editando al chequear unicidad.
 */
function insertarLineasEnsamblaje($conectar, int $ensamblajeId, array $detalle, ?int $excluirEnsamblajeId = null): void
{
    foreach ($detalle as $linea) {
        if ($linea['tipo'] === 'produccion') {
            $produccionId = $linea['molde_produccion_id'];

            $prod = executeQuery(
                $conectar,
                "SELECT id, fecha_hora_fin, deleted_at FROM produccion WHERE id = :id",
                ['id' => $produccionId]
            );
            if (empty($prod)) {
                throw new Exception("La producción #$produccionId ya no existe.");
            }
            if (!empty($prod[0]['deleted_at'])) {
                throw new Exception("La producción #$produccionId está inactiva.");
            }
            if (empty($prod[0]['fecha_hora_fin'])) {
                throw new Exception("La producción #$produccionId aún no ha finalizado su corrida.");
            }

            $paramsUnicidad = ['produccion_id' => $produccionId];
            $sqlUnicidad = "SELECT id FROM rel_ensamblaje_producto
                             WHERE molde_produccion_id = :produccion_id AND deleted_at IS NULL";
            if ($excluirEnsamblajeId !== null) {
                $sqlUnicidad .= " AND ensamblaje_id != :excluir_id";
                $paramsUnicidad['excluir_id'] = $excluirEnsamblajeId;
            }
            $yaUsada = executeQuery($conectar, $sqlUnicidad, $paramsUnicidad);
            if (!empty($yaUsada)) {
                throw new Exception("La producción #$produccionId ya está vinculada a otro ensamblaje activo.");
            }

            // Snapshot completo de la fila de producción al momento de vincularla.
            $snapshotRows = executeQuery(
                $conectar,
                "SELECT * FROM view_producciones_disponibles_ensamblaje WHERE produccion_id = :id",
                ['id' => $produccionId]
            );
            $snapshot = json_encode($snapshotRows[0] ?? ['produccion_id' => $produccionId], JSON_UNESCAPED_UNICODE);

            executeNonQuery($conectar, "
                INSERT INTO rel_ensamblaje_producto (
                    ensamblaje_id, molde_produccion_id, js_query_consulta_produccion,
                    created_at
                ) VALUES (
                    :ensamblaje_id, :molde_produccion_id, :snapshot,
                    NOW()
                )
            ", [
                'ensamblaje_id'        => $ensamblajeId,
                'molde_produccion_id'  => $produccionId,
                'snapshot'             => $snapshot,
            ]);
        } else {
            // tipo === 'derivado'
            $derivadoId = $linea['derivado_id'];
            $derivado = executeQuery($conectar, "SELECT id FROM derivado WHERE id = :id AND deleted_at IS NULL", ['id' => $derivadoId]);
            if (empty($derivado)) {
                throw new Exception("El derivado #$derivadoId no existe o está inactivo.");
            }

            executeNonQuery($conectar, "
                INSERT INTO rel_ensamblaje_producto (
                    ensamblaje_id, derivado_id, created_at
                ) VALUES (
                    :ensamblaje_id, :derivado_id, NOW()
                )
            ", [
                'ensamblaje_id' => $ensamblajeId,
                'derivado_id'   => $derivadoId,
            ]);
        }
    }
}

/**
 * Recalcula js_moldes_utilizados / js_derivados_utilizados como resúmenes
 * de conveniencia a partir del detalle activo real (fuente de verdad =
 * rel_ensamblaje_producto). Se llama después de cualquier cambio en el
 * detalle para que ambos queden siempre sincronizados.
 */
function recalcularResumenesEnsamblaje($conectar, int $ensamblajeId): void
{
    $moldes = executeQuery($conectar, "
        SELECT rep.molde_produccion_id AS produccion_id, mo.nombre AS molde_nombre,
               pd.cantidad AS cantidad_kg, pd.fecha
        FROM rel_ensamblaje_producto rep
        JOIN produccion pd ON pd.id = rep.molde_produccion_id
        LEFT JOIN molde mo ON mo.id = pd.molde_id
        WHERE rep.ensamblaje_id = :id AND rep.deleted_at IS NULL AND rep.molde_produccion_id IS NOT NULL
    ", ['id' => $ensamblajeId]);

    $derivados = executeQuery($conectar, "
        SELECT rep.derivado_id, dv.nombre AS derivado_nombre
        FROM rel_ensamblaje_producto rep
        LEFT JOIN derivado dv ON dv.id = rep.derivado_id
        WHERE rep.ensamblaje_id = :id AND rep.deleted_at IS NULL AND rep.derivado_id IS NOT NULL
    ", ['id' => $ensamblajeId]);

    executeNonQuery($conectar, "
        UPDATE ensamblaje SET
            js_moldes_utilizados    = :moldes,
            js_derivados_utilizados = :derivados
        WHERE id = :id
    ", [
        'id'        => $ensamblajeId,
        'moldes'    => json_encode($moldes, JSON_UNESCAPED_UNICODE),
        'derivados' => json_encode($derivados, JSON_UNESCAPED_UNICODE),
    ]);
}

// Soft delete: desactiva el ensamblaje y sus líneas activas (libera las
// producciones vinculadas para que puedan usarse en otro ensamblaje).
function eliminarEnsamblaje()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM ensamblaje WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    if (!empty($existe[0]['deleted_at'])) responder(false, 'Este registro ya estaba inactivo.');

    $conectar->beginTransaction();
    try {
        executeNonQuery(
            $conectar,
            "UPDATE rel_ensamblaje_producto SET deleted_at = NOW(), update_at = NOW()
             WHERE ensamblaje_id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );

        $cambios = [[
            'campo' => 'Estado', 'valor_antes' => 'Activo', 'valor_despues' => 'Inactivo (producciones liberadas)',
        ]];
        $movimiento   = obtenerMovimientoSesion('desactivar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery(
            $conectar,
            "UPDATE ensamblaje SET
                deleted_at   = NOW(),
                update_at    = NOW(),
                js_usuario   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id",
            ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]
        );

        $conectar->commit();
        responder(true, 'Ensamblaje desactivado correctamente. Las producciones vinculadas quedaron libres.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error desactivando ensamblaje: " . $e->getMessage());
        responder(false, 'No se pudo desactivar el ensamblaje: ' . $e->getMessage());
    }
}

// Restaura el ensamblaje y las líneas que fueron desactivadas junto con él.
// Si alguna producción ya fue "atrapada" por otro ensamblaje mientras tanto,
// se aborta para no duplicar el uso.
function reactivarEnsamblaje()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM ensamblaje WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    if (empty($existe[0]['deleted_at'])) responder(false, 'Este registro ya estaba activo.');

    $conectar->beginTransaction();
    try {
        $lineas = executeQuery(
            $conectar,
            "SELECT * FROM rel_ensamblaje_producto WHERE ensamblaje_id = :id AND deleted_at IS NOT NULL",
            ['id' => $id]
        );

        foreach ($lineas as $linea) {
            if (!empty($linea['molde_produccion_id'])) {
                $ocupada = executeQuery(
                    $conectar,
                    "SELECT id FROM rel_ensamblaje_producto
                     WHERE molde_produccion_id = :pid AND deleted_at IS NULL AND ensamblaje_id != :eid",
                    ['pid' => $linea['molde_produccion_id'], 'eid' => $id]
                );
                if (!empty($ocupada)) {
                    throw new Exception(
                        "No se puede reactivar: la producción #{$linea['molde_produccion_id']} ya fue usada en otro ensamblaje mientras este estaba inactivo."
                    );
                }
            }
        }

        executeNonQuery(
            $conectar,
            "UPDATE rel_ensamblaje_producto SET deleted_at = NULL, update_at = NOW() WHERE ensamblaje_id = :id AND deleted_at IS NOT NULL",
            ['id' => $id]
        );

        $cambios = [[
            'campo' => 'Estado', 'valor_antes' => 'Inactivo', 'valor_despues' => 'Activo',
        ]];
        $movimiento   = obtenerMovimientoSesion('reactivar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery(
            $conectar,
            "UPDATE ensamblaje SET
                deleted_at   = NULL,
                update_at    = NOW(),
                js_usuario   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id",
            ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]
        );

        $conectar->commit();
        responder(true, 'Ensamblaje reactivado correctamente.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error reactivando ensamblaje: " . $e->getMessage());
        responder(false, 'No se pudo reactivar el ensamblaje: ' . $e->getMessage());
    }
}

// Marca el inicio real del armado con la hora del servidor.
function iniciarEnsamblaje(int $id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at, inicio FROM ensamblaje WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    if (!empty($existe[0]['deleted_at'])) responder(false, 'No puedes iniciar un ensamblaje inactivo.');
    if (!empty($existe[0]['inicio'])) responder(false, 'Este ensamblaje ya fue iniciado.');

    $cambios = [[
        'campo' => 'Inicio de ensamblaje', 'valor_antes' => '(sin iniciar)', 'valor_despues' => 'Iniciado ahora',
    ]];
    $movimiento   = obtenerMovimientoSesion('iniciar_ensamblaje', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE ensamblaje SET
            inicio       = NOW(),
            update_at    = NOW(),
            js_usuario   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]);

    responder(true, 'Ensamblaje iniciado.');
}

// Marca el fin real del armado con la hora del servidor.
function finalizarEnsamblaje(int $id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at, inicio, fin FROM ensamblaje WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    if (!empty($existe[0]['deleted_at'])) responder(false, 'No puedes finalizar un ensamblaje inactivo.');
    if (empty($existe[0]['inicio'])) responder(false, 'Primero debes iniciar el ensamblaje.');
    if (!empty($existe[0]['fin'])) responder(false, 'Este ensamblaje ya fue finalizado.');

    $cambios = [[
        'campo' => 'Fin de ensamblaje', 'valor_antes' => '(en curso)', 'valor_despues' => 'Finalizado ahora',
    ]];
    $movimiento   = obtenerMovimientoSesion('finalizar_ensamblaje', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCABED_UNICODE ?? JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE ensamblaje SET
            fin          = NOW(),
            update_at    = NOW(),
            js_usuario   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]);

    responder(true, 'Ensamblaje finalizado.');
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