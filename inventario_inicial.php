<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
if (strtoupper(trim((string)($_SESSION['rol_usuario'] ?? ''))) !== 'ADMINISTRADOR') {
    http_response_code(403);
    exit('No tienes permiso para registrar inventario inicial.');
}
// El saldo maestro ahora vive en producto.stock; este formulario antiguo
// creaba empaquetados ficticios y ya no debe seguir registrando movimientos.
header('Location: stock.php');
exit;
