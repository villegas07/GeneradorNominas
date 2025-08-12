<?php
// Iniciar sesión si aún no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si la variable de sesión 'usuario' no está definida
if (!isset($_SESSION['usuario'])) {
    // Redirigir al inicio de sesión
    header("Location: ../index.php");
    exit();
}
?>
