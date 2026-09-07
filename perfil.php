<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/controllers/bd.php';
require __DIR__ . '/controllers/UserController.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = conectar_oll_BD();

$activePage   = 'perfil';
$pageTitle    = 'Mi perfil';
$pageSubtitle = 'Tu información de cuenta';

$controller = new UserController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        switch ($_POST['accion']) {
            case 'obtener':
                $data = $controller->getProfileById((int) $_SESSION['usuario_id']);
                if (!$data) {
                    echo json_encode(['ok' => false, 'msg' => 'No se pudo cargar el perfil.']);
                    exit;
                }
                echo json_encode(['ok' => true, 'data' => $data]);
                exit;

            case 'cambiar_password':
                echo json_encode($controller->changeOwnPassword(
                    (int) $_SESSION['usuario_id'],
                    trim($_POST['actual'] ?? ''),
                    trim($_POST['nueva'] ?? ''),
                    trim($_POST['confirmar'] ?? '')
                ));
                exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
        exit;
    }
}

require __DIR__ . '/header.php'; ?>

<div class="pc-perfil-shell" style="max-width:720px;margin:0 auto;">

    <div class="pc-perfil-header" style="background:linear-gradient(135deg, var(--pc-navy) 0%, #1c2b4d 100%);border-radius:var(--pc-radius);color:#fff;padding:30px;display:flex;align-items:center;gap:22px;flex-wrap:wrap;box-shadow:0 14px 30px rgba(12,28,51,0.18);">
        <div style="width:84px;height:84px;border-radius:50%;border:3px solid rgba(255,255,255,0.35);background:rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;font-size:32px;flex-shrink:0;">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div>
            <p style="font-size:24px;font-weight:700;margin:0;font-family:'Poppins',sans-serif;" id="pfNombre">Cargando...</p>
            <p style="opacity:0.7;margin:3px 0 10px;font-size:14px;" id="pfUsuario">@usuario</p>
            <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.15);padding:5px 14px;border-radius:999px;font-size:12px;letter-spacing:0.04em;text-transform:uppercase;" id="pfRol">ROL</span>
        </div>
    </div>

    <div class="pc-card" style="margin-top:18px;">
        <h3 style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:var(--pc-muted);margin:0 0 16px;font-weight:700;">
            <i class="fa-solid fa-id-card"></i> Información personal
        </h3>

        <div class="pc-perfil-row" style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--pc-border);gap:12px;flex-wrap:wrap;">
            <span style="color:var(--pc-muted);">Nombre completo</span>
            <span style="font-weight:600;color:var(--pc-navy);" id="pfNombreCompleto">-</span>
        </div>
        <div class="pc-perfil-row" style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--pc-border);gap:12px;flex-wrap:wrap;">
            <span style="color:var(--pc-muted);">Usuario</span>
            <span style="font-weight:600;color:var(--pc-navy);" id="pfUsuario2">-</span>
        </div>
        <div class="pc-perfil-row" style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--pc-border);gap:12px;flex-wrap:wrap;">
            <span style="color:var(--pc-muted);">Rol</span>
            <span style="font-weight:600;color:var(--pc-navy);" id="pfRol2">-</span>
        </div>
        <div class="pc-perfil-row" style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;gap:12px;flex-wrap:wrap;">
            <span style="color:var(--pc-muted);">Miembro desde</span>
            <span style="font-weight:600;color:var(--pc-navy);" id="pfMiembroDesde">-</span>
        </div>

        <div style="font-size:13px;color:var(--pc-muted);margin-top:14px;background:#f4f6fb;padding:12px 14px;border-radius:12px;">
            <i class="fa-solid fa-circle-info" style="color:#2F6FED;"></i>
            Para modificar tu usuario o rol, contacta a otro administrador desde el módulo de Usuarios.
        </div>
    </div>

    <div class="pc-card" style="margin-top:18px;">
        <h3 style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:var(--pc-muted);margin:0 0 16px;font-weight:700;">
            <i class="fa-solid fa-lock"></i> Seguridad
        </h3>
        <button class="pc-btn pc-btn-primary" id="btnCambiarPassword" type="button">
            <i class="fa-solid fa-key"></i> Cambiar contraseña
        </button>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
async function llamarPerfilAdmin(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch('perfil.php', {
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

async function cargarPerfilAdmin() {
    const json = await llamarPerfilAdmin('obtener');
    if (!json.ok) {
        Swal.fire('Error', json.msg, 'error');
        return;
    }

    const u = json.data;
    const rolData = JSON.parse(u.rol_y_perfiles || '{}');

    document.getElementById('pfNombre').textContent = u.nombre_completo;
    document.getElementById('pfUsuario').textContent = '@' + u.user_;
    document.getElementById('pfUsuario2').textContent = u.user_;
    document.getElementById('pfNombreCompleto').textContent = u.nombre_completo;
    document.getElementById('pfRol').textContent = rolData.rol ?? 'operario';
    document.getElementById('pfRol2').textContent = rolData.rol ?? 'operario';
    document.getElementById('pfMiembroDesde').textContent =
        u.created_at ? new Date(u.created_at).toLocaleDateString('es-PE') : '-';
}

document.getElementById('btnCambiarPassword').addEventListener('click', async () => {
    const { value: formValues, isConfirmed } = await Swal.fire({
        title: 'Cambiar contraseña',
        html:
            '<input id="swal-actual" type="password" class="swal2-input" placeholder="Contraseña actual">' +
            '<input id="swal-nueva" type="password" class="swal2-input" placeholder="Nueva contraseña">' +
            '<input id="swal-confirmar" type="password" class="swal2-input" placeholder="Confirmar nueva contraseña">',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const actual = document.getElementById('swal-actual').value;
            const nueva = document.getElementById('swal-nueva').value;
            const confirmar = document.getElementById('swal-confirmar').value;
            if (!actual || !nueva || !confirmar) {
                Swal.showValidationMessage('Completa los tres campos.');
                return false;
            }
            return { actual, nueva, confirmar };
        }
    });

    if (!isConfirmed) return;

    const json = await llamarPerfilAdmin('cambiar_password', formValues);
    if (json.ok) {
        Swal.fire('Listo', json.msg, 'success');
    } else {
        Swal.fire('Error', json.msg, 'error');
    }
});

cargarPerfilAdmin();
</script>

<?php require __DIR__ . '/footer.php'; ?>