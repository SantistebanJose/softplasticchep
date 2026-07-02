<?php
/**
 * Script de configuración inicial para ejecutar el SQL de creación de tablas.
 * Úsalo solo en entorno local o de desarrollo.
 */

define('SKIP_AUTH', true);
require __DIR__ . '/includes/config.php';

$sql = file_get_contents(__DIR__ . '/sql/create_tables.sql');
if ($sql === false) {
    die('No se pudo leer sql/create_tables.sql');
}

try {
    $pdo->exec($sql);
    echo 'Tablas creadas o verificadas correctamente.';
} catch (PDOException $e) {
    echo 'Error al crear tablas: ' . $e->getMessage();
}
