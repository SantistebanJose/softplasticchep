<?php
/**
 * ajax_verificar_pin_operario.php
 * PASO 2 del login de operario: recibe DNI + PIN, crea la sesión si coincide.
 * Ajusta el require_once a la ruta real de tu proyecto si difiere.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

echo json_encode(verificarPinOperario($_POST['dni'] ?? '', $_POST['pin'] ?? ''));