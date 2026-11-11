<?php
/**
 * Interruptores de funcionalidades aún en despliegue gradual.
 * FEATURE_STICKER_QR se habilita cuando esté disponible la impresora.
 */
if (!defined('FEATURE_RUTA_EXTERIOR_PINZA_PALANITA')) {
    define('FEATURE_RUTA_EXTERIOR_PINZA_PALANITA', false);
}

// Habilitar cuando esté disponible la impresora de stickers.
if (!defined('FEATURE_STICKER_QR')) {
    define('FEATURE_STICKER_QR', false);
}
