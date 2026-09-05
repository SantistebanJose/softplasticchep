<?php

/**
 * controllers/ver_comprobante.php
 *
 * Sirve el archivo de comprobante (img_comprobante) de una compra,
 * validando que el usuario tenga sesión activa ANTES de entregar el
 * archivo. Desde que las imágenes se subieron a Cloudinary,
 * img_comprobante ya guarda una URL absoluta (https://res.cloudinary.com/...)
 * en vez de una ruta local, así que este script valida sesión y
 * redirige a esa URL en vez de leer un archivo de disco.
 *
 * Uso: <img src="controllers/ver_comprobante.php?id=123">
 *      <iframe src="controllers/ver_comprobante.php?id=123">
 *
 * NOTA: a diferencia de la versión con archivo local (que servía el
 * contenido con Content-Disposition: inline sin exponer nunca la ruta
 * real), esta redirige al navegador a la URL pública de Cloudinary.
 * Eso significa que, una vez cargado el iframe/img, la URL de Cloudinary
 * queda visible en la consola/red del navegador y se podría copiar y
 * compartir sin pasar por esta validación de sesión. Si necesitas que
 * el comprobante siga siendo inaccesible sin sesión incluso conociendo
 * esa URL, usa "signed delivery" de Cloudinary (URLs firmadas con
 * expiración) en vez de uploads públicos — dímelo si quieres esa versión.
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

$url = $compra[0]['img_comprobante']; // URL absoluta de Cloudinary

if (ob_get_level() > 0) {
    ob_end_clean();
}

// Redirige al navegador a la URL de Cloudinary. Evita cache del propio
// redirect (no del archivo final, que Cloudinary sirve con su propio CDN).
header('Cache-Control: private, max-age=0, must-revalidate');
header('Location: ' . $url);
exit;