<?php
session_start();
include '../config/db.php';

// === BLOQUE AJAX: asignación, eliminación ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && in_array($_POST['accion'], ['asignar', 'eliminar_asignacion'])) {
    header('Content-Type: application/json');
    $accion = $_POST['accion'];

    if ($accion === 'asignar') {
        $id_docente = intval($_POST['id_docente']);
        $id_unidad = intval($_POST['id_unidad']);

        // Verificar duplicado
        $stmt = $conn->prepare("SELECT COUNT(*) FROM docente_unidad WHERE id_docente = ? AND id_unidad = ?");
        $stmt->bind_param("ii", $id_docente, $id_unidad);
        $stmt->execute();
        $stmt->bind_result($dup);
        $stmt->fetch();
        $stmt->close();

        if ($dup > 0) {
            echo json_encode(['success' => false, 'duplicado' => true]);
        } else {
            $stmt = $conn->prepare("INSERT INTO docente_unidad (id_docente, id_unidad) VALUES (?, ?)");
            $stmt->bind_param("ii", $id_docente, $id_unidad);
            $success = $stmt->execute();
            echo json_encode(['success' => $success]);
            $stmt->close();
        }
        exit;
    }

    if ($accion === 'eliminar_asignacion') {
        $id = intval($_POST['id_docente_unidad']);
        $stmt = $conn->prepare("DELETE FROM docente_unidad WHERE id_docente_unidad = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => $stmt->affected_rows > 0]);
        $stmt->close();
        exit;
    }
}

// === CONSULTAS PARA LISTAR ===
$docentes = $conn->query("SELECT id_docente, nombre FROM docente ORDER BY nombre");
$unidades = $conn->query("
    SELECT u.id_unidad, u.nombre, u.grupo, p.codigo AS cohorte, s.nombre AS sede
    FROM unidad_curricular u
    JOIN periodo p ON u.id_periodo = p.id_periodo
    JOIN sede s ON u.id_sede = s.id_sede
    ORDER BY p.codigo DESC, u.nombre
");
$asignaciones = $conn->query("
    SELECT du.id_docente_unidad, du.id_docente, du.id_unidad,
           d.nombre AS docente_nombre, u.nombre AS unidad_nombre, u.grupo, p.codigo AS cohorte
    FROM docente_unidad du
    JOIN docente d ON du.id_docente = d.id_docente
    JOIN unidad_curricular u ON du.id_unidad = u.id_unidad
    JOIN periodo p ON u.id_periodo = p.id_periodo
    ORDER BY d.nombre, p.codigo, u.nombre
");

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-5">
    <h2>Asignación de Unidades a Docentes</h2>

    <!-- Formulario de asignación -->
    <form id="formAsignar" class="row g-3 mb-4">
        <input type="hidden" name="accion" value="asignar">
        <div class="col-md-5">
            <label>Docente</label>
            <select name="id_docente" class="form-select" required>
                <option value="">Seleccione docente</option>
                <?php while($d = $docentes->fetch_assoc()): ?>
                <option value="<?= $d['id_docente'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label>Unidad Curricular</label>
            <select name="id_unidad" class="form-select" required>
                <option value="">Seleccione unidad</option>
                <?php while($u = $unidades->fetch_assoc()): ?>
                <option value="<?= $u['id_unidad'] ?>">
                    <?= htmlspecialchars($u['nombre']) ?> (<?= htmlspecialchars($u['cohorte']) ?> - Grupo
                    <?= htmlspecialchars($u['grupo']) ?> / <?= htmlspecialchars($u['sede']) ?>)
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-success w-100">Asignar</button>
        </div>
    </form>

    <!-- Tabla de asignaciones -->
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Docente</th>
                <th>Unidad</th>
                <th>Grupo</th>
                <th>Cohorte</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; while($a = $asignaciones->fetch_assoc()): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($a['docente_nombre']) ?></td>
                <td><?= htmlspecialchars($a['unidad_nombre']) ?></td>
                <td><?= htmlspecialchars($a['grupo']) ?></td>
                <td><?= htmlspecialchars($a['cohorte']) ?></td>
                <td>
                    <button class="btn btn-danger btn-sm"
                        onclick="eliminarAsignacion(<?= $a['id_docente_unidad'] ?>)">Eliminar</button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
document.getElementById('formAsignar').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('asignar_unidad.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(r => r.json())
        .then(data => {
            if (data.duplicado) {
                Swal.fire('Error', 'Esta unidad ya está asignada a un docente', 'error');
            } else if (data.success) {
                Swal.fire('Asignado', 'Unidad asignada correctamente', 'success')
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', 'No se pudo asignar unidad', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Fallo en la solicitud', 'error'));
});

function eliminarAsignacion(id) {
    Swal.fire({
        title: '¿Eliminar asignación?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(res => {
        if (res.isConfirmed) {
            const f = new FormData();
            f.append('accion', 'eliminar_asignacion');
            f.append('id_docente_unidad', id);
            fetch('asignar_unidad.php', {
                    method: 'POST',
                    body: f
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) Swal.fire('Eliminado', 'Asignación eliminada', 'success')
                        .then(() => location.reload());
                    else Swal.fire('Error', 'No se eliminó', 'error');
                })
                .catch(() => Swal.fire('Error', 'Fallo en la solicitud', 'error'));
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>