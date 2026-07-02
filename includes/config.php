<?php
/**
 * Plásticos Chepito — Configuración de conexión a base de datos
 */

session_start();

define('DB_HOST', 'bi.back-mrsoft.com');
define('DB_PORT', '5432');
define('DB_NAME', 'bdplasticche');
define('DB_USER', 'usrweb');
define('DB_PASS', 'admin-Captaian*1278871/&%561652');

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

if (!isset($activePage)) {
    $activePage = 'dashboard';
}