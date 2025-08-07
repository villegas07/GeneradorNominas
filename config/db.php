<?php
require_once __DIR__ . '/../.env.php';

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}
