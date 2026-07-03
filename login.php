<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/controllers/clssAuth.php';

if (!empty($_SESSION['usuario_id'])) {
    redirect('index.php');
}

$error = '';
$user_ = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_    = trim($_POST['user_'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $result = intentarLogin($pdo, $user_, $password);

    if ($result['success']) {
        redirect('index.php');
    } else {
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · Plásticos Chepito</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
<div class="pc-login-shell">
    <div class="pc-login-card">
        <img src="assets/img/logo.png" alt="Plásticos Chepito">
        <h1>Iniciar sesión</h1>
        <p class="sub">Módulo de producción — Plásticos Chepito S.A.C.</p>

        <?php if ($error): ?>
            <div class="pc-login-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" autocomplete="off">
            <div class="pc-field">
                <label for="user_">Usuario</label>
                <input class="pc-input" type="text" id="user_" name="user_" value="<?= htmlspecialchars($user_) ?>" placeholder="usuario" required autofocus>
            </div>
            <div class="pc-field">
                <label for="password">Contraseña</label>
                <input class="pc-input" type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="pc-btn pc-btn-primary pc-login-btn">
                <i class="fa-solid fa-right-to-bracket"></i> Ingresar
            </button>
        </form>
    </div>
</div>
</body>
</html>