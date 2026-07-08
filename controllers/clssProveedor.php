<?php

/**
 * controllers/clssProveedor.php
 * Controlador del módulo de Proveedores
 * Tabla real: proveedor (ruc PK, razon_social, nombre_comercial, telefonos_contacto jsonb,
 *             correo, ubigeo, ubicacion, js_consulta_api jsonb, js_session, js_historial,
 *             created_at, update_at, deleted_at)
 * Soft delete vía deleted_at (no existe columna 'activo').
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 *
 * El campo "ruc" en la tabla identifica al proveedor y ahora acepta tanto
 * RUC (11 dígitos, empresa) como DNI (8 dígitos, persona natural).
 *
 * NOTA IMPORTANTE:
 * Este archivo SIEMPRE debe responder JSON puro. Para blindarnos contra cualquier
 * warning/notice/deprecated que PHP imprima antes de nuestro echo, abrimos un
 * buffer de salida al inicio y lo descartamos justo antes de emitir el JSON en
 * responder(). Además, TODO el despacho de acciones va envuelto en try/catch
 * para que ningún error de base de datos quede oculto: si algo falla, el
 * frontend recibe success:false con el motivo real, en vez de un falso éxito.
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    try {
        controladorProveedor($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssProveedor.php: " . $e->getMessage());
        responder(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssProveedor.php: " . $e->getMessage());
        responder(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorProveedor($accion)
{
    switch ($accion) {
        case 'LISTARPROVEEDORES':
            listarProveedores();
            break;
        case 'OBTENERPROVEEDOR':
            obtenerProveedor(trim($_POST['ruc'] ?? ''));
            break;
        case 'GUARDARPROVEEDOR':
            guardarProveedor();
            break;
        case 'ELIMINARPROVEEDOR':
            eliminarProveedor();
            break;
        case 'REACTIVARPROVEEDOR':
            reactivarProveedor();
            break;
        case 'CONSULTARDOCUMENTO':
            consultarDocumento(trim($_POST['numero'] ?? ''));
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// HELPERS DE VALIDACIÓN
// =============================================================================

/**
 * El proveedor puede identificarse con RUC (11 dígitos, empresa)
 * o con DNI (8 dígitos, persona natural).
 */
function esDocumentoProveedorValido(string $numero): bool
{
    return (bool) preg_match('/^\d{8}$|^\d{11}$/', $numero);
}

// =============================================================================
// PROVEEDORES
// =============================================================================

function listarProveedores()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(razon_social) LIKE LOWER(:texto)
                     OR LOWER(nombre_comercial) LIKE LOWER(:texto)
                     OR ruc LIKE :texto)";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "deleted_at IS NOT NULL";
    }

    $sql = "SELECT * FROM proveedor WHERE " . implode(' AND ', $where) . " ORDER BY razon_social";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['proveedores' => $result]);
}

function obtenerProveedor($ruc)
{
    $conectar = conectar_oll_BD();
    if (empty($ruc)) responder(false, 'RUC/DNI inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM proveedor WHERE ruc = :ruc",
        ['ruc' => $ruc]
    );
    if (empty($result)) responder(false, 'Proveedor no encontrado.');
    responder(true, 'OK', ['proveedor' => $result[0]]);
}

/**
 * Obtiene la IP real del cliente, considerando proxies/balanceadores comunes.
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

/**
 * Arma el bloque de auditoría (usuario/sesión) para un movimiento dado.
 */
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
 * Compara un registro anterior contra los datos nuevos y devuelve
 * solo los campos cuyo valor cambió, con etiqueta legible.
 */
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

function guardarProveedor()
{
    $conectar = conectar_oll_BD();

    $ruc              = trim($_POST['ruc'] ?? '');
    $razon_social     = trim($_POST['razon_social'] ?? '');
    $nombre_comercial = trim($_POST['nombre_comercial'] ?? '');
    $correo           = trim($_POST['correo'] ?? '');
    $ubigeo           = trim($_POST['ubigeo'] ?? '');
    $ubicacion        = trim($_POST['ubicacion'] ?? '');
    $telefonosJson    = trim($_POST['telefonos_contacto'] ?? '[]');
    $consultaApiJson  = trim($_POST['js_consulta_api'] ?? '');

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (!esDocumentoProveedorValido($ruc)) {
        responder(false, 'El documento del proveedor debe tener 11 dígitos (RUC) u 8 dígitos (DNI).');
    }
    if (empty($razon_social)) responder(false, 'La razón social / nombre es obligatorio.');
    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        responder(false, 'El correo no tiene un formato válido.');
    }

    // Normalizamos teléfonos: [{ "telefono": "...", "contacto": "..." }, ...]
    $telefonos = json_decode($telefonosJson, true);
    if (!is_array($telefonos)) $telefonos = [];
    $telefonos = array_values(array_filter(
        $telefonos,
        fn($t) => is_array($t) && !empty(trim($t['telefono'] ?? ''))
    ));

    // Si mandaron una consulta de API, validamos que sea JSON válido
    if ($consultaApiJson !== '' && json_decode($consultaApiJson) === null) {
        $consultaApiJson = '';
    }

    // Mapa de campos editables → etiqueta legible para el historial
    $mapaCampos = [
        'razon_social'       => 'Razón social',
        'nombre_comercial'   => 'Nombre comercial',
        'correo'             => 'Correo',
        'ubigeo'             => 'Ubigeo',
        'ubicacion'          => 'Ubicación',
        'telefonos_contacto' => 'Teléfonos de contacto',
    ];

    $datosNuevos = [
        'razon_social'       => $razon_social,
        'nombre_comercial'   => $nombre_comercial ?: null,
        'correo'             => $correo ?: null,
        'ubigeo'             => $ubigeo ?: null,
        'ubicacion'          => $ubicacion ?: null,
        'telefonos_contacto' => json_encode($telefonos, JSON_UNESCAPED_UNICODE),
    ];

    $existente = executeQuery($conectar, "SELECT * FROM proveedor WHERE ruc = :ruc", ['ruc' => $ruc]);

    if (empty($existente)) {
        // ── Creación ─────────────────────────────────────────────────────────
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);

        $movimiento         = obtenerMovimientoSesion('crear', $cambios);
        $js_session         = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        try {
            $filas = executeNonQuery($conectar, "
                INSERT INTO proveedor (
                    ruc, razon_social, nombre_comercial, telefonos_contacto,
                    correo, ubigeo, ubicacion, js_consulta_api,
                    created_at, js_session, js_historial
                ) VALUES (
                    :ruc, :razon_social, :nombre_comercial, :telefonos_contacto::jsonb,
                    :correo, :ubigeo, :ubicacion, :js_consulta_api::jsonb,
                    NOW(), :js_session, :js_historial
                )
            ", [
                'ruc'                => $ruc,
                'razon_social'       => $datosNuevos['razon_social'],
                'nombre_comercial'   => $datosNuevos['nombre_comercial'],
                'telefonos_contacto' => $datosNuevos['telefonos_contacto'],
                'correo'             => $datosNuevos['correo'],
                'ubigeo'             => $datosNuevos['ubigeo'],
                'ubicacion'          => $datosNuevos['ubicacion'],
                'js_consulta_api'    => $consultaApiJson !== '' ? $consultaApiJson : null,
                'js_session'         => $js_session,
                'js_historial'       => $js_historial_nuevo,
            ]);
        } catch (PDOException $e) {
            // Código 23505 = violación de llave única (Postgres) -> ya existe ese RUC/DNI
            if ($e->getCode() === '23505') {
                responder(false, 'Ya existe un proveedor registrado con ese RUC/DNI.');
            }
            throw $e; // lo captura el try/catch general y responde el mensaje real
        }

        if ($filas < 1) {
            responder(false, 'No se pudo insertar el proveedor (0 filas afectadas). Revisa permisos/constraints en la BD.');
        }

        responder(true, 'Proveedor creado correctamente.', ['ruc' => $ruc, 'modo' => 'crear']);
    } else {
        // ── Edición ──────────────────────────────────────────────────────────
        $registroAnterior = $existente[0];
        // El driver puede devolver el jsonb ya decodificado; lo normalizamos a string para comparar justo
        $registroAnterior['telefonos_contacto'] = is_string($registroAnterior['telefonos_contacto'] ?? null)
            ? $registroAnterior['telefonos_contacto']
            : json_encode($registroAnterior['telefonos_contacto'] ?? [], JSON_UNESCAPED_UNICODE);

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $movimiento         = obtenerMovimientoSesion('editar', $cambios);
        $js_session         = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        // Solo pisamos js_consulta_api si mandaron una consulta nueva, para no perder la anterior
        $actualizarApi = $consultaApiJson !== '';

        $sql = "
            UPDATE proveedor SET
                razon_social       = :razon_social,
                nombre_comercial   = :nombre_comercial,
                telefonos_contacto = :telefonos_contacto::jsonb,
                correo             = :correo,
                ubigeo             = :ubigeo,
                ubicacion          = :ubicacion,
                update_at          = NOW(),
                js_session         = :js_session,
                js_historial       = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb"
                . ($actualizarApi ? ", js_consulta_api = :js_consulta_api::jsonb" : "") . "
            WHERE ruc = :ruc
        ";

        $params = [
            'razon_social'       => $datosNuevos['razon_social'],
            'nombre_comercial'   => $datosNuevos['nombre_comercial'],
            'telefonos_contacto' => $datosNuevos['telefonos_contacto'],
            'correo'             => $datosNuevos['correo'],
            'ubigeo'             => $datosNuevos['ubigeo'],
            'ubicacion'          => $datosNuevos['ubicacion'],
            'js_session'         => $js_session,
            'js_historial'       => $js_historial_nuevo,
            'ruc'                => $ruc,
        ];
        if ($actualizarApi) $params['js_consulta_api'] = $consultaApiJson;

        $filas = executeNonQuery($conectar, $sql, $params);

        if ($filas < 1) {
            responder(false, 'No se pudo actualizar el proveedor (0 filas afectadas).');
        }

        responder(true, 'Proveedor actualizado correctamente.', ['ruc' => $ruc, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarProveedor()
{
    $conectar = conectar_oll_BD();
    $ruc      = trim($_POST['ruc'] ?? '');
    if (empty($ruc)) responder(false, 'RUC/DNI inválido.');

    $existe = executeQuery($conectar, "SELECT ruc, deleted_at FROM proveedor WHERE ruc = :ruc", ['ruc' => $ruc]);
    if (empty($existe)) responder(false, 'Proveedor no encontrado.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Este proveedor ya estaba inactivo.');
    }

    // No permitir desactivar un proveedor con compras registradas (referencia viva)
    $enUso = executeQuery(
        $conectar,
        "SELECT id FROM compra WHERE proveedor_id = :ruc AND deleted_at IS NULL LIMIT 1",
        ['ruc' => $ruc]
    );
    if (!empty($enUso)) {
        responder(false, 'No puedes desactivar este proveedor: tiene compras registradas.');
    }

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Activo',
        'valor_despues' => 'Inactivo',
    ]];

    $movimiento         = obtenerMovimientoSesion('desactivar', $cambios);
    $js_session         = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    $filas = executeNonQuery(
        $conectar,
        "UPDATE proveedor SET
            deleted_at   = NOW(),
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE ruc = :ruc",
        [
            'ruc'          => $ruc,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );

    if ($filas < 1) {
        responder(false, 'No se pudo desactivar el proveedor.');
    }

    responder(true, 'Proveedor desactivado correctamente.');
}

function reactivarProveedor()
{
    $conectar = conectar_oll_BD();
    $ruc      = trim($_POST['ruc'] ?? '');
    if (empty($ruc)) responder(false, 'RUC/DNI inválido.');

    $existe = executeQuery($conectar, "SELECT ruc, deleted_at FROM proveedor WHERE ruc = :ruc", ['ruc' => $ruc]);
    if (empty($existe)) responder(false, 'Proveedor no encontrado.');
    if (empty($existe[0]['deleted_at'])) {
        responder(false, 'Este proveedor ya estaba activo.');
    }

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Inactivo',
        'valor_despues' => 'Activo',
    ]];

    $movimiento         = obtenerMovimientoSesion('reactivar', $cambios);
    $js_session         = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    $filas = executeNonQuery(
        $conectar,
        "UPDATE proveedor SET
            deleted_at   = NULL,
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE ruc = :ruc",
        [
            'ruc'          => $ruc,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );

    if ($filas < 1) {
        responder(false, 'No se pudo reactivar el proveedor.');
    }

    responder(true, 'Proveedor reactivado correctamente.');
}

// =============================================================================
// CONSULTA API (RUC / DNI) — graphperu.daustinn.com
// Se usa para consultar el documento del proveedor (8 u 11 dígitos).
//
// OJO: la API responde con ESTRUCTURAS DISTINTAS según sea persona (DNI) o
// empresa (RUC):
//   - Empresa (RUC, 11 dígitos): documentID, name, state, condition, address,
//     ubigeo, viaType, viaName, zoneType, number, district, province, region.
//   - Persona (DNI, 8 dígitos): documentID, surnames, names, fullName,
//     paternalLastName, maternalLastName.  (NO trae "name", ni dirección).
// Por eso aquí normalizamos siempre un campo "name" y un campo "tipo",
// para que el frontend no tenga que preocuparse por cuál vino.
// =============================================================================

function consultarDocumento($numero)
{
    $numero = preg_replace('/\D/', '', $numero); // solo dígitos

    if (strlen($numero) !== 8 && strlen($numero) !== 11) {
        responder(false, 'El número debe tener 8 dígitos (DNI) u 11 dígitos (RUC).');
    }

    $url = "https://graphperu.daustinn.com/api/query/{$numero}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $respuesta = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error     = curl_error($ch);
    // Nota: desde PHP 8.0 los handles de cURL se liberan automáticamente al salir
    // de scope (son objetos CurlHandle), y curl_close() está deprecado desde 8.5
    // (emite un warning que además rompía el JSON de esta respuesta). Ya no se llama.

    if ($respuesta === false) {
        responder(false, 'No se pudo conectar con el servicio de consulta: ' . $error);
    }
    if ($httpCode !== 200) {
        responder(false, 'El servicio de consulta respondió con error (HTTP ' . $httpCode . ').');
    }

    $datos = json_decode($respuesta, true);
    if (!is_array($datos) || empty($datos['documentID'])) {
        responder(false, 'No se encontraron datos para ese número.');
    }

    // Normalizamos para que el frontend siempre lea "name" y sepa el "tipo".
    if (strlen($numero) === 8) {
        $datos['tipo'] = 'DNI';
        $nombreCompleto = $datos['fullName']
            ?? trim(($datos['names'] ?? '') . ' ' . ($datos['surnames'] ?? ''));
        $datos['name'] = $nombreCompleto !== '' ? $nombreCompleto : null;
    } else {
        $datos['tipo'] = 'RUC';
        // "name", "address", "state", "condition", "ubigeo", etc. ya vienen así de la API.
    }

    // Devolvemos los datos normalizados + el JSON crudo (para guardarlo en js_consulta_api)
    responder(true, 'Consulta realizada correctamente.', [
        'data' => $datos,
        'raw'  => $respuesta,
    ]);
}

// =============================================================================
// HELPER
// =============================================================================

function responder(bool $ok, string $msg, array $extra = []): void
{
    // Descartamos cualquier salida acumulada en el buffer (warnings, deprecations,
    // notices de PHP, etc.) para garantizar que SIEMPRE se emita JSON puro.
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}