<?php
require '../config/db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM docente WHERE id_docente = $id");
}

header("Location: docentes.php");
exit;
