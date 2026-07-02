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

$projectDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']);
$baseUrl = '/';
if ($projectDir !== false && strpos($projectDir, $documentRoot) === 0) {
    $baseUrl = '/' . trim(str_replace($documentRoot, '', $projectDir), '/');
    if ($baseUrl === '') {
        $baseUrl = '/';
    }
}

function appUrl(string $path = ''): string
{
    global $baseUrl;
    $path = ltrim($path, '/');
    if ($baseUrl === '/') {
        return $path === '' ? '/' : '/' . $path;
    }
    return $path === '' ? $baseUrl : $baseUrl . '/' . $path;
}

function redirect(string $path): void
{
    header('Location: ' . appUrl($path));
    exit;
}

if (!defined('SKIP_AUTH') && !in_array(basename($_SERVER['SCRIPT_NAME']), ['login.php', 'setup.php'], true)) {
    if (empty($_SESSION['usuario_id'])) {
        redirect('login.php');
    }
}

if (!isset($activePage)) {
    $activePage = 'dashboard';
}