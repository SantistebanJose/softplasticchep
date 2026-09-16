<?php

require __DIR__ . '/controllers/bd.php';
require __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/clssVerificarSession.php';

iniciarSesionSegura();

// Para peticiones AJAX devolvemos JSON; para navegación normal, redirigimos.
if (empty($_SESSION['usuario_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        responderAcceso(401, 'Debes iniciar sesión para acceder a este recurso.');
    }

    header('Location: login.php');
    exit;
}

// El módulo de usuarios es exclusivo para administradores.
$usuarioSesion = exigirSesion();
exigirRol($usuarioSesion, ['administrador']);

$pdo = conectar_oll_BD();

$activePage   = 'usuarios';
$pageTitle    = 'Usuarios';
$pageSubtitle = 'Crear y gestionar accesos';

$controller = new UserController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        switch ((string) $_POST['accion']) {
            case 'listar':
                $usuarios = $controller->getAllUsers(
                    trim($_POST['texto'] ?? ''),
                    trim($_POST['estado'] ?? '')
                );

                echo json_encode([
                    'ok'       => true,
                    'usuarios' => $usuarios,
                ]);
                exit;

            case 'guardar':
                // En creación el input hidden id llega como cadena vacía.
                $id = trim((string) ($_POST['id'] ?? ''));

                $data = [
                    'id'              => $id === '' ? null : (int) $id,
                    'user_'           => trim($_POST['user_'] ?? ''),
                    // No se aplica trim a contraseñas: los espacios son válidos.
                    'password'        => (string) ($_POST['password'] ?? ''),
                    'confirm_password'=> (string) ($_POST['confirm_password'] ?? ''),
                    'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
                    'rol_y_perfiles'  => [
                        'rol'      => $_POST['rol'] ?? 'operario',
                        'perfiles' => [],
                    ],
                ];

                if ($data['password'] !== $data['confirm_password']) {
                    echo json_encode([
                        'ok'  => false,
                        'msg' => 'Las contraseñas no coinciden.',
                    ]);
                    exit;
                }

                echo json_encode($controller->saveUser($data));
                exit;

            case 'obtener':
                $id = (int) ($_POST['id'] ?? 0);

                if ($id <= 0) {
                    echo json_encode([
                        'ok'  => false,
                        'msg' => 'ID inválido.',
                    ]);
                    exit;
                }

                $usuario = $controller->getById($id);

                if (empty($usuario)) {
                    echo json_encode([
                        'ok'  => false,
                        'msg' => 'Usuario no encontrado.',
                    ]);
                    exit;
                }

                echo json_encode([
                    'ok'   => true,
                    'data' => $usuario,
                ]);
                exit;

            case 'eliminar':
                $id = (int) ($_POST['id'] ?? 0);

                if ($id <= 0) {
                    echo json_encode([
                        'ok'  => false,
                        'msg' => 'ID inválido.',
                    ]);
                    exit;
                }

                // Evita que el administrador actual se elimine a sí mismo.
                if ($id === (int) $usuarioSesion['id']) {
                    echo json_encode([
                        'ok'  => false,
                        'msg' => 'No puedes eliminar tu propia cuenta mientras tienes la sesión activa.',
                    ]);
                    exit;
                }

                echo json_encode($controller->deleteUser($id));
                exit;

            default:
                http_response_code(400);

                echo json_encode([
                    'ok'  => false,
                    'msg' => 'Acción no reconocida.',
                ]);
                exit;
        }
    } catch (PDOException $e) {
        error_log('Error de base de datos en usuarios.php: ' . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            'ok'  => false,
            'msg' => 'Error de base de datos.',
        ]);
        exit;

    } catch (Throwable $e) {
        error_log('Error inesperado en usuarios.php: ' . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            'ok'  => false,
            'msg' => 'Error inesperado en el servidor.',
        ]);
        exit;
    }
}

require __DIR__ . '/header.php';
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Usuarios</h2>

        <button class="pc-btn pc-btn-primary" type="button" onclick="abrirModalCrear()">
            <i class="fa-solid fa-user-plus"></i> Nuevo usuario
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input
            type="text"
            id="fusu_texto"
            class="form-control"
            style="max-width:260px"
            placeholder="Buscar por usuario o nombre..."
        >

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
                <tr>
                    <td colspan="7" style="text-align:center;">Cargando...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div
    class="modal fade"
    id="modalUsuario"
    tabindex="-1"
    aria-labelledby="modalUsuarioTitulo"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formUsuario">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUsuarioTitulo">Nuevo usuario</h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"
                    ></button>
                </div>

                <div class="modal-body">
                    <div
                        id="errorFormularioUsuario"
                        class="alert alert-danger d-none"
                        role="alert"
                    ></div>

                    <input type="hidden" name="id" id="usu_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="usu_user">Usuario *</label>

                            <input
                                type="text"
                                class="form-control"
                                name="user_"
                                id="usu_user"
                                required
                            >
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="usu_nombre">Nombre completo *</label>

                            <input
                                type="text"
                                class="form-control"
                                name="nombre_completo"
                                id="usu_nombre"
                                required
                            >
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="usu_rol">Rol *</label>

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
                            <label class="form-label" for="usu_password">
                                Contraseña <small>(obligatoria al crear)</small>
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                name="password"
                                id="usu_password"
                            >
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="usu_confirm_password">
                                Confirmar contraseña
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                name="confirm_password"
                                id="usu_confirm_password"
                            >
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <strong>Nota:</strong> al editar un usuario puedes dejar la contraseña vacía para mantenerla.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Guardar usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const elementoModalUsuario = document.getElementById('modalUsuario');
const modalUsuario = new bootstrap.Modal(elementoModalUsuario);
const errorFormularioUsuario = document.getElementById('errorFormularioUsuario');

function escaparHtml(valor) {
    const elemento = document.createElement('div');
    elemento.textContent = valor ?? '';
    return elemento.innerHTML;
}

function obtenerRol(rolYPerfiles) {
    try {
        const datos = JSON.parse(rolYPerfiles || '{}');
        return datos.rol || 'operario';
    } catch {
        return 'operario';
    }
}

function ocultarErrorFormulario() {
    errorFormularioUsuario.textContent = '';
    errorFormularioUsuario.classList.add('d-none');
}

function mostrarErrorFormulario(mensaje) {
    errorFormularioUsuario.textContent = mensaje || 'No se pudo guardar el usuario.';
    errorFormularioUsuario.classList.remove('d-none');
}

function alertaDespuesDeCerrarModal(titulo, mensaje, tipo) {
    elementoModalUsuario.addEventListener('hidden.bs.modal', () => {
        Swal.fire(titulo, mensaje, tipo);
    }, { once: true });

    modalUsuario.hide();
}

async function llamarUsuarios(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });

    const resp = await fetch('usuarios.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body
    });

    const texto = await resp.text();

    try {
        return JSON.parse(texto);
    } catch {
        console.error(`Respuesta no válida para accion=${accion}:`, texto);
        throw new Error('El servidor no devolvió una respuesta JSON válida.');
    }
}

async function cargarUsuarios() {
    const texto = document.getElementById('fusu_texto').value.trim();
    const estado = document.getElementById('fusu_estado').value;
    const json = await llamarUsuarios('listar', { texto, estado });
    const tbody = document.getElementById('tbodyUsuarios');

    if (!json.ok) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center;">
                    ${escaparHtml(json.msg)}
                </td>
            </tr>
        `;
        return;
    }

    const usuarios = json.usuarios || [];

    if (usuarios.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center;">
                    No hay usuarios registrados.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = usuarios.map(usuario => {
        const rol = obtenerRol(usuario.rol_y_perfiles);
        const creado = usuario.created_at
            ? new Date(usuario.created_at).toLocaleDateString('es-PE')
            : '-';

        return `
            <tr id="fila-${Number(usuario.id)}">
                <td data-label="Usuario">${escaparHtml(usuario.user_)}</td>
                <td data-label="Nombre completo">${escaparHtml(usuario.nombre_completo)}</td>
                <td data-label="Rol">${escaparHtml(rol)}</td>

                <td data-label="Origen">
                    ${usuario.operario_id
                        ? '<span class="badge bg-info">Operario</span>'
                        : '<span class="badge bg-secondary">Manual</span>'
                    }
                </td>

                <td data-label="Estado">
                    ${usuario.deleted_at
                        ? '<span class="badge bg-secondary">Inactivo</span>'
                        : '<span class="badge bg-success">Activo</span>'
                    }
                </td>

                <td data-label="Creado">${escaparHtml(creado)}</td>

                <td data-label="Acciones" class="pc-td-acciones">
                    <button
                        class="pc-icon-btn"
                        type="button"
                        onclick="abrirModalEditar(${Number(usuario.id)})"
                        title="Editar usuario"
                    >
                        <i class="fa-solid fa-pen"></i>
                    </button>

                    ${!usuario.deleted_at
                        ? `
                            <button
                                class="pc-icon-btn"
                                type="button"
                                onclick="eliminarUsuario(${Number(usuario.id)})"
                                title="Eliminar usuario"
                            >
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        `
                        : ''
                    }
                </td>
            </tr>
        `;
    }).join('');
}

function abrirModalCrear() {
    document.getElementById('formUsuario').reset();
    document.getElementById('usu_id').value = '';
    document.getElementById('modalUsuarioTitulo').textContent = 'Nuevo usuario';

    ocultarErrorFormulario();
    modalUsuario.show();
}

async function abrirModalEditar(id) {
    try {
        const json = await llamarUsuarios('obtener', { id });

        if (!json.ok) {
            Swal.fire('Error', json.msg, 'error');
            return;
        }

        const usuario = json.data;
        const rol = obtenerRol(usuario.rol_y_perfiles);

        document.getElementById('modalUsuarioTitulo').textContent = 'Editar usuario';
        document.getElementById('usu_id').value = usuario.id || '';
        document.getElementById('usu_user').value = usuario.user_ || '';
        document.getElementById('usu_nombre').value = usuario.nombre_completo || '';
        document.getElementById('usu_rol').value = rol;
        document.getElementById('usu_password').value = '';
        document.getElementById('usu_confirm_password').value = '';

        ocultarErrorFormulario();
        modalUsuario.show();
    } catch (error) {
        console.error('Error obteniendo usuario:', error);
        Swal.fire('Error', 'No se pudo obtener el usuario.', 'error');
    }
}

document.getElementById('formUsuario').addEventListener('submit', async function (evento) {
    evento.preventDefault();
    ocultarErrorFormulario();

    try {
        const formData = new FormData(this);
        formData.append('accion', 'guardar');

        const respuesta = await fetch('usuarios.php', {
            method: 'POST',
            body: formData
        });

        const json = await respuesta.json();

        if (json.ok) {
            alertaDespuesDeCerrarModal('Listo', json.msg, 'success');
            cargarUsuarios();
            return;
        }

        mostrarErrorFormulario(json.msg);
    } catch (error) {
        console.error('Error guardando usuario:', error);
        mostrarErrorFormulario(
            'No se pudo procesar la respuesta del servidor. Inténtalo nuevamente.'
        );
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
    }).then(async resultado => {
        if (!resultado.isConfirmed) {
            return;
        }

        try {
            const json = await llamarUsuarios('eliminar', { id });

            if (json.ok) {
                Swal.fire('Eliminado', json.msg, 'success');
                cargarUsuarios();
                return;
            }

            Swal.fire('Error', json.msg, 'error');
        } catch (error) {
            console.error('Error eliminando usuario:', error);
            Swal.fire('Error', 'No se pudo eliminar el usuario.', 'error');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    cargarUsuarios().catch(error => {
        console.error('Error cargando usuarios:', error);

        document.getElementById('tbodyUsuarios').innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center;color:red;">
                    Error de conexión con el servidor. Revisa la consola.
                </td>
            </tr>
        `;
    });

    let temporizadorBusqueda = null;

    document.getElementById('fusu_texto').addEventListener('input', () => {
        clearTimeout(temporizadorBusqueda);

        temporizadorBusqueda = setTimeout(() => {
            cargarUsuarios();
        }, 350);
    });

    document.getElementById('fusu_estado').addEventListener('change', cargarUsuarios);
});
</script>

<?php require __DIR__ . '/footer.php'; ?>