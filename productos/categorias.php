<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../controllers/CategoriaController.php';

$activePage   = 'productos';
$pageTitle    = 'Categorías de producto';
$pageSubtitle = 'Ganchos, colgadores, matamoscas, etc.';

$controller = new CategoriaController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        switch ($_POST['accion']) {
            case 'guardar':
                $result = $controller->save([
                    'id' => $_POST['id'] ?? null,
                    'nombre' => trim($_POST['nombre'] ?? ''),
                    'descripcion' => trim($_POST['descripcion'] ?? ''),
                ]);
                echo json_encode($result);
                exit;

            case 'eliminar':
                $id = $_POST['id'] ?? null;
                echo json_encode($controller->delete((int) $id));
                exit;

            case 'obtener':
                $data = $controller->getById((int) ($_POST['id'] ?? 0));
                echo json_encode(['ok' => true, 'data' => $data]);
                exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

$categorias = $controller->getAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="pc-card">
    <div class="pc-card-header">
        <h2>Categorías</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nueva categoría
        </button>
    </div>

    <table class="pc-table">
        <thead><tr><th>Nombre</th><th>Descripción</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($categorias as $c): ?>
            <tr id="fila-<?= $c['id'] ?>">
                <td><?= htmlspecialchars($c['nombre']) ?></td>
                <td><?= htmlspecialchars($c['descripcion'] ?? '-') ?></td>
                <td>
                    <button class="pc-icon-btn" onclick="abrirModalEditar(<?= $c['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                    <button class="pc-icon-btn" onclick="eliminarCategoria(<?= $c['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="modalCategoria" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formCategoria">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCategoriaTitulo">Nueva categoría</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="cat_id">
          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="cat_nombre" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="descripcion" id="cat_descripcion"></textarea>
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
const modalCategoria = new bootstrap.Modal(document.getElementById('modalCategoria'));

function abrirModalCrear() {
    document.getElementById('formCategoria').reset();
    document.getElementById('cat_id').value = '';
    document.getElementById('modalCategoriaTitulo').textContent = 'Nueva categoría';
    modalCategoria.show();
}

async function abrirModalEditar(id) {
    const resp = await fetch('categorias.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `accion=obtener&id=${id}`
    });
    const json = await resp.json();
    if (!json.ok) { Swal.fire('Error', json.msg, 'error'); return; }
    document.getElementById('modalCategoriaTitulo').textContent = 'Editar categoría';
    document.getElementById('cat_id').value = json.data.id;
    document.getElementById('cat_nombre').value = json.data.nombre;
    document.getElementById('cat_descripcion').value = json.data.descripcion ?? '';
    modalCategoria.show();
}

document.getElementById('formCategoria').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'guardar');
    const resp = await fetch('categorias.php', { method: 'POST', body: formData });
    const json = await resp.json();
    if (json.ok) {
        Swal.fire('Listo', json.msg, 'success').then(() => location.reload());
    } else {
        Swal.fire('Error', json.msg, 'error');
    }
});

function eliminarCategoria(id) {
    Swal.fire({
        title: '¿Eliminar categoría?', icon: 'warning',
        showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const resp = await fetch('categorias.php', {
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

<?php require __DIR__ . '/../includes/footer.php'; ?>