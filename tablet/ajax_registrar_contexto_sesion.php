<?php
/** Registra el contexto del dispositivo cuando el operario acaba de ingresar. */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['operario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Inicia sesión para registrar el dispositivo.']);
    exit;
}

require_once __DIR__ . '/../controllers/bd.php';
require_once __DIR__ . '/../controllers/executeQuery.php';
require_once __DIR__ . '/../controllers/auditoria.php';

function limpiarDatoDispositivo($valor, int $maximo): string
{
    if (!is_string($valor) && !is_numeric($valor)) return 'N/A';
    $valor = trim((string)$valor);
    $valor = preg_replace('/[\x00-\x1F\x7F]/u', '', $valor) ?? '';
    return $valor === '' ? 'N/A' : mb_substr($valor, 0, $maximo, 'UTF-8');
}

$latitud = $_POST['gps_lat'] ?? '';
$longitud = $_POST['gps_lon'] ?? '';
$precision = $_POST['gps_precision_m'] ?? '';
$gpsValido = is_numeric($latitud) && is_numeric($longitud)
    && (float)$latitud >= -90 && (float)$latitud <= 90
    && (float)$longitud >= -180 && (float)$longitud <= 180;
$gps = $gpsValido ? [
    'lat' => round((float)$latitud, 7),
    'lon' => round((float)$longitud, 7),
    'precision_m' => is_numeric($precision) && (float)$precision >= 0
        ? round(min((float)$precision, 1000000), 1)
        : null,
    'fuente' => 'GPS del navegador',
] : null;

$_POST['device_id'] = limpiarDatoDispositivo($_POST['device_id'] ?? '', 128);
$_POST['device_nombre'] = limpiarDatoDispositivo($_POST['device_nombre'] ?? '', 100);
$_POST['device_modelo'] = limpiarDatoDispositivo($_POST['device_modelo'] ?? '', 255);

try {
    $evento = obtenerMovimientoSesion('inicio_sesion_tablet');
    $evento['ubicacion_gps'] = $gps;
    $evento['gps_autorizado'] = $gps !== null;
    $jsonEvento = json_encode($evento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $pdo = conectar_oll_BD();
    $actualizadas = executeNonQuery($pdo, "
        UPDATE usuario
        SET json_historial = CASE
                WHEN json_historial IS NULL THEN '[]'::jsonb
                WHEN jsonb_typeof(json_historial) = 'array' THEN json_historial
                ELSE jsonb_build_array(json_historial)
            END || jsonb_build_array(CAST(:evento_historial AS jsonb)),
            js_ultima_modificacion = CAST(:evento_ultimo AS jsonb)
        WHERE operario_id = :operario_id
    ", [
        'evento_historial' => $jsonEvento,
        'evento_ultimo' => $jsonEvento,
        'operario_id' => (int)$_SESSION['operario_id'],
    ]);

    if ($actualizadas < 1) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No se encontró la cuenta vinculada al operario.']);
        exit;
    }

    $_SESSION['operario_dispositivo'] = [
        'device_id' => $_POST['device_id'],
        'device_nombre' => $_POST['device_nombre'],
        'device_modelo' => $_POST['device_modelo'],
        'ubicacion_gps' => $gps,
    ];

    echo json_encode(['success' => true, 'gps_guardado' => $gps !== null]);
} catch (Throwable $error) {
    error_log('No se pudo registrar el contexto de inicio de sesión del operario: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo guardar el registro de inicio de sesión.']);
}
