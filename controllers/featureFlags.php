<?php
/**
 * Interruptores de funcionalidades aún en despliegue gradual.
 * Cambiar a true para habilitar la ruta de Pinza Palanita al exterior.
 */
if (!defined('FEATURE_RUTA_EXTERIOR_PINZA_PALANITA')) {
    define('FEATURE_RUTA_EXTERIOR_PINZA_PALANITA', false);
}
