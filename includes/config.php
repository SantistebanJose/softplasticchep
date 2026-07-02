<?php
/**
 * Plásticos Chepito — Configuración de conexión a base de datos
 * Ajusta estos valores según tu entorno local (Apache/PostgreSQL en Windows).
 */

session_start();

define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'chepito_plastic');
define('DB_USER', 'postgres');
define('DB_PASS', '76008509');

try {
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// Nombre de la página activa, usado por sidebar.php para resaltar el menú.
// Cada página debe definir $activePage ANTES de incluir header.php, ej:
// $activePage = 'productos';
if (!isset($activePage)) {
    $activePage = 'dashboard';
}
