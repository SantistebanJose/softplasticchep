(() => {
    // Se captura de inmediato: currentScript solo existe durante la ejecución inicial.
    const scriptActual = document.currentScript && document.currentScript.src ? document.currentScript.src : '';
    const urlTrineo = scriptActual
        ? new URL('../img/trineo-navidad.png' + (new URL(scriptActual).search || ''), scriptActual).href
        : '';

    // El trineo avanza mirando hacia la derecha.
    const VOLTEAR_IMAGEN = false;
    const reducirMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reducirMovimiento) {
        document.documentElement.classList.add('pc-navidad-reducir-movimiento');
    }

    const estilo = document.createElement('style');
    estilo.textContent = `
        .pc-navidad-decoracion{position:fixed;inset:0 0 auto;height:91px;z-index:1040;pointer-events:none;overflow:hidden;}
        .pc-navidad-cuerda{position:absolute;inset:0 0 auto;height:91px;opacity:.9;}
        .pc-navidad-cuerda svg{display:block;width:100%;height:100%;overflow:visible;}
        .pc-navidad-cuerda path{fill:none;stroke:#477354;stroke-width:1.25;vector-effect:non-scaling-stroke;}
        .pc-navidad-luces{position:absolute;inset:0 0 auto;height:64px;}
        .pc-navidad-luz{position:absolute;left:var(--left);top:var(--top);width:8px;height:8px;border-radius:50%;background:var(--luz);box-shadow:0 0 7px 2px color-mix(in srgb,var(--luz),transparent 35%);animation:pcNavidadBrillo 2.4s ease-in-out infinite alternate;animation-delay:var(--delay);}
        .pc-navidad-gancho{position:absolute;top:var(--top);left:var(--left);width:32px;height:48px;transform-origin:50% 0;animation:pcNavidadBalanceo 3.4s ease-in-out infinite;animation-delay:var(--delay);}
        .pc-navidad-gancho svg{display:block;width:32px;height:39px;overflow:visible;filter:drop-shadow(0 2px 1px #17233a22);}
        .pc-navidad-gancho path{fill:none;stroke:var(--color);stroke-width:3.2;stroke-linecap:round;stroke-linejoin:round;}
        .pc-navidad-prenda{position:absolute;left:50%;top:31px;width:13px;height:12px;transform:translateX(-50%);background:var(--prenda);clip-path:polygon(28% 0,42% 11%,58% 11%,72% 0,100% 24%,82% 43%,74% 34%,74% 100%,26% 100%,26% 34%,18% 43%,0 24%);}
        .pc-navidad-nieve{position:fixed;inset:0;z-index:1039;pointer-events:none;overflow:hidden;}
        .pc-navidad-copo{position:absolute;top:-12px;left:var(--left);color:#7db8e8;font-size:var(--size);opacity:var(--opacity);animation:pcNavidadCaer var(--duration) linear infinite;animation-delay:var(--delay);}
        .pc-navidad-trineo{--alto:150px;position:fixed;top:var(--top);right:0;z-index:1041;white-space:nowrap;pointer-events:none;line-height:1;visibility:hidden;}
        .pc-navidad-trineo.volando{visibility:visible;animation:pcNavidadCruce 14s linear forwards;}
        .pc-navidad-trineo img{display:block;height:var(--alto);width:auto;max-width:none;filter:drop-shadow(0 4px 6px #17233a55);animation:pcNavidadFlotar 2.2s ease-in-out infinite alternate;}
        .pc-navidad-trineo.pc-navidad-volteado img{scale:-1 1;}
        .pc-navidad-trineo-emoji{display:block;font-size:34px;filter:drop-shadow(0 3px 5px #17233a44);}
        .pc-navidad-trineo-emoji .pc-navidad-papa{display:inline-block;position:relative;margin-left:-14px;transform:translateY(-13px);font-size:.62em;}
        .pc-navidad-trineo-emoji .pc-navidad-estela{margin-left:8px;color:#f2bf42;font-size:.45em;letter-spacing:3px;}
        @keyframes pcNavidadCruce{from{transform:translateX(calc(-100vw - 100%))}to{transform:translateX(110vw)}}
        @keyframes pcNavidadFlotar{from{transform:translateY(-5px) rotate(-1.2deg)}to{transform:translateY(6px) rotate(1.2deg)}}
        @keyframes pcNavidadBalanceo{0%,100%{rotate:-7deg}50%{rotate:7deg}}
        @keyframes pcNavidadBrillo{from{opacity:.48;filter:saturate(.75)}to{opacity:1;filter:saturate(1.25)}}
        @keyframes pcNavidadCaer{to{transform:translate3d(20px,105vh,0) rotate(180deg)}}
        .pc-navidad-reducir-movimiento .pc-navidad-gancho,.pc-navidad-reducir-movimiento .pc-navidad-luz,.pc-navidad-reducir-movimiento .pc-navidad-copo{animation:none!important}
        @media(max-width:700px){.pc-navidad-decoracion{height:75px}.pc-navidad-cuerda{height:58px}.pc-navidad-luces{height:52px}.pc-navidad-gancho{width:26px;height:42px}.pc-navidad-gancho svg{width:26px;height:34px}.pc-navidad-prenda{top:28px;width:11px;height:10px}.pc-navidad-trineo{--alto:100px}.pc-navidad-trineo-emoji{font-size:27px}}
        @media(prefers-reduced-motion:reduce){.pc-navidad-gancho,.pc-navidad-luz,.pc-navidad-copo,.pc-navidad-trineo{animation:none!important;visibility:hidden!important}.pc-navidad-trineo img{animation:none!important}}
    `;
    document.head.appendChild(estilo);

    const decoracion = document.createElement('div');
    decoracion.className = 'pc-navidad-decoracion';
    decoracion.setAttribute('aria-hidden', 'true');
    const luces = ['#f2bf42','#e85858','#59b978','#55a9e8','#e85858','#f2bf42','#59b978','#55a9e8','#e85858','#59b978','#f2bf42','#55a9e8','#e85858','#59b978','#f2bf42','#55a9e8'];
    const paleta = [
        ['#d83d43','#ef5555'],['#348d57','#56ad68'],['#d69c24','#efc54a'],
        ['#3987b8','#57aade'],['#7e5db5','#a47bd4'],['#d83d43','#ef5555'],
        ['#348d57','#56ad68'],['#d69c24','#efc54a'],['#3987b8','#57aade'],
    ];

    function dibujarDecoracion() {
        const movil = window.innerWidth <= 700;
        const cantidad = movil ? 9 : 17;
        const lucesPorVista = movil ? 9 : 15;
        const separacion = 80 / (cantidad - 1);
        const profundidad = movil ? 28 : 42;
        const curvaY = (porcentaje) => 3 + profundidad * 4 * (porcentaje / 100) * (1 - porcentaje / 100);
        const lucesHtml = Array.from({ length: lucesPorVista }, (_, i) => {
            const left = 2 + i * (82 / (lucesPorVista - 1));
            return `<i class="pc-navidad-luz" style="--left:${left}%;--top:${curvaY(left) - 3}px;--luz:${luces[i % luces.length]};--delay:${(i % 5) * .27}s"></i>`;
        }).join('');
        const ganchosHtml = Array.from({ length: cantidad }, (_, i) => {
            const [color, prenda] = paleta[i % paleta.length];
            const left = 3 + i * separacion;
            const delay = (i % 6) * -.48;
            const top = curvaY(left) - 5;
            return `<span class="pc-navidad-gancho" style="--left:${left}%;--top:${top}px;--color:${color};--prenda:${prenda};--delay:${delay}s"><svg viewBox="0 0 36 44" focusable="false"><path d="M18 8a4 4 0 1 0-4-4M18 8v4L3 26l15 4 15-4-15-14M9 24l9 4 9-4"></path></svg><i class="pc-navidad-prenda"></i></span>`;
        }).join('');
        const coposHtml = Array.from({ length: movil ? 12 : 23 }, (_, i) => `<i class="pc-navidad-copo" style="--left:${(i * 41 + 9) % 100}%;--size:${8 + (i % 4) * 3}px;--opacity:${.18 + (i % 4) * .05};--duration:${13 + (i % 8) * 2}s;--delay:${-(i % 17)}s">${i % 3 === 0 ? '✦' : '•'}</i>`).join('');
        decoracion.innerHTML = `<div class="pc-navidad-cuerda"><svg viewBox="0 0 100 94" preserveAspectRatio="none" focusable="false"><path d="M0 3 Q50 87 100 3"></path></svg></div><div class="pc-navidad-luces">${lucesHtml}</div>${ganchosHtml}`;
        nieve.innerHTML = coposHtml;
    }

    const nieve = document.createElement('div');
    nieve.className = 'pc-navidad-nieve';
    nieve.setAttribute('aria-hidden', 'true');
    document.body.append(decoracion, nieve);

    const trineo = document.createElement('div');
    trineo.className = 'pc-navidad-trineo';
    trineo.setAttribute('aria-hidden', 'true');
    document.body.appendChild(trineo);

    const trineoEmoji = () => {
        trineo.classList.remove('pc-navidad-volteado');
        trineo.innerHTML = '<span class="pc-navidad-trineo-emoji"><span>🦌🦌</span><span>🛷</span><span class="pc-navidad-papa">🎅</span><span class="pc-navidad-estela">✦ ✨ ✦</span></span>';
    };

    const volarTrineo = () => {
        const movil = window.innerWidth <= 700;
        const base = movil ? 50 : 60;
        const rango = movil ? 30 : 60;
        trineo.style.setProperty('--top', `${base + Math.random() * rango}px`);
        trineo.classList.remove('volando');
        void trineo.offsetWidth;
        trineo.classList.add('volando');
    };

    // Arranca una sola vez, en cuanto el trineo está listo (imagen cargada o fallback).
    let iniciado = false;
    const iniciarTrineo = () => {
        if (iniciado || reducirMovimiento) return;
        iniciado = true;
        volarTrineo();
        window.setInterval(volarTrineo, 60000);
    };

    if (!reducirMovimiento) {
        trineo.addEventListener('animationend', (evento) => {
            if (evento.target === trineo) trineo.classList.remove('volando');
        });
    }

    if (urlTrineo) {
        if (VOLTEAR_IMAGEN) trineo.classList.add('pc-navidad-volteado');
        const imagen = new Image();
        imagen.alt = '';
        imagen.decoding = 'async';
        imagen.draggable = false;
        imagen.addEventListener('load', iniciarTrineo, { once: true });
        imagen.addEventListener('error', () => { trineoEmoji(); iniciarTrineo(); }, { once: true });
        imagen.src = urlTrineo;
        trineo.appendChild(imagen);
        iniciarTrineo();
    } else {
        trineoEmoji();
        iniciarTrineo();
    }

    let breakpointMovil = window.innerWidth <= 700;
    window.addEventListener('resize', () => {
        const nuevoBreakpointMovil = window.innerWidth <= 700;
        if (nuevoBreakpointMovil !== breakpointMovil) {
            breakpointMovil = nuevoBreakpointMovil;
            dibujarDecoracion();
        }
    }, { passive: true });
    dibujarDecoracion();
})();
