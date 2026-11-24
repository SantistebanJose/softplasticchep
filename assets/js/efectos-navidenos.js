(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
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
        @keyframes pcNavidadBalanceo{0%,100%{rotate:-7deg}50%{rotate:7deg}}
        @keyframes pcNavidadBrillo{from{opacity:.48;filter:saturate(.75)}to{opacity:1;filter:saturate(1.25)}}
        @keyframes pcNavidadCaer{to{transform:translate3d(20px,105vh,0) rotate(180deg)}}
        .pc-navidad-reducir-movimiento .pc-navidad-gancho,.pc-navidad-reducir-movimiento .pc-navidad-luz,.pc-navidad-reducir-movimiento .pc-navidad-copo{animation:none!important}
        @media(max-width:700px){.pc-navidad-decoracion{height:75px}.pc-navidad-cuerda{height:58px}.pc-navidad-luces{height:52px}.pc-navidad-gancho{width:26px;height:42px}.pc-navidad-gancho svg{width:26px;height:34px}.pc-navidad-prenda{top:28px;width:11px;height:10px}}
        @media(prefers-reduced-motion:reduce){.pc-navidad-gancho,.pc-navidad-luz,.pc-navidad-copo{animation:none!important}}
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
