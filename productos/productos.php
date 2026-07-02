<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../controllers/ProductController.php';
require __DIR__ . '/../controllers/CategoriaController.php';

$activePage  = 'productos';
$pageTitle   = 'Productos';
$pageSubtitle = 'Catálogo de productos terminados';

$controller = new ProductController($pdo);
$categoriaController = new CategoriaController($pdo);
$categorias = $categoriaController->getAll();

// ------------------------------------------------------------------
// Manejo de acciones (AJAX): guardar (crear/editar) y eliminar
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        switch ($_POST['accion']) {
            case 'guardar':
                $result = $controller->save([
                    'id' => $_POST['id'] ?? null,
                    'codigo' => trim($_POST['codigo'] ?? ''),
                    'nombre' => trim($_POST['nombre'] ?? ''),
                    'categoria_id' => $_POST['categoria_id'] ?: null,
                    'modelo_id' => $_POST['modelo_id'] ?: null,
                    'color' => trim($_POST['color'] ?? ''),
                    'medida' => trim($_POST['medida'] ?? ''),
                    'precio' => $_POST['precio'] ?: 0,
                    'stock_minimo' => $_POST['stock_minimo'] ?: 0,
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

            case 'modelos_por_categoria':
                $categoria_id = $_POST['categoria_id'] ?? null;
                echo json_encode(['ok' => true, 'data' => $controller->getModelsByCategory((int) $categoria_id)]);
                exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
        exit;
    }
}

// ------------------------------------------------------------------
// Datos para el listado inicial
// ------------------------------------------------------------------
$productos = $controller->getAllActive();
require __DIR__ . '/../includes/header.php';
?>

<div class="pc-card">
    <div class="pc-card-header">
        <h2>Listado de productos</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nuevo producto
        </button>
    </div>

    <table class="pc-table" id="tablaProductos">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Modelo</th>
                <th>Color</th>
                <th>Medida</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($productos as $p): ?>
            <tr id="fila-<?= $p['id'] ?>">
                <td><?= htmlspecialchars($p['codigo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['nombre']) ?></td>
                <td><?= htmlspecialchars($p['categoria_nombre']) ?></td>
                <td><?= htmlspecialchars($p['modelo_nombre'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['color'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['medida'] ?? '-') ?></td>
                <td>S/ <?= number_format($p['precio'], 2) ?></td>
                <td><?= (int)$p['stock_actual'] ?></td>
                <td>
                    <button class="pc-icon-btn" onclick="abrirModalEditar(<?= $p['id'] ?>)" title="Editar">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button class="pc-icon-btn" onclick="eliminarProducto(<?= $p['id'] ?>)" title="Eliminar">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($productos)): ?>
            <tr><td colspan="9" style="text-align:center;">No hay productos registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Crear/Editar (Bootstrap) -->
<div class="modal fade" id="modalProducto" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formProducto">
        <div class="modal-header">
          <h5 class="modal-title" id="modalProductoTitulo">Nuevo producto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="prod_id">

          <div class="mb-2">
            <label class="form-label">Código</label>
            <input type="text" class="form-control" name="codigo" id="prod_codigo">
          </div>

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="prod_nombre" required>
          </div>

          <div class="row">
            <div class="col-6 mb-2">
              <label class="form-label">Categoría *</label>
              <select class="form-select" name="categoria_id" id="prod_categoria" required onchange="cargarModelos(this.value)">
                <option value="">Seleccione...</option>
                <?php foreach ($categorias as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 mb-2">
              <label class="form-label">Modelo</label>
              <select class="form-select" name="modelo_id" id="prod_modelo">
                <option value="">Seleccione categoría primero</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-6 mb-2">
              <label class="form-label">Color</label>
              <input type="text" class="form-control" name="color" id="prod_color">
            </div>
            <div class="col-6 mb-2">
              <label class="form-label">Medida</label>
              <input type="text" class="form-control" name="medida" id="prod_medida">
            </div>
          </div>

          <div class="row">
            <div class="col-6 mb-2">
              <label class="form-label">Precio (S/)</label>
              <input type="number" step="0.01" min="0" class="form-control" name="precio" id="prod_precio">
            </div>
            <div class="col-6 mb-2">
              <label class="form-label">Stock mínimo</label>
              <input type="number" min="0" class="form-control" name="stock_minimo" id="prod_stock_minimo">
            </div>
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
const modalProducto = new bootstrap.Modal(document.getElementById('modalProducto'));

function abrirModalCrear() {
    document.getElementById('formProducto').reset();
    document.getElementById('prod_id').value = '';
    document.getElementById('prod_modelo').innerHTML = '<option value="">Seleccione categoría primero</option>';
    document.getElementById('modalProductoTitulo').textContent = 'Nuevo producto';
    modalProducto.show();
}

async function abrirModalEditar(id) {
    const resp = await fetch('productos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `accion=obtener&id=${id}`
    });
    const json = await resp.json();
    if (!json.ok) { Swal.fire('Error', json.msg, 'error'); return; }

    const p = json.data;
    document.getElementById('modalProductoTitulo').textContent = 'Editar producto';
    document.getElementById('prod_id').value = p.id;
    document.getElementById('prod_codigo').value = p.codigo ?? '';
    document.getElementById('prod_nombre').value = p.nombre ?? '';
    document.getElementById('prod_color').value = p.color ?? '';
    document.getElementById('prod_medida').value = p.medida ?? '';
    document.getElementById('prod_precio').value = p.precio ?? '';
    document.getElementById('prod_stock_minimo').value = p.stock_minimo ?? '';
    document.getElementById('prod_categoria').value = p.categoria_id ?? '';

    await cargarModelos(p.categoria_id, p.modelo_id);
    modalProducto.show();
}

async function cargarModelos(categoriaId, modeloSeleccionado = null) {
    const select = document.getElementById('prod_modelo');
    select.innerHTML = '<option value="">Cargando...</option>';
    if (!categoriaId) {
        select.innerHTML = '<option value="">Seleccione categoría primero</option>';
        return;
    }
    const resp = await fetch('productos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `accion=modelos_por_categoria&categoria_id=${categoriaId}`
    });
    const json = await resp.json();
    select.innerHTML = '<option value="">Sin modelo específico</option>';
    (json.data || []).forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.nombre;
        if (modeloSeleccionado == m.id) opt.selected = true;
        select.appendChild(opt);
    });
}

document.getElementById('formProducto').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'guardar');

    const resp = await fetch('productos.php', { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.ok) {
        Swal.fire('Listo', json.msg, 'success').then(() => location.reload());
    } else {
        Swal.fire('Error', json.msg, 'error');
    }
});

function eliminarProducto(id) {
    Swal.fire({
        title: '¿Eliminar producto?',
        text: 'Esta acción lo desactivará del catálogo.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const resp = await fetch('productos.php', {
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