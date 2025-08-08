<?php
session_start();
if (isset($_SESSION['usuario'])) {
    header("Location: pages/dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - Sistema de Nómina</title>
    <link rel="icon" href="assets/images/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #004080, #007bff);
            font-family: 'Segoe UI', sans-serif;
            overflow: hidden;
        }

        .login-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding: 40px 30px;
            width: 100%;
            max-width: 400px;
            animation: fadeIn 1s ease-in-out;
        }

        .login-logo {
            width: 60px;
            height: auto;
            margin-bottom: 15px;
        }

        .login-title {
            font-size: 24px;
            font-weight: 600;
            color: #004080;
        }

        .form-control {
            border-radius: 8px;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: none;
        }

        .btn-primary {
            background-color: #004080;
            border: none;
            border-radius: 8px;
        }

        .btn-primary:hover {
            background-color: #003060;
        }

        .alert {
            font-size: 14px;
            padding: 8px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        footer {
            font-size: 13px;
            color: #888;
        }
    </style>
</head>
<body>

<div class="login-card text-center">
    <img src="assets/images/logo.png" alt="Logo" class="login-logo">
    <div class="login-title mb-4">Sistema de Nómina</div>

    <?php
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']);
    }
    ?>

    <form action="utils/validar.php" method="POST">
        <div class="form-group mb-3 text-start">
            <label for="usuario" class="form-label fw-semibold">Usuario</label>
            <input type="text" name="usuario" id="usuario" class="form-control" placeholder="Ingrese su usuario" required>
        </div>
        <div class="form-group mb-4 text-start">
            <label for="contrasena" class="form-label fw-semibold">Contraseña</label>
            <input type="password" name="contrasena" id="contrasena" class="form-control" placeholder="Ingrese su contraseña" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Iniciar Sesión</button>
    </form>

    <footer class="mt-4">
        &copy; <?php echo date("Y"); ?> Sistema de Nómina
    </footer>
</div>

</body>
</html>
