<?php
include '../config/db.php';
$id = intval($_GET['id_docente'] ?? 0);
$stmt = $conn->prepare("
  SELECT u.id_unidad, u.nombre AS unidad, u.grupo, u.valor, p.codigo AS cohorte
  FROM docente_unidad du
  JOIN unidad_curricular u ON du.id_unidad = u.id_unidad
  JOIN periodo p ON u.id_periodo = p.id_periodo
  WHERE du.id_docente = ?
");
$stmt->bind_param("i",$id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
header('Content-Type: application/json');
echo json_encode($data);
