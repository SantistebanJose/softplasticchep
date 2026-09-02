<?php
/**
 * controllers/ajax_actualizar_pin.php
 * Permite que el usuario logueado en el sistema general (admin, gerencia,
 * etc.) cambie su propio PIN desde el perfil. Usa la sesión general
 * ($_SESSION['usuario_id']), NO la sesión de operario de tablet.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';

if (empty($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida. Vuelve a iniciar sesión.']);
    exit;
}

$pin = trim($_POST['pin'] ?? '');

if (!preg_match('/^\d{4}$/', $pin)) {
    echo json_encode(['success' => false, 'message' => 'El PIN debe tener exactamente 4 dígitos.']);
    exit;
}

$conectar = conectar_oll_BD();

executeQuery(
    $conectar,
    "UPDATE usuario SET pin = :pin WHERE id = :id",
    ['pin' => $pin, 'id' => $_SESSION['usuario_id']]
);

echo json_encode(['success' => true, 'message' => 'PIN actualizado correctamente.']);