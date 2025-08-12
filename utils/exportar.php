<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Parámetros GET
$filtro_tipo = $_GET['tipo'] ?? 'mes';
$filtro_valor = $_GET['valor'] ?? date('Y-m');
$sede_id = isset($_GET['sede_id']) ? intval($_GET['sede_id']) : 0;

// Construcción de condiciones WHERE para filtrar
$where_conditions = [];

// Filtrado por fecha según tipo
if ($filtro_tipo === 'mes') {
    $fecha_filtrada = $conn->real_escape_string($filtro_valor);
    $where_conditions[] = "DATE_FORMAT(f.fecha, '%Y-%m') = '$fecha_filtrada'";
} elseif ($filtro_tipo === 'semana') {
    if (strpos($filtro_valor, '-W') !== false) {
        list($anio, $semana) = explode('-W', $filtro_valor);
        $anio = intval($anio);
        $semana = intval($semana);
        $where_conditions[] = "YEAR(f.fecha) = $anio AND WEEK(f.fecha, 1) = $semana";
    }
}

// Filtrado por sede (relacionada a unidad curricular)
if ($sede_id > 0) {
    $where_conditions[] = "uc.id_sede = $sede_id";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";

// Consulta para obtener métricas
$sql = "
    SELECT 
        s.nombre AS sede,
        d.nombre AS docente,
        IFNULL(SUM(df.monto), 0) AS total_facturado,
        IFNULL(SUM(pf.monto), 0) AS total_pagado,
        (IFNULL(SUM(df.monto), 0) - IFNULL(SUM(pf.monto), 0)) AS total_pendiente
    FROM detalle_factura df
    JOIN factura f ON df.id_factura = f.id_factura
    JOIN docente d ON f.id_docente = d.id_docente
    JOIN unidad_curricular uc ON df.id_unidad = uc.id_unidad
    JOIN sede s ON uc.id_sede = s.id_sede
    LEFT JOIN pago_factura pf ON f.id_factura = pf.id_factura
    $where_clause
    GROUP BY s.id_sede, d.id_docente
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
    $sheet->setCellValue('C' . $fila, number_format($row['total_facturado'], 2, '.', ','));
    $sheet->setCellValue('D' . $fila, number_format($row['total_pagado'], 2, '.', ','));
    $sheet->setCellValue('E' . $fila, number_format($row['total_pendiente'], 2, '.', ','));
    $fila++;
}

// Enviar archivo para descarga
$nombre_archivo = "Reporte_Metricas_{$filtro_tipo}_{$filtro_valor}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
