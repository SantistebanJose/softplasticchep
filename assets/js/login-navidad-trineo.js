(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const estilo = document.createElement('style');
    estilo.textContent = `
        .pc-login-trineo{position:fixed;left:0;top:0;z-index:6;visibility:hidden;pointer-events:none;line-height:0;will-change:left,top,transform}
        .pc-login-trineo.volando{visibility:visible}
        .pc-login-trineo img{display:block;width:auto;height:138px;max-width:none;filter:drop-shadow(0 4px 6px #17233a55);animation:pcLoginTrineoFlotar 1.8s ease-in-out infinite alternate}
        .pc-login-trineo-fallback{display:block;font-size:clamp(48px,8vw,78px);line-height:1;white-space:nowrap;filter:drop-shadow(0 4px 6px #17233a55)}
        @keyframes pcLoginTrineoFlotar{from{translate:0 -5px}to{translate:0 5px}}
        @media(max-width:700px){.pc-login-trineo img{height:92px}}
        @media(prefers-reduced-motion:reduce){.pc-login-trineo{display:none!important}.pc-login-trineo img{animation:none!important}}
    `;
    document.head.appendChild(estilo);

    const trineo = document.createElement('div');
    trineo.className = 'pc-login-trineo';
    trineo.setAttribute('aria-hidden', 'true');

    const imagen = new Image();
    imagen.alt = '';
    imagen.decoding = 'async';
    imagen.draggable = false;
    imagen.src = '../assets/img/trineo-navidad.png?v=20261125';
    trineo.appendChild(imagen);
    document.body.appendChild(trineo);

    const obtenerPuntoCurvo = (p0, p1, p2, p3, t) => {
        const t2 = t * t;
        const t3 = t2 * t;
        const valor = eje => 0.5 * (
            2 * p1[eje]
            + (-p0[eje] + p2[eje]) * t
            + (2 * p0[eje] - 5 * p1[eje] + 4 * p2[eje] - p3[eje]) * t2
            + (-p0[eje] + 3 * p1[eje] - 3 * p2[eje] + p3[eje]) * t3
        );
        const derivada = eje => 0.5 * (
            (-p0[eje] + p2[eje])
            + 2 * (2 * p0[eje] - 5 * p1[eje] + 4 * p2[eje] - p3[eje]) * t
            + 3 * (-p0[eje] + 3 * p1[eje] - 3 * p2[eje] + p3[eje]) * t2
        );
        return { x: valor('x'), y: valor('y'), dx: derivada('x'), dy: derivada('y') };
    };

    function crearRuta() {
        const anchoPantalla = window.innerWidth;
        const altoPantalla = window.innerHeight;
        const anchoTrineo = trineo.getBoundingClientRect().width || 170;
        const variacion = () => (Math.random() - 0.5) * altoPantalla * 0.08;
        return [
            { x: -anchoTrineo, y: altoPantalla * 0.34 },
            { x: anchoPantalla * 0.13, y: altoPantalla * 0.18 + variacion() },
            { x: anchoPantalla * 0.30, y: altoPantalla * 0.48 + variacion() },
            { x: anchoPantalla * 0.48, y: altoPantalla * 0.76 + variacion() },
            { x: anchoPantalla * 0.66, y: altoPantalla * 0.43 + variacion() },
            { x: anchoPantalla * 0.84, y: altoPantalla * 0.18 + variacion() },
            { x: anchoPantalla + anchoTrineo, y: altoPantalla * 0.38 }
        ];
    }

    let vueloActual = null;
    function iniciarVuelo() {
        if (vueloActual) vueloActual.cancel();
        const ruta = crearRuta();
        const segmentos = ruta.length - 1;
        const muestrasPorSegmento = 16;
        const fotogramas = [];

        for (let segmento = 0; segmento < segmentos; segmento++) {
            for (let muestra = 0; muestra < muestrasPorSegmento; muestra++) {
                const t = muestra / muestrasPorSegmento;
                const punto = obtenerPuntoCurvo(
                    ruta[Math.max(0, segmento - 1)],
                    ruta[segmento],
                    ruta[segmento + 1],
                    ruta[Math.min(ruta.length - 1, segmento + 2)],
                    t
                );
                const inclinacion = Math.max(-20, Math.min(20, Math.atan2(punto.dy, punto.dx) * 180 / Math.PI));
                fotogramas.push({
                    left: `${punto.x}px`,
                    top: `${punto.y}px`,
                    transform: `translate(-50%, -50%) rotate(${inclinacion}deg)`,
                    offset: (segmento + t) / segmentos,
                    easing: 'linear'
                });
            }
        }
        const ultimo = ruta[ruta.length - 1];
        fotogramas.push({
            left: `${ultimo.x}px`,
            top: `${ultimo.y}px`,
            transform: 'translate(-50%, -50%)',
            offset: 1,
            easing: 'linear'
        });

        trineo.classList.add('volando');
        vueloActual = trineo.animate(fotogramas, { duration: 17000, easing: 'linear', fill: 'forwards' });
        vueloActual.onfinish = () => {
            trineo.classList.remove('volando');
            window.setTimeout(iniciarVuelo, 1200);
        };
    }

    imagen.addEventListener('error', () => {
        trineo.innerHTML = '<span class="pc-login-trineo-fallback">🦌🦌🛷🎅✨</span>';
    }, { once: true });

    // Empieza al cargar el login; el navegador ya precarga el PNG desde el <head>.
    iniciarVuelo();
})();
