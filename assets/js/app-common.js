/**
 * app-common.js
 * Funciones compartidas por TODAS las páginas del panel (compras.php,
 * materiales.php, proveedores.php, etc). Reemplaza los `llamar()` locales
 * que cada página tenía copiados y pegados por su cuenta — la idea es que
 * de aquí en adelante exista un solo lugar que arreglar, en vez de uno por
 * página cada vez que se agrega un módulo nuevo.
 *
 * IMPORTANTE: device-tracking.js debe cargarse ANTES que este archivo.
 *
 * Uso en cada página (reemplaza el <script> inline que definía llamar()):
 *   <script src="js/device-tracking.js"></script>
 *   <script src="js/app-common.js"></script>
 *   <script>
 *     const CONTROLADOR_COMPRAS = 'controllers/clssCompra.php';
 *     const llamarCompras = (accion, params = {}) => llamar(CONTROLADOR_COMPRAS, accion, params);
 *     ...
 *   </script>
 */

/**
 * Llamada genérica a los controladores clss*.php vía x-www-form-urlencoded.
 * Agrega automáticamente device_id y device_nombre a TODA petición, para
 * que ninguna acción que dispare auditoría (crear, editar, desactivar,
 * reactivar, etc.) se quede sin esos datos por un olvido en el frontend.
 *
 * Si params trae explícitamente device_id/device_nombre, esos valores
 * ganan (poco probable, pero deja la puerta abierta por si algún día hace
 * falta forzar un valor puntual).
 */
async function llamar(url, accion, params = {}) {
    const body = new URLSearchParams({
        accion,
        device_id: DeviceTracking.getDeviceId(),
        device_nombre: DeviceTracking.getDeviceNombre(),
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

/**
 * Para acciones que suben archivos (comprobantes, fotos, etc.) y por eso
 * arman su propio FormData en vez de pasar por llamar(). Agrega accion,
 * device_id y device_nombre al FormData que ya armó la página, para no
 * tener que repetir esas líneas sueltas en cada formulario nuevo.
 *
 * Uso:
 *   const formData = new FormData();
 *   formData.append('id', compraIdActual);
 *   formData.append('proveedor_id', ...);
 *   ...
 *   prepararFormDataConDevice(formData, 'GUARDARCOMPRA');
 *   const resp = await fetch(CONTROLADOR_COMPRAS, { method: 'POST', body: formData });
 */
function prepararFormDataConDevice(formData, accion) {
    formData.append('accion', accion);
    formData.append('device_id', DeviceTracking.getDeviceId());
    formData.append('device_nombre', DeviceTracking.getDeviceNombre());
    return formData;
}
