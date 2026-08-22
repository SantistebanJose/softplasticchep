<?php
session_start();

if (!empty($_SESSION['operario_id'])) {
    header('Location: panel.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Ingreso operario · Plásticos Chepito</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/loginoperarios.css">
</head>
<body>
<div class="pc-op-shell">
    <div class="pc-op-card">
        <h1>Bienvenido</h1>
        <p class="pc-op-sub">Ingresa tu DNI</p>

        <div class="pc-op-display" id="pcOpDisplay">
            <span class="pc-op-dot"></span><span class="pc-op-dot"></span>
            <span class="pc-op-dot"></span><span class="pc-op-dot"></span>
            <span class="pc-op-dot"></span><span class="pc-op-dot"></span>
            <span class="pc-op-dot"></span><span class="pc-op-dot"></span>
        </div>

        <div class="pc-op-msg" id="pcOpMsg"></div>

        <div class="pc-op-keypad">
            <button type="button" class="pc-op-key" data-key="1">1</button>
            <button type="button" class="pc-op-key" data-key="2">2</button>
            <button type="button" class="pc-op-key" data-key="3">3</button>
            <button type="button" class="pc-op-key" data-key="4">4</button>
            <button type="button" class="pc-op-key" data-key="5">5</button>
            <button type="button" class="pc-op-key" data-key="6">6</button>
            <button type="button" class="pc-op-key" data-key="7">7</button>
            <button type="button" class="pc-op-key" data-key="8">8</button>
            <button type="button" class="pc-op-key" data-key="9">9</button>
            <button type="button" class="pc-op-key pc-op-key-action" id="pcOpBorrar"><i class="fa-solid fa-delete-left"></i></button>
            <button type="button" class="pc-op-key" data-key="0">0</button>
            <button type="button" class="pc-op-key pc-op-key-action" id="pcOpLimpiar"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <button type="button" class="pc-btn pc-btn-primary pc-op-btn" id="pcOpIngresar">
            <i class="fa-solid fa-right-to-bracket"></i> Ingresa
        </button>
    </div>
</div>

<script src="../assets/js/loginoperarios.js"></script>
</body>
</html>