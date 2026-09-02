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
    document.getElementById('pfCargo').textContent = json.cargo || '-';
    document.getElementById('pfArea').textContent = json.area || '-';

    const nombresSucursales = (json.sucursales || []).map(s => s.nombre).join(', ');
    document.getElementById('pfSucursales').textContent = nombresSucursales || '-';

    const pfPin = document.getElementById('pfPin');
    pfPin.dataset.pin = json.pin || '';
    pfPin.textContent = '••••';
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