// Helpers de fecha del negocio: los registros y filtros se muestran en hora local de Lima.
function fechaLocalISO(fecha = new Date()) {
    const pad = n => String(n).padStart(2, '0');
    return `${fecha.getFullYear()}-${pad(fecha.getMonth() + 1)}-${pad(fecha.getDate())}`;
}

function fechaHoraLocalInput(fecha = new Date()) {
    const pad = n => String(n).padStart(2, '0');
    return `${fecha.getFullYear()}-${pad(fecha.getMonth() + 1)}-${pad(fecha.getDate())}T${pad(fecha.getHours())}:${pad(fecha.getMinutes())}`;
}

const solicitudesLlamarEnCurso = new Map();
const ACCIONES_QUE_MODIFICAN_DATOS = /^(GUARDAR|CREAR|REGISTRAR|AGREGAR|EDITAR|ELIMINAR|REACTIVAR|INICIAR|FINALIZAR|ENVIAR|PASAR|COMPLEMENTAR|SELECCIONAR|ACTUALIZAR|CERRAR|ANULAR|APROBAR|RECHAZAR|ASIGNAR|DESASIGNAR)/i;

async function llamar(url, accion, params = {}) {
    const body = new URLSearchParams({
        accion,
        device_id: DeviceTracking.getDeviceId(),
        device_nombre: DeviceTracking.getDeviceNombre(),
        device_modelo: await DeviceTracking.getDeviceModelo(), // NUEVO
        ...params
    });

    const esEscritura = ACCIONES_QUE_MODIFICAN_DATOS.test(String(accion));
    const claveSolicitud = `${url}|${accion}|${body.toString()}`;
    if (solicitudesLlamarEnCurso.has(claveSolicitud)) {
        return solicitudesLlamarEnCurso.get(claveSolicitud);
    }

    const boton = esEscritura && document.activeElement instanceof HTMLButtonElement
        ? document.activeElement : null;
    const botonYaDeshabilitado = boton?.disabled ?? false;
    if (boton && !botonYaDeshabilitado) {
        boton.disabled = true;
        boton.dataset.enviandoSolicitud = '1';
        boton.setAttribute('aria-busy', 'true');
    }

    const solicitud = (async () => {
        try {
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
        } finally {
            solicitudesLlamarEnCurso.delete(claveSolicitud);
            if (boton && boton.dataset.enviandoSolicitud === '1') {
                delete boton.dataset.enviandoSolicitud;
                boton.removeAttribute('aria-busy');
                if (!botonYaDeshabilitado) boton.disabled = false;
            }
        }
    })();

    solicitudesLlamarEnCurso.set(claveSolicitud, solicitud);
    return solicitud;
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
