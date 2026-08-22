<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

if (empty($_SESSION['operario_id'])) {
    header('Location: loginoperarios.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel operario · Plásticos Chepito</title>
</head>
<body>
    <p>Hola, <?= htmlspecialchars($_SESSION['operario_nombre']) ?>. Aquí van las pantallas de la tablet.</p>
</body>
</html>