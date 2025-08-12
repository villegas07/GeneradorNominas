<?php
ob_start(); // Evitar el error de salida previa

require('../fpdf/fpdf.php');
require('../config/db.php');
require('../utils/numero_a_letras.php'); // Conversión de números a letras

// Validar el parámetro recibido
if (isset($_GET['id_factura'])) {
    $id_factura = intval($_GET['id_factura']);
} elseif (isset($_GET['id'])) {
    $id_factura = intval($_GET['id']);
} else {
    die("Error: No se proporcionó el ID de la factura.");
}

// Consultar factura principal y docente
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

// Consultar detalles de factura
$stmt_detalle = $conn->prepare("
    SELECT df.porcentaje_pago, df.descripcion, df.monto
    FROM detalle_factura df
    WHERE df.id_factura = ?
");
$stmt_detalle->bind_param("i", $id_factura);
$stmt_detalle->execute();
$detalle_result = $stmt_detalle->get_result();
$detalles = $detalle_result->fetch_all(MYSQLI_ASSOC);

$metodo_pago = !empty($factura['metodo_pago']) ? $factura['metodo_pago'] : 'Efectivo';

// Leer configuración
$configFile = '../config/config.json';
$config = json_decode(file_get_contents($configFile), true);
$logoPath = '../' . $config['logo_path'];
$nit = $config['nit'];

class PDF extends FPDF
{
    public $logoPath;
    public $numeroFactura;

    function __construct($orientation='P', $unit='mm', $size='A4', $logoPath, $numeroFactura)
    {
        parent::__construct($orientation, $unit, $size);
        $this->logoPath = $logoPath;
        $this->numeroFactura = $numeroFactura;
    }

    function Header()
    {
        // Logo
        if (file_exists($this->logoPath)) {
            $this->Image($this->logoPath, 10, 10, 40);
        }

        // Título centrado
        $this->SetFont('Arial', 'B', 18);
        $this->Cell(0, 10, utf8_decode('Recibo de Pago'), 0, 0, 'C');

        // Número de factura arriba a la derecha
        $this->SetFont('Arial', '', 12);
        $this->SetXY(-60, 10);
        $this->Cell(50, 10, utf8_decode('Factura Nº ' . $this->numeroFactura), 0, 0, 'R');

        $this->Ln(20);
    }
}

// Crear PDF
$pdf = new PDF('P', 'mm', 'A4', $logoPath, $id_factura);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// Nit
$pdf->SetXY(10, 50);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 6, utf8_decode('Nit: ' . $nit), 0, 1);

// Tabla Año, Mes, Día y Total
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

// PÁGUESE A LA ORDEN DE
$pdf->Ln(5);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 7, utf8_decode('PÁGUESE A LA ORDEN DE:'), 1, 0);
$pdf->Cell(140, 7, utf8_decode($factura['docente_nombre']), 1, 1);

// LA SUMA DE
$pdf->Cell(50, 7, utf8_decode('LA SUMA DE:'), 1, 0);
$pdf->Cell(140, 7, utf8_decode(numeroALetras($factura['total_pago']) . ' pesos'), 1, 1);

// POR CONCEPTO DE
$pdf->Cell(50, 7, utf8_decode('POR CONCEPTO DE:'), 1, 0);
$pdf->Cell(140, 7, utf8_decode('HONORARIOS PROFESIONALES CONVENIO UREL POLINORTE'), 1, 1);

// Tabla Concepto y Monto
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 7, utf8_decode('CONCEPTO'), 1, 0, 'C');
$pdf->Cell(95, 7, utf8_decode('MONTO'), 1, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 7, utf8_decode('IPC'), 1, 0, 'C');
$pdf->Cell(95, 7, '', 1, 1, 'C');

// Mostrar todos los cursos
foreach ($detalles as $detalle) {
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

    $pdf->Cell(95, 7, $concepto, 1, 0);
    $pdf->Cell(95, 7, $monto, 1, 1, 'R');
}

// MÉTODO DE PAGO
$pdf->Ln(5);
$pdf->SetXY(115, $pdf->GetY());
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(45, 10, utf8_decode('MÉTODO DE PAGO:'), 1, 0, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(40, 10, utf8_decode($metodo_pago), 1, 1, 'L');

// TOTAL
$pdf->SetXY(115, $pdf->GetY());
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(45, 10, utf8_decode('TOTAL:'), 1, 0, 'L');
$pdf->Cell(40, 10, '$' . number_format($factura['total_pago'], 2), 1, 1, 'R');

// Firma
$pdf->Ln(20);
$y_firma = $pdf->GetY();
$pdf->Line(20, $y_firma, 100, $y_firma);
$pdf->Line(115, $y_firma, 195, $y_firma);
$pdf->SetFont('Arial', '', 10);
$pdf->SetXY(20, $y_firma + 2);
$pdf->Cell(80, 7, utf8_decode('Firma del Beneficiario'), 0, 0, 'C');
$pdf->SetXY(115, $y_firma + 2);
$pdf->Cell(80, 7, utf8_decode('C.I.:'), 0, 1, 'C');

// Identificación del docente
$pdf->SetXY(20, $y_firma + 10);
$pdf->Cell(80, 7, utf8_decode('ID: ' . $factura['identificacion']), 0, 0, 'C');

ob_end_clean(); // Limpiar salida previa
$pdf->Output();
?>
