<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Parámetros GET
$filtro_tipo = $_GET['tipo'] ?? 'mes'; // mes o semana
$filtro_valor = $_GET['valor'] ?? date('Y-m'); // 2025-08 o 2025-W32

// Filtro de fecha usando factura.fecha
if ($filtro_tipo === 'mes') {
    $filtro_fecha = "DATE_FORMAT(f.fecha, '%Y-%m') = '" . $conn->real_escape_string($filtro_valor) . "'";
} elseif ($filtro_tipo === 'semana') {
    list($anio, $semana) = explode('-W', $filtro_valor);
    $filtro_fecha = "YEAR(f.fecha) = " . intval($anio) . " AND WEEK(f.fecha, 1) = " . intval($semana);
} else {
    die("Tipo de filtro inválido");
}

// Consulta de métricas
$sql = "
    SELECT 
        s.nombre AS sede,
        d.nombre AS docente,
        IFNULL(SUM(DISTINCT l.valor_total), 0) AS total_facturado,
        IFNULL(SUM(pf.monto), 0) AS total_pagado,
        (IFNULL(SUM(DISTINCT l.valor_total), 0) - IFNULL(SUM(pf.monto), 0)) AS total_pendiente
    FROM docente d
    JOIN liquidacion l ON d.id_docente = l.id_docente
    JOIN unidad_curricular u ON l.id_unidad = u.id_unidad
    JOIN sede s ON u.id_sede = s.id_sede
    LEFT JOIN factura f ON d.id_docente = f.id_docente
    LEFT JOIN pago_factura pf ON f.id_factura = pf.id_factura
    WHERE $filtro_fecha
    GROUP BY s.nombre, d.nombre
    ORDER BY s.nombre, d.nombre
";

$result = $conn->query($sql);
if (!$result) {
    die("Error en la consulta: " . $conn->error);
}

// Crear Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Encabezados
$sheet->setCellValue('A1', 'Sede');
$sheet->setCellValue('B1', 'Docente');
$sheet->setCellValue('C1', 'Total Facturado');
$sheet->setCellValue('D1', 'Total Pagado');
$sheet->setCellValue('E1', 'Total Pendiente');

$fila = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue('A' . $fila, $row['sede']);
    $sheet->setCellValue('B' . $fila, $row['docente']);
    $sheet->setCellValue('C' . $fila, number_format($row['total_facturado'], 2));
    $sheet->setCellValue('D' . $fila, number_format($row['total_pagado'], 2));
    $sheet->setCellValue('E' . $fila, number_format($row['total_pendiente'], 2));
    $fila++;
}

// Descargar archivo
$nombre_archivo = "Reporte_Metricas_" . $filtro_tipo . "_" . $filtro_valor . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
