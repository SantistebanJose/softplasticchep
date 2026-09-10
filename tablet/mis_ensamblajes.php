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
    <title>Mis ensamblajes · Plásticos Chepito</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/panel_operario.css">
    <style>
/* ============================================================
   Mis ensamblajes — mismo patrón que mis_producciones.php /
   perfil_usuario.php: el .pc-op-panel-shell se acota con
   max-width + margin:0 auto para que header y contenido
   compartan siempre el mismo ancho centrado.
   Acento en rojo (var(--pc-red)) para emparejar visualmente con
   el panel de administración de Ensamblaje.
   ============================================================ */
.pc-op-panel-shell { max-width: 980px; margin: 0 auto; }

.pc-op-panel-shell .me-back {
    display: inline-flex; align-items: center; gap: 8px;
    color: var(--pc-navy); font-weight: 600; text-decoration: none;
    margin: 6px 0 18px; font-size: 14px;
}
.pc-op-panel-shell .me-back i { font-size: 12px; }

.me-title-row {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 22px; flex-wrap: wrap;
}
.me-title-icon {
    width: 48px; height: 48px; border-radius: var(--pc-radius);
    background: linear-gradient(135deg, #c0392b, #7a2015);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
    box-shadow: 0 6px 14px rgba(192, 57, 43, 0.3);
}
.me-title {
    font-family: 'Poppins', sans-serif; font-weight: 700;
    font-size: 24px; color: var(--pc-navy); margin: 0; line-height: 1.2;
}
.me-periodo-tag { color: var(--pc-muted); font-size: 14px; min-height: 18px; }

/* ---- Filtros: segmented control ---- */
.me-filtros {
    display: flex; gap: 6px;
    background: var(--pc-surface); border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius); padding: 6px; margin-bottom: 18px;
    box-shadow: 0 6px 20px rgba(12,28,51,0.05);
}
.me-filtro-btn {
    flex: 1; border: none; background: transparent; color: var(--pc-muted);
    border-radius: 9px; padding: 12px 8px; font-weight: 600; font-size: 14px;
    font-family: inherit; display: flex; align-items: center; justify-content: center;
    gap: 7px; transition: all .15s ease;
}
.me-filtro-btn.active {
    background: linear-gradient(135deg, #c0392b, #942f22);
    color: #fff; box-shadow: 0 6px 14px rgba(192, 57, 43, 0.3);
}
.me-filtro-btn:active { transform: scale(0.97); }

/* ---- Grid principal ---- */
.me-grid { display: grid; grid-template-columns: 1fr; gap: 18px; align-items: start; }
.me-col { display: flex; flex-direction: column; gap: 18px; min-width: 0; }

/* ---- Héroe: total ensamblado ---- */
.me-hero {
    position: relative; overflow: hidden;
    border-radius: var(--pc-radius);
    background: linear-gradient(135deg, var(--pc-navy) 0%, #1c2b4d 100%);
    color: #fff; padding: 26px 24px;
    box-shadow: 0 14px 30px rgba(12, 28, 51, 0.18);
}
.me-hero::before {
    content: ""; position: absolute; width: 170px; height: 170px;
    border-radius: 50%; background: rgba(255,255,255,0.08);
    top: -60px; right: -60px;
}
.me-hero-label {
    font-size: 12.5px; text-transform: uppercase; letter-spacing: .04em;
    opacity: 0.7; font-weight: 600; position: relative; z-index: 1;
}
.me-hero-chips { display: flex; flex-wrap: wrap; gap: 22px; margin-top: 12px; position: relative; z-index: 1; }
.me-hero-chip .valor { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 32px; display: block; line-height: 1.1; }
.me-hero-chip .unidad { font-size: 13px; opacity: 0.75; font-weight: 600; }
.me-hero-empty { font-size: 14.5px; opacity: 0.85; position: relative; z-index: 1; }

/* ---- Métricas rápidas ---- */
.me-resumen-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.me-resumen-card {
    background: var(--pc-surface); border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius); padding: 16px 12px; text-align: center;
    box-shadow: 0 6px 20px rgba(12,28,51,0.05);
}
.me-resumen-card .icono {
    width: 34px; height: 34px; border-radius: 10px;
    background: rgba(192,57,43,0.1); color: #c0392b;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 8px; font-size: 14px;
}
.me-resumen-card .valor { font-family: 'Poppins', sans-serif; font-size: 20px; font-weight: 800; color: var(--pc-navy); display: block; }
.me-resumen-card .etiqueta { font-size: 11px; color: var(--pc-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }

.me-seccion-titulo {
    display: flex; align-items: center; gap: 8px;
    font-family: 'Poppins', sans-serif; font-weight: 700; color: var(--pc-navy);
    margin: 0; font-size: 15.5px;
}
.me-seccion-titulo i { color: #f0a500; }

/* ---- Top productos ---- */
.me-prod-card {
    background: var(--pc-surface); border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius); padding: 14px 16px; margin-bottom: 10px;
    display: flex; align-items: center; gap: 14px;
    box-shadow: 0 4px 14px rgba(12,28,51,0.05);
}
.me-prod-rank {
    width: 34px; height: 34px; border-radius: 50%; display: flex;
    align-items: center; justify-content: center; font-weight: 800;
    font-family: 'Poppins', sans-serif; font-size: 14px; color: #fff;
    flex-shrink: 0; background: #a8b1c2;
}
.me-prod-rank.r1 { background: linear-gradient(135deg, #f0c419, #c99b00); }
.me-prod-rank.r2 { background: linear-gradient(135deg, #c7cedb, #8f99ad); }
.me-prod-rank.r3 { background: linear-gradient(135deg, #d99457, #a8622a); }
.me-prod-info { flex: 1; min-width: 0; }
.me-prod-nombre { font-weight: 700; color: var(--pc-navy); font-size: 14.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.me-prod-codigo { font-size: 12.5px; color: var(--pc-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.me-prod-cantidad { font-weight: 800; color: #c0392b; font-size: 15px; text-align: right; white-space: nowrap; flex-shrink: 0; }
.me-prod-ensamblajes { font-size: 11px; color: var(--pc-muted); font-weight: 600; text-align: right; }

/* ---- Toggle + detalle ---- */
.me-toggle-detalle {
    width: 100%; background: var(--pc-surface); border: 1px dashed var(--pc-border);
    border-radius: var(--pc-radius); color: #c0392b; font-weight: 700; font-size: 14px;
    padding: 13px 0; display: flex; align-items: center; justify-content: center; gap: 8px;
}
#meDetalleWrap { display: none; margin-top: 10px; }

.me-detalle-item {
    background: var(--pc-surface); border: 1px solid var(--pc-border);
    border-left: 4px solid #a8b1c2; border-radius: var(--pc-radius);
    padding: 12px 14px; margin-bottom: 8px; font-size: 13.5px;
    box-shadow: 0 3px 10px rgba(12,28,51,0.04);
}
.me-detalle-item.estado-pendiente { border-left-color: #f0a500; }
.me-detalle-item.estado-enviado   { border-left-color: #22a06b; }

.me-detalle-item .fila-top { display: flex; justify-content: space-between; gap: 10px; font-weight: 700; color: var(--pc-navy); }
.me-detalle-item .fila-sub { color: var(--pc-muted); font-size: 12px; margin-top: 4px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }

.me-estado-badge {
    display: inline-block; font-size: 10.5px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .3px; padding: 2px 8px; border-radius: 999px; background: #eef1f8; color: var(--pc-muted);
}
.me-estado-badge.estado-pendiente { background: #fdf1d8; color: #8a5c00; }
.me-estado-badge.estado-enviado   { background: #d9f3e8; color: #0f6b45; }

.me-empty {
    text-align: center; color: var(--pc-muted); padding: 22px 10px; font-size: 13.5px;
    background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: var(--pc-radius);
}
.me-empty i { display: block; font-size: 22px; margin-bottom: 8px; opacity: 0.5; }

.me-skeleton {
    background: linear-gradient(90deg, #eef1f8 25%, #e4e9f4 37%, #eef1f8 63%);
    background-size: 400% 100%; animation: me-shimmer 1.4s ease infinite; border-radius: 8px;
}
@keyframes me-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }

/* ---- Tablet: aparece la segunda columna (mismo breakpoint que mis_producciones.php) ---- */
@media (min-width: 768px) {
    .me-grid { grid-template-columns: minmax(280px, 340px) 1fr; gap: 22px; }
    .me-title-icon { width: 52px; height: 52px; font-size: 22px; }
    .me-title { font-size: 27px; }
    .me-hero { padding: 30px 28px; }
    .me-hero-chip .valor { font-size: 36px; }
}

/* ---- Celular chico ---- */
@media (max-width: 479px) {
    .me-filtro-btn span.me-filtro-label { display: none; }
    .me-resumen-grid { gap: 8px; }
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

    <a href="panel.php" class="me-back">
        <i class="fa-solid fa-arrow-left"></i> Volver al panel
    </a>

    <div class="me-title-row">
        <div class="me-title-icon"><i class="fa-solid fa-layer-group"></i></div>
        <div>
            <h1 class="me-title">Mis ensamblajes</h1>
            <div class="me-periodo-tag" id="mePeriodoTag">Cargando tu reporte...</div>
        </div>
    </div>

    <div class="me-filtros">
        <button type="button" class="me-filtro-btn active" data-modo="dia">
            <i class="fa-solid fa-sun"></i> <span class="me-filtro-label">Hoy</span>
        </button>
        <button type="button" class="me-filtro-btn" data-modo="semana">
            <i class="fa-solid fa-calendar-week"></i> <span class="me-filtro-label">Semana</span>
        </button>
        <button type="button" class="me-filtro-btn" data-modo="mes">
            <i class="fa-solid fa-calendar-days"></i> <span class="me-filtro-label">Mes</span>
        </button>
    </div>

    <div class="me-grid">

        <!-- Columna izquierda: total + métricas rápidas -->
        <div class="me-col">
            <div class="me-hero">
                <div class="me-hero-label">Total ensamblado</div>
                <div class="me-hero-chips" id="meHeroChips">
                    <div class="me-skeleton" style="width:120px;height:36px;"></div>
                </div>
            </div>

            <div class="me-resumen-grid" id="meResumenGrid">
                <div class="me-resumen-card"><div class="me-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
                <div class="me-resumen-card"><div class="me-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
                <div class="me-resumen-card"><div class="me-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
                <div class="me-resumen-card"><div class="me-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
            </div>
        </div>

        <!-- Columna derecha: top productos + detalle -->
        <div class="me-col">
            <div class="me-seccion-titulo"><i class="fa-solid fa-trophy"></i> Top productos ensamblados</div>
            <div id="meTopProductos">
                <div class="me-empty"><i class="fa-solid fa-spinner fa-spin"></i>Cargando...</div>
            </div>

            <button type="button" class="me-toggle-detalle" id="meToggleDetalle">
                <i class="fa-solid fa-chevron-down"></i> Ver detalle de ensamblajes
            </button>
            <div id="meDetalleWrap">
                <div id="meDetalle"></div>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const URL_CONTROLADOR = '../controllers/clssReporteEnsamblaje.php';
    let modoActual = 'dia';

    const btnsFiltro   = document.querySelectorAll('.me-filtro-btn');
    const periodoTag   = document.getElementById('mePeriodoTag');
    const heroChips    = document.getElementById('meHeroChips');
    const resumenGrid  = document.getElementById('meResumenGrid');
    const topProdEl    = document.getElementById('meTopProductos');
    const detalleEl    = document.getElementById('meDetalle');
    const detalleWrap  = document.getElementById('meDetalleWrap');
    const toggleBtn    = document.getElementById('meToggleDetalle');

    const ESTADO_LABEL = { pendiente: 'Pendiente', enviado: 'Enviado a empaquetado' };

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
            ? '<i class="fa-solid fa-chevron-down"></i> Ver detalle de ensamblajes'
            : '<i class="fa-solid fa-chevron-up"></i> Ocultar detalle de ensamblajes';
    });

    function formatoNumero(n) {
        const num = Number(n) || 0;
        return num.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function cargarReporte() {
        periodoTag.textContent = 'Cargando tu reporte...';
        heroChips.innerHTML = '<div class="me-skeleton" style="width:120px;height:36px;"></div>';
        resumenGrid.innerHTML = `
            <div class="me-resumen-card"><div class="me-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
            <div class="me-resumen-card"><div class="me-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
            <div class="me-resumen-card"><div class="me-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
            <div class="me-resumen-card"><div class="me-skeleton" style="width:60%;height:16px;margin:0 auto;"></div></div>
        `;
        topProdEl.innerHTML = '<div class="me-empty"><i class="fa-solid fa-spinner fa-spin"></i>Cargando...</div>';
        detalleEl.innerHTML = '';

        const formData = new FormData();
        formData.append('accion', 'MISENSAMBLAJESOPERARIO');
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

        // ── Héroe: un chip grande por cada unidad ensamblada (kg, docena, etc.) ──
        const unidades = data.resumen_por_unidad || [];
        if (unidades.length === 0) {
            heroChips.innerHTML = '<div class="me-hero-empty">Aún no registras ensamblajes en este periodo.</div>';
        } else {
            heroChips.innerHTML = unidades.map(u => `
                <div class="me-hero-chip">
                    <span class="valor">${formatoNumero(u.cantidad_total)}</span>
                    <span class="unidad">${u.unidad}</span>
                </div>
            `).join('');
        }

        // ── Métricas secundarias ──
        resumenGrid.innerHTML = `
            <div class="me-resumen-card">
                <div class="icono"><i class="fa-solid fa-layer-group"></i></div>
                <span class="valor">${formatoNumero(data.resumen_general.total_ensamblajes)}</span>
                <span class="etiqueta">Ensamblajes</span>
            </div>
            <div class="me-resumen-card">
                <div class="icono"><i class="fa-solid fa-box"></i></div>
                <span class="valor">${formatoNumero(data.resumen_general.productos_distintos)}</span>
                <span class="etiqueta">Productos distintos</span>
            </div>
            <div class="me-resumen-card">
                <div class="icono"><i class="fa-solid fa-hourglass-half"></i></div>
                <span class="valor">${formatoNumero(data.resumen_general.pendientes)}</span>
                <span class="etiqueta">Pendientes</span>
            </div>
            <div class="me-resumen-card">
                <div class="icono"><i class="fa-solid fa-truck-ramp-box"></i></div>
                <span class="valor">${formatoNumero(data.resumen_general.enviados_empaquetado)}</span>
                <span class="etiqueta">A empaquetado</span>
            </div>
        `;

        // ── Top productos ──
        if (!data.top_productos || data.top_productos.length === 0) {
            topProdEl.innerHTML = `
                <div class="me-empty">
                    <i class="fa-regular fa-folder-open"></i>
                    No registraste ensamblajes en este periodo.
                </div>
            `;
        } else {
            topProdEl.innerHTML = data.top_productos.map((p, i) => {
                const rank = i + 1;
                const rankClass = rank <= 3 ? `r${rank}` : '';
                return `
                    <div class="me-prod-card">
                        <div class="me-prod-rank ${rankClass}">${rank}</div>
                        <div class="me-prod-info">
                            <div class="me-prod-nombre">${p.descripcion || 'Sin producto'}</div>
                            <div class="me-prod-codigo">${p.codigo || ''}</div>
                        </div>
                        <div>
                            <div class="me-prod-cantidad">${formatoNumero(p.cantidad_total)} ${p.unidad || 'kg'}</div>
                            <div class="me-prod-ensamblajes">${p.ensamblajes} ensamblaje(s)</div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // ── Detalle registro a registro ──
        if (!data.detalle || data.detalle.length === 0) {
            detalleEl.innerHTML = '<div class="me-empty"><i class="fa-regular fa-folder-open"></i>Sin ensamblajes registrados.</div>';
        } else {
            detalleEl.innerHTML = data.detalle.map(d => {
                const estadoClass = 'estado-' + (d.estado || 'pendiente');
                const estadoLabel = ESTADO_LABEL[d.estado] || d.estado;
                return `
                    <div class="me-detalle-item ${estadoClass}">
                        <div class="fila-top">
                            <span>${d.producto_codigo ? d.producto_codigo + ' — ' : ''}${d.producto || 'Sin producto'}</span>
                            <span>${formatoNumero(d.cantidad)} ${d.unidad || 'kg'}</span>
                        </div>
                        <div class="fila-sub">
                            <span class="me-estado-badge ${estadoClass}">${estadoLabel}</span>
                            <span>${d.fecha} · ${d.hora}</span>
                            ${d.categoria_material ? `<span>· ${d.categoria_material}</span>` : ''}
                            ${d.proveniente ? `<span>· ${d.proveniente}</span>` : ''}
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