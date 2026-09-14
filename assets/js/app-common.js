async function llamar(url, accion, params = {}) {
    const body = new URLSearchParams({
        accion,
        device_id: DeviceTracking.getDeviceId(),
        device_nombre: DeviceTracking.getDeviceNombre(),
        device_modelo: await DeviceTracking.getDeviceModelo(), // NUEVO
        ...params
    });

    const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });

    const texto = await resp.text();
    try {
        return JSON.parse(texto);
    } catch (e) {
        console.error(`Respuesta no es JSON válido para accion=${accion}:`, texto);
        throw new Error(`El servidor no devolvió JSON válido (accion=${accion}). Revisa la consola.`);
    }
}

// OJO: ahora es async porque getDeviceModelo() puede ser async la primera
// vez (antes de cachearse). Cualquier llamado existente a esta función
// necesita agregar `await`.
async function prepararFormDataConDevice(formData, accion) {
    formData.append('accion', accion);
    formData.append('device_id', DeviceTracking.getDeviceId());
    formData.append('device_nombre', DeviceTracking.getDeviceNombre());
    formData.append('device_modelo', await DeviceTracking.getDeviceModelo()); // NUEVO
    return formData;
}