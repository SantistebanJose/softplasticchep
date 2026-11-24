(function () {
    'use strict';

    let stream = null;
    let timer = null;
    let detector = null;
    let jsQrReady = null;
    let onRead = null;
    let reading = false;

    function cargarJsQr() {
        if (typeof window.jsQR === 'function') return Promise.resolve();
        if (jsQrReady) return jsQrReady;
        jsQrReady = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
            script.async = true;
            script.onload = () => typeof window.jsQR === 'function'
                ? resolve()
                : reject(new Error('No se pudo iniciar el lector QR.'));
            script.onerror = () => reject(new Error('No se pudo cargar el lector QR.'));
            document.head.appendChild(script);
        });
        return jsQrReady;
    }

    function cerrar() {
        reading = false;
        if (timer) clearTimeout(timer);
        timer = null;
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null;
        document.getElementById('pcQrCameraModal')?.remove();
    }

    async function abrirLectorQR(callback) {
        onRead = callback;
        if (!navigator.mediaDevices?.getUserMedia) {
            const manual = window.prompt('Este navegador no permite leer QR con cámara. Escribe el código del sticker:');
            callback(manual ? manual.trim() : null);
            return;
        }
        try {
            detector = null;
            if ('BarcodeDetector' in window) {
                try { detector = new BarcodeDetector({ formats: ['qr_code'] }); } catch (_) { detector = null; }
            }
            if (!detector) await cargarJsQr();
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            const modal = document.createElement('div');
            modal.id = 'pcQrCameraModal';
            modal.innerHTML = '<div class="pc-qr-camera-dialog" role="dialog" aria-modal="true" aria-label="Escanear código QR"><button type="button" class="pc-qr-camera-close" aria-label="Cerrar">×</button><h3>Escanea el sticker QR</h3><p>Centra el código dentro del recuadro.</p><div class="pc-qr-camera-frame"><video playsinline muted></video></div><div class="pc-qr-camera-status" aria-live="polite">Buscando código…</div><form class="pc-qr-manual-form"><input autocomplete="off" placeholder="O escribe el código" aria-label="Código QR"><button type="submit">Usar código</button></form></div>';
            document.body.appendChild(modal);
            modal.querySelector('.pc-qr-camera-close').addEventListener('click', cerrar);
            modal.addEventListener('click', event => { if (event.target === modal) cerrar(); });
            modal.querySelector('.pc-qr-manual-form').addEventListener('submit', event => {
                event.preventDefault();
                const value = modal.querySelector('input').value.trim();
                if (value) { cerrar(); callback(value); }
            });
            const video = modal.querySelector('video');
            video.srcObject = stream;
            await video.play();
            reading = true;
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d', { willReadFrequently: true });
            const buscar = async () => {
                if (!reading) return;
                try {
                    let value = null;
                    if (detector) {
                        const codes = await detector.detect(video);
                        value = codes.length ? codes[0].rawValue : null;
                    } else if (video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA && video.videoWidth) {
                        const scale = Math.min(1, 800 / video.videoWidth);
                        canvas.width = Math.round(video.videoWidth * scale);
                        canvas.height = Math.round(video.videoHeight * scale);
                        context.drawImage(video, 0, 0, canvas.width, canvas.height);
                        const frame = context.getImageData(0, 0, canvas.width, canvas.height);
                        value = window.jsQR(frame.data, frame.width, frame.height, { inversionAttempts: 'dontInvert' })?.data;
                    }
                    if (value) {
                        value = value.trim();
                        cerrar();
                        onRead?.(value);
                        return;
                    }
                } catch (_) { /* Sigue leyendo el siguiente fotograma. */ }
                timer = setTimeout(buscar, 180);
            };
            buscar();
        } catch (error) {
            cerrar();
            callback(null);
            window.alert(error?.name === 'NotAllowedError'
                ? 'Permite el acceso a la cámara para escanear el sticker.'
                : error?.message?.includes('lector QR')
                    ? 'No se pudo cargar el lector QR. Conéctate a internet e inténtalo de nuevo.'
                    : 'No se pudo abrir la cámara. Revisa los permisos del navegador y vuelve a intentar.');
        }
    }

    const style = document.createElement('style');
    style.textContent = '#pcQrCameraModal{position:fixed;inset:0;z-index:20000;background:#0f172acc;display:grid;place-items:center;padding:16px}.pc-qr-camera-dialog{position:relative;background:#fff;color:#172033;border-radius:18px;padding:22px;width:min(480px,100%);box-shadow:0 20px 60px #0005}.pc-qr-camera-dialog h3{font-size:1.2rem;margin:0 36px 4px 0}.pc-qr-camera-dialog p{color:#64748b;margin:0 0 16px}.pc-qr-camera-close{position:absolute;right:14px;top:10px;border:0;background:transparent;font-size:30px;color:#64748b}.pc-qr-camera-frame{overflow:hidden;border-radius:14px;background:#0b1220;aspect-ratio:4/3;display:grid;place-items:center}.pc-qr-camera-frame video{width:100%;height:100%;object-fit:cover}.pc-qr-camera-status{text-align:center;padding:12px;color:#475569}.pc-qr-manual-form{display:flex;gap:8px}.pc-qr-manual-form input{min-width:0;flex:1;border:1px solid #cbd5e1;border-radius:9px;padding:10px}.pc-qr-manual-form button{border:0;border-radius:9px;padding:10px 14px;background:#155eef;color:#fff;font-weight:700}';
    document.head.appendChild(style);
    window.abrirLectorQR = abrirLectorQR;
})();
