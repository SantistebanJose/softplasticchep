<?php

/**
 * controllers/auditoria.php
 * Helpers de auditoría compartidos por TODOS los controladores clss*.php.
 *
 * Centraliza lo que antes estaba duplicado en cada controlador:
 *   - obtenerIpCliente()        -> IP pública del request (detrás de proxy/Render)
 *   - obtenerUbicacionPorIp()   -> geolocalización APROXIMADA de esa IP (ip-api.com)
 *   - obtenerMovimientoSesion() -> arma el registro de auditoría (usuario, ip,
 *     ubicación, device_id/device_nombre, cambios, timestamp) que se guarda
 *     en js_session/js_historial de cada tabla.
 *
 * Uso en cada controlador clssX.php:
 *   require_once __DIR__ . '/auditoria.php';
 *   ...
 *   $movimiento = obtenerMovimientoSesion('crear', $cambios);
 *
 * IMPORTANTE al integrar: hay que BORRAR las copias sueltas de
 * obtenerIpCliente() y obtenerMovimientoSesion() que ya existen en cada
 * clssX.php. Si no las borras, PHP tira "Cannot redeclare function" y el
 * controlador deja de responder.
 */

function obtenerIpCliente(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return trim($_SERVER['HTTP_X_REAL_IP']);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'N/A';
}

/**
 * Geolocaliza una IP pública (ciudad, región, país, lat/lon) usando la API
 * gratuita de ip-api.com (sin API key, límite ~45 peticiones/min por IP de
 * servidor). Es una ubicación APROXIMADA (normalmente a nivel de ciudad,
 * según dónde el ISP tiene registrada esa IP) — no es GPS ni exacta.
 *
 * No sirve para IPs privadas/locales (127.0.0.1, 192.168.x.x, 10.x.x.x,
 * etc.) — en ese caso regresa null sin llamar a la API. Se cachea en
 * $_SESSION por IP durante la sesión para no gastar el límite de la API
 * pegándole en cada acción (una misma sesión casi siempre viene de la
 * misma IP).
 */
function obtenerUbicacionPorIp(string $ip): ?array
{
    if ($ip === 'N/A' || $ip === '') return null;

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return null; // IP privada/local, no geolocalizable
    }

    if (!isset($_SESSION['_geo_cache'])) $_SESSION['_geo_cache'] = [];
    if (array_key_exists($ip, $_SESSION['_geo_cache'])) return $_SESSION['_geo_cache'][$ip];

    $ubicacion = null;
    try {
        $ch = curl_init("http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,lat,lon");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $respuesta = curl_exec($ch);
        // Sin curl_close() a propósito (Deprecated en PHP 8.5, ya no hace nada desde 8.0)

        if ($respuesta !== false) {
            $data = json_decode($respuesta, true);
            if (($data['status'] ?? '') === 'success') {
                $ubicacion = [
                    'pais'   => $data['country']    ?? null,
                    'region' => $data['regionName'] ?? null,
                    'ciudad' => $data['city']       ?? null,
                    'lat'    => $data['lat']        ?? null,
                    'lon'    => $data['lon']        ?? null,
                ];
            }
        }
    } catch (Throwable $e) {
        error_log("No se pudo geolocalizar IP $ip: " . $e->getMessage());
    }

    $_SESSION['_geo_cache'][$ip] = $ubicacion;
    return $ubicacion;
}

/**
 * Arma el registro de auditoría estándar que cada controlador guarda en
 * js_session (último movimiento) y agrega a js_historial (array acumulado).
 * $accion es libre por módulo (ej. 'crear', 'editar', 'desactivar', 'crear_linea').
 */
function obtenerMovimientoSesion(string $accion, array $cambios = []): array
{
    $ip = obtenerIpCliente();

    $esOperario = !empty($_SESSION['operario_id']);

    return [
        'usuario'        => $esOperario ? $_SESSION['operario_id'] : ($_SESSION['usuario_id'] ?? 'Sistema'),
        'nombre'         => $esOperario ? ($_SESSION['operario_nombre'] ?? 'Operario') : ($_SESSION['nombre_usuario'] ?? 'Usuario Desconocido'),
        'user'           => $esOperario ? 'operario' : ($_SESSION['user_usuario'] ?? 'N/A'),
        'perfiles'       => $_SESSION['perfiles'] ?? 'N/A',
        'rol'            => $esOperario ? 'operario' : ($_SESSION['rol_usuario'] ?? 'N/A'),
        'accion'         => $accion,
        'ip'             => $ip,
        'ubicacion'      => obtenerUbicacionPorIp($ip),
        'device_id'      => trim($_POST['device_id'] ?? '') ?: 'N/A',
        'device_nombre'  => trim($_POST['device_nombre'] ?? '') ?: 'N/A',
        'device_modelo'  => trim($_POST['device_modelo'] ?? '') ?: 'N/A',
        'cambios'        => $cambios,
        'timestamp'      => date('Y-m-d H:i:s'),
    ];
}