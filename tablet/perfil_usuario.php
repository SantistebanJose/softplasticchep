<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

if (empty($_SESSION['operario_id'])) {
    header('Location: loginoperarios.php');
    exit;
}

$nombreOperario = $_SESSION['operario_nombre'] ?? 'Operario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Mi perfil · Plásticos Chepito</title>
    <link rel="icon" type="image/png" href="../assets/img/logo.png?v=20261028">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/panel_operario.css">
    <style>
.pc-op-panel-shell { max-width: 720px; margin: 0 auto; }

.pc-perfil-header {
    background: linear-gradient(135deg, var(--pc-navy) 0%, #1c2b4d 100%);
    border-radius: var(--pc-radius);
    color: #fff;
    padding: 30px;
    display: flex;
    align-items: center;
    gap: 22px;
    flex-wrap: wrap;
    margin-top: 18px;
    box-shadow: 0 14px 30px rgba(12, 28, 51, 0.18);
}
.pc-perfil-avatar {
    width: 84px; height: 84px; border-radius: 50%;
    border: 3px solid rgba(255,255,255,0.35);
    background: rgba(255,255,255,0.08);
    display: flex; align-items: center; justify-content: center;
    font-size: 32px; flex-shrink: 0;
}
.pc-perfil-nombre { font-size: 24px; font-weight: 700; margin: 0; font-family:'Poppins',sans-serif; }
.pc-perfil-usuario { opacity: 0.7; margin: 3px 0 10px; font-size: 14px; }
.pc-perfil-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,0.15);
    padding: 5px 14px; border-radius: 999px; font-size: 12px;
    letter-spacing: 0.04em; text-transform: uppercase;
}

.pc-perfil-card {
    background: var(--pc-surface); border-radius: var(--pc-radius);
    padding: 24px 26px; margin-top: 18px;
    box-shadow: 0 6px 20px rgba(12,28,51,0.06);
}
.pc-perfil-card h3 {
    font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--pc-muted); margin: 0 0 16px;
    display: flex; align-items: center; gap: 8px;
    font-weight: 700;
}

.pc-perfil-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 0; border-bottom: 1px solid var(--pc-border);
    gap: 12px; flex-wrap: wrap;
}
.pc-perfil-row:last-of-type { border-bottom: none; }

.pc-perfil-label {
    display: flex; align-items: center; gap: 12px;
    color: var(--pc-muted); font-size: 14.5px; min-width: 170px;
}
.pc-perfil-label i {
    width: 34px; height: 34px; border-radius: 10px;
    background: rgba(47,111,237,0.1); color: #2F6FED;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}

.pc-perfil-valor {
    display: flex; align-items: center; gap: 10px;
    font-weight: 600; color: var(--pc-navy); font-size: 15px;
}
.pc-perfil-valor button {
    border-radius: 8px;
}

.pc-perfil-nota {
    font-size: 13px; color: var(--pc-muted); margin-top: 14px;
    display: flex; gap: 8px; align-items: flex-start;
    background: #f4f6fb; padding: 12px 14px; border-radius: 12px;
}
.pc-perfil-nota i { color: #2F6FED; margin-top: 1px; }

.pc-perfil-pass-box {
    background: #eaf7ee; border-left: 4px solid #2fae5f;
    padding: 16px 18px; border-radius: 12px;
    display: flex; gap: 12px; align-items: center;
}
.pc-perfil-pass-box.aviso { background: #fff6e6; border-left-color: #e0a324; }
.pc-perfil-pass-box.peligro { background: #fdeaea; border-left-color: #d64545; }
.pc-perfil-pass-box i { font-size: 20px; }
.pc-perfil-pass-box .titulo { font-weight: 700; color: var(--pc-navy); }
.pc-perfil-pass-box .subtitulo { font-size: 13px; color: var(--pc-muted); margin-top: 2px; }

.pc-perfil-back {
    display: inline-flex; align-items: center; gap: 8px;
    color: var(--pc-navy); font-weight: 600; text-decoration: none;
    margin-top: 6px; font-size: 14px;
}
.pc-perfil-back i { font-size: 12px; }

.pc-perfil-edit-bank {
    display: inline-flex; align-items: center; justify-content: center; gap: 9px;
    border: 0; border-radius: 12px; padding: 13px 18px; margin-top: 18px;
    color: #fff; background: #1769e8; font: 600 14px Inter, sans-serif;
    box-shadow: 0 6px 14px rgba(23,105,232,.2); cursor: pointer;
}
.pc-perfil-edit-bank:hover { background: #0c58cc; }
.swal2-popup.pc-banco-popup {
    width: min(470px, calc(100vw - 28px)); padding: 28px 28px 22px;
    border-radius: 22px; font-family: Inter, sans-serif;
}
.pc-banco-popup .swal2-title {
    padding: 0; margin: 4px 0 8px; color: #17243a;
    font: 700 22px/1.25 Poppins, sans-serif;
}
.pc-banco-popup .swal2-html-container { margin: 0; color: #64748b; font-size: 14px; }
.pc-banco-popup .swal2-actions { width: 100%; gap: 10px; margin: 22px 0 0; }
.pc-banco-popup .swal2-styled { margin: 0; border-radius: 11px; padding: 12px 20px; font: 600 14px Inter, sans-serif; }
.pc-banco-popup .swal2-confirm { background: #1769e8; }
.pc-banco-popup .swal2-cancel { background: #eef2f7; color: #344256; }
.pc-banco-form { display: grid; gap: 16px; padding-top: 12px; text-align: left; }
.pc-banco-intro {
    display: flex; gap: 12px; align-items: center; padding: 13px;
    border: 1px solid #dce9ff; border-radius: 14px; background: #f4f8ff;
    color: #365577; line-height: 1.45;
}
.pc-banco-intro i { display: grid; place-items: center; flex: 0 0 38px; height: 38px; border-radius: 12px; background: #e4efff; color: #1769e8; }
.pc-banco-field { display: grid; gap: 7px; }
.pc-banco-field label { color: #26364d; font: 600 14px Inter, sans-serif; }
.pc-banco-field input, .pc-banco-field select {
    box-sizing: border-box; width: 100%; min-height: 47px; padding: 11px 13px;
    border: 1px solid #d8e0eb; border-radius: 11px; background: #fff;
    color: #17243a; font: 500 15px Inter, sans-serif; outline: none;
}
.pc-banco-field input:focus, .pc-banco-field select:focus { border-color: #3983f6; box-shadow: 0 0 0 3px rgba(57,131,246,.14); }
.pc-banco-hint { color: #7c8ba0; font-size: 12px; line-height: 1.4; }

@media (min-width: 768px) {
    .pc-perfil-header { padding: 38px 42px; }
    .pc-perfil-avatar { width: 104px; height: 104px; font-size: 40px; }
    .pc-perfil-nombre { font-size: 30px; }
    .pc-perfil-card { padding: 30px 36px; }
    .pc-perfil-row { padding: 17px 0; }
    .pc-perfil-label, .pc-perfil-valor { font-size: 16px; }
}
@media (max-width: 479px) {
    .pc-perfil-header { flex-direction: column; text-align: center; }
    .pc-perfil-row { flex-direction: column; align-items: flex-start; }
    .pc-perfil-valor { width: 100%; justify-content: space-between; }
}
</style>
</head>
<body>
<?php $prefijoEfectosNavidenos = '../'; require __DIR__ . '/../components/efectos_navidenos.php'; ?>
<div class="pc-op-panel-shell">

    <header class="pc-op-brand-bar">
        <div class="pc-op-brand">
            <img src="../assets/img/logo.png" alt="Plásticos Chepito" class="pc-op-brand-mark">
            <div class="pc-op-brand-text">
                <span class="pc-op-brand-name">Plásticos Chepito</span>
                <span class="pc-op-brand-tag">Hecho a mano, hecho para durar</span>
            </div>
        </div>
        <a href="logoutoperario.php" class="pc-op-panel-logout">
            <i class="fa-solid fa-right-from-bracket"></i> Salir
        </a>
    </header>

    <a href="panel.php" class="pc-perfil-back">
        <i class="fa-solid fa-arrow-left"></i> Volver al panel
    </a>

    <div class="pc-perfil-header">
        <div class="pc-perfil-avatar"><i class="fa-solid fa-user"></i></div>
        <div>
            <p class="pc-perfil-nombre" id="pfNombre">Cargando...</p>
            <p class="pc-perfil-usuario" id="pfUsuario">@usuario</p>
            <span class="pc-perfil-badge" id="pfRol">ROL</span>
        </div>
    </div>

    <div class="pc-perfil-card">
        <h3><i class="fa-solid fa-id-card"></i> Información personal</h3>

        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-user"></i> Nombre completo</span>
            <span class="pc-perfil-valor" id="pfNombreCompleto">-</span>
        </div>

        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-at"></i> Usuario</span>
            <span class="pc-perfil-valor" id="pfUsuario2">-</span>
        </div>

        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-briefcase"></i> Perfil / Rol</span>
            <span class="pc-perfil-valor" id="pfRol2">-</span>
        </div>

        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-hashtag"></i> PIN</span>
            <span class="pc-perfil-valor">
                <span id="pfPin" data-pin="">••••</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnTogglePin" title="Mostrar/ocultar">
                    <i class="fa-solid fa-eye"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnEditarPin">
                    <i class="fa-solid fa-pen"></i> Editar
                </button>
            </span>
        </div>
        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-id-badge"></i> DNI</span>
            <span class="pc-perfil-valor" id="pfDni">-</span>
        </div>

        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-building-columns"></i> Banco</span>
            <span class="pc-perfil-valor" id="pfBanco">-</span>
        </div>
        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-credit-card"></i> Cuenta</span>
            <span class="pc-perfil-valor" id="pfCuenta">-</span>
        </div>
        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-money-check"></i> CCI</span>
            <span class="pc-perfil-valor" id="pfCci">-</span>
        </div>

        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-briefcase"></i> Cargo</span>
            <span class="pc-perfil-valor" id="pfCargo">-</span>
        </div>

        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-building"></i> Área</span>
            <span class="pc-perfil-valor" id="pfArea">-</span>
        </div>

        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-store"></i> Sucursales</span>
            <span class="pc-perfil-valor" id="pfSucursales">-</span>
        </div>

        <div class="pc-perfil-row">
            <span class="pc-perfil-label"><i class="fa-solid fa-calendar-check"></i> Miembro desde</span>
            <span class="pc-perfil-valor" id="pfMiembroDesde">-</span>
        </div>

        <div class="pc-perfil-nota">
            <i class="fa-solid fa-circle-info"></i>
            Para modificar tu nombre, usuario o rol, contacta al administrador del sistema.
        </div>
        <button type="button" class="pc-perfil-edit-bank" id="btnEditarDatosBancarios">
            <i class="fa-solid fa-pen"></i> Actualizar datos bancarios
        </button>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_PERFIL = '../controllers_tablet/clssPerfilOperario.php';

async function llamarPerfil(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_PERFIL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    const texto = await resp.text();
    try {
        return JSON.parse(texto);
    } catch (e) {
        console.error(`Respuesta no es JSON válido para accion=${accion}:`, texto);
        throw new Error('El servidor no devolvió una respuesta válida.');
    }
}

async function cargarPerfil() {
    const json = await llamarPerfil('OBTENERPERFILOPERARIO');
    if (!json.success) {
        Swal.fire('Error', json.message, 'error');
        return;
    }

    document.getElementById('pfNombre').textContent = json.nombre_completo;
    document.getElementById('pfUsuario').textContent = '@' + json.usuario;
    document.getElementById('pfUsuario2').textContent = json.usuario;
    document.getElementById('pfRol').textContent = json.rol;
    document.getElementById('pfRol2').textContent = json.rol;
    document.getElementById('pfNombreCompleto').textContent = json.nombre_completo;
    document.getElementById('pfMiembroDesde').textContent = json.miembro_desde || '-';

    document.getElementById('pfDni').textContent = json.dni || '-';
    const banco = json.banco || {};
    document.getElementById('pfBanco').textContent = banco.nombre || banco.banco || '-';
    document.getElementById('pfCuenta').textContent = banco.cuenta ? `•••• ${String(banco.cuenta).slice(-4)}` : '-';
    document.getElementById('pfCci').textContent = banco.cci ? `•••• ${String(banco.cci).slice(-4)}` : '-';
    document.getElementById('pfCargo').textContent = json.cargo || '-';
    document.getElementById('pfArea').textContent = json.area || '-';

    const nombresSucursales = (json.sucursales || []).map(s => s.nombre).join(', ');
    document.getElementById('pfSucursales').textContent = nombresSucursales || '-';

    const pfPin = document.getElementById('pfPin');
    pfPin.dataset.pin = json.pin || '';
    pfPin.textContent = '••••';
}

document.getElementById('btnEditarDatosBancarios').addEventListener('click', async () => {
    const perfil = await llamarPerfil('OBTENERPERFILOPERARIO');
    if (!perfil.success) { Swal.fire('Error', perfil.message, 'error'); return; }
    const banco = perfil.banco || {};
    const opciones = (perfil.bancos_disponibles || []).map(b =>
        `<option value="${escaparHtml(b.nombre)}" ${b.nombre === (banco.nombre || banco.banco) ? 'selected' : ''}>${escaparHtml(b.nombre)}</option>`
    ).join('');
    const { value: datos, isConfirmed } = await Swal.fire({
        title: 'Actualizar datos bancarios',
        html: `<div class="pc-banco-form">
            <div class="pc-banco-intro"><i class="fa-solid fa-shield-halved"></i><span>Actualiza la información donde recibes tus pagos. Tus números se mostrarán protegidos en el perfil.</span></div>
            <div class="pc-banco-field"><label for="swal-banco">Banco</label><select id="swal-banco"><option value="">Selecciona un banco</option>${opciones}</select></div>
            <div class="pc-banco-field"><label for="swal-cuenta">Número de cuenta</label><input id="swal-cuenta" inputmode="numeric" maxlength="30" placeholder="De 6 a 30 dígitos" value="${escaparHtml(banco.cuenta || banco.numero_cuenta || '')}"><span class="pc-banco-hint">Solo números, sin espacios ni guiones.</span></div>
            <div class="pc-banco-field"><label for="swal-cci">CCI</label><input id="swal-cci" inputmode="numeric" maxlength="20" placeholder="20 dígitos" value="${escaparHtml(banco.cci || '')}"><span class="pc-banco-hint">Debe contener exactamente 20 dígitos.</span></div>
        </div>`,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: {
            popup: 'pc-banco-popup',
            confirmButton: 'swal2-confirm',
            cancelButton: 'swal2-cancel',
        },
        preConfirm: () => {
            const cuenta = document.getElementById('swal-cuenta').value.trim();
            const cci = document.getElementById('swal-cci').value.trim();
            if (cuenta && !/^\d{6,30}$/.test(cuenta)) { Swal.showValidationMessage('La cuenta debe tener de 6 a 30 dígitos, solo números.'); return false; }
            if (cci && !/^\d{20}$/.test(cci)) { Swal.showValidationMessage('El CCI debe tener exactamente 20 dígitos.'); return false; }
            return { banco_nombre: document.getElementById('swal-banco').value, banco_cuenta: cuenta, banco_cci: cci };
        }
    });
    if (!isConfirmed) return;
    const respuesta = await llamarPerfil('ACTUALIZARBANCOPERFIL', datos);
    if (!respuesta.success) { Swal.fire('No se pudo actualizar', respuesta.message, 'error'); return; }
    await Swal.fire('Actualizado', respuesta.message, 'success');
    cargarPerfil();
});

function escaparHtml(valor) {
    return String(valor).replace(/[&<>"']/g, caracter => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[caracter]));
}

let pinVisible = false;
document.getElementById('btnTogglePin').addEventListener('click', () => {
    const pfPin = document.getElementById('pfPin');
    pinVisible = !pinVisible;
    pfPin.textContent = pinVisible ? (pfPin.dataset.pin || '----') : '••••';
    document.querySelector('#btnTogglePin i').className = pinVisible ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
});

document.getElementById('btnEditarPin').addEventListener('click', async () => {
    const { value: nuevoPin, isConfirmed } = await Swal.fire({
        title: 'Nuevo PIN',
        input: 'text',
        inputLabel: 'Ingresa un PIN de 4 dígitos',
        inputAttributes: { maxlength: 4, inputmode: 'numeric', autocapitalize: 'off' },
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            if (!/^\d{4}$/.test(value || '')) return 'El PIN debe tener exactamente 4 dígitos.';
        }
    });

    if (!isConfirmed) return;

    const json = await llamarPerfil('ACTUALIZARPINOPERARIO', { pin: nuevoPin });
    if (json.success) {
        Swal.fire('Listo', json.message, 'success');
        cargarPerfil();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

cargarPerfil();
</script>
</body>
</html>
