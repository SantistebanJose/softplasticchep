<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/controllers/UserController.php';

$activePage   = 'usuarios';
$pageTitle    = 'Usuarios';
$pageSubtitle = 'Crear y gestionar accesos';

$controller = new UserController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        switch ($_POST['accion']) {
            case 'guardar':
                $data = [
                    'id' => $_POST['id'] ?? null,
                    'user_' => trim($_POST['user_'] ?? ''),
                    'password' => trim($_POST['password'] ?? ''),
                    'confirm_password' => trim($_POST['confirm_password'] ?? ''),
                    'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
                    'rol_y_perfiles' => ['rol' => $_POST['rol'] ?? 'operario', 'perfiles' => []],
                ];
                if ($data['password'] !== $data['confirm_password']) {
                    echo json_encode(['ok' => false, 'msg' => 'Las contraseñas no coinciden.']);
                    exit;
                }
                echo json_encode($controller->saveUser($data));
                exit;

            case 'obtener':
                $id = $_POST['id'] ?? null;
                if (!$id) {
                    echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
                    exit;
                }
                $usuario = $controller->getById((int) $id);
                echo json_encode(['ok' => true, 'data' => $usuario]);
                exit;

            case 'eliminar':
                $id = $_POST['id'] ?? null;
                if (!$id) {
                    echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
                    exit;
                }
                echo json_encode($controller->deleteUser((int) $id));
                exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
        exit;
    }
}

$usuarios = $controller->getAllUsers();
require __DIR__ . '/includes/header.php';
?>

<div class="pc-card">
    <div class="pc-card-header">
        <h2>Usuarios</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-user-plus"></i> Nuevo usuario
        </button>
    </div>

    <table class="pc-table" id="tablaUsuarios">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nombre completo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Creado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <?php $rolData = json_decode($u['rol_y_perfiles'] ?? '{}', true); ?>
            <tr id="fila-<?= $u['id'] ?>">
                <td><?= htmlspecialchars($u['user_']) ?></td>
                <td><?= htmlspecialchars($u['nombre_completo']) ?></td>
                <td><?= htmlspecialchars($rolData['rol'] ?? 'operario') ?></td>
                <td><?= $u['deleted_at'] ? '<span class="text-danger">Inactivo</span>' : '<span class="text-success">Activo</span>' ?></td>
                <td><?= htmlspecialchars(date('d/m/Y', strtotime($u['created_at'] ?? 'now'))) ?></td>
                <td>
                    <button class="pc-icon-btn" onclick="abrirModalEditar(<?= $u['id'] ?>)" title="Editar usuario">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <?php if (!$u['deleted_at']): ?>
                        <button class="pc-icon-btn" onclick="eliminarUsuario(<?= $u['id'] ?>)" title="Eliminar usuario">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($usuarios)): ?>
            <tr><td colspan="6" style="text-align:center;">No hay usuarios registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="modalUsuario" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="formUsuario">
        <div class="modal-header">
          <h5 class="modal-title" id="modalUsuarioTitulo">Nuevo usuario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="usu_id">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Usuario *</label>
              <input type="text" class="form-control" name="user_" id="usu_user" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Nombre completo *</label>
              <input type="text" class="form-control" name="nombre_completo" id="usu_nombre" required>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Rol *</label>
              <select class="form-select" name="rol" id="usu_rol" required>
                <option value="operario">Operario</option>
                <option value="administrador">Administrador</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Estado</label>
              <input type="text" class="form-control" value="Activo" disabled>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Contraseña <?= '<small>(obligatoria al crear)</small>' ?></label>
              <input type="password" class="form-control" name="password" id="usu_password">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Confirmar contraseña</label>
              <input type="password" class="form-control" name="confirm_password" id="usu_confirm_password">
            </div>
          </div>

          <div class="alert alert-info">
            <strong>Nota:</strong> al editar un usuario puedes dejar la contraseña vacía para mantenerla.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar usuario</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const modalUsuario = new bootstrap.Modal(document.getElementById('modalUsuario'));

function abrirModalCrear() {
    document.getElementById('formUsuario').reset();
    document.getElementById('usu_id').value = '';
    document.getElementById('modalUsuarioTitulo').textContent = 'Nuevo usuario';
    modalUsuario.show();
}

async function abrirModalEditar(id) {
    const resp = await fetch('usuarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `accion=obtener&id=${id}`
    });
    const json = await resp.json();
    if (!json.ok) {
        Swal.fire('Error', json.msg, 'error');
        return;
    }

    const user = json.data;
    const roleData = JSON.parse(user.rol_y_perfiles || '{}');

    document.getElementById('modalUsuarioTitulo').textContent = 'Editar usuario';
    document.getElementById('usu_id').value = user.id;
    document.getElementById('usu_user').value = user.user_ || '';
    document.getElementById('usu_nombre').value = user.nombre_completo || '';
    document.getElementById('usu_rol').value = roleData.rol || 'operario';
    document.getElementById('usu_password').value = '';
    document.getElementById('usu_confirm_password').value = '';
    modalUsuario.show();
}

document.getElementById('formUsuario').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'guardar');

    const resp = await fetch('usuarios.php', { method: 'POST', body: formData });
    const json = await resp.json();
    if (json.ok) {
        Swal.fire('Listo', json.msg, 'success').then(() => location.reload());
    } else {
        Swal.fire('Error', json.msg, 'error');
    }
});

function eliminarUsuario(id) {
    Swal.fire({
        title: '¿Eliminar usuario?',
        text: 'El usuario dejará de estar activo en el sistema.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const resp = await fetch('usuarios.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `accion=eliminar&id=${id}`
        });
        const json = await resp.json();
        if (json.ok) {
            document.getElementById(`fila-${id}`).remove();
            Swal.fire('Eliminado', json.msg, 'success');
        } else {
            Swal.fire('Error', json.msg, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>