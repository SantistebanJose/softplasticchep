<?php

/**
 * controllers/clssUnidadMedida.php
 * Controlador del módulo de Unidad de Medida
 * Tabla real: unidad_medida (id, nombre, nombre_corto, unidad_base_id, equivalencia,
 *             unidad_referencia_id, equivalencia_referencia,
 *             js_session, js_historial, created_at, update_at, deleted_at)
 * unidad_base_id NULL => unidad RAÍZ (kg, metro, unidad...). NOT NULL => unidad
 * COMPUESTA que pertenece a la familia de esa raíz (ej: "Saco 25kg" -> raíz
 * "Gramo"). unidad_base_id SIEMPRE apunta a la raíz real de la familia, nunca
 * a otra compuesta, para que toda conversión de stock (cantidad_base =
 * cantidad * equivalencia) siga siendo una sola multiplicación en el resto
 * del sistema.
 *
 * Sin embargo, en el formulario el usuario SÍ puede elegir como base otra
 * unidad compuesta ya creada (ej: "Kilogramo" al crear "Saco"). Cuando pasa
 * eso, el controlador "aplana" el cálculo antes de guardar:
 *   - unidad_base_id / equivalencia   -> siempre relativos a la raíz real
 *     (ej: Saco -> Gramo, equivalencia 25000)
 *   - unidad_referencia_id / equivalencia_referencia -> lo que el usuario
 *     realmente eligió, solo para mostrarlo bonito en el listado
 *     (ej: Saco = 25 Kilogramo)
 *
 * Soft delete vía deleted_at.
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorUnidadMedida($_POST["accion"]);
}

function controladorUnidadMedida($accion)
{
    switch ($accion) {
        case 'LISTARUNIDADESMEDIDA':
            listarUnidadesMedida();
            break;
        case 'LISTARUNIDADESRAIZ':
            listarUnidadesRaiz();
            break;
        case 'LISTARUNIDADESPARABASE':
            listarUnidadesParaBase(intval($_POST['excluir'] ?? 0));
            break;
        case 'LISTARUNIDADESCOMPATIBLES':
            listarUnidadesCompatibles(intval($_POST['unidad_medida_id'] ?? 0));
            break;
        case 'OBTENERUNIDADMEDIDA':
            obtenerUnidadMedida(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARUNIDADMEDIDA':
            guardarUnidadMedida();
            break;
        case 'ELIMINARUNIDADMEDIDA':
            eliminarUnidadMedida();
            break;
        case 'REACTIVARUNIDADMEDIDA':
            reactivarUnidadMedida();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// UNIDAD DE MEDIDA
// =============================================================================

function listarUnidadesMedida()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(u.nombre) LIKE LOWER(:texto) OR LOWER(u.nombre_corto) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "u.deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "u.deleted_at IS NOT NULL";
    }

    // Traemos el nombre de la unidad base REAL (siempre raíz, para la familia)
    // y también el de la unidad de referencia que el usuario eligió al llenar
    // el formulario (puede ser una compuesta, solo para mostrar bonito).
    $sql = "
        SELECT
            u.*,
            b.nombre       AS base_nombre,
            b.nombre_corto AS base_corto,
            r.nombre       AS ref_nombre,
            r.nombre_corto AS ref_corto
        FROM unidad_medida u
        LEFT JOIN unidad_medida b ON b.id = u.unidad_base_id
        LEFT JOIN unidad_medida r ON r.id = u.unidad_referencia_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY COALESCE(b.nombre, u.nombre), u.equivalencia
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['unidades' => $result]);
}

// Solo unidades RAÍZ (unidad_base_id IS NULL), activas. Se conserva por si
// otro módulo (ej: material.php) todavía la usa para algún selector que solo
// deba mostrar raíces.
function listarUnidadesRaiz()
{
    $conectar = conectar_oll_BD();
    $result = executeQuery(
        $conectar,
        "SELECT * FROM unidad_medida WHERE unidad_base_id IS NULL AND deleted_at IS NULL ORDER BY nombre"
    );
    responder(true, 'OK', ['unidades' => $result]);
}

// Todas las unidades activas (raíces Y compuestas) que pueden elegirse como
// "unidad base" al crear/editar una compuesta. Excluye la unidad que se está
// editando, para que no pueda elegirse a sí misma.
function listarUnidadesParaBase($excluirId)
{
    $conectar = conectar_oll_BD();
    $result = executeQuery(
        $conectar,
        "SELECT * FROM unidad_medida WHERE deleted_at IS NULL AND id <> :excluir ORDER BY nombre",
        ['excluir' => $excluirId]
    );
    responder(true, 'OK', ['unidades' => $result]);
}

// Unidades compatibles con la familia de una unidad raíz dada: la raíz misma
// + todas las compuestas que apuntan a ella. Para el selector de compras.
// No necesita cambios: unidad_base_id siempre apunta a la raíz real, incluso
// cuando la equivalencia se calculó a partir de otra compuesta.
function listarUnidadesCompatibles($unidadMedidaId)
{
    $conectar = conectar_oll_BD();
    if (!$unidadMedidaId) responder(false, 'Debes indicar la unidad de medida del material.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM unidad_medida
         WHERE deleted_at IS NULL
           AND (id = :id OR unidad_base_id = :id)
         ORDER BY equivalencia",
        ['id' => $unidadMedidaId]
    );
    responder(true, 'OK', ['unidades' => $result]);
}

function obtenerUnidadMedida($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM unidad_medida WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Unidad de medida no encontrada.');
    responder(true, 'OK', ['unidad' => $result[0]]);
}

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

function compararCambios(array $anterior, array $nuevo, array $mapaCampos): array
{
    $cambios = [];
    foreach ($mapaCampos as $campo => $etiqueta) {
        $valorAntes   = $anterior[$campo] ?? null;
        $valorDespues = $nuevo[$campo]    ?? null;

        $antesComp   = ($valorAntes   === '' ? null : $valorAntes);
        $despuesComp = ($valorDespues === '' ? null : $valorDespues);

        if ($antesComp !== $despuesComp) {
            $cambios[] = [
                'campo'         => $etiqueta,
                'valor_antes'   => $valorAntes   ?? '(vacío)',
                'valor_despues' => $valorDespues ?? '(vacío)',
            ];
        }
    }
    return $cambios;
}

// Traduce un id de unidad a "Nombre (corto)" legible, para el historial.
function obtenerNombreUnidadLegible($conectar, $unidadId): string
{
    if (empty($unidadId)) return 'Ninguna (es unidad raíz)';
    $result = executeQuery(
        $conectar,
        "SELECT nombre, nombre_corto FROM unidad_medida WHERE id = :id",
        ['id' => $unidadId]
    );
    if (empty($result)) return "Unidad #$unidadId (no encontrada)";
    return $result[0]['nombre'] . ' (' . $result[0]['nombre_corto'] . ')';
}

function guardarUnidadMedida()
{
    $conectar     = conectar_oll_BD();
    $id           = intval($_POST['id'] ?? 0);
    $nombre       = trim($_POST['nombre'] ?? '');
    $nombreCorto  = trim($_POST['nombre_corto'] ?? '');

    // Lo que el usuario eligió en el formulario. Si viene vacío, es unidad
    // RAÍZ. Si viene con valor, puede ser otra raíz O otra compuesta (ej:
    // Kilogramo) — eso se resuelve más abajo.
    $unidadBaseId          = !empty($_POST['unidad_base_id']) ? intval($_POST['unidad_base_id']) : null;
    $equivalenciaIngresada = $unidadBaseId !== null ? floatval($_POST['equivalencia'] ?? 0) : 1;

    // ── Validaciones básicas ─────────────────────────────────────────────────
    if (empty($nombre))      responder(false, 'El nombre es obligatorio.');
    if (empty($nombreCorto)) responder(false, 'El nombre corto (abreviatura) es obligatorio.');

    // Estos son los valores que finalmente se guardan: unidad_base_id/equivalencia
    // SIEMPRE relativos a la raíz real de la familia (para no romper las
    // conversiones de stock en el resto del sistema). unidad_referencia_id/
    // equivalencia_referencia guardan lo que el usuario realmente eligió, solo
    // para mostrarlo bonito en el listado.
    $unidadBaseRealId       = null;
    $equivalenciaReal       = 1;
    $unidadReferenciaId     = null;
    $equivalenciaReferencia = null;

    if ($unidadBaseId !== null) {
        if ($unidadBaseId === $id) responder(false, 'Una unidad no puede ser su propia unidad base.');
        if ($equivalenciaIngresada <= 0) responder(false, 'La equivalencia debe ser mayor a 0 para una unidad compuesta.');

        $seleccionada = executeQuery(
            $conectar,
            "SELECT id, unidad_base_id, equivalencia FROM unidad_medida WHERE id = :id AND deleted_at IS NULL",
            ['id' => $unidadBaseId]
        );
        if (empty($seleccionada)) responder(false, 'La unidad base seleccionada no existe o está inactiva.');
        $sel = $seleccionada[0];

        if (empty($sel['unidad_base_id'])) {
            // Eligió una raíz directamente (ej: Gramo) -> sin nada que aplanar
            $unidadBaseRealId = (int) $sel['id'];
            $equivalenciaReal = $equivalenciaIngresada;
        } else {
            // Eligió otra compuesta (ej: Kilogramo) -> aplanamos hacia SU raíz real
            $unidadBaseRealId = (int) $sel['unidad_base_id'];
            $equivalenciaReal = $equivalenciaIngresada * (float) $sel['equivalencia'];

            $unidadReferenciaId     = (int) $sel['id'];
            $equivalenciaReferencia = $equivalenciaIngresada;
        }
    }

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM unidad_medida WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe una unidad de medida con ese nombre.');

    $mapaCampos = [
        'nombre'          => 'Nombre',
        'nombre_corto'    => 'Abreviatura',
        'unidad_base_nom' => 'Unidad base (familia)',
        'equivalencia'    => 'Equivalencia',
    ];

    $datosNuevos = [
        'nombre'          => $nombre,
        'nombre_corto'    => $nombreCorto,
        'unidad_base_nom' => obtenerNombreUnidadLegible($conectar, $unidadBaseRealId),
        'equivalencia'    => $equivalenciaReal,
    ];

    if ($id === 0) {
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('crear', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        try {
            $result = executeQuery($conectar, "
                INSERT INTO unidad_medida (
                    nombre, nombre_corto, unidad_base_id, equivalencia,
                    unidad_referencia_id, equivalencia_referencia,
                    created_at, js_session, js_historial
                )
                VALUES (
                    :nombre, :nombre_corto, :unidad_base_id, :equivalencia,
                    :unidad_referencia_id, :equivalencia_referencia,
                    NOW(), :js_session, :js_historial
                )
                RETURNING id
            ", [
                'nombre'                  => $datosNuevos['nombre'],
                'nombre_corto'            => $datosNuevos['nombre_corto'],
                'unidad_base_id'          => $unidadBaseRealId,
                'equivalencia'            => $equivalenciaReal,
                'unidad_referencia_id'    => $unidadReferenciaId,
                'equivalencia_referencia' => $equivalenciaReferencia,
                'js_session'              => $js_session,
                'js_historial'            => $js_historial_nuevo,
            ]);
        } catch (Exception $e) {
            responder(false, 'No se pudo guardar: ' . $e->getMessage());
        }
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Unidad de medida creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        $actual = executeQuery($conectar, "SELECT * FROM unidad_medida WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Unidad de medida no encontrada.');
        $registroAnterior = $actual[0];
        $registroAnterior['unidad_base_nom'] = obtenerNombreUnidadLegible($conectar, $registroAnterior['unidad_base_id']);

        // Si esta unidad ya tiene otras unidades dependiendo de ella (ya sea
        // como base real o como referencia elegida en el formulario) y se
        // intenta convertirla en compuesta, bloquear.
        if ($unidadBaseId !== null) {
            $hijas = executeQuery(
                $conectar,
                "SELECT id FROM unidad_medida
                 WHERE (unidad_base_id = :id OR unidad_referencia_id = :id)
                   AND deleted_at IS NULL",
                ['id' => $id]
            );
            if (!empty($hijas)) {
                responder(false, 'Esta unidad tiene otras unidades dependiendo de ella (como base o como referencia); no puede convertirse en compuesta.');
            }
        }

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('editar', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        try {
            executeQuery($conectar, "
                UPDATE unidad_medida SET
                    nombre                   = :nombre,
                    nombre_corto             = :nombre_corto,
                    unidad_base_id           = :unidad_base_id,
                    equivalencia             = :equivalencia,
                    unidad_referencia_id     = :unidad_referencia_id,
                    equivalencia_referencia  = :equivalencia_referencia,
                    update_at                = NOW(),
                    js_session               = :js_session,
                    js_historial             = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
                WHERE id = :id
            ", [
                'nombre'                  => $datosNuevos['nombre'],
                'nombre_corto'            => $datosNuevos['nombre_corto'],
                'unidad_base_id'          => $unidadBaseRealId,
                'equivalencia'            => $equivalenciaReal,
                'unidad_referencia_id'    => $unidadReferenciaId,
                'equivalencia_referencia' => $equivalenciaReferencia,
                'id'                      => $id,
                'js_session'              => $js_session,
                'js_historial'            => $js_historial_nuevo,
            ]);
        } catch (Exception $e) {
            responder(false, 'No se pudo actualizar: ' . $e->getMessage());
        }
        responder(true, 'Unidad de medida actualizada correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

function eliminarUnidadMedida()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM unidad_medida WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Unidad de medida no encontrada.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Esta unidad de medida ya estaba inactiva.');
    }

    // No permitir desactivar si está en uso por algún material
    $enUso = executeQuery(
        $conectar,
        "SELECT id FROM material WHERE unidad_medida_id = :id AND deleted_at IS NULL",
        ['id' => $id]
    );
    if (!empty($enUso)) {
        responder(false, 'No puedes desactivar esta unidad: está siendo usada por uno o más materiales activos.');
    }

    // No permitir desactivar una unidad que tenga otras unidades activas
    // dependiendo de ella, ya sea como base real o como referencia elegida
    // en el formulario (ej: no desactivar Kilogramo si Saco lo usa de base).
    $conHijas = executeQuery(
        $conectar,
        "SELECT id FROM unidad_medida
         WHERE (unidad_base_id = :id OR unidad_referencia_id = :id)
           AND deleted_at IS NULL",
        ['id' => $id]
    );
    if (!empty($conHijas)) {
        responder(false, 'No puedes desactivar esta unidad: tiene otras unidades activas dependiendo de ella (ej: sacos, bolsas).');
    }

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Activo',
        'valor_despues' => 'Inactivo',
    ]];

    $movimiento          = obtenerMovimientoSesion('desactivar', $cambios);
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE unidad_medida SET
            deleted_at   = NOW(),
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id",
        [
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );
    responder(true, 'Unidad de medida desactivada correctamente.');
}

function reactivarUnidadMedida()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Inactivo',
        'valor_despues' => 'Activo',
    ]];

    $movimiento          = obtenerMovimientoSesion('reactivar', $cambios);
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE unidad_medida SET
            deleted_at   = NULL,
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id",
        [
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );
    responder(true, 'Unidad de medida reactivada correctamente.');
}

function responder(bool $ok, string $msg, array $extra = []): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}