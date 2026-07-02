<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../controllers/ModelController.php';
require __DIR__ . '/../controllers/CategoriaController.php';

$activePage   = 'productos';
$pageTitle    = 'Modelos de producto';
$pageSubtitle = 'Modelos por categoría';

$controller = new ModelController($pdo);
$categoriaController = new CategoriaController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        switch ($_POST['accion']) {
            case 'guardar':
                $result = $controller->save([
                    'id' => $_POST['id'] ?? null,
                    'categoria_id' => $_POST['categoria_id'] ?? null,
                    'nombre' => trim($_POST['nombre'] ?? ''),
                ]);
                echo json_encode($result);
                exit;

            case 'eliminar':
                $id = $_POST['id'] ?? null;
                if (!$id) {
                    echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
                    exit;
                }
                echo json_encode($controller->delete((int) $id));
                exit;

            case 'obtener':
                $id = $_POST['id'] ?? null;
                $data = $controller->getById((int) $id);
                echo json_encode(['ok' => true, 'data' => $data]);
                exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
        exit;
    }
}

$modelos = $controller->getAll();
$categorias = $categoriaController->getAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="pc-card">
    <div class="pc-card-header">
        <h2>Modelos</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nuevo modelo
        </button>
    </div>

    <table class="pc-table">
        <thead><tr><th>Modelo</th><th>Categoría</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($modelos as $m): ?>
            <tr id="fila-<?= $m['id'] ?>">
                <td><?= htmlspecialchars($m['nombre']) ?></td>
                <td><?= htmlspecialchars($m['categoria_nombre']) ?></td>
                <td>
                    <button class="pc-icon-btn" onclick="abrirModalEditar(<?= $m['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                    <button class="pc-icon-btn" onclick="eliminarModelo(<?= $m['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($modelos)): ?>
            <tr><td colspan="3" style="text-align:center;">No hay modelos registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="modalModelo" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formModelo">
        <div class="modal-header">
          <h5 class="modal-title" id="modalModeloTitulo">Nuevo modelo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="modelo_id">
          <div class="mb-2">
            <label class="form-label">Categoría *</label>
            <select class="form-select" name="categoria_id" id="modelo_categoria" required>
              <option value="">Seleccione...</option>
              <?php foreach ($categorias as $categoria): ?>
                <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Nombre del modelo *</label>
            <input type="text" class="form-control" name="nombre" id="modelo_nombre" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const modalModelo = new bootstrap.Modal(document.getElementById('modalModelo'));

function abrirModalCrear() {
    document.getElementById('formModelo').reset();
    document.getElementById('modelo_id').value = '';
    document.getElementById('modalModeloTitulo').textContent = 'Nuevo modelo';
    modalModelo.show();
}

async function abrirModalEditar(id) {
    const resp = await fetch('modelos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `accion=obtener&id=${id}`
    });
    const json = await resp.json();
    if (!json.ok) { Swal.fire('Error', json.msg, 'error'); return; }

    document.getElementById('modalModeloTitulo').textContent = 'Editar modelo';
    document.getElementById('modelo_id').value = json.data.id;
    document.getElementById('modelo_categoria').value = json.data.categoria_id ?? '';
    document.getElementById('modelo_nombre').value = json.data.nombre ?? '';
    modalModelo.show();
}

document.getElementById('formModelo').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'guardar');

    const resp = await fetch('modelos.php', { method: 'POST', body: formData });
    const json = await resp.json();
    if (json.ok) {
        Swal.fire('Listo', json.msg, 'success').then(() => location.reload());
    } else {
        Swal.fire('Error', json.msg, 'error');
    }
});

function eliminarModelo(id) {
    Swal.fire({
        title: '¿Eliminar modelo?',
        text: 'Esta acción lo desactivará del catálogo.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const resp = await fetch('modelos.php', {
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

<?php require __DIR__ . '/../includes/footer.php';
