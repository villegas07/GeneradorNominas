<?php
include '../config/db.php';
header('Content-Type: application/json');
$id = intval($_GET['id_docente'] ?? 0);
$stmt = $conn->prepare("
  SELECT
    l.id_liquidacion,
    u.nombre AS unidad,
    u.grupo,
    p.codigo AS cohorte,
    l.primer_pago,
    l.segundo_pago,
    l.observacion,
    l.pago_inicial_pagado
  FROM liquidacion l
  JOIN unidad_curricular u ON l.id_unidad = u.id_unidad
  JOIN periodo p ON u.id_periodo = p.id_periodo
  WHERE l.id_docente = ?
    AND (l.pago_inicial_pagado = 0 OR l.segundo_pago > 0)
");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
echo json_encode($data);
