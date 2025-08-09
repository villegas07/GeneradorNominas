<?php
session_start();
include '../config/db.php';
require_once '../utils/verificar_sesion.php';

$edit_unidad = null;

// Manejar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $accion = $_POST['accion'];

    if ($accion === 'guardar_unidad') {
        $id = $_POST['id'] ?? null;
        $nombre = $conn->real_escape_string($_POST['nombre']);
        $descripcion = $conn->real_escape_string($_POST['descripcion']);
        $valor_base = floatval($_POST['valor_base']);

        if (empty($nombre) || empty($valor_base)) {
            echo json_encode(['success' => false, 'msg' => 'El nombre y el valor base son obligatorios.']);
            exit;
        }

        if ($id) { // Actualizar
            $stmt = $conn->prepare("UPDATE catalogo_unidades SET nombre = ?, descripcion = ?, valor_base = ? WHERE id = ?");
            $stmt->bind_param("ssdi", $nombre, $descripcion, $valor_base, $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'msg' => 'Unidad actualizada exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'msg' => 'Error al actualizar: ' . $stmt->error]);
            }
        } else { // Crear
            $stmt = $conn->prepare("INSERT INTO catalogo_unidades (nombre, descripcion, valor_base) VALUES (?, ?, ?)");
            $stmt->bind_param("ssd", $nombre, $descripcion, $valor_base);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'msg' => 'Unidad creada exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'msg' => 'Error al crear: ' . $stmt->error]);
            }
        }
        $stmt->close();
        exit;
    }

    if ($accion === 'eliminar_unidad') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM catalogo_unidades WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'msg' => 'Unidad eliminada exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Error al eliminar: ' . $stmt->error]);
        }
        $stmt->close();
        exit;
    }
}

// Manejar GET para Editar (cargar datos en el formulario)
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM catalogo_unidades WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_unidad = $result->fetch_assoc();
    $stmt->close();
}

// Obtener todas las unidades del catálogo
$catalogo = $conn->query("SELECT * FROM catalogo_unidades ORDER BY nombre ASC");

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-5">

    <!-- Formulario para Crear/Editar -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-primary text-white rounded-top-4">
            <h5 class="mb-0"><?= $edit_unidad ? 'Editar' : 'Añadir Nueva' ?> Unidad al Catálogo</h5>
        </div>
        <div class="card-body">
            <form id="form-catalogo" class="row g-3">
                <input type="hidden" name="id" value="<?= $edit_unidad['id'] ?? '' ?>">
                <div class="col-md-6">
                    <label for="nombre" class="form-label fw-semibold">Nombre de la Unidad</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($edit_unidad['nombre'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="valor_base" class="form-label fw-semibold">Valor Base ($)</label>
                    <input type="number" step="0.01" class="form-control" id="valor_base" name="valor_base" value="<?= htmlspecialchars($edit_unidad['valor_base'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label for="descripcion" class="form-label fw-semibold">Descripción (Opcional)</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="2"><?= htmlspecialchars($edit_unidad['descripcion'] ?? '') ?></textarea>
                </div>
                <div class="col-12 text-end">
                    <?php if ($edit_unidad): ?>
                    <a href="catalogo_unidades.php" class="btn btn-secondary">Cancelar Edición</a>
                    <?php endif; ?>
                    <button type="submit" name="guardar" class="btn btn-primary fw-semibold">Guardar Unidad</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Unidades Existentes -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body">
            <h5 class="mb-3">Catálogo de Unidades Curriculares</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th class="text-end">Valor Base</th>
                            <th style="width: 150px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($catalogo->num_rows > 0): ?>
                            <?php while ($unidad = $catalogo->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($unidad['nombre']) ?></td>
                                <td><?= htmlspecialchars($unidad['descripcion']) ?></td>
                                <td class="text-end">$<?= number_format($unidad['valor_base'], 2) ?></td>
                                <td class="text-center">
                                    <a href="catalogo_unidades.php?edit=<?= $unidad['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarUnidad(<?= $unidad['id'] ?>)">Eliminar</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No hay unidades en el catálogo.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('form-catalogo').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'guardar_unidad');

    fetch('catalogo_unidades.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: data.msg
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.msg
            });
        }
    })
    .catch(error => Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo conectar con el servidor.' }));
});

function eliminarUnidad(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¡No podrás revertir esta acción!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, ¡eliminar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('accion', 'eliminar_unidad');
            formData.append('id', id);

            fetch('catalogo_unidades.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        '¡Eliminado!',
                        data.msg,
                        'success'
                    ).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.msg
                    });
                }
            })
            .catch(error => Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo conectar con el servidor.' }));
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>