<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // Asegúrate de que la ruta sea válida

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $contrasena = $_POST["contrasena"];

    // Verifica que se enviaron datos
    if (empty($usuario) || empty($contrasena)) {
        $_SESSION['error'] = "Por favor, complete todos los campos.";
        header("Location: ../index.php");
        exit();
    }

    // Consulta preparada
    $stmt = $conn->prepare("SELECT id_usuario, username, password_hash, rol, estado FROM usuario WHERE username = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $fila = $resultado->fetch_assoc();

        if (!$fila['estado']) {
            $_SESSION['error'] = "Usuario inactivo. Contacte al administrador.";
            header("Location: ../index.php");
            exit();
        }

        if (password_verify($contrasena, $fila["password_hash"])) {
            $_SESSION["usuario"] = $fila["username"];
            $_SESSION["rol"] = $fila["rol"];
            $_SESSION["id_usuario"] = $fila["id_usuario"];
            header("Location: ../pages/dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Contraseña incorrecta.";
            header("Location: ../index.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Usuario no encontrado.";
        header("Location: ../index.php");
        exit();
    }
} else {
    header("Location: ../index.php");
    exit();
}
