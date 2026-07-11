<?php

/**
 * controllers/ver_comprobante.php
 *
 * Sirve el archivo de comprobante (img_comprobante) de una compra,
 * validando que el usuario tenga sesión activa ANTES de entregar el
 * archivo. Se usa en vez de exponer la ruta física directa
 * (uploads/comprobantes/xxx.jpg) para que el link no se pueda copiar
 * y compartir libremente: sin sesión válida, no se ve nada.
 *
 * Uso: <img src="controllers/ver_comprobante.php?id=123">
 *      <iframe src="controllers/ver_comprobante.php?id=123">
 *
 * Content-Disposition: inline -> el navegador lo muestra embebido
 * (en el <img>/<iframe>) en vez de forzar descarga.
 */

session_start();
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';

// ── Validación de sesión ────────────────────────────────────────────────
// Ajusta esta condición a como manejas login en el resto del sistema
// (viendo clssCompra.php, usan $_SESSION['usuario_id']).
if (empty($_SESSION['usuario_id'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acceso denegado. Debes iniciar sesión para ver este archivo.';
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo 'ID inválido.';
    exit;
}

$conectar = conectar_oll_BD();

// Se permite ver el comprobante de compras activas e inactivas (para
// poder auditar historial); si quieres restringir solo a activas,
// agrega "AND deleted_at IS NULL" al WHERE.
$compra = executeQuery(
    $conectar,
    "SELECT img_comprobante FROM compra WHERE id = :id",
    ['id' => $id]
);

if (empty($compra) || empty($compra[0]['img_comprobante'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Comprobante no encontrado.';
    exit;
}

$rutaRelativa = $compra[0]['img_comprobante'];   // ej: uploads/comprobantes/comprobante_xxx.jpg
$rutaFisica   = __DIR__ . '/../' . $rutaRelativa;

if (!is_file($rutaFisica)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'El archivo ya no existe en el servidor.';
    exit;
}

// ── Content-Type según extensión ────────────────────────────────────────
$extension = strtolower(pathinfo($rutaFisica, PATHINFO_EXTENSION));
$mimes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
];
$mime = $mimes[$extension] ?? 'application/octet-stream';

// Limpia cualquier buffer de salida previo (por si algo hizo echo antes)
if (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($rutaFisica));
// "inline" = se muestra embebido en el navegador, no se descarga
header('Content-Disposition: inline; filename="' . basename($rutaFisica) . '"');
// Evita que el navegador cachee agresivamente un archivo que pudo cambiar
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');

readfile($rutaFisica);
exit;