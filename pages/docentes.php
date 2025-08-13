<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');

    if ($_POST['accion'] === 'editar') {
        $id = intval($_POST['id_docente']);
        $identificacion = $_POST['identificacion'];

        $stmt = $conn->prepare("SELECT COUNT(*) FROM docente WHERE identificacion = ? AND id_docente != ?");
        $stmt->bind_param("si", $identificacion, $id);
        $stmt->execute();
        $stmt->bind_result($existe);
        $stmt->fetch();
        $stmt->close();

        if ($existe > 0) {
            echo json_encode(['success' => false, 'duplicado' => true]);
        } else {
            $stmt = $conn->prepare("UPDATE docente SET nombre=?, identificacion=?, nit=? WHERE id_docente=?");
            $stmt->bind_param("sssi", $_POST['nombre'], $identificacion, $_POST['nit'], $id);
            $ok = $stmt->execute();
            echo json_encode(['success' => $ok]);
            $stmt->close();
        }
        exit;
    }

    if ($_POST['accion'] === 'eliminar') {
        $id = intval($_POST['id_docente']);
        $stmt = $conn->prepare("DELETE FROM docente WHERE id_docente = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        echo json_encode(['success' => $affected > 0]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'crear') {
    header('Content-Type: application/json');
    $identificacion = trim($_POST['identificacion']);

    // Validar duplicado
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM docente WHERE identificacion = ?");
    $stmt->bind_param("s", $identificacion);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $existe = (int)$row['total'];
    $stmt->close();

    if ($existe > 0) {
        echo json_encode(['success' => false, 'duplicado' => true]);
        exit;
    }

    // Insertar nuevo docente
    $stmt = $conn->prepare("INSERT INTO docente (nombre, identificacion, nit) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $_POST['nombre'], $identificacion, $_POST['nit']);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $ok]);
    exit;
}


include '../includes/header.php';
include '../includes/navbar.php';

$result = $conn->query("SELECT * FROM docente ORDER BY nombre");
?>

<div class="container mt-5">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center rounded-top-4">
            <h5 class="mb-0">Gestión de Docentes</h5>
            <button class="btn btn-light btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#formRegistro">
                ➕ Nuevo Docente
            </button>
        </div>
        <div class="collapse" id="formRegistro"> <!-- Ahora colapsado por defecto -->
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="accion" value="crear">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Identificación</label>
                        <input type="text" name="identificacion" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">NIT</label>
                        <input type="text" name="nit" class="form-control">
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-success fw-semibold">Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mt-4 shadow-sm border-0 rounded-4">
        <div class="card-body">
            <div class="mb-3">
                <input type="text" id="buscarDocente" class="form-control shadow-sm" placeholder="🔍 Buscar por nombre, identificación o NIT...">
            </div>
            <div class="table-responsive">
                <table id="tablaDocentes" class="table table-bordered table-hover align-middle rounded-4 overflow-hidden">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>N°</th>
                            <th>Nombre</th>
                            <th>Identificación</th>
                            <th>NIT</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($d = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center"><?= $i++ ?></td>
                                <td><?= htmlspecialchars($d['nombre']) ?></td>
                                <td><?= htmlspecialchars($d['identificacion']) ?></td>
                                <td><?= htmlspecialchars($d['nit']) ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-warning" onclick='editarDocente(<?= json_encode($d) ?>)'>Editar</button>
                                        <button class="btn btn-outline-danger" onclick="eliminarDocente(<?= $d['id_docente'] ?>)">Eliminar</button>
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
            <input type="hidden" name="id_docente" id="edit_id_docente">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title">Editar Docente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                <label class="form-label mt-2">Identificación</label>
                <input type="text" name="identificacion" id="edit_identificacion" class="form-control" required>
                <label class="form-label mt-2">NIT</label>
                <input type="text" name="nit" id="edit_nit" class="form-control">
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    setupTablePagination('#tablaDocentes tbody', 'pagination', 'buscarDocente');
});

function editarDocente(d) {
    document.getElementById('edit_id_docente').value = d.id_docente;
    document.getElementById('edit_nombre').value = d.nombre;
    document.getElementById('edit_identificacion').value = d.identificacion;
    document.getElementById('edit_nit').value = d.nit;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

document.getElementById('formEditar').addEventListener('submit', e => {
    e.preventDefault();
    const form = new FormData(e.target);
    fetch('docentes.php', { method: 'POST', body: form })
        .then(res => res.json())
        .then(data => {
            if (data.duplicado) Swal.fire('Error', 'Ya existe otro docente con esa identificación', 'error');
            else if (data.success) Swal.fire('Actualizado', 'Docente actualizado con éxito', 'success').then(() => location.reload());
            else Swal.fire('Error', 'No se pudo actualizar el docente', 'error');
        });
});

function eliminarDocente(id) {
    Swal.fire({
        title: '¿Eliminar docente?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            const form = new FormData();
            form.append('accion', 'eliminar');
            form.append('id_docente', id);
            fetch('docentes.php', { method: 'POST', body: form })
                .then(res => res.json())
                .then(data => {
                    if (data.success) Swal.fire('Eliminado', 'Docente eliminado con éxito', 'success').then(() => location.reload());
                    else Swal.fire('Error', 'No se pudo eliminar el docente', 'error');
                });
        }
    });
}
// Interceptar submit de creación
document.querySelector('form[method="POST"][class="row g-3"]').addEventListener('submit', e => {
    e.preventDefault();
    const form = new FormData(e.target);
    fetch('docentes.php', { method: 'POST', body: form })
        .then(res => res.json())
        .then(data => {
            if (data.duplicado) {
                Swal.fire('Error', 'Ya existe un docente con esa identificación', 'error');
            } else if (data.success) {
                Swal.fire('Registrado', 'Docente registrado exitosamente', 'success')
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', 'No se pudo registrar el docente', 'error');
            }
        })
        .catch(err => {
            Swal.fire('Error', 'Hubo un problema al procesar la solicitud', 'error');
            console.error(err);
        });
});


<?php if (isset($_SESSION['mensaje'])): ?>
Swal.fire({
    icon: '<?= $_SESSION['mensaje']['tipo'] ?>',
    title: '<?= $_SESSION['mensaje']['tipo'] === 'success' ? 'Éxito' : 'Error' ?>',
    text: '<?= $_SESSION['mensaje']['texto'] ?>',
});
<?php unset($_SESSION['mensaje']); endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
