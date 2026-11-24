(() => {
    const capa = document.createElement('div');
    capa.className = 'pc-login-pinzas-navidenas';
    capa.setAttribute('aria-hidden', 'true');

    const estilo = document.createElement('style');
    estilo.textContent = `
        .pc-login-pinzas-navidenas{position:fixed;inset:0;z-index:5;overflow:hidden;pointer-events:none}
        .pc-login-pinza{position:absolute;top:-64px;left:var(--x);width:var(--size);height:calc(var(--size)*1.55);opacity:.72;animation:pcLoginPinzaCaer var(--duracion) linear var(--delay) infinite}
        .pc-login-pinza svg{display:block;width:100%;height:100%;overflow:visible;filter:drop-shadow(0 2px 2px #13213b22)}
        .pc-login-pinza .cuerpo{fill:var(--color);stroke:#26334a;stroke-width:1.6;stroke-linejoin:round}
        .pc-login-pinza .detalle{fill:none;stroke:#26334a;stroke-width:1.6;stroke-linecap:round}
        .pc-login-pinza .union{fill:#f6c94a;stroke:#26334a;stroke-width:1.3}
        @keyframes pcLoginPinzaCaer{0%{transform:translate3d(0,-8vh,0) rotate(var(--giro-inicial));}45%{transform:translate3d(var(--deriva),52vh,0) rotate(calc(var(--giro-inicial) + 150deg));}100%{transform:translate3d(calc(var(--deriva)*-0.7),112vh,0) rotate(calc(var(--giro-inicial) + 360deg));}}
        @media(prefers-reduced-motion:reduce){.pc-login-pinza{animation:none;top:var(--reposo);opacity:.2}}
    `;
    document.head.appendChild(estilo);

    const colores = ['#ef5960', '#49a96c', '#f0bd3d', '#50a7df', '#9a72c8', '#f47b45'];
    const cantidad = window.innerWidth < 600 ? 14 : 24;
    capa.innerHTML = Array.from({ length: cantidad }, (_, i) => {
        const x = (i * 67 + 11) % 100;
        const size = 17 + ((i * 7) % 13);
        const duracion = 8 + ((i * 5) % 9);
        const delay = -((i * 3) % 17);
        const giro = ((i * 47) % 80) - 40;
        const deriva = `${((i * 19) % 70) - 35}px`;
        const color = colores[i % colores.length];
        return `<span class="pc-login-pinza" style="--x:${x}%;--size:${size}px;--duracion:${duracion}s;--delay:${delay}s;--giro-inicial:${giro}deg;--deriva:${deriva};--color:${color};--reposo:${(i * 13) % 90}vh"><svg viewBox="0 0 36 56" focusable="false"><path class="cuerpo" d="M18 4c-6 0-9 5-7 10l3 7-7 25c-1 4 1 7 5 7l6-20 6 20c4 0 6-3 5-7l-7-25 3-7c2-5-1-10-7-10Z"/><path class="detalle" d="M12 17h12M10 28h16"/><circle class="union" cx="18" cy="25" r="3.2"/></svg></span>`;
    }).join('');
    document.body.appendChild(capa);
})();
