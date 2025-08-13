<?php
ob_start();

require('../fpdf/fpdf.php');
require('../config/db.php');
require('../utils/numero_a_letras.php');

// Validar ID de factura
if (isset($_GET['id_factura'])) {
    $id_factura = intval($_GET['id_factura']);
} elseif (isset($_GET['id'])) {
    $id_factura = intval($_GET['id']);
} else {
    die("Error: No se proporcionó el ID de la factura.");
}

// Obtener datos de la factura
$stmt = $conn->prepare("
    SELECT f.*, d.nombre AS docente_nombre, d.identificacion
    FROM factura f
    JOIN docente d ON f.id_docente = d.id_docente
    WHERE f.id_factura = ?
");
$stmt->bind_param("i", $id_factura);
$stmt->execute();
$result = $stmt->get_result();
$factura = $result->fetch_assoc();

if (!$factura) {
    die('Factura no encontrada');
}

// Obtener detalles de la factura
$stmt_detalle = $conn->prepare("
    SELECT df.porcentaje_pago, df.descripcion, df.monto, df.id_unidad
    FROM detalle_factura df
    WHERE df.id_factura = ?
");
$stmt_detalle->bind_param("i", $id_factura);
$stmt_detalle->execute();
$detalle_result = $stmt_detalle->get_result();
$detalles = $detalle_result->fetch_all(MYSQLI_ASSOC);

// Obtener estudiantes relacionados
$stmt_estudiantes = $conn->prepare("
    SELECT DISTINCT e.nombre AS estudiante_nombre
    FROM liquidacion_estudiante le
    JOIN estudiante e 
        ON e.id_estudiante = le.id_estudiante
    JOIN liquidacion l 
        ON le.id_liquidacion = l.id_liquidacion
    WHERE l.id_docente = ?
      AND l.pago_inicial_pagado = 1
");
$stmt_estudiantes->bind_param("i", $factura['id_docente']);
$stmt_estudiantes->execute();
$res_estudiantes = $stmt_estudiantes->get_result();
$estudiantes = $res_estudiantes->fetch_all(MYSQLI_ASSOC);

$nombres_estudiantes = empty($estudiantes)
    ? '---'
    : implode(', ', array_column($estudiantes, 'estudiante_nombre'));

// Configuración general
$configFile = '../config/config.json';
$config = json_decode(file_get_contents($configFile), true);
$logoPath = '../' . $config['logo_path'];
$nit = $config['nit'];

// Clase PDF personalizada
class PDF extends FPDF
{
    public $logoPath;
    public $numeroFactura;

    function __construct($orientation, $unit, $size, $logoPath, $numeroFactura)
    {
        parent::__construct($orientation, $unit, $size);
        $this->logoPath = $logoPath;
        $this->numeroFactura = $numeroFactura;
    }

    function Header()
    {
        if (file_exists($this->logoPath)) {
            $this->Image($this->logoPath, 10, 10, 40);
        }
        $this->SetFont('Arial', 'B', 18);
        $this->Cell(0, 10, utf8_decode('Recibo de Pago'), 0, 0, 'C');
        $this->SetFont('Arial', '', 12);
        $this->SetXY(-60, 10);
        $this->Cell(50, 10, utf8_decode('Factura Nº ' . $this->numeroFactura), 0, 0, 'R');
        $this->Ln(20);
    }

    // Calcular cuántas líneas ocupa un texto en un ancho específico
    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++; $sep = -1; $j = $i; $l = 0; $nl++;
                continue;
            }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else {
                    $i = $sep + 1;
                }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    function Row($data, $widths, $aligns)
    {
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($widths[$i], $data[$i]));
        }
        $h = 5 * $nb;
        $this->CheckPageBreak($h);
        for ($i = 0; $i < count($data); $i++) {
            $w = $widths[$i];
            $a = isset($aligns[$i]) ? $aligns[$i] : 'L';
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, 5, $data[$i], 0, $a);
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    function CheckPageBreak($h)
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }
}

// Crear PDF
$pdf = new PDF('P', 'mm', 'A4', $logoPath, $id_factura);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// Datos de cabecera
$pdf->SetXY(10, 50);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 6, utf8_decode('Nit: ' . $nit), 0, 1);

// Fecha y total
$pdf->SetXY(120, 45);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(17, 7, utf8_decode('AÑO'), 1, 0, 'C');
$pdf->Cell(17, 7, utf8_decode('MES'), 1, 0, 'C');
$pdf->Cell(17, 7, utf8_decode('DÍA'), 1, 0, 'C');
$pdf->Cell(30, 7, utf8_decode('TOTAL A PAGAR'), 1, 1, 'C');

$pdf->SetX(120);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(17, 7, date('Y', strtotime($factura['fecha'])), 1, 0, 'C');
$pdf->Cell(17, 7, date('m', strtotime($factura['fecha'])), 1, 0, 'C');
$pdf->Cell(17, 7, date('d', strtotime($factura['fecha'])), 1, 0, 'C');
$pdf->Cell(30, 7, '$' . number_format($factura['total_pago'], 2), 1, 1, 'C');

// Datos del beneficiario
$pdf->Ln(5);
$pdf->Cell(50, 7, utf8_decode('PÁGUESE A LA ORDEN DE:'), 1, 0);
$pdf->Cell(140, 7, utf8_decode($factura['docente_nombre']), 1, 1);

$pdf->Cell(50, 7, utf8_decode('LA SUMA DE:'), 1, 0);
$pdf->Cell(140, 7, utf8_decode(numeroALetras($factura['total_pago']) . ' pesos'), 1, 1);

$pdf->Cell(50, 7, utf8_decode('POR CONCEPTO DE:'), 1, 0);
$pdf->Cell(140, 7, utf8_decode('HONORARIOS PROFESIONALES CONVENIO UREL POLINORTE'), 1, 1);

// Tabla de conceptos
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(70, 7, utf8_decode('CONCEPTO'), 1, 0, 'C');
$pdf->Cell(70, 7, utf8_decode('ESTUDIANTE(S)'), 1, 0, 'C');
$pdf->Cell(50, 7, utf8_decode('MONTO'), 1, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$widths = [70, 70, 50];
$aligns = ['L', 'L', 'R'];

foreach ($detalles as $detalle) {
    $id_unidad_detalle = $detalle['id_unidad'] ?? null;

    if ($id_unidad_detalle) {
        $stmt_est_detalle = $conn->prepare("
            SELECT e.nombre AS estudiante_nombre
            FROM liquidacion_estudiante le
            JOIN estudiante e 
                ON e.id_estudiante = le.id_estudiante
            JOIN liquidacion l 
                ON le.id_liquidacion = l.id_liquidacion
            WHERE l.id_unidad = ? 
              AND l.id_docente = ? 
              AND l.pago_inicial_pagado = 1
        ");
        $stmt_est_detalle->bind_param("ii", $id_unidad_detalle, $factura['id_docente']);
        $stmt_est_detalle->execute();
        $res_est_detalle = $stmt_est_detalle->get_result();
        $estudiantes_detalle = $res_est_detalle->fetch_all(MYSQLI_ASSOC);

        $nombres_estudiantes_detalle = !empty($estudiantes_detalle)
            ? implode(", ", array_column($estudiantes_detalle, 'estudiante_nombre'))
            : '---';
    } else {
        $nombres_estudiantes_detalle = $nombres_estudiantes;
    }

    $valor_porcentaje = rtrim(rtrim($detalle['porcentaje_pago'], '0'), '.');
    if ($valor_porcentaje == '50') {
        $etiqueta_porcentaje = 'Inicio (' . $valor_porcentaje . '%)';
    } elseif ($valor_porcentaje == '100') {
        $etiqueta_porcentaje = 'Completo (' . $valor_porcentaje . '%)';
    } else {
        $etiqueta_porcentaje = $valor_porcentaje . '%';
    }

    $concepto = utf8_decode($etiqueta_porcentaje . ' de ' . $detalle['descripcion']);
    $monto = '$' . number_format($detalle['monto'], 2) . ' pesos';

    $pdf->Row(
        [$concepto, utf8_decode($nombres_estudiantes_detalle), $monto],
        $widths,
        $aligns
    );
}

// Método de pago y total
$metodo_pago = !empty($factura['metodo_pago']) ? $factura['metodo_pago'] : 'Efectivo';

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(115, 10, '', 0, 0);
$pdf->Cell(40, 10, utf8_decode('MÉTODO DE PAGO:'), 1, 0, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(35, 10, utf8_decode($metodo_pago), 1, 1, 'L');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(115, 10, '', 0, 0);
$pdf->Cell(40, 10, utf8_decode('TOTAL:'), 1, 0, 'L');
$pdf->Cell(35, 10, '$' . number_format($factura['total_pago'], 2), 1, 1, 'R');

$pdf->Ln(20);

// Firmas
$y_firma = $pdf->GetY();
$pdf->Line(20, $y_firma, 100, $y_firma);
$pdf->Line(115, $y_firma, 195, $y_firma);
$pdf->SetFont('Arial', '', 10);
$pdf->Ln(2);
$pdf->Cell(80, 7, utf8_decode('Firma del Beneficiario'), 0, 0, 'C');
$pdf->Cell(15, 7, '', 0, 0);
$pdf->Cell(80, 7, utf8_decode('C.I.:'), 0, 1, 'C');

$pdf->Ln(5);
$pdf->Cell(80, 7, utf8_decode('ID: ' . $factura['identificacion']), 0, 0, 'C');

ob_end_clean();
$pdf->Output();
