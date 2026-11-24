(function () {
    'use strict';

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);

    function crearQrSvg(texto) {
        const alfabeto = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
        if (texto.length > 47 || [...texto].some(char => !alfabeto.includes(char))) {
            throw new Error('El código SKU no cabe en el formato QR local.');
        }

        const bits = [];
        const agregarBits = (valor, cantidad) => {
            for (let i = cantidad - 1; i >= 0; i--) bits.push((valor >>> i) & 1);
        };
        agregarBits(0b0010, 4); // Modo alfanumérico QR.
        agregarBits(texto.length, 9);
        for (let i = 0; i + 1 < texto.length; i += 2) {
            agregarBits(alfabeto.indexOf(texto[i]) * 45 + alfabeto.indexOf(texto[i + 1]), 11);
        }
        if (texto.length % 2) agregarBits(alfabeto.indexOf(texto[texto.length - 1]), 6);

        const capacidadBits = 34 * 8; // QR versión 2, corrección L.
        for (let i = 0; i < Math.min(4, capacidadBits - bits.length); i++) bits.push(0);
        while (bits.length % 8) bits.push(0);
        const datos = [];
        for (let i = 0; i < bits.length; i += 8) {
            let byte = 0;
            for (let j = 0; j < 8; j++) byte = (byte << 1) | bits[i + j];
            datos.push(byte);
        }
        for (let pad = 0; datos.length < 34; pad++) datos.push(pad % 2 === 0 ? 0xEC : 0x11);

        const multiplicar = (a, b) => {
            let producto = 0;
            for (let i = 0; i < 8; i++) {
                if (b & 1) producto ^= a;
                const alto = a & 0x80;
                a = (a << 1) & 0xFF;
                if (alto) a ^= 0x1D;
                b >>>= 1;
            }
            return producto;
        };
        const generador = [1];
        let exponente = 1;
        for (let i = 0; i < 10; i++) {
            const siguiente = Array(generador.length + 1).fill(0);
            generador.forEach((coef, j) => {
                siguiente[j] ^= coef;
                siguiente[j + 1] ^= multiplicar(coef, exponente);
            });
            generador.splice(0, generador.length, ...siguiente);
            exponente = multiplicar(exponente, 2);
        }
        const mensaje = datos.concat(Array(10).fill(0));
        for (let i = 0; i < datos.length; i++) {
            const coef = mensaje[i];
            if (coef) generador.forEach((g, j) => { mensaje[i + j] ^= multiplicar(g, coef); });
        }
        const codewords = datos.concat(mensaje.slice(datos.length));

        const size = 25;
        const matrix = Array.from({ length: size }, () => Array(size).fill(false));
        const funcion = Array.from({ length: size }, () => Array(size).fill(false));
        const ponerFuncion = (x, y, oscuro) => {
            if (x >= 0 && y >= 0 && x < size && y < size) {
                matrix[y][x] = oscuro;
                funcion[y][x] = true;
            }
        };
        const finder = (cx, cy) => {
            for (let dy = -1; dy <= 7; dy++) for (let dx = -1; dx <= 7; dx++) {
                const distancia = Math.max(Math.abs(dx - 3), Math.abs(dy - 3));
                ponerFuncion(cx + dx, cy + dy, dx >= 0 && dx <= 6 && dy >= 0 && dy <= 6 && (distancia !== 2));
            }
        };
        finder(0, 0);
        finder(size - 7, 0);
        finder(0, size - 7);
        for (let i = 8; i < size - 8; i++) {
            ponerFuncion(i, 6, i % 2 === 0);
            ponerFuncion(6, i, i % 2 === 0);
        }
        for (let dy = -2; dy <= 2; dy++) for (let dx = -2; dx <= 2; dx++) {
            ponerFuncion(18 + dx, 18 + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
        }
        // Reservar los módulos de información de formato.
        for (let i = 0; i <= 5; i++) ponerFuncion(8, i, false);
        ponerFuncion(8, 7, false); ponerFuncion(8, 8, false); ponerFuncion(7, 8, false);
        for (let i = 9; i < 15; i++) ponerFuncion(14 - i, 8, false);
        for (let i = 0; i < 8; i++) ponerFuncion(size - 1 - i, 8, false);
        for (let i = 8; i < 15; i++) ponerFuncion(8, size - 15 + i, false);
        ponerFuncion(8, 17, true); // Módulo oscuro fijo de la versión 2.

        let indiceBit = 0;
        let haciaArriba = true;
        for (let derecha = size - 1; derecha >= 1; derecha -= 2) {
            if (derecha === 6) derecha = 5;
            for (let paso = 0; paso < size; paso++) {
                const y = haciaArriba ? size - 1 - paso : paso;
                for (let lado = 0; lado < 2; lado++) {
                    const x = derecha - lado;
                    if (funcion[y][x]) continue;
                    let bit = indiceBit < codewords.length * 8
                        ? ((codewords[indiceBit >>> 3] >>> (7 - (indiceBit & 7))) & 1)
                        : 0;
                    if ((x + y) % 2 === 0) bit ^= 1; // Máscara QR 0.
                    matrix[y][x] = bit === 1;
                    indiceBit++;
                }
            }
            haciaArriba = !haciaArriba;
        }

        let formato = 0b01000 << 10;
        for (let bit = 14; bit >= 10; bit--) {
            if ((formato >>> bit) & 1) formato ^= 0x537 << (bit - 10);
        }
        formato = ((0b01000 << 10) | formato) ^ 0x5412;
        for (let i = 0; i <= 5; i++) matrix[i][8] = ((formato >>> i) & 1) !== 0;
        matrix[7][8] = ((formato >>> 6) & 1) !== 0;
        matrix[8][8] = ((formato >>> 7) & 1) !== 0;
        matrix[8][7] = ((formato >>> 8) & 1) !== 0;
        for (let i = 9; i < 15; i++) matrix[8][14 - i] = ((formato >>> i) & 1) !== 0;
        for (let i = 0; i < 8; i++) matrix[8][size - 1 - i] = ((formato >>> i) & 1) !== 0;
        for (let i = 8; i < 15; i++) matrix[size - 15 + i][8] = ((formato >>> i) & 1) !== 0;

        let path = '';
        matrix.forEach((fila, y) => fila.forEach((oscuro, x) => {
            if (oscuro) path += `M${x + 4} ${y + 4}h1v1h-1z`;
        }));
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 33 33" shape-rendering="crispEdges" role="img" aria-label="QR SKU"><rect width="33" height="33" fill="#fff"/><path d="${path}" fill="#000"/></svg>`;
    }

    function printSticker(button) {
        const stage = button.dataset.stickerStage;
        const id = Number(button.dataset.stickerId || 0);
        const names = { P: 'PRODUCCIÓN', E: 'ENSAMBLAJE', A: 'EMPAQUETADO' };
        if (!names[stage] || !id) return;

        const rawCode = button.dataset.stickerCode || '';
        const code = button.dataset.stickerPass
            ? rawCode
            : `${stage}-${rawCode}`;
        const cinta = button.dataset.stickerTape || '';
        if (!code) return;
        let qrSvg;
        try {
            qrSvg = crearQrSvg(code);
        } catch (error) {
            window.alert(error.message || 'No se pudo generar el QR del SKU.');
            return;
        }
        const stickerHtml = `<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sticker ${escapeHtml(code)}</title>
<style>
@page{size:70mm 45mm;margin:2mm}*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;color:#111}
.label{width:66mm;height:41mm;display:flex;flex-direction:column;justify-content:space-between;border:1px solid #111;padding:2.5mm}
.stage{font-size:8pt;font-weight:700;letter-spacing:.12em}.code{font-size:12pt;font-weight:800;line-height:1.1;overflow-wrap:anywhere}
.qr{display:block;width:19mm;height:19mm;margin:0 auto}.tape{font-size:8pt;font-family:monospace}
.caption{font-size:6.5pt;text-transform:uppercase;color:#444}@media screen{body{padding:12px;background:#eee}.label{background:#fff;margin:auto}}
</style></head><body><main class="label"><div class="stage">${names[stage]} · #${id}</div><div class="code">${escapeHtml(code)}</div>
${qrSvg.replace('<svg ', '<svg class="qr" width="160" height="160" ')}<div><div class="caption">Código para cinta</div><div class="tape">${escapeHtml(cinta)}</div></div></main></body></html>`;
        const archivoSticker = new Blob([stickerHtml], { type: 'text/html;charset=utf-8' });
        const urlSticker = URL.createObjectURL(archivoSticker);
        const popup = window.open(urlSticker, '_blank', 'width=520,height=420');
        if (!popup) {
            URL.revokeObjectURL(urlSticker);
            window.alert('Permite las ventanas emergentes para imprimir el sticker.');
            return;
        }
        let impresionIniciada = false;
        const imprimirCuandoEsteListo = () => {
            if (impresionIniciada || popup.closed) return;
            impresionIniciada = true;
            popup.focus();
            popup.print();
        };
        popup.addEventListener('load', () => window.setTimeout(imprimirCuandoEsteListo, 350), { once: true });
        // Fallback para navegadores que no notifican el load del popup Blob.
        window.setTimeout(imprimirCuandoEsteListo, 1500);
        window.setTimeout(() => URL.revokeObjectURL(urlSticker), 30000);
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-sticker-stage]');
        if (button) printSticker(button);
    });
})();
