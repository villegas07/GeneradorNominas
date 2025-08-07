<?php
require '../config/db.php';

if (!isset($_GET['id'])) {
    header("Location: docentes.php");
    exit;
}

$id = intval($_GET['id']);

// Actualizar docente si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $identificacion = trim($_POST['identificacion']);
    $nit = trim($_POST['nit']);

    $stmt = $conn->prepare("UPDATE docente SET nombre=?, identificacion=?, nit=? WHERE id_docente=?");
    $stmt->bind_param("sssi", $nombre, $identificacion, $nit, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: docentes.php");
    exit;
}

// Obtener datos actuales
$result = $conn->query("SELECT * FROM docente WHERE id_docente = $id");
$docente = $result->fetch_assoc();

if (!$docente) {
    echo "Docente no encontrado.";
    exit;
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">
    <h2>Editar Docente</h2>
    <form method="POST" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($docente['nombre']) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Identificación</label>
            <input type="text" name="identificacion" class="form-control" value="<?= htmlspecialchars($docente['identificacion']) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">NIT</label>
            <input type="text" name="nit" class="form-control" value="<?= htmlspecialchars($docente['nit']) ?>">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="docentes.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
