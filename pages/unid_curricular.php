<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

// =======================
// ACCIONES AJAX
// =======================
// =======================
// ACCIONES AJAX
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $accion = $_POST['accion'];

    if ($accion === 'crear_periodo') {
        $codigo = trim($_POST['codigo']);
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];

        $stmt = $conn->prepare("SELECT COUNT(*) FROM periodo WHERE codigo = ?");
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        $stmt->bind_result($existe);
        $stmt->fetch();
        $stmt->close();

        if ($existe > 0) {
            echo json_encode(['success' => false, 'duplicado' => true]);
        } else {
            $stmt = $conn->prepare("INSERT INTO periodo (codigo, fecha_inicio, fecha_fin) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $codigo, $fecha_inicio, $fecha_fin);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $stmt->insert_id, 'codigo' => $codigo]);
            } else {
                echo json_encode(['success' => false]);
            }
            $stmt->close();
        }
        exit;
    }

    if ($accion === 'crear_unidad') {
        $stmt = $conn->prepare("INSERT INTO unidad_curricular (nombre, grupo, valor, id_periodo, id_sede) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdii", $_POST['nombre'], $_POST['grupo'], $_POST['valor'], $_POST['id_periodo'], $_POST['id_sede']);
        $success = $stmt->execute();
        echo json_encode(['success' => $success]);
        $stmt->close();
        exit;
    }

    if ($accion === 'editar') {
        $stmt = $conn->prepare("UPDATE unidad_curricular SET nombre=?, grupo=?, valor=?, id_periodo=?, id_sede=? WHERE id_unidad=?");
        $stmt->bind_param("ssdiii", $_POST['nombre'], $_POST['grupo'], $_POST['valor'], $_POST['id_periodo'], $_POST['id_sede'], $_POST['id_unidad']);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }

    // Eliminar unidad curricular con verificación para alerta de errores
    if ($accion === 'eliminar') {
        $id = intval($_POST['id_unidad']);

        try {
            $stmt = $conn->prepare("DELETE FROM unidad_curricular WHERE id_unidad = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            $success = $stmt->affected_rows > 0;
            echo json_encode([
                'success' => $success,
                'msg' => $success 
                    ? 'Unidad eliminada correctamente.'
                    : 'No se encontró la unidad a eliminar.'
            ]);

            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // Verifica que sea un error por clave foránea (código 1451)
            if ($e->getCode() == 1451) {
                echo json_encode([
                    'success' => false,
                    'msg' => 'Esta acción no se puede realizar porque la unidad está asignada a un docente.'
                ]);
            } else {
                // Otro tipo de error
                echo json_encode([
                    'success' => false,
                    'msg' => 'Error inesperado: ' . $e->getMessage()
                ]);
            }
        }

        exit;
    }
}



$unidades = $conn->query("
  SELECT u.*, p.codigo AS cohorte, s.nombre AS sede_nombre
  FROM unidad_curricular u
  JOIN periodo p ON u.id_periodo = p.id_periodo
  JOIN sede s ON u.id_sede = s.id_sede
  ORDER BY p.codigo DESC, u.nombre
");
$periodos = $conn->query("SELECT id_periodo, codigo FROM periodo ORDER BY codigo DESC");
$sedes = $conn->query("SELECT id_sede, nombre FROM sede ORDER BY nombre");
$catalogo_unidades = $conn->query("SELECT * FROM catalogo_unidades ORDER BY nombre ASC");

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-5">
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center rounded-top-4">
      <h5 class="mb-0">Gestión de Unidades Curriculares</h5>
      <button class="btn btn-light btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#formRegistroUnidad">
        ➕ Nueva Unidad
      </button>
    </div>
    <div class="collapse" id="formRegistroUnidad">
      <div class="card-body">
        <form id="formUnidad" class="row g-3">
          <input type="hidden" name="accion" value="crear_unidad">
          <input type="hidden" name="nombre" id="hidden_nombre_unidad">
          <div class="col-md-3">
            <label class="form-label fw-semibold">Nombre (del Catálogo)</label>
            <select id="select_catalogo_unidad" class="form-select" required>
                <option value="">Seleccione una unidad...</option>
                <?php while($cat = $catalogo_unidades->fetch_assoc()): ?>
                <option value="<?= $cat['id'] ?>" data-valor="<?= $cat['valor_base'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Grupo</label>
            <input type="text" name="grupo" class="form-control" required>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Valor</label>
            <input type="number" step="0.01" name="valor" id="input_valor_unidad" class="form-control" required readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Cohorte</label>
            <div class="d-flex">
              <select name="id_periodo" id="select_periodo" class="form-select me-2" required>
                <option value="">Seleccione…</option>
                <?php $periodos->data_seek(0); while($p = $periodos->fetch_assoc()): ?>
                  <option value="<?= $p['id_periodo'] ?>"><?= htmlspecialchars($p['codigo']) ?></option>
                <?php endwhile; ?>
              </select>
              <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalPeriodo">+</button>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Sede</label>
            <select name="id_sede" class="form-select" required>
              <option value="">Seleccione</option>
              <?php $sedes->data_seek(0); while($s = $sedes->fetch_assoc()): ?>
                <option value="<?= $s['id_sede'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-12 text-end">
            <button type="submit" class="btn btn-success fw-semibold">Registrar Unidad</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="card mt-4 shadow-sm border-0 rounded-4">
    <div class="card-body">
      <div class="mb-3">
        <input type="text" id="filtroTabla" class="form-control shadow-sm" placeholder="🔍 Buscar por nombre, grupo, cohorte o sede...">
      </div>
      <div class="table-responsive">
        <table id="tablaUnidades" class="table table-bordered table-hover align-middle rounded-4 overflow-hidden">
          <thead class="table-dark text-center">
            <tr>
              <th>#</th>
              <th>Nombre</th>
              <th>Grupo</th>
              <th>Valor</th>
              <th>Cohorte</th>
              <th>Sede</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php $i=1; while($u = $unidades->fetch_assoc()): ?>
            <tr>
              <td class="text-center"><?= $i++ ?></td>
              <td><?= htmlspecialchars($u['nombre']) ?></td>
              <td><?= htmlspecialchars($u['grupo']) ?></td>
              <td><?= number_format($u['valor'], 2) ?></td>
              <td><?= htmlspecialchars($u['cohorte']) ?></td>
              <td><?= htmlspecialchars($u['sede_nombre']) ?></td>
              <td class="text-center">
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-warning" onclick='editarUnidad(<?= json_encode($u) ?>)'>Editar</button>
                  <button class="btn btn-outline-danger" onclick="eliminarUnidad(<?= $u['id_unidad'] ?>)">Eliminar</button>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex justify-content-center mt-3">
        <ul class="pagination" id="pagination"></ul>
      </div>
    </div>
  </div>
</div>

<!-- Modal de Edición -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog">
    <form id="formEditar" class="modal-content rounded-4 border-0 shadow-sm">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_unidad" id="edit_id_unidad">
      <div class="modal-header bg-primary text-white rounded-top-4">
        <h5 class="modal-title">Editar Unidad</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
        <label class="form-label mt-2">Grupo</label>
        <input type="text" name="grupo" id="edit_grupo" class="form-control" required>
        <label class="form-label mt-2">Valor</label>
        <input type="number" step="0.01" name="valor" id="edit_valor" class="form-control" required>
        <label class="form-label mt-2">Cohorte</label>
        <select name="id_periodo" id="edit_id_periodo" class="form-select" required>
          <?php $periodos->data_seek(0); while($p = $periodos->fetch_assoc()): ?>
            <option value="<?= $p['id_periodo'] ?>"><?= htmlspecialchars($p['codigo']) ?></option>
          <?php endwhile; ?>
        </select>
        <label class="form-label mt-2">Sede</label>
        <select name="id_sede" id="edit_id_sede" class="form-select" required>
          <?php $sedes->data_seek(0); while($s = $sedes->fetch_assoc()): ?>
            <option value="<?= $s['id_sede'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Guardar Cambios</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal para crear cohorte -->
<div class="modal fade" id="modalPeriodo" tabindex="-1">
  <div class="modal-dialog">
    <form id="formPeriodo" class="modal-content rounded-4 border-0 shadow-sm">
      <input type="hidden" name="accion" value="crear_periodo">
      <div class="modal-header bg-primary text-white rounded-top-4">
        <h5 class="modal-title">Registrar Cohorte</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Código</label>
        <input type="text" name="codigo" class="form-control" required>
        <label class="form-label mt-2">Fecha Inicio</label>
        <input type="date" name="fecha_inicio" class="form-control" required>
        <label class="form-label mt-2">Fecha Fin</label>
        <input type="date" name="fecha_fin" class="form-control" required>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Guardar Cohorte</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Script para auto-rellenar valor desde el catálogo
  const selectCatalogo = document.getElementById('select_catalogo_unidad');
  const inputValor = document.getElementById('input_valor_unidad');
  const hiddenNombre = document.getElementById('hidden_nombre_unidad');

  selectCatalogo.addEventListener('change', function() {
      const selectedOption = this.options[this.selectedIndex];
      const valor = selectedOption.getAttribute('data-valor');
      const nombre = selectedOption.text;

      if (valor) {
          inputValor.value = valor;
          hiddenNombre.value = nombre;
      } else {
          inputValor.value = '';
          hiddenNombre.value = '';
      }
  });

  // Lógica existente de la página
  const table = document.querySelector('#tablaUnidades tbody');
  const rows = Array.from(table.getElementsByTagName('tr'));
  const pagination = document.getElementById('pagination');
  let currentPage = 1, rowsPerPage = 10, filteredRows = [...rows];

  function displayRows(page) {
    rows.forEach(r => r.style.display = 'none');
    const start = (page - 1) * rowsPerPage;
    filteredRows.slice(start, start + rowsPerPage).forEach(r => r.style.display = '');
  }

  function setupPagination() {
    pagination.innerHTML = '';
    const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
    if (totalPages <= 1) return;

    const buildItem = (label, disabled = false, active = false) =>
      `<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
         <a class="page-link" href="#">${label}</a>
       </li>`;

    pagination.insertAdjacentHTML('beforeend', buildItem('Anterior', currentPage === 1));
    for (let i = 1; i <= totalPages; i++) {
      pagination.insertAdjacentHTML('beforeend', buildItem(i, false, i === currentPage));
    }
    pagination.insertAdjacentHTML('beforeend', buildItem('Siguiente', currentPage === totalPages));
    pagination.querySelectorAll('.page-link').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        const txt = btn.textContent;
        if (txt === 'Anterior' && currentPage > 1) currentPage--;
        else if (txt === 'Siguiente' && currentPage < totalPages) currentPage++;
        else if (!isNaN(txt)) currentPage = parseInt(txt);
        displayRows(currentPage);
        setupPagination();
      });
    });
  }

  document.getElementById('filtroTabla').addEventListener('input', function() {
    const term = this.value.toLowerCase();
    filteredRows = rows.filter(r => {
      const tds = r.getElementsByTagName('td');
      return [tds[1], tds[2], tds[4], tds[5]].some(td => td.textContent.toLowerCase().includes(term));
    });
    currentPage = 1;
    displayRows(currentPage);
    setupPagination();
  });

  displayRows(currentPage);
  setupPagination();
});

function editarUnidad(u) {
  document.getElementById('edit_id_unidad').value = u.id_unidad;
  document.getElementById('edit_nombre').value = u.nombre;
  document.getElementById('edit_grupo').value = u.grupo;
  document.getElementById('edit_valor').value = u.valor;
  document.getElementById('edit_id_periodo').value = u.id_periodo;
  document.getElementById('edit_id_sede').value = u.id_sede;
  new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

document.getElementById('formEditar').addEventListener('submit', e => {
  e.preventDefault();
  const form = new FormData(e.target);
  fetch(location.href, { method: 'POST', body: form })
    .then(res => res.json())
    .then(data => {
      if (data.success) Swal.fire('Actualizado', 'Unidad actualizada con éxito', 'success').then(() => location.reload());
      else Swal.fire('Error', 'No se pudo actualizar la unidad', 'error');
    });
});

function eliminarUnidad(id) {
  console.log("Eliminando unidad con ID:", id);
  Swal.fire({
    title: '¿Eliminar unidad?',
    text: "Esta acción no se puede deshacer.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then(result => {
    if (result.isConfirmed) {
      const form = new FormData();
      form.append('accion', 'eliminar');
      form.append('id_unidad', id);
      fetch(location.href, { method: 'POST', body: form })
        .then(res => res.json())
        .then(data => {
  if (data.success) {
    Swal.fire('Eliminado', data.msg, 'success').then(() => location.reload());
  } else {
    Swal.fire('Error', data.msg, 'error');
  }
});

    }
  });
}

document.getElementById('formPeriodo').addEventListener('submit', e => {
  e.preventDefault();
  fetch(location.href, { method: 'POST', body: new FormData(e.target) })
    .then(res => res.json())
    .then(data => {
      if (data.duplicado) Swal.fire('Error', 'Código de cohorte ya existente', 'error');
      else if (data.success) {
        const option = document.createElement('option');
        option.value = data.id;
        option.text = data.codigo;
        option.selected = true;
        document.getElementById('select_periodo').appendChild(option);
        document.getElementById('edit_id_periodo').appendChild(option.cloneNode(true));
        bootstrap.Modal.getInstance(document.getElementById('modalPeriodo')).hide();
        Swal.fire('Éxito', 'Cohorte creado correctamente', 'success');
      } else Swal.fire('Error', 'No se pudo crear el cohorte', 'error');
    });
});

document.getElementById('formUnidad').addEventListener('submit', e => {
  e.preventDefault();
  fetch(location.href, { method: 'POST', body: new FormData(e.target) })
    .then(res => res.json())
    .then(data => {
      if (data.success) Swal.fire('Éxito', 'Unidad registrada correctamente', 'success').then(() => location.reload());
      else Swal.fire('Error', 'No se pudo registrar la unidad', 'error');
    })
    .catch(err => Swal.fire('Error', 'Fallo en la solicitud AJAX: ' + err, 'error'));
});
</script>

<?php include '../includes/footer.php'; ?>
