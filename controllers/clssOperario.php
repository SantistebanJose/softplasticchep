<?php

/**
 * controllers/clssOperario.php
 * Controlador del módulo de Operarios
 * Tabla real: operario (id, nombre_completo, cargo_id, dni, activo, created_at,
 *             updated_at, deleted_at, js_session, js_historial, js_sucursales,
 *             js_etapas_relacionadas)
 *
 * cargo_id (int, FK -> cargo.id): reemplaza a la antigua columna de texto
 * libre 'cargo'. La tabla 'cargo' sigue el mismo patrón de 'etapa'
 * (id, nombre, orden, deleted_at). listarOperarios()/obtenerOperario()
 * hacen LEFT JOIN contra 'cargo' para devolver 'cargo_nombre' junto al id,
 * así el frontend no tiene que resolverlo aparte.
 *
 * Soft delete vía deleted_at (mismo patrón que 'orden_produccion' / 'moldes').
 * La columna 'activo' se mantiene sincronizada con deleted_at por
 * compatibilidad con otros módulos que ya filtran por activo = true
 * (ej. buscarOperarios() en clssProduccion.php):
 *   - Desactivar -> activo = false, deleted_at = NOW()
 *   - Reactivar  -> activo = true,  deleted_at = NULL
 *
 * js_sucursales (jsonb): arreglo denormalizado de las sucursales donde
 * trabaja el operario, ej: [{"sucursal_id":1,"nombre":"SUCURSAL 1"}].
 * js_etapas_relacionadas (jsonb): mismo patrón para las etapas en las que
 * puede involucrarse el operario, ej: [{"etapa_id":1,"nombre":"PRODUCCIÓN"}].
 * En ambos casos el id es la fuente de verdad; 'nombre' es solo para no
 * tener que hacer join al listar. Se resincronizan completos en cada
 * guardado (no se hace merge parcial).
 *
 * DNI y cuenta de acceso: el DNI es obligatorio al crear un operario.
 * Al registrarlo, crearUsuarioDesdeOperario() genera automáticamente una
 * fila en 'usuario' con user_ = DNI y pass_ = DNI hasheado (bcrypt vía
 * password_hash), vinculada por 'usuario.operario_id' (FK, UNIQUE).
 * Si ya existe un usuario con ese DNI como login, no se duplica: el
 * operario se crea igual y la respuesta trae un aviso ('usuario_creado'
 * => false). Editar el operario NO toca la cuenta de acceso existente.
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

// PHP 8.5 imprime avisos "Deprecated" como HTML antes del cuerpo de la
// respuesta si display_errors está activo. Como este controlador SIEMPRE
// responde JSON puro (ver responder()), cualquier warning/notice impreso
// aquí rompe el JSON.parse() del frontend. Se silencia solo la salida en
// pantalla de deprecated/notice (los errores reales igual se registran
// en el log si error_log/log_errors está configurado en php.ini).
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

function controladorOperario($accion)
{
    switch ($accion) {
        case 'LISTAROPERARIOS':
            listarOperarios();
            break;
        case 'OBTENEROPERARIO':
            obtenerOperario(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDAROPERARIO':
            guardarOperario();
            break;
        case 'ELIMINAROPERARIO':
            eliminarOperario();
            break;
        case 'REACTIVAROPERARIO':
            reactivarOperario();
            break;
        case 'CREARUSUARIODESDEOPERARIO':
            crearUsuarioManualDesdeOperario();
            break;
        case 'LISTARCARGOS':
            listarCargos();
            break;
        case 'BUSCARDNI':
            buscarDNI();
            break;
        case 'LISTARETAPASACTIVAS':
            listarEtapasActivas();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// AUDITORÍA (idéntico patrón a clssOrdenProduccion.php)
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

/**
 * Lista los cargos activos (tabla 'cargo'), mismo patrón que
 * listarEtapasActivas(). Usada para poblar el <select> del modal
 * y el filtro de la tabla si en algún momento se agrega.
 */
function listarCargos()
{
    $conectar = conectar_oll_BD();
    $result = executeQuery($conectar, "
        SELECT id, nombre FROM cargo
        WHERE deleted_at IS NULL
        ORDER BY orden
    ");
    responder(true, 'OK', ['cargos' => $result]);
}

/**
 * Compara el arreglo de sucursales asignado antes/después y devuelve
 * una entrada de cambio legible (o null si no hubo diferencia).
 */
function compararSucursales(?string $anteriorJson, array $nuevasSucursales): ?array
{
    $anteriorArr = json_decode($anteriorJson ?? '[]', true) ?: [];

    $nombresAntes   = array_column($anteriorArr, 'nombre');
    $nombresDespues = array_column($nuevasSucursales, 'nombre');

    sort($nombresAntes);
    sort($nombresDespues);

    if ($nombresAntes === $nombresDespues) {
        return null;
    }

    return [
        'campo'         => 'Sucursales asignadas',
        'valor_antes'   => $nombresAntes   ? implode(', ', $nombresAntes)   : '(ninguna)',
        'valor_despues' => $nombresDespues ? implode(', ', $nombresDespues) : '(ninguna)',
    ];
}

/**
 * Mismo patrón que compararSucursales() pero para js_etapas_relacionadas.
 */
function compararEtapas(?string $anteriorJson, array $nuevasEtapas): ?array
{
    $anteriorArr = json_decode($anteriorJson ?? '[]', true) ?: [];

    $nombresAntes   = array_column($anteriorArr, 'nombre');
    $nombresDespues = array_column($nuevasEtapas, 'nombre');

    sort($nombresAntes);
    sort($nombresDespues);

    if ($nombresAntes === $nombresDespues) {
        return null;
    }

    return [
        'campo'         => 'Etapas relacionadas',
        'valor_antes'   => $nombresAntes   ? implode(', ', $nombresAntes)   : '(ninguna)',
        'valor_despues' => $nombresDespues ? implode(', ', $nombresDespues) : '(ninguna)',
    ];
}

/**
 * Recibe los ids de sucursal seleccionados en el form, los valida contra
 * la tabla sucursal (solo activas) y devuelve el arreglo denormalizado
 * listo para guardar en js_sucursales.
 */
function resolverSucursales($conectar, array $idsSucursal): array
{
    $idsSucursal = array_values(array_unique(array_map('intval', $idsSucursal)));
    $idsSucursal = array_filter($idsSucursal, fn($id) => $id > 0);
    if (empty($idsSucursal)) return [];

    $placeholders = [];
    $params = [];
    foreach ($idsSucursal as $i => $id) {
        $key = "id{$i}";
        $placeholders[] = ":{$key}";
        $params[$key] = $id;
    }

    $sql = "SELECT id, nombre FROM sucursal
            WHERE id IN (" . implode(',', $placeholders) . ") AND delete_at IS NULL";
    $result = executeQuery($conectar, $sql, $params);

    return array_map(fn($s) => [
        'sucursal_id' => (int)$s['id'],
        'nombre'      => $s['nombre'],
    ], $result);
}

/**
 * Mismo patrón que resolverSucursales() pero contra la tabla etapa
 * (usa deleted_at, no delete_at como sucursal).
 */
function resolverEtapas($conectar, array $idsEtapa): array
{
    $idsEtapa = array_values(array_unique(array_map('intval', $idsEtapa)));
    $idsEtapa = array_filter($idsEtapa, fn($id) => $id > 0);
    if (empty($idsEtapa)) return [];

    $placeholders = [];
    $params = [];
    foreach ($idsEtapa as $i => $id) {
        $key = "id{$i}";
        $placeholders[] = ":{$key}";
        $params[$key] = $id;
    }

    $sql = "SELECT id, nombre FROM etapa
            WHERE id IN (" . implode(',', $placeholders) . ") AND deleted_at IS NULL";
    $result = executeQuery($conectar, $sql, $params);

    return array_map(fn($e) => [
        'etapa_id' => (int)$e['id'],
        'nombre'   => $e['nombre'],
    ], $result);
}

function registrarMovimiento($conectar, int $id, string $accion, array $cambios): void
{
    $movimiento         = obtenerMovimientoSesion($accion, $cambios);
    $js_session         = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery($conectar, "
        UPDATE operario SET
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", [
        'id'           => $id,
        'js_session'   => $js_session,
        'js_historial' => $js_historial_nuevo,
    ]);
}

// =============================================================================
// OPERARIOS
// =============================================================================

function listarOperarios()
{
    $conectar = conectar_oll_BD();

    $texto       = trim($_POST['texto'] ?? '');
    $visibilidad = trim($_POST['visibilidad'] ?? 'activas'); // activas, eliminadas, todas
    $sucursal_id = intval($_POST['sucursal_id'] ?? 0);
    $etapa_id    = intval($_POST['etapa_id'] ?? 0);

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(o.nombre_completo) LIKE LOWER(:texto) OR LOWER(c.nombre) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($visibilidad === 'eliminadas') {
        $where[] = "o.deleted_at IS NOT NULL";
    } elseif ($visibilidad !== 'todas') {
        $where[] = "o.deleted_at IS NULL";
    }
    if ($sucursal_id > 0) {
        $where[] = "EXISTS (
            SELECT 1 FROM jsonb_array_elements(COALESCE(o.js_sucursales, '[]'::jsonb)) AS suc
            WHERE (suc->>'sucursal_id')::int = :sucursal_id
        )";
        $params['sucursal_id'] = $sucursal_id;
    }
    if ($etapa_id > 0) {
        $where[] = "EXISTS (
            SELECT 1 FROM jsonb_array_elements(COALESCE(o.js_etapas_relacionadas, '[]'::jsonb)) AS et
            WHERE (et->>'etapa_id')::int = :etapa_id
        )";
        $params['etapa_id'] = $etapa_id;
    }

    // LEFT JOIN contra cargo para traer el nombre a mostrar en la tabla,
    // y de cargo hacia area para mostrar en qué área está ese cargo,
    // sin obligar al frontend a resolverlo aparte.
    $sql = "SELECT o.*, c.nombre AS cargo_nombre, ar.nombre AS area_nombre
            FROM operario o
            LEFT JOIN cargo c ON c.id = o.cargo_id
            LEFT JOIN area ar ON ar.id = c.area_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.nombre_completo";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['operarios' => decodificarJsonFilas($result)]);
}

function obtenerOperario($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery($conectar, "
        SELECT o.*, c.nombre AS cargo_nombre, ar.nombre AS area_nombre,
               u.id AS usuario_id, u.user_ AS usuario_login
        FROM operario o
        LEFT JOIN cargo c ON c.id = o.cargo_id
        LEFT JOIN area ar ON ar.id = c.area_id
        LEFT JOIN usuario u ON u.operario_id = o.id
        WHERE o.id = :id
    ", ['id' => $id]);
    if (empty($result)) responder(false, 'Operario no encontrado.');
    $filas = decodificarJsonFilas($result);
    responder(true, 'OK', ['operario' => $filas[0]]);
}
/**
 * executeQuery devuelve las columnas jsonb (js_sucursales,
 * js_etapas_relacionadas, etc.) como strings JSON crudos, no como arreglos
 * PHP. Si no se decodifican antes de json_encode() en responder(), llegan
 * al frontend como un string con JSON escapado en vez de un array, y
 * {}.map() revienta en el navegador.
 */
function decodificarJsonFilas(array $filas): array
{
    $columnasJson = ['js_sucursales', 'js_etapas_relacionadas'];
    foreach ($filas as &$fila) {
        foreach ($columnasJson as $col) {
            if (isset($fila[$col]) && is_string($fila[$col])) {
                $fila[$col] = json_decode($fila[$col], true) ?: [];
            } elseif (!isset($fila[$col])) {
                $fila[$col] = [];
            }
        }
    }
    unset($fila);
    return $filas;
}

function guardarOperario()
{
    $conectar = conectar_oll_BD();

    $id              = intval($_POST['id'] ?? 0);
    $nombre_completo = mb_strtoupper(trim($_POST['nombre_completo'] ?? ''), 'UTF-8');
    $cargo_id        = intval($_POST['cargo_id'] ?? 0) ?: null;
    $dni             = trim($_POST['dni'] ?? '');

    // sucursales/etapas llegan como JSON: "[1,3]" (ids seleccionados en el multi-select)
    $sucursalesInput = json_decode($_POST['sucursales'] ?? '[]', true);
    if (!is_array($sucursalesInput)) $sucursalesInput = [];

    $etapasInput = json_decode($_POST['etapas'] ?? '[]', true);
    if (!is_array($etapasInput)) $etapasInput = [];

    if (empty($nombre_completo)) responder(false, 'El nombre completo es obligatorio.');
    if (empty($dni)) {
        responder(false, 'El DNI es obligatorio: se usa para generar el usuario de acceso al sistema.');
    }
    if (!preg_match('/^\d{8}$/', $dni)) {
        responder(false, 'El DNI debe tener 8 dígitos.');
    }

    // Validar unicidad del DNI (excluyendo al propio registro en edición)
    $existe = executeQuery($conectar, "
        SELECT id FROM operario WHERE dni = :dni AND id <> :id
    ", ['dni' => $dni, 'id' => $id]);
    if (!empty($existe)) {
        responder(false, 'Ya existe un operario registrado con ese DNI.');
    }

    $sucursalesResueltas = resolverSucursales($conectar, $sucursalesInput);
    $js_sucursales_json  = json_encode($sucursalesResueltas, JSON_UNESCAPED_UNICODE);

    $etapasResueltas  = resolverEtapas($conectar, $etapasInput);
    $js_etapas_json   = json_encode($etapasResueltas, JSON_UNESCAPED_UNICODE);

    $mapaCampos = [
        'nombre_completo' => 'Nombre completo',
        'cargo_id'        => 'Cargo',
        'dni'             => 'DNI',
    ];

    $datosNuevos = [
        'nombre_completo' => $nombre_completo,
        'cargo_id'        => $cargo_id,
        'dni'             => $dni !== '' ? $dni : null,
    ];

    if ($id === 0) {
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);
        if (!empty($sucursalesResueltas)) {
            $cambios[] = [
                'campo'         => 'Sucursales asignadas',
                'valor_antes'   => '(ninguna)',
                'valor_despues' => implode(', ', array_column($sucursalesResueltas, 'nombre')),
            ];
        }
        if (!empty($etapasResueltas)) {
            $cambios[] = [
                'campo'         => 'Etapas relacionadas',
                'valor_antes'   => '(ninguna)',
                'valor_despues' => implode(', ', array_column($etapasResueltas, 'nombre')),
            ];
        }

        $movimiento         = obtenerMovimientoSesion('crear', $cambios);
        $js_session         = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $result = executeQuery($conectar, "
            INSERT INTO operario
                (nombre_completo, cargo_id, dni, activo, created_at, js_session, js_historial, js_sucursales, js_etapas_relacionadas)
            VALUES
                (:nombre_completo, :cargo_id, :dni, true, NOW(), :js_session, :js_historial, :js_sucursales, :js_etapas_relacionadas)
            RETURNING id
        ", [
            'nombre_completo'         => $datosNuevos['nombre_completo'],
            'cargo_id'                => $datosNuevos['cargo_id'],
            'dni'                     => $datosNuevos['dni'],
            'js_session'              => $js_session,
            'js_historial'            => $js_historial_nuevo,
            'js_sucursales'           => $js_sucursales_json,
            'js_etapas_relacionadas'  => $js_etapas_json,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;

        $usuarioCreado = null;
        if ($nuevo_id) {
            $usuarioCreado = crearUsuarioDesdeOperario($conectar, $nuevo_id, $datosNuevos['dni'], $datosNuevos['nombre_completo']);
        }

        $mensaje = 'Operario creado correctamente.';
        if ($usuarioCreado && $usuarioCreado['ok'] === false) {
            $mensaje .= ' Aviso: ' . $usuarioCreado['motivo'];
        }

        responder(true, $mensaje, [
            'id'             => $nuevo_id,
            'modo'           => 'crear',
            'usuario_creado' => $usuarioCreado['ok'] ?? false,
        ]);
    } else {
        $actual = executeQuery($conectar, "SELECT * FROM operario WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Operario no encontrado.');
        $registroAnterior = $actual[0];

        if (!empty($registroAnterior['deleted_at'])) {
            responder(false, 'No puedes editar un operario inactivo. Reactívalo primero.');
        }

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $cambioSucursales = compararSucursales($registroAnterior['js_sucursales'] ?? null, $sucursalesResueltas);
        if ($cambioSucursales !== null) {
            $cambios[] = $cambioSucursales;
        }

        $cambioEtapas = compararEtapas($registroAnterior['js_etapas_relacionadas'] ?? null, $etapasResueltas);
        if ($cambioEtapas !== null) {
            $cambios[] = $cambioEtapas;
        }

        $movimiento         = obtenerMovimientoSesion('editar', $cambios);
        $js_session         = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeQuery($conectar, "
            UPDATE operario SET
                nombre_completo         = :nombre_completo,
                cargo_id                = :cargo_id,
                dni                     = :dni,
                js_sucursales           = :js_sucursales,
                js_etapas_relacionadas  = :js_etapas_relacionadas,
                updated_at              = NOW(),
                js_session              = :js_session,
                js_historial            = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nombre_completo'        => $datosNuevos['nombre_completo'],
            'cargo_id'               => $datosNuevos['cargo_id'],
            'dni'                    => $datosNuevos['dni'],
            'js_sucursales'          => $js_sucursales_json,
            'js_etapas_relacionadas' => $js_etapas_json,
            'id'                     => $id,
            'js_session'             => $js_session,
            'js_historial'           => $js_historial_nuevo,
        ]);
        responder(true, 'Operario actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}
function eliminarOperario()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT deleted_at FROM operario WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Operario no encontrado.');
    if (!empty($actual[0]['deleted_at'])) responder(false, 'Este operario ya estaba inactivo.');

    executeQuery($conectar, "
        UPDATE operario SET
            activo     = false,
            deleted_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ", ['id' => $id]);

    $cambios = [[
        'campo' => 'Estado', 'valor_antes' => 'Activo', 'valor_despues' => 'Inactivo',
    ]];
    registrarMovimiento($conectar, $id, 'desactivar', $cambios);

    responder(true, 'Operario desactivado correctamente.');
}

function reactivarOperario()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT deleted_at FROM operario WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Operario no encontrado.');
    if (empty($actual[0]['deleted_at'])) responder(false, 'Este operario ya estaba activo.');

    executeQuery($conectar, "
        UPDATE operario SET
            activo     = true,
            deleted_at = NULL,
            updated_at = NOW()
        WHERE id = :id
    ", ['id' => $id]);

    $cambios = [[
        'campo' => 'Estado', 'valor_antes' => 'Inactivo', 'valor_despues' => 'Activo',
    ]];
    registrarMovimiento($conectar, $id, 'reactivar', $cambios);

    responder(true, 'Operario reactivado correctamente.');
}

// =============================================================================
// ETAPAS (solo lectura desde este controlador — soporte para el multi-select)
// =============================================================================

function listarEtapasActivas()
{
    $conectar = conectar_oll_BD();
    $result = executeQuery($conectar, "
        SELECT id, nombre, orden FROM etapa
        WHERE deleted_at IS NULL
        ORDER BY orden
    ");
    responder(true, 'OK', ['etapas' => $result]);
}

/**
 * Crea automáticamente la cuenta de acceso (tabla 'usuario') para un
 * operario recién registrado: user_ = DNI, pass_ = DNI hasheado.
 * Si ya existe un usuario con ese DNI como login, no lo duplica —
 * devuelve el motivo para que guardarOperario() lo informe en la
 * respuesta sin bloquear la creación del operario.
 */
function crearUsuarioDesdeOperario($conectar, int $operarioId, string $dni, string $nombreCompleto): array
{
    $existe = executeQuery($conectar, "SELECT id FROM usuario WHERE user_ = :user_", ['user_' => $dni]);
    if (!empty($existe)) {
        return ['ok' => false, 'motivo' => 'Ya existe un usuario con ese DNI como login; no se creó automáticamente.'];
    }

    $rolYPerfiles = json_encode(['rol' => 'operario', 'perfiles' => []], JSON_UNESCAPED_UNICODE);
    $passHash     = password_hash($dni, PASSWORD_DEFAULT);
    $pinPorDefecto = '1111'; // NUEVO: PIN inicial de acceso al panel operario (char(4))

    $movimiento    = obtenerMovimientoSesion('crear_auto_desde_operario', [[
        'campo' => 'Origen', 'valor_antes' => '(nuevo)', 'valor_despues' => 'Autogenerado al registrar operario #' . $operarioId,
    ]]);
    $jsonHistorial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery($conectar, "
        INSERT INTO usuario
            (user_, pass_, pin, nombre_completo, rol_y_perfiles, operario_id, json_historial, fecha_cambio_pass, created_at, updated_at)
        VALUES
            (:user_, :pass_, :pin, :nombre_completo, :rol_y_perfiles, :operario_id, :json_historial, NOW(), NOW(), NOW())
    ", [
        'user_'            => $dni,
        'pass_'            => $passHash,
        'pin'              => $pinPorDefecto,
        'nombre_completo'  => $nombreCompleto,
        'rol_y_perfiles'   => $rolYPerfiles,
        'operario_id'      => $operarioId,
        'json_historial'   => $jsonHistorial,
    ]);

    return ['ok' => true];
}
/**
 * Acción manual (botón en el modal de edición) para vincular/crear la
 * cuenta de acceso de un operario que quedó sin 'usuario' — ya sea por
 * colisión de DNI al crearlo, o por ser un registro anterior a que el
 * DNI se volviera obligatorio. Reusa crearUsuarioDesdeOperario().
 */
function crearUsuarioManualDesdeOperario()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT id, nombre_completo, dni FROM operario WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Operario no encontrado.');
    $operario = $actual[0];

    if (empty($operario['dni'])) {
        responder(false, 'Este operario no tiene DNI registrado; agrégalo primero para poder crear su cuenta de acceso.');
    }

    $yaVinculado = executeQuery($conectar, "SELECT id FROM usuario WHERE operario_id = :id", ['id' => $id]);
    if (!empty($yaVinculado)) {
        responder(false, 'Este operario ya tiene una cuenta de acceso vinculada.');
    }

    $resultado = crearUsuarioDesdeOperario($conectar, $id, $operario['dni'], $operario['nombre_completo']);
    if ($resultado['ok']) {
        responder(true, 'Cuenta de acceso creada correctamente (usuario y contraseña = DNI).');
    } else {
        responder(false, $resultado['motivo']);
    }
}

function resolverOperariosEnsamblaje($conectar, array $idsOperario): array
{
    $idsOperario = array_values(array_unique(array_map('intval', $idsOperario)));
    $idsOperario = array_filter($idsOperario, fn($id) => $id > 0);
    if (empty($idsOperario)) return [];

    $placeholders = [];
    $params = [];
    foreach ($idsOperario as $i => $id) {
        $key = "op{$i}";
        $placeholders[] = ":{$key}";
        $params[$key] = $id;
    }

    // 'cargo' ya no es columna de operario -> se resuelve con JOIN a cargo.
    $sql = "SELECT o.id, o.nombre_completo, c.nombre AS cargo
            FROM operario o
            LEFT JOIN cargo c ON c.id = o.cargo_id
            WHERE o.id IN (" . implode(',', $placeholders) . ") AND o.activo = true";
    $result = executeQuery($conectar, $sql, $params);

    if (count($result) !== count($idsOperario)) {
        throw new Exception('Uno o más operarios seleccionados no existen o están inactivos.');
    }

    return array_map(fn($o) => [
        'operario_id'     => (int)$o['id'],
        'nombre_completo' => $o['nombre_completo'],
        'cargo'           => $o['cargo'],
    ], $result);
}

// =============================================================================
// CONSULTA EXTERNA DE DNI
// =============================================================================

function buscarDNI()
{
    $dni = trim($_POST['dni'] ?? '');

    if (!preg_match('/^\d{8}$/', $dni)) {
        responder(false, 'El DNI debe tener 8 dígitos.');
    }

    $url = "https://graphperu.daustinn.com/api/query/{$dni}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
        ],
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ]);
    $respuesta = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    // curl_close() ya no hace nada desde PHP 8.0 y genera un aviso
    // "Deprecated" en PHP 8.5+; se omite intencionalmente.

    if ($respuesta === false) {
        responder(false, 'No se pudo conectar con el servicio de consulta DNI: ' . $errorCurl);
    }
    if ($httpCode !== 200) {
        error_log("BUSCARDNI dni={$dni} httpCode={$httpCode} body={$respuesta}");
        responder(false, 'DNI no encontrado o servicio no disponible (HTTP ' . $httpCode . ').');
    }

    $datos = json_decode($respuesta, true);
    if (!$datos || empty($datos['fullName'])) {
        responder(false, 'No se encontraron datos para ese DNI.');
    }

    responder(true, 'OK', [
        'dni'             => $datos['documentID'] ?? $dni,
        'nombre_completo' => $datos['fullName']    ?? '',
    ]);
}

// =============================================================================
// HELPER
// =============================================================================

function responder(bool $ok, string $msg, array $extra = []): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

// =============================================================================
// DISPATCH
// =============================================================================

if (isset($_POST["accion"])) {
    controladorOperario($_POST["accion"]);
}