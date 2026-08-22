<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

header('Content-Type: application/json; charset=utf-8');

$dni = trim($_POST['dni'] ?? '');
echo json_encode(intentarLoginOperario($dni));
// Si ya tienes un helper responder() como en el resto del proyecto, cámbialo por ese.