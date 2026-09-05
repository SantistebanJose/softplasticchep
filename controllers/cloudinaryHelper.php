<?php
/**
 * Helper compartido para subir/borrar imágenes en Cloudinary.
 * Usado por clssProductos.php y clssMoldes.php.
 * Requiere las variables de entorno CLOUDINARY_* configuradas en Render
 * (Environment) o en tu .env local.
 */

define('CLOUDINARY_CLOUD_NAME',    getenv('CLOUDINARY_CLOUD_NAME')    ?: '');
define('CLOUDINARY_UPLOAD_PRESET', getenv('CLOUDINARY_UPLOAD_PRESET') ?: '');
define('CLOUDINARY_API_KEY',       getenv('CLOUDINARY_API_KEY')       ?: '');
define('CLOUDINARY_API_SECRET',    getenv('CLOUDINARY_API_SECRET')    ?: '');

/**
 * Sube un archivo temporal a Cloudinary usando el upload preset "unsigned".
 * $carpeta agrupa las imágenes dentro de Cloudinary (ej. "productos", "moldes").
 * Devuelve ['url' => secure_url, 'public_id' => ...] o corta con responder() si falla.
 */
function subirImagenACloudinary(string $rutaTmp, string $nombreOriginal, string $carpeta): array
{
    if (empty(CLOUDINARY_CLOUD_NAME) || empty(CLOUDINARY_UPLOAD_PRESET)) {
        responder(false, 'Cloudinary no está configurado (faltan variables de entorno).');
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD_NAME . "/image/upload",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'file'          => new CURLFile($rutaTmp, mime_content_type($rutaTmp), $nombreOriginal),
            'upload_preset' => CLOUDINARY_UPLOAD_PRESET,
            'folder'        => $carpeta,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $respuesta = curl_exec($ch);
    $error     = curl_error($ch);
    curl_close($ch);

    if ($error) {
        responder(false, 'Error de conexión al subir la imagen: ' . $error);
    }

    $data = json_decode($respuesta, true);
    if (empty($data['secure_url'])) {
        responder(false, 'No se pudo subir la imagen: ' . ($data['error']['message'] ?? 'error desconocido en Cloudinary'));
    }

    return ['url' => $data['secure_url'], 'public_id' => $data['public_id']];
}

/**
 * Borra una imagen de Cloudinary a partir de su URL (secure_url guardada en BD).
 * Requiere API Key/Secret (llamada firmada) — a diferencia de subir, borrar
 * NO se puede hacer con el preset unsigned por seguridad.
 * Si no se puede extraer el public_id, no hace nada (mejor dejar la imagen
 * huérfana en Cloudinary que romper el flujo de guardado).
 */
function borrarImagenCloudinary(?string $url): void
{
    if (empty($url)) return;
    if (empty(CLOUDINARY_API_KEY) || empty(CLOUDINARY_API_SECRET)) return;

    if (!preg_match('#/upload/(?:v\d+/)?(.+)\.[a-zA-Z0-9]+$#', $url, $m)) {
        return;
    }
    $publicId = $m[1];

    $timestamp = time();
    $firma = sha1("public_id={$publicId}&timestamp={$timestamp}" . CLOUDINARY_API_SECRET);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD_NAME . "/image/destroy",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
            'api_key'   => CLOUDINARY_API_KEY,
            'signature' => $firma,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    curl_exec($ch);
    curl_close($ch);
}