<?php
require_once '../config/db.php';

$nombre_unidad = $_POST['nombre_unidad'];
$cohorte = $_POST['cohorte'];
$sede = $_POST['sede'];
$grupo = $_POST['grupo'];

$sql = "SELECT COUNT(*) as total 
        FROM asignaturas 
        WHERE nombre_unidad = ? 
          AND cohorte = ? 
          AND sede = ?
          AND grupo = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $nombre_unidad, $cohorte, $sede, $grupo);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode([
    "existe" => $result['total'] > 0
]);
