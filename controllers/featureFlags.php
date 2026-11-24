<?php
/**
 * Interruptores de funcionalidades aún en despliegue gradual.
 * FEATURE_STICKER_QR permite probar la vista e impresión de etiquetas QR.
 */
if (!defined('FEATURE_RUTA_EXTERIOR_PINZA_PALANITA')) {
    define('FEATURE_RUTA_EXTERIOR_PINZA_PALANITA', false);
}

// Activado para probar stickers QR desde producción, ensamblaje y empaquetado.
if (!defined('FEATURE_STICKER_QR')) {
    define('FEATURE_STICKER_QR', true);
}

// Decoración estacional navideña para el panel administrativo y el de operarios.
if (!defined('FEATURE_EFECTOS_NAVIDENOS')) {
    define('FEATURE_EFECTOS_NAVIDENOS', true);
}
