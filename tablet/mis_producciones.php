<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

if (empty($_SESSION['operario_id'])) {
    header('Location: loginoperarios.php');
    exit;
}

$nombreOperario = $_SESSION['operario_nombre'] ?? 'Operario';
$primerNombre   = trim(explode(' ', $nombreOperario)[0]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Mis producciones · Plásticos Chepito</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/panel_operario.css">
    <style>
/* ============================================================
   Mis producciones — mismo patrón que perfil_usuario.php:
   el .pc-op-panel-shell se acota con max-width + margin:0 auto
   para que el header y el contenido compartan SIEMPRE el mismo
   ancho centrado (evita el "hueco" descuadrado a los costados).
   Un poco más ancho que perfil (980 vs 720) porque esta página
   tiene tarjetas en columnas en vez de solo filas.
   ============================================================ */
.pc-op-panel-shell { max-width: 980px; margin: 0 auto; }

.pc-op-panel-shell .mp-back {
    display: inline-flex; align-items: center; gap: 8px;
    color: var(--pc-navy); font-weight: 600; text-decoration: none;
    margin: 6px 0 18px; font-size: 14px;
}
.pc-op-panel-shell .mp-back i { font-size: 12px; }

.mp-title-row {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 22px; flex-wrap: wrap;
}
.mp-title-icon {
    width: 48px; height: 48px; border-radius: var(--pc-radius);
    background: linear-gradient(135deg, #0EA5A5, #096e6e);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
    box-shadow: 0 6px 14px rgba(14, 165, 165, 0.3);
}
.mp-title {
    font-family: 'Poppins', sans-serif; font-weight: 700;
    font-size: 24px; color: var(--pc-navy); margin: 0; line-height: 1.2;
}
.mp-periodo-tag { color: var(--pc-muted); font-size: 14px; min-height: 18px; }

/* ---- Filtros: segmented control ---- */
.mp-filtros {
    display: flex; gap: 6px;
    background: var(--pc-surface); border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius); padding: 6px; margin-bottom: 18px;
    box-shadow: 0 6px 20px rgba(12,28,51,0.05);
}
.mp-filtro-btn {
    flex: 1; border: none; background: transparent; color: var(--pc-muted);
    border-radius: 9px; padding: 12px 8px; font-weight: 600; font-size: 14px;
    font-family: inherit; display: flex; align-items: center; justify-content: center;
    gap: 7px; transition: all .15s ease;
}
.mp-filtro-btn.active {
    background: linear-gradient(135deg, #2f6fed, #1c4fc2);
    color: #fff; box-shadow: 0 6px 14px rgba(47, 111, 237, 0.3);
}
.mp-filtro-btn:active { transform: scale(0.97); }

/* ---- Grid principal: 1 columna angosto, 2 desde tablet (768px, igual que perfil) ---- */
.mp-grid { display: grid; grid-template-columns: 1fr; gap: 18px; align-items: start; }
.mp-col { display: flex; flex-direction: column; gap: 18px; min-width: 0; }

/* ---- Héroe: total producido ---- */
.mp-hero {
    position: relative; overflow: hidden;
    border-radius: var(--pc-radius);
    background: linear-gradient(135deg, var(--pc-navy) 0%, #1c2b4d 100%);
    color: #fff; padding: 26px 24px;
    box-shadow: 0 14px 30px rgba(12, 28, 51, 0.18);
}
.mp-hero::before {
    content: ""; position: absolute; width: 170px; height: 170px;
    border-radius: 50%; background: rgba(255,255,255,0.08);
    top: -60px; right: -60px;
}
.mp-hero-label {
    font-size: 12.5px; text-transform: uppercase; letter-spacing: .04em;
    opacity: 0.7; font-weight: 600; position: relative; z-index: 1;
}
.mp-hero-chips { display: flex; flex-wrap: wrap; gap: 22px; margin-top: 12px; position: relative; z-index: 1; }
.mp-hero-chip .valor { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 32px; display: block; line-height: 1.1; }
.mp-hero-chip .unidad { font-size: 13px; opacity: 0.75; font-weight: 600; }
.mp-hero-empty { font-size: 14.5px; opacity: 0.85; position: relative; z-index: 1; }

/* ---- Métricas rápidas ---- */
.mp-resumen-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.mp-resumen-card {
    background: var(--pc-surface); border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius); padding: 16px 12px; text-align: center;
    box-shadow: 0 6px 20px rgba(12,28,51,0.05);
}
.mp-resumen-card .icono {
    width: 34px; height: 34px; border-radius: 10px;
    background: rgba(47,111,237,0.1); color: #2F6FED;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 8px; font-size: 14px;
}
.mp-resumen-card .valor { font-family: 'Poppins', sans-serif; font-size: 20px; font-weight: 800; color: var(--pc-navy); display: block; }
.mp-resumen-card .etiqueta { font-size: 11px; color: var(--pc-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }

.mp-seccion-titulo {
    display: flex; align-items: center; gap: 8px;
    font-family: 'Poppins', sans-serif; font-weight: 700; color: var(--pc-navy);
    margin: 0; font-size: 15.5px;
}
.mp-seccion-titulo i { color: #f0a500; }

/* ---- Top moldes ---- */
.mp-molde-card {
    background: var(--pc-surface); border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius); padding: 14px 16px; margin-bottom: 10px;
    display: flex; align-items: center; gap: 14px;
    box-shadow: 0 4px 14px rgba(12,28,51,0.05);
}
.mp-molde-rank {
    width: 34px; height: 34px; border-radius: 50%; display: flex;
    align-items: center; justify-content: center; font-weight: 800;
    font-family: 'Poppins', sans-serif; font-size: 14px; color: #fff;
    flex-shrink: 0; background: #a8b1c2;
}
.mp-molde-rank.r1 { background: linear-gradient(135deg, #f0c419, #c99b00); }
.mp-molde-rank.r2 { background: linear-gradient(135deg, #c7cedb, #8f99ad); }
.mp-molde-rank.r3 { background: linear-gradient(135deg, #d99457, #a8622a); }
.mp-molde-info { flex: 1; min-width: 0; }
.mp-molde-nombre { font-weight: 700; color: var(--pc-navy); font-size: 14.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mp-molde-producto { font-size: 12.5px; color: var(--pc-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mp-molde-cantidad { font-weight: 800; color: #2F6FED; font-size: 15px; text-align: right; white-space: nowrap; flex-shrink: 0; }
.mp-molde-avances { font-size: 11px; color: var(--pc-muted); font-weight: 600; text-align: right; }

/* ---- Toggle + detalle ---- */
.mp-toggle-detalle {
    width: 100%; background: var(--pc-surface); border: 1px dashed var(--pc-border);
    border-radius: var(--pc-radius); color: #2F6FED; font-weight: 700; font-size: 14px;
    padding: 13px 0; display: flex; align-items: center; justify-content: center; gap: 8px;
}
#mpDetalleWrap { display: none; margin-top: 10px; }

.mp-detalle-item {
    background: var(--pc-surface); border: 1px solid var(--pc-border);
    border-left: 4px solid #a8b1c2; border-radius: var(--pc-radius);
    padding: 12px 14px; margin-bottom: 8px; font-size: 13.5px;
    box-shadow: 0 3px 10px rgba(12,28,51,0.04);
}
.mp-detalle-item.turno-dia       { border-left-color: #f0a500; }
.mp-detalle-item.turno-tarde     { border-left-color: #2F6FED; }
.mp-detalle-item.turno-noche     { border-left-color: var(--pc-navy); }
.mp-detalle-item.turno-madrugada { border-left-color: #7c4dd9; }

.mp-detalle-item .fila-top { display: flex; justify-content: space-between; gap: 10px; font-weight: 700; color: var(--pc-navy); }
.mp-detalle-item .fila-sub { color: var(--pc-muted); font-size: 12px; margin-top: 4px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }

.mp-turno-badge {
    display: inline-block; font-size: 10.5px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .3px; padding: 2px 8px; border-radius: 999px; background: #eef1f8; color: var(--pc-muted);
}
.mp-turno-badge.turno-dia       { background: #fdf1d8; color: #8a5c00; }
.mp-turno-badge.turno-tarde     { background: #e6efff; color: #1c4fc2; }
.mp-turno-badge.turno-noche     { background: #e3e5f0; color: var(--pc-navy); }
.mp-turno-badge.turno-madrugada { background: #ede4fb; color: #5a2fa0; }

.mp-empty {
    text-align: center; color: var(--pc-muted); padding: 22px 10px; font-size: 13.5px;
    background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: var(--pc-radius);
}
.mp-empty i { display: block; font-size: 22px; margin-bottom: 8px; opacity: 0.5; }

.mp-skeleton {
    background: linear-gradient(90deg, #eef1f8 25%, #e4e9f4 37%, #eef1f8 63%);
    background-size: 400% 100%; animation: mp-shimmer 1.4s ease infinite; border-radius: 8px;
}
@keyframes mp-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }

/* ---- Tablet: aparece la segunda columna (mismo breakpoint que perfil_usuario.php) ---- */
@media (min-width: 768px) {
    .mp-grid { grid-template-columns: minmax(280px, 340px) 1fr; gap: 22px; }
    .mp-title-icon { width: 52px; height: 52px; font-size: 22px; }
    .mp-title { font-size: 27px; }
    .mp-hero { padding: 30px 28px; }
    .mp-hero-chip .valor { font-size: 36px; }
}

/* ---- Celular chico: mismo criterio que perfil_usuario.php ---- */
@media (max-width: 479px) {
    .mp-filtro-btn span.mp-filtro-label { display: none; }
    .mp-resumen-grid { gap: 8px; }
}
    </style>
</head>
<body>
<div class="pc-op-panel-shell">

    <header class="pc-op-brand-bar">
        <div class="pc-op-brand">
            <img src="../assets/img/logo.png" alt="Plásticos Chepito" class="pc-op-brand-mark">
            <div class="pc-op-brand-text">
                <span class="pc-op-brand-name">Plásticos Chepito</span>
                <span class="pc-op-brand-tag">Hecho a mano, hecho para durar</span>
            </div>
        </div>
        <div class="pc-op-actions">
            <a href="perfil_usuario.php" class="pc-op-panel-perfil">
                <i class="fa-solid fa-user"></i> Mi perfil
            </a>
            <a href="logoutoperario.php" class="pc-op-panel-logout">
                <i class="fa-solid fa-right-from-bracket"></i> Salir
            </a>
        </div>
    </header>

    <a href="panel.php" class="mp-back">
        <i class="fa-solid fa-arrow-left"></i> Volver al panel
    </a>

    <div class="mp-title-row">
        <div class="mp-title-icon"><i class="fa-solid fa-chart-column"></i></div>
        <div>
            <h1 class="mp-title">Mis producciones</h1>
            <div class="mp-periodo-tag" id="mpPeriodoTag">Cargando tu reporte...</div>
        </div>
    </div>

    <div class="mp-filtros">
        <button type="button" class="mp-filtro-btn active" data-modo="dia">
            <i class="fa-solid fa-sun"></i> <span class="mp-filtro-label">Hoy</span>
        </button>
        <button type="button" class="mp-filtro-btn" data-modo="semana">
            <i class="fa-solid fa-calendar-week"></i> <span class="mp-filtro-label">Semana</span>
        </button>
        <button type="button" class="mp-filtro-btn" data-modo="mes">
            <i class="fa-solid fa-calendar-days"></i> <span class="mp-filtro-label">Mes</span>
        </button>
    </div>

    <div class="mp-grid">

        <!-- Columna izquierda: total + métricas rápidas -->
        <div class="mp-col">
            <div class="mp-hero">
                <div class="mp-hero-label">Total producido</div>
                <div class="mp-hero-chips" id="mpHeroChips">
                    <div class="mp-skeleton" style="width:120px;height:36px;"></div>
                </div>
            </div>

            <div class="mp-resumen-grid" id="mpResumenGrid">
                <div class="mp-resumen-card"><div class="mp-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
                <div class="mp-resumen-card"><div class="mp-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
            </div>
        </div>

        <!-- Columna derecha: top moldes + detalle -->
        <div class="mp-col">
            <div class="mp-seccion-titulo"><i class="fa-solid fa-trophy"></i> Top moldes trabajados</div>
            <div id="mpTopMoldes">
                <div class="mp-empty"><i class="fa-solid fa-spinner fa-spin"></i>Cargando...</div>
            </div>

            <button type="button" class="mp-toggle-detalle" id="mpToggleDetalle">
                <i class="fa-solid fa-chevron-down"></i> Ver detalle de avances
            </button>
            <div id="mpDetalleWrap">
                <div id="mpDetalle"></div>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const URL_CONTROLADOR = '../controllers/clssReporteProduccion.php';
    let modoActual = 'dia';

    const btnsFiltro   = document.querySelectorAll('.mp-filtro-btn');
    const periodoTag   = document.getElementById('mpPeriodoTag');
    const heroChips    = document.getElementById('mpHeroChips');
    const resumenGrid  = document.getElementById('mpResumenGrid');
    const topMoldesEl  = document.getElementById('mpTopMoldes');
    const detalleEl    = document.getElementById('mpDetalle');
    const detalleWrap  = document.getElementById('mpDetalleWrap');
    const toggleBtn    = document.getElementById('mpToggleDetalle');

    const TURNO_LABEL = { dia: 'Día', tarde: 'Tarde', noche: 'Noche', madrugada: 'Madrugada' };

    btnsFiltro.forEach(btn => {
        btn.addEventListener('click', () => {
            btnsFiltro.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            modoActual = btn.dataset.modo;
            cargarReporte();
        });
    });

    toggleBtn.addEventListener('click', () => {
        const abierto = detalleWrap.style.display === 'block';
        detalleWrap.style.display = abierto ? 'none' : 'block';
        toggleBtn.innerHTML = abierto
            ? '<i class="fa-solid fa-chevron-down"></i> Ver detalle de avances'
            : '<i class="fa-solid fa-chevron-up"></i> Ocultar detalle de avances';
    });

    function formatoNumero(n) {
        const num = Number(n) || 0;
        return num.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function cargarReporte() {
        periodoTag.textContent = 'Cargando tu reporte...';
        heroChips.innerHTML = '<div class="mp-skeleton" style="width:120px;height:36px;"></div>';
        resumenGrid.innerHTML = `
            <div class="mp-resumen-card"><div class="mp-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
            <div class="mp-resumen-card"><div class="mp-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
        `;
        topMoldesEl.innerHTML = '<div class="mp-empty"><i class="fa-solid fa-spinner fa-spin"></i>Cargando...</div>';
        detalleEl.innerHTML = '';

        const formData = new FormData();
        formData.append('accion', 'MISPRODUCCIONESOPERARIO');
        formData.append('modo', modoActual);
        formData.append('fecha', new Date().toISOString().slice(0, 10));

        fetch(URL_CONTROLADOR, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire('Aviso', data.message || 'No se pudo cargar tu reporte.', 'warning');
                    periodoTag.textContent = '';
                    return;
                }
                pintarReporte(data);
            })
            .catch(err => {
                console.error(err);
                periodoTag.textContent = '';
                Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
            });
    }

    function pintarReporte(data) {
        periodoTag.textContent = data.periodo.etiqueta;

        // ── Héroe: un chip grande por cada unidad producida (kg, bultos, etc.) ──
        const unidades = data.resumen_por_unidad || [];
        if (unidades.length === 0) {
            heroChips.innerHTML = '<div class="mp-hero-empty">Aún no registras avances en este periodo.</div>';
        } else {
            heroChips.innerHTML = unidades.map(u => `
                <div class="mp-hero-chip">
                    <span class="valor">${formatoNumero(u.total_producido)}</span>
                    <span class="unidad">${u.unidad}</span>
                </div>
            `).join('');
        }

        // ── Métricas secundarias ──
        resumenGrid.innerHTML = `
            <div class="mp-resumen-card">
                <div class="icono"><i class="fa-solid fa-layer-group"></i></div>
                <span class="valor">${formatoNumero(data.resumen_general.total_avances)}</span>
                <span class="etiqueta">Avances</span>
            </div>
            <div class="mp-resumen-card">
                <div class="icono"><i class="fa-solid fa-cube"></i></div>
                <span class="valor">${formatoNumero(data.resumen_general.moldes_distintos)}</span>
                <span class="etiqueta">Moldes distintos</span>
            </div>
        `;

        // ── Top moldes ──
        if (!data.top_moldes || data.top_moldes.length === 0) {
            topMoldesEl.innerHTML = `
                <div class="mp-empty">
                    <i class="fa-regular fa-folder-open"></i>
                    No registraste avances en este periodo.
                </div>
            `;
        } else {
            topMoldesEl.innerHTML = data.top_moldes.map((m, i) => {
                const rank = i + 1;
                const rankClass = rank <= 3 ? `r${rank}` : '';
                return `
                    <div class="mp-molde-card">
                        <div class="mp-molde-rank ${rankClass}">${rank}</div>
                        <div class="mp-molde-info">
                            <div class="mp-molde-nombre">${m.molde_nombre || 'Sin molde'}</div>
                            <div class="mp-molde-producto">${m.producto_descripcion || ''}</div>
                        </div>
                        <div>
                            <div class="mp-molde-cantidad">${formatoNumero(m.kg_producido)} ${m.unidad || 'kg'}</div>
                            <div class="mp-molde-avances">${m.avances} avance(s)</div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // ── Detalle avance a avance ──
        if (!data.detalle || data.detalle.length === 0) {
            detalleEl.innerHTML = '<div class="mp-empty"><i class="fa-regular fa-folder-open"></i>Sin avances registrados.</div>';
        } else {
            detalleEl.innerHTML = data.detalle.map(d => {
                const turnoClass = 'turno-' + (d.turno || '');
                const turnoLabel = TURNO_LABEL[d.turno] || d.turno;
                return `
                    <div class="mp-detalle-item ${turnoClass}">
                        <div class="fila-top">
                            <span>${d.molde_nombre || 'Sin molde'}</span>
                            <span>${formatoNumero(d.kg_producido)} ${d.unidad || 'kg'}</span>
                        </div>
                        <div class="fila-sub">
                            <span class="mp-turno-badge ${turnoClass}">${turnoLabel}</span>
                            <span>${d.fecha} · ${d.hora}</span>
                            <span>· ${d.maquina_nombre}</span>
                            ${d.color_nombre ? `<span>· ${d.color_nombre}</span>` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }
    }

    cargarReporte();
})();
</script>
</body>
</html>