<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

$id_docente = intval($_GET['id_docente'] ?? 0);
if ($id_docente <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT u.id_unidad, u.nombre AS unidad, u.grupo, p.codigo AS cohorte, u.valor
    FROM unidad_curricular u
    JOIN periodo p ON u.id_periodo = p.id_periodo
    JOIN docente_unidad du ON du.id_unidad = u.id_unidad
    WHERE du.id_docente = ?
      AND u.id_unidad NOT IN (
        SELECT l.id_unidad FROM liquidacion l WHERE l.id_docente = ?
      )
    ORDER BY u.nombre
");
$stmt->bind_param('ii', $id_docente, $id_docente);
$stmt->execute();
$result = $stmt->get_result();
$unidades = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

header('Content-Type: application/json');
echo json_encode($unidades);
