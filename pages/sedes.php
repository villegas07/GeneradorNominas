<?php
session_start();
include '../config/db.php';

// Gestionar AJAX para creación país, edición y eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $accion = $_POST['accion'];

    // CREAR país
    if ($accion === 'crear_pais') {
        $nombre = trim($_POST['nombre']);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pais WHERE nombre = ?");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $stmt->bind_result($existe);
        $stmt->fetch(); $stmt->close();
        if ($existe > 0) {
            echo json_encode(['success' => false, 'duplicado' => true]);
        } else {
            $stmt = $conn->prepare("INSERT INTO pais (nombre) VALUES (?)");
            $stmt->bind_param("s", $nombre);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $stmt->insert_id, 'nombre' => $nombre]);
            } else {
                echo json_encode(['success' => false]);
            }
            $stmt->close();
        }
        exit;
    }

    // EDITAR sede
    if ($accion === 'editar') {
        $id = intval($_POST['id_sede']);
        $nombre = trim($_POST['nombre']);
        $id_pais = intval($_POST['id_pais']);

        $stmt = $conn->prepare("SELECT COUNT(*) FROM sede WHERE nombre = ? AND id_pais = ? AND id_sede != ?");
        $stmt->bind_param("sii", $nombre, $id_pais, $id);
        $stmt->execute();
        $stmt->bind_result($existe);
        $stmt->fetch(); $stmt->close();
        if ($existe > 0) {
            echo json_encode(['success' => false, 'duplicado' => true]);
            exit;
        }
        $stmt = $conn->prepare("UPDATE sede SET nombre = ?, id_pais = ? WHERE id_sede = ?");
        $stmt->bind_param("sii", $nombre, $id_pais, $id);
        $success = $stmt->execute(); $stmt->close();
        echo json_encode(['success' => $success]);
        exit;
    }

    // ELIMINAR sede
    if ($accion === 'eliminar') {
        $id = intval($_POST['id_sede']);
        $stmt = $conn->prepare("DELETE FROM sede WHERE id_sede = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        echo json_encode(['success' => $affected > 0]);
        exit;
    }
}

// Crear sede normal (form HTML)
$registro_estado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre = trim($_POST['nombre']);
    $id_pais = intval($_POST['id_pais']);
    $stmt = $conn->prepare("SELECT COUNT(*) FROM sede WHERE nombre = ? AND id_pais = ?");
    $stmt->bind_param("si", $nombre, $id_pais);
    $stmt->execute();
    $stmt->bind_result($existe);
    $stmt->fetch(); $stmt->close();
    if ($existe > 0) {
        $registro_estado = 'duplicado';
    } else {
        $stmt = $conn->prepare("INSERT INTO sede (nombre, id_pais) VALUES (?, ?)");
        $stmt->bind_param("si", $nombre, $id_pais);
        $ok = $stmt->execute();
        $registro_estado = $ok ? 'ok' : 'error';
        $stmt->close();
    }
    header("Location: sedes.php");
    exit;
}

// Obtener datos
$paises = $conn->query("SELECT id_pais, nombre FROM pais ORDER BY nombre");
$sedes = $conn->query("SELECT s.id_sede, s.nombre, s.id_pais, p.nombre AS pais FROM sede s JOIN pais p ON s.id_pais = p.id_pais ORDER BY s.nombre");
include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-5">
  <h2>Gestión de Sedes</h2>
  <form method="POST" class="row g-3 mb-4">
    <input type="hidden" name="accion" value="crear">
    <div class="col-md-5">
      <label>Sede</label><input type="text" name="nombre" class="form-control" required>
    </div>
    <div class="col-md-5">
      <label>País</label>
      <div class="d-flex">
        <select id="select_pais" name="id_pais" class="form-select me-2" required>
          <option value="">Seleccione país</option>
          <?php while($pais = $paises->fetch_assoc()): ?>
            <option value="<?= $pais['id_pais'] ?>"><?= htmlspecialchars($pais['nombre']) ?></option>
          <?php endwhile; ?>
        </select>
        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#modalPais">+</button>
      </div>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-success w-100">Registrar</button>
    </div>
  </form>

  <table class="table table-bordered table-striped">
    <thead class="table-dark"><tr><th>#</th><th>Sede</th><th>País</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php $i=1; while($s = $sedes->fetch_assoc()): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($s['nombre']) ?></td>
        <td><?= htmlspecialchars($s['pais']) ?></td>
        <td>
          <button class="btn btn-sm btn-warning" onclick='editarSede(<?= json_encode($s) ?>)'>Editar</button>
          <button class="btn btn-sm btn-danger" onclick="eliminarSede(<?= $s['id_sede'] ?>)">Eliminar</button>
        </td>
      </tr>
      <?php endwhile;?>
    </tbody>
  </table>
</div>

<!-- Modal editar sede -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog">
    <form id="formEditar" class="modal-content">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_sede" id="edit_id_sede">
      <div class="modal-header"><h5>Editar Sede</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label>Sede</label><input type="text" name="nombre" id="edit_nombre" class="form-control" required>
        <label class="mt-2">País</label>
        <div class="d-flex">
          <select name="id_pais" id="edit_id_pais" class="form-select me-2" required></select>
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('btnPaisModal').click()">+</button>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary">Guardar Cambios</button></div>
    </form>
  </div>
</div>

<!-- Modal crear país -->
<div class="modal fade" id="modalPais" tabindex="-1">
  <div class="modal-dialog">
    <form id="formPais" class="modal-content">
      <input type="hidden" name="accion" value="crear_pais">
      <div class="modal-header"><h5>Registrar País</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><label>Nombre del País</label><input type="text" name="nombre" class="form-control" required></div>
      <div class="modal-footer"><button class="btn btn-primary">Guardar País</button></div>
    </form>
  </div>
</div>

<script>
function editarSede(s) {
  document.getElementById('edit_id_sede').value = s.id_sede;
  document.getElementById('edit_nombre').value = s.nombre;
  const sel = document.getElementById('edit_id_pais');
  sel.innerHTML = document.getElementById('select_pais').innerHTML;
  sel.value = s.id_pais;
  new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

document.getElementById('formEditar').addEventListener('submit', e => {
  e.preventDefault();
  fetch('sedes.php', { method:'POST', body:new FormData(e.target) })
    .then(r => r.json())
    .then(data => {
      if (data.duplicado) Swal.fire('Error','Sede ya existe en ese país','error');
      else if (data.success) Swal.fire('Actualizado','Sede actualizada','success').then(()=>location.reload());
      else Swal.fire('Error','No se pudo actualizar','error');
    });
});

function eliminarSede(id) {
  Swal.fire({
    title: '¿Eliminar?',
    text:'Esta acción no se puede deshacer',
    icon:'warning',
    showCancelButton:true,
    confirmButtonText:'Sí, eliminar'
  }).then(r=>{
    if(r.isConfirmed){
      const f = new FormData();
      f.append('accion','eliminar'); f.append('id_sede',id);
      fetch('sedes.php',{method:'POST',body:f})
        .then(r=>r.json())
        .then(data=>{
          if(data.success) Swal.fire('Eliminado','Sede eliminada','success').then(()=>location.reload());
          else Swal.fire('Error','No se eliminó','error');
        });
    }
  });
}

document.getElementById('formPais').addEventListener('submit', e =>{
  e.preventDefault();
  fetch('sedes.php',{method:'POST',body:new FormData(e.target)})
    .then(r=>r.json())
    .then(data=>{
      if(data.duplicado) Swal.fire('Error','País ya existe','error');
      else if(data.success){
        const op = document.createElement('option');
        op.value = data.id; op.text = data.nombre; op.selected=true;
        document.getElementById('select_pais').appendChild(op);
        document.getElementById('edit_id_pais').appendChild(op.cloneNode(true));
        bootstrap.Modal.getInstance(document.getElementById('modalPais')).hide();
        Swal.fire('Registrado','País añadido','success');
      } else Swal.fire('Error','No se pudo registrar país','error');
    });
});
</script>

<?php include '../includes/footer.php'; ?>
