/**
 * device-tracking.js
 * Genera un UUID persistente por dispositivo/navegador (localStorage) y
 * permite asignarle un nombre legible. Se usa para llenar device_id y
 * device_nombre en cada request hacia los controladores clss*.php.
 */
const DeviceTracking = (() => {
    const KEY_ID     = 'app_device_id';
    const KEY_NOMBRE = 'app_device_nombre';

    function generarUUID() {
        // crypto.randomUUID() existe en navegadores modernos (Chrome 92+,
        // Safari 15.4+). Fallback simple por si acaso.
        if (crypto?.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function getDeviceId() {
        let id = localStorage.getItem(KEY_ID);
        if (!id) {
            id = generarUUID();
            localStorage.setItem(KEY_ID, id);
        }
        return id;
    }

    function getDeviceNombre() {
        return localStorage.getItem(KEY_NOMBRE) || 'N/A';
    }

    function setDeviceNombre(nombre) {
        localStorage.setItem(KEY_NOMBRE, nombre.trim());
    }

    // Pide el nombre una sola vez (ej. al cargar la tablet por primera vez).
    // Llamar esto al iniciar la app si getDeviceNombre() devuelve 'N/A'.
    function pedirNombreSiFalta() {
        if (getDeviceNombre() === 'N/A') {
            const nombre = prompt('Nombre de este dispositivo (ej. "Tablet Producción 1"):');
            if (nombre) setDeviceNombre(nombre);
        }
    }

    return { getDeviceId, getDeviceNombre, setDeviceNombre, pedirNombreSiFalta };
})();