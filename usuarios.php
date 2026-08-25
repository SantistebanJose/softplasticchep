<?php
require __DIR__ . '/controllers/bd.php';
require __DIR__ . '/controllers/UserController.php';

$pdo = conectar_oll_BD();

$activePage   = 'usuarios';
$pageTitle    = 'Usuarios';
$pageSubtitle = 'Crear y gestionar accesos';

$controller = new UserController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        switch ($_POST['accion']) {
            case 'listar':
                $usuarios = $controller->getAllUsers(
                    trim($_POST['texto'] ?? ''),
                    trim($_POST['estado'] ?? '')
                );
                echo json_encode(['ok' => true, 'usuarios' => $usuarios]);
                exit;

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
                echo json_encode(['ok' => true, 'data' => $controller->getById((int) $id)]);
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

require __DIR__ . '/header.php'; ?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Usuarios</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-user-plus"></i> Nuevo usuario
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fusu_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por usuario o nombre...">
        <select id="fusu_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaUsuarios">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nombre completo</th>
                <th>Rol</th>
                <th>Origen</th>
                <th>Estado</th>
                <th>Creado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyUsuarios">
            <tr><td colspan="7" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
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
              <label class="form-label">Contraseña <small>(obligatoria al crear)</small></label>
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

document.addEventListener('DOMContentLoaded', () => {
    cargarUsuarios().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyUsuarios').innerHTML =
            `<tr><td colspan="7" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    let debounceTimer = null;
    document.getElementById('fusu_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarUsuarios, 350);
    });

    document.getElementById('fusu_estado').addEventListener('change', cargarUsuarios);
});

async function llamarUsuarios(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch('usuarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    const texto = await resp.text();
    try {
        return JSON.parse(texto);
    } catch (e) {
        console.error(`Respuesta no es JSON válido para accion=${accion}:`, texto);
        throw new Error(`El servidor no devolvió JSON válido (accion=${accion}).`);
    }
}

async function cargarUsuarios() {
    const texto  = document.getElementById('fusu_texto').value.trim();
    const estado = document.getElementById('fusu_estado').value;

    const json = await llamarUsuarios('listar', { texto, estado });
    const tbody = document.getElementById('tbodyUsuarios');

    if (!json.ok) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">${json.msg}</td></tr>`;
        return;
    }

    const usuarios = json.usuarios || [];
    if (usuarios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No hay usuarios registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = usuarios.map(u => {
        const rolData = JSON.parse(u.rol_y_perfiles || '{}');
        return `
        <tr id="fila-${u.id}">
            <td data-label="Usuario">${u.user_}</td>
            <td data-label="Nombre completo">${u.nombre_completo}</td>
            <td data-label="Rol">${rolData.rol ?? 'operario'}</td>
            <td data-label="Origen">${u.operario_id
                ? '<span class="badge bg-info">Operario</span>'
                : '<span class="badge bg-secondary">Manual</span>'}
            </td>
            <td data-label="Estado">${u.deleted_at
                ? '<span class="badge bg-secondary">Inactivo</span>'
                : '<span class="badge bg-success">Activo</span>'}
            </td>
            <td data-label="Creado">${new Date(u.created_at).toLocaleDateString('es-PE')}</td>
            <td data-label="Acciones" class="pc-td-acciones">
                <button class="pc-icon-btn" onclick="abrirModalEditar(${u.id})" title="Editar usuario">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${!u.deleted_at
                    ? `<button class="pc-icon-btn" onclick="eliminarUsuario(${u.id})" title="Eliminar usuario">
                           <i class="fa-solid fa-trash"></i></button>`
                    : ''}
            </td>
        </tr>`;
    }).join('');
}

function abrirModalCrear() {
    document.getElementById('formUsuario').reset();
    document.getElementById('usu_id').value = '';
    document.getElementById('modalUsuarioTitulo').textContent = 'Nuevo usuario';
    modalUsuario.show();
}

async function abrirModalEditar(id) {
    const json = await llamarUsuarios('obtener', { id });
    if (!json.ok) { Swal.fire('Error', json.msg, 'error'); return; }

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
        modalUsuario.hide();
        Swal.fire('Listo', json.msg, 'success');
        cargarUsuarios();
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
        const json = await llamarUsuarios('eliminar', { id });
        if (json.ok) {
            Swal.fire('Eliminado', json.msg, 'success');
            cargarUsuarios();
        } else {
            Swal.fire('Error', json.msg, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>