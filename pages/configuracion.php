<?php
session_start();
// Incluir el header y navbar
include '../includes/header.php';
include '../includes/navbar.php';
require_once '../utils/verificar_sesion.php';

// Ruta al archivo de configuración
$configFile = '../config/config.json';
$config = json_decode(file_get_contents($configFile), true);
$successMessage = '';
$errorMessage = '';

// Manejar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Actualizar NIT
    if (isset($_POST['nit'])) {
        $config['nit'] = trim($_POST['nit']);
    }

    // Manejar subida de logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = '../assets/images/';
        // Sanitize filename
        $filename = preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($_FILES['logo']['name']));
        $uploadFile = $uploadDir . $filename;
        $imageFileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));

        // Validar tipo de archivo
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imageFileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadFile)) {
                $config['logo_path'] = 'assets/images/' . $filename;
            } else {
                $errorMessage = 'Error al mover el archivo subido.';
            }
        } else {
            $errorMessage = 'Sólo se permiten archivos de imagen (JPG, JPEG, PNG, GIF).';
        }
    }

    // Guardar la configuración actualizada
    if (empty($errorMessage)) {
        if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT))) {
            $successMessage = '¡Configuración guardada exitosamente!';
        } else {
            $errorMessage = 'Error al guardar el archivo de configuración.';
        }
    }
}
?>

<div class="container mt-5">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-primary text-white rounded-top-4">
            <h5 class="mb-0">Configuración de la Factura</h5>
        </div>
        <div class="card-body">
            <?php if ($successMessage): ?>
                <div class="alert alert-success"><?= $successMessage ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger"><?= $errorMessage ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-6">
                    <label for="nit" class="form-label fw-semibold">NIT de la Empresa</label>
                    <input type="text" class="form-control" id="nit" name="nit" value="<?= htmlspecialchars($config['nit']) ?>">
                </div>

                <div class="col-md-6">
                    <label for="logo" class="form-label fw-semibold">Logo de la Empresa</label>
                    <input class="form-control" type="file" id="logo" name="logo">
                </div>

                <div class="col-12 mt-4">
                    <h6 class="fw-semibold">Logo Actual</h6>
                    <img src="../<?= htmlspecialchars($config['logo_path']) ?>" alt="Logo Actual" class="img-thumbnail" style="max-height: 100px;">
                </div>

                <div class="col-12 text-end mt-4">
                    <button type="submit" class="btn btn-primary fw-semibold">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
