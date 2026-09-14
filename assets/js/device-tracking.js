const DeviceTracking = (() => {
    const KEY_ID     = 'app_device_id';
    const KEY_NOMBRE = 'app_device_nombre';
    const KEY_MODELO = 'app_device_modelo';

    function generarUUID() {
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

    function pedirNombreSiFalta() {
        if (getDeviceNombre() === 'N/A') {
            const nombre = prompt('Nombre de este dispositivo (ej. "Tablet Producción 1"):');
            if (nombre) setDeviceNombre(nombre);
        }
    }

    // NUEVO: intenta el modelo real vía Client Hints de alta entropía
    // (solo Chrome/Edge en Android da modelo real, ej. "Pixel 7 (Android)");
    // en el resto de navegadores (iOS Safari, Firefox, desktop) cae al
    // user-agent crudo, que al menos da SO/navegador.
    async function detectarModeloDispositivo() {
        if (navigator.userAgentData?.getHighEntropyValues) {
            try {
                const ua = await navigator.userAgentData.getHighEntropyValues(['model', 'platform', 'platformVersion']);
                if (ua.model) return `${ua.model} (${ua.platform})`;
            } catch (e) { /* sigue al fallback */ }
        }
        return navigator.userAgent;
    }

    // Se cachea en localStorage igual que device_id: el modelo de un
    // dispositivo no cambia entre sesiones, así que se detecta una sola vez.
    async function getDeviceModelo() {
        let modelo = localStorage.getItem(KEY_MODELO);
        if (!modelo) {
            modelo = await detectarModeloDispositivo();
            localStorage.setItem(KEY_MODELO, modelo);
        }
        return modelo;
    }

    return { getDeviceId, getDeviceNombre, setDeviceNombre, pedirNombreSiFalta, getDeviceModelo };
})();