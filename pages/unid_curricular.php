<?php 
session_start();
include '../config/db.php';

// =======================
// CREAR COHORTE (AJAX)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $accion = $_POST['accion'];

    // CREAR COHORTE
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

    // CREAR UNIDAD CURRICULAR
    if ($accion === 'crear_unidad') {
        $stmt = $conn->prepare("INSERT INTO unidad_curricular (nombre, grupo, valor, id_periodo, id_sede) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdii", $_POST['nombre'], $_POST['grupo'], $_POST['valor'], $_POST['id_periodo'], $_POST['id_sede']);
        $success = $stmt->execute();
        echo json_encode(['success' => $success]);
        exit;
    }

    // EDITAR UNIDAD
    if ($accion === 'editar') {
        $stmt = $conn->prepare("UPDATE unidad_curricular SET nombre=?, grupo=?, valor=?, id_periodo=?, id_sede=? WHERE id_unidad=?");
        $stmt->bind_param("ssdiii", $_POST['nombre'], $_POST['grupo'], $_POST['valor'], $_POST['id_periodo'], $_POST['id_sede'], $_POST['id_unidad']);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }

    // ELIMINAR UNIDAD
    if ($accion === 'eliminar') {
        $stmt = $conn->prepare("DELETE FROM unidad_curricular WHERE id_unidad=?");
        $stmt->bind_param("i", $_POST['id_unidad']);
        $stmt->execute();
        echo json_encode(['success' => $stmt->affected_rows > 0]);
        $stmt->close();
        exit;
    }
}

// CONSULTAS PARA LISTAR
$unidades = $conn->query("
  SELECT u.*, p.codigo AS cohorte, s.nombre AS sede_nombre
  FROM unidad_curricular u
  JOIN periodo p ON u.id_periodo = p.id_periodo
  JOIN sede s ON u.id_sede = s.id_sede
  ORDER BY p.codigo DESC, u.nombre
");
$periodos = $conn->query("SELECT id_periodo, codigo FROM periodo ORDER BY codigo DESC");
$sedes = $conn->query("SELECT id_sede, nombre FROM sede ORDER BY nombre");
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">
  <h2>Unidades Curriculares</h2>



  <!-- FORMULARIO CREAR UNIDAD -->
  <form id="formUnidad" class="row g-3 mb-4">
    <input type="hidden" name="accion" value="crear_unidad">
    <div class="col-md-3">
      <label>Nombre</label>
      <input type="text" name="nombre" class="form-control" required>
    </div>
    <div class="col-md-2">
      <label>Grupo</label>
      <input type="text" name="grupo" class="form-control" required>
    </div>
    <div class="col-md-2">
      <label>Valor</label>
      <input type="number" step="0.01" name="valor" class="form-control" required>
    </div>
    <div class="col-md-3">
      <label>Cohorte</label>
      <div class="d-flex">
        <select name="id_periodo" id="select_periodo" class="form-select me-2" required>
          <option value="">Seleccione…</option>
          <?php $periodos->data_seek(0); while($p = $periodos->fetch_assoc()): ?>
            <option value="<?= $p['id_periodo'] ?>"><?= htmlspecialchars($p['codigo']) ?></option>
          <?php endwhile; ?>
        </select>
        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#modalPeriodo">+</button>
      </div>
    </div>
    <div class="col-md-2">
      <label>Sede</label>
      <select name="id_sede" class="form-select" required>
        <option value="">Seleccione</option>
        <?php $sedes->data_seek(0); while($s = $sedes->fetch_assoc()): ?>
          <option value="<?= $s['id_sede'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-12 d-flex justify-content-end">
      <button type="submit" class="btn btn-success">Registrar Unidad</button>
    </div>
  </form>

    <!-- Campo de búsqueda -->
  <div class="mb-3">
    <input type="text" id="filtroTabla" class="form-control" placeholder="Filtrar por nombre, grupo, cohorte o sede...">
  </div>

  <!-- TABLA DE UNIDADES -->
  <div class="table-responsive">
    <table id="tablaUnidades" class="table table-bordered table-striped">
      <thead class="table-dark">
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
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($u['nombre']) ?></td>
            <td><?= htmlspecialchars($u['grupo']) ?></td>
            <td><?= number_format($u['valor'], 2) ?></td>
            <td><?= htmlspecialchars($u['cohorte']) ?></td>
            <td><?= htmlspecialchars($u['sede_nombre']) ?></td>
            <td>
              <button class="btn btn-warning btn-sm" onclick='editarUnidad(<?= json_encode($u) ?>)'>Editar</button>
              <button class="btn btn-danger btn-sm" onclick="eliminarUnidad(<?= $u['id_unidad'] ?>)">Eliminar</button>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <div class="d-flex justify-content-center mt-3">
    <nav>
      <ul class="pagination" id="pagination"></ul>
    </nav>
  </div>
</div>

<!-- MODAL EDITAR UNIDAD -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog">
    <form id="formEditar" class="modal-content">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_unidad" id="edit_id_unidad">
      <div class="modal-header"><h5>Editar Unidad</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label>Nombre</label><input type="text" name="nombre" id="edit_nombre" class="form-control" required>
        <label>Grupo</label><input type="text" name="grupo" id="edit_grupo" class="form-control" required>
        <label>Valor</label><input type="number" step="0.01" name="valor" id="edit_valor" class="form-control" required>
        <label>Cohorte</label>
        <select name="id_periodo" id="edit_id_periodo" class="form-select" required>
          <?php $periodos->data_seek(0); while($p = $periodos->fetch_assoc()): ?>
            <option value="<?= $p['id_periodo'] ?>"><?= htmlspecialchars($p['codigo']) ?></option>
          <?php endwhile; ?>
        </select>
        <label>Sede</label>
        <select name="id_sede" id="edit_id_sede" class="form-select" required>
          <?php $sedes->data_seek(0); while($s = $sedes->fetch_assoc()): ?>
            <option value="<?= $s['id_sede'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="modal-footer"><button class="btn btn-primary">Guardar Cambios</button></div>
    </form>
  </div>
</div>

<!-- MODAL CREAR COHORTE -->
<div class="modal fade" id="modalPeriodo" tabindex="-1">
  <div class="modal-dialog">
    <form id="formPeriodo" class="modal-content">
      <input type="hidden" name="accion" value="crear_periodo">
      <div class="modal-header"><h5>Registrar Cohorte</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label>Código</label>
        <input type="text" name="codigo" class="form-control" required>
        <label class="mt-2">Fecha Inicio</label>
        <input type="date" name="fecha_inicio" class="form-control" required>
        <label class="mt-2">Fecha Fin</label>
        <input type="date" name="fecha_fin" class="form-control" required>
      </div>
      <div class="modal-footer"><button class="btn btn-primary">Guardar Cohorte</button></div>
    </form>
  </div>
</div>

<script>
// =============================
// PAGINACIÓN
// =============================
document.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('tablaUnidades').getElementsByTagName('tbody')[0];
  const rows = table.getElementsByTagName('tr');
  const rowsPerPage = 10;
  const pagination = document.getElementById('pagination');
  const pageCount = Math.ceil(rows.length / rowsPerPage);
  let currentPage = 1;

  function displayRows(page) {
    for (let i = 0; i < rows.length; i++) rows[i].style.display = 'none';
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    for (let i = start; i < end && i < rows.length; i++) rows[i].style.display = '';
  }

  function setupPagination() {
    pagination.innerHTML = '';
    const prevLi = document.createElement('li');
    prevLi.className = 'page-item ' + (currentPage === 1 ? 'disabled' : '');
    const prevA = document.createElement('a');
    prevA.className = 'page-link';
    prevA.href = '#';
    prevA.innerText = 'Anterior';
    prevA.addEventListener('click', e => { e.preventDefault(); if (currentPage > 1) { currentPage--; displayRows(currentPage); setupPagination(); } });
    prevLi.appendChild(prevA);
    pagination.appendChild(prevLi);

    for (let i = 1; i <= pageCount; i++) {
      const li = document.createElement('li');
      li.className = 'page-item ' + (i === currentPage ? 'active' : '');
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.innerText = i;
      a.addEventListener('click', e => { e.preventDefault(); currentPage = i; displayRows(currentPage); setupPagination(); });
      li.appendChild(a);
      pagination.appendChild(li);
    }

    const nextLi = document.createElement('li');
    nextLi.className = 'page-item ' + (currentPage === pageCount ? 'disabled' : '');
    const nextA = document.createElement('a');
    nextA.className = 'page-link';
    nextA.href = '#';
    nextA.innerText = 'Siguiente';
    nextA.addEventListener('click', e => { e.preventDefault(); if (currentPage < pageCount) { currentPage++; displayRows(currentPage); setupPagination(); } });
    nextLi.appendChild(nextA);
    pagination.appendChild(nextLi);
  }

  displayRows(currentPage);
  setupPagination();
});

// =============================
// FILTRO DE TABLA
// =============================
document.getElementById('filtroTabla').addEventListener('keyup', function() {
  const filtro = this.value.toLowerCase();
  const filas = document.querySelectorAll('#tablaUnidades tbody tr');
  filas.forEach(fila => {
    const textoFila = fila.textContent.toLowerCase();
    fila.style.display = textoFila.includes(filtro) ? '' : 'none';
  });
});

// EDITAR UNIDAD
function editarUnidad(u) {
  document.getElementById('edit_id_unidad').value = u.id_unidad;
  document.getElementById('edit_nombre').value = u.nombre;
  document.getElementById('edit_grupo').value = u.grupo;
  document.getElementById('edit_valor').value = u.valor;
  document.getElementById('edit_id_periodo').value = u.id_periodo;
  document.getElementById('edit_id_sede').value = u.id_sede;
  new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

document.getElementById('formEditar').addEventListener('submit', e=>{
  e.preventDefault();
  fetch(location.href,{method:'POST',body:new FormData(e.target)})
    .then(r=>r.json())
    .then(data=>{
      if(data.success) Swal.fire('Actualizado','Unidad actualizada','success').then(()=>location.reload());
      else Swal.fire('Error','No se actualizó','error');
    });
});

// ELIMINAR UNIDAD
function eliminarUnidad(id) {
  Swal.fire({title:'¿Eliminar unidad?',text:'No se puede deshacer',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar'})
  .then(res=>{
    if(res.isConfirmed){
      const f=new FormData(); f.append('accion','eliminar'); f.append('id_unidad',id);
      fetch(location.href,{method:'POST',body:f})
      .then(r=>r.json()).then(data=>{
        if(data.success) Swal.fire('Eliminado','Unidad eliminada','success').then(()=>location.reload());
        else Swal.fire('Error','No se eliminó','error');
      });
    }
  });
}

// CREAR COHORTE
document.getElementById('formPeriodo').addEventListener('submit', e=>{
  e.preventDefault();
  fetch(location.href,{method:'POST',body:new FormData(e.target)})
  .then(r=>r.json())
  .then(data=>{
    if(data.duplicado) Swal.fire('Error','Código de cohorte ya existente','error');
    else if(data.success){
      const option=document.createElement('option');
      option.value=data.id; option.text=data.codigo; option.selected=true;
      document.getElementById('select_periodo').appendChild(option);
      document.getElementById('edit_id_periodo').appendChild(option.cloneNode(true));
      bootstrap.Modal.getInstance(document.getElementById('modalPeriodo')).hide();
      Swal.fire('Éxito','Cohorte creado correctamente','success');
    } else Swal.fire('Error','No se pudo crear el cohorte','error');
  });
});

// CREAR UNIDAD (AJAX)
document.getElementById('formUnidad').addEventListener('submit', e=>{
  e.preventDefault();
  fetch(location.href,{method:'POST',body:new FormData(e.target)})
  .then(r=>r.json())
  .then(data=>{
    if(data.success){
      Swal.fire('Éxito','Unidad registrada correctamente','success')
      .then(()=>location.reload());
    } else {
      Swal.fire('Error','No se pudo registrar la unidad','error');
    }
  })
  .catch(err=>Swal.fire('Error','Fallo en la solicitud AJAX: '+err,'error'));
});
</script>

<?php include '../includes/footer.php'; ?>
