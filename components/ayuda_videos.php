<?php
$ayudaVideosRol = strtolower(trim((string)($ayudaVideosRol ?? '')));
if (!in_array($ayudaVideosRol, ['conductor', 'operario', 'administrador'], true)) return;
$prefijoAyuda = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/tablet/') ? '../' : '';
?>
<section class="pc-ayuda-videos" data-endpoint="<?= htmlspecialchars($prefijoAyuda . 'ayuda_videos_feed.php', ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="pcAyudaVideosTitulo">
    <div class="pc-ayuda-videos-head">
        <div><span class="pc-ayuda-eyebrow">Aprende a tu ritmo</span><h2 id="pcAyudaVideosTitulo"><i class="fa-solid fa-circle-play"></i> Videos de ayuda</h2><p>Tutoriales para tu trabajo.</p></div>
    </div>
    <div class="pc-ayuda-videos-grid" data-ayuda-grid><div class="pc-ayuda-videos-state">Cargando videos...</div></div>
</section>
<style>
.pc-ayuda-videos{margin:24px 0;padding:20px;border:1px solid #e5e7eb;border-radius:16px;background:#fff;color:#17243a}
.pc-ayuda-videos-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.pc-ayuda-eyebrow{font-size:.75rem;color:#65748b;text-transform:uppercase;font-weight:700;letter-spacing:.07em}
.pc-ayuda-videos h2{font-size:1.2rem;font-weight:750;margin:4px 0;color:#17243a}
.pc-ayuda-videos h2 i{color:#ef4444;margin-right:6px}.pc-ayuda-videos p{margin:0;color:#718096;font-size:.9rem}
.pc-ayuda-videos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:14px}
.pc-ayuda-video-card{border:1px solid #e7eaf0;border-radius:12px;overflow:hidden;background:#fff}
.pc-ayuda-video-thumb{display:block;position:relative;width:100%;padding:0;border:0;background:#17243a;cursor:pointer;aspect-ratio:16/9;overflow:hidden}
.pc-ayuda-video-thumb img{width:100%;height:100%;object-fit:cover;opacity:.88;transition:transform .2s,opacity .2s}.pc-ayuda-video-thumb:hover img{transform:scale(1.03);opacity:1}
.pc-ayuda-video-play{position:absolute;inset:0;display:grid;place-items:center;color:#fff;font-size:48px;text-shadow:0 2px 8px #0008}
.pc-ayuda-video-content{padding:12px 14px}.pc-ayuda-video-content h3{font-size:.98rem;font-weight:700;margin:0 0 6px}.pc-ayuda-video-content p{font-size:.83rem;line-height:1.45;margin:0 0 10px}
.pc-ayuda-video-module{display:inline-flex;background:#eef3ff;color:#2455bb;border-radius:999px;padding:4px 9px;font-size:.72rem;font-weight:700;text-transform:capitalize}
.pc-ayuda-videos-state{grid-column:1/-1;padding:20px;border-radius:10px;text-align:center;background:#f8fafc;color:#718096}
.pc-ayuda-video-overlay{position:fixed;inset:0;z-index:10050;background:#101828d9;display:none;align-items:center;justify-content:center;padding:18px}.pc-ayuda-video-overlay.is-open{display:flex}
.pc-ayuda-video-dialog{width:min(900px,100%);background:#fff;border-radius:14px;overflow:hidden}.pc-ayuda-video-dialog-head{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;font-weight:700}.pc-ayuda-video-dialog-head button{border:0;background:#f1f3f6;border-radius:8px;width:38px;height:38px;font-size:20px;cursor:pointer}.pc-ayuda-video-frame{aspect-ratio:16/9;background:#000}.pc-ayuda-video-frame iframe{width:100%;height:100%;border:0}
@media(max-width:600px){.pc-ayuda-videos{padding:15px;margin:18px 0}.pc-ayuda-videos-grid{grid-template-columns:1fr}.pc-ayuda-video-dialog-head{font-size:.9rem}}
</style>
<div class="pc-ayuda-video-overlay" data-ayuda-overlay role="dialog" aria-modal="true" aria-label="Reproductor de video" onclick="if(event.target===this) cerrarVideoAyuda(this)">
    <div class="pc-ayuda-video-dialog"><div class="pc-ayuda-video-dialog-head"><span data-ayuda-dialog-title></span><button type="button" aria-label="Cerrar video" onclick="cerrarVideoAyuda(this.closest('[data-ayuda-overlay]'))">&times;</button></div><div class="pc-ayuda-video-frame" data-ayuda-frame></div></div>
</div>
<script>
(() => {
    const root = document.currentScript.parentElement.querySelector('.pc-ayuda-videos');
    if (!root || !root.matches('.pc-ayuda-videos')) return;
    const grid = root.querySelector('[data-ayuda-grid]');
    const overlay = root.parentElement.querySelector('[data-ayuda-overlay]');
    const nombres = {compras:'Compras', perfil:'Perfil', produccion:'Producción', ensamblaje:'Ensamblaje', empaquetado:'Empaquetado', reportes:'Reportes', general:'General'};
    const escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    fetch(root.dataset.endpoint, {credentials:'same-origin'}).then(r => r.json().then(j => {if(!r.ok || !j.ok) throw new Error(j.msg || 'No se pudieron cargar los videos.'); return j;}))
      .then(({videos}) => {
        if (!videos.length) { grid.innerHTML = '<div class="pc-ayuda-videos-state">Todavía no hay tutoriales asignados a tu perfil.</div>'; return; }
        grid.innerHTML = videos.map(v => `<article class="pc-ayuda-video-card"><button class="pc-ayuda-video-thumb" type="button" data-video-id="${encodeURIComponent(v.youtube_id)}" data-video-title="${escapeHtml(v.titulo)}" aria-label="Reproducir ${escapeHtml(v.titulo)}"><img src="https://img.youtube.com/vi/${encodeURIComponent(v.youtube_id)}/hqdefault.jpg" alt=""><span class="pc-ayuda-video-play"><i class="fa-solid fa-circle-play"></i></span></button><div class="pc-ayuda-video-content"><h3>${escapeHtml(v.titulo)}</h3>${v.descripcion ? `<p>${escapeHtml(v.descripcion)}</p>` : ''}<span class="pc-ayuda-video-module">${escapeHtml(nombres[v.modulo] || v.modulo)}</span></div></article>`).join('');
        grid.querySelectorAll('[data-video-id]').forEach(btn => btn.addEventListener('click', () => {
          const id = decodeURIComponent(btn.dataset.videoId);
          const frame = overlay.querySelector('[data-ayuda-frame]');
          overlay.querySelector('[data-ayuda-dialog-title]').textContent = btn.dataset.videoTitle;
          const iframe = document.createElement('iframe'); iframe.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?autoplay=1&rel=0`; iframe.title = btn.dataset.videoTitle; iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'; iframe.allowFullscreen = true; frame.replaceChildren(iframe);
          overlay.classList.add('is-open'); document.body.style.overflow = 'hidden';
        }));
      }).catch(err => { grid.innerHTML = `<div class="pc-ayuda-videos-state">${escapeHtml(err.message)}</div>`; });
})();
function cerrarVideoAyuda(overlay) { if (!overlay) return; overlay.classList.remove('is-open'); overlay.querySelector('[data-ayuda-frame]')?.replaceChildren(); document.body.style.overflow = ''; }
document.addEventListener('keydown', e => {if(e.key === 'Escape') document.querySelectorAll('.pc-ayuda-video-overlay.is-open').forEach(cerrarVideoAyuda);});
</script>
