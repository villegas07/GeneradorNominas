<?php
session_start();
include '../config/db.php';
require_once '../utils/verificar_sesion.php';

$mensaje = '';
$edit_unidad = null;

// Manejar POST para Guardar (Crear o Actualizar)
if (isset($_POST['guardar'])) {
    $id = $_POST['id'] ?? null;
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $valor_base = floatval($_POST['valor_base']);

    if ($id) { // Actualizar
        $stmt = $conn->prepare("UPDATE catalogo_unidades SET nombre = ?, descripcion = ?, valor_base = ? WHERE id = ?");
        $stmt->bind_param("ssdi", $nombre, $descripcion, $valor_base, $id);
        if ($stmt->execute()) {
            $mensaje = "Unidad actualizada exitosamente.";
        } else {
            $mensaje = "Error al actualizar la unidad: " . $stmt->error;
        }
    } else { // Crear
        $stmt = $conn->prepare("INSERT INTO catalogo_unidades (nombre, descripcion, valor_base) VALUES (?, ?, ?)");
        $stmt->bind_param("ssd", $nombre, $descripcion, $valor_base);
        if ($stmt->execute()) {
            $mensaje = "Unidad creada exitosamente.";
        } else {
            $mensaje = "Error al crear la unidad: " . $stmt->error;
        }
    }
    $stmt->close();
}

// Manejar GET para Eliminar
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM catalogo_unidades WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $mensaje = "Unidad eliminada exitosamente.";
    } else {
        $mensaje = "Error al eliminar la unidad: " . $stmt->error;
    }
    $stmt->close();
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

    <?php if ($mensaje): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Formulario para Crear/Editar -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-primary text-white rounded-top-4">
            <h5 class="mb-0"><?= $edit_unidad ? 'Editar' : 'Añadir Nueva' ?> Unidad al Catálogo</h5>
        </div>
        <div class="card-body">
            <form action="catalogo_unidades.php" method="POST" class="row g-3">
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
                                    <a href="catalogo_unidades.php?delete=<?= $unidad['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que deseas eliminar esta unidad?');">Eliminar</a>
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

<?php include '../includes/footer.php'; ?>
