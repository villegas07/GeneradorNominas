<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

// Capturar sede seleccionada
$sede_id = isset($_GET['sede_id']) ? intval($_GET['sede_id']) : 0;

// Obtener todas las sedes para el select
$sedes = $conn->query("SELECT id_sede, nombre FROM sede ORDER BY nombre");

// --- CONSULTAS DINÁMICAS CON FILTRO DE SEDE ---

// Condición para el JOIN y WHERE según la sede
$filtro_sede_condicion = '';
if ($sede_id > 0) {
    // Esta condición se insertará en las consultas que lo necesiten
    $filtro_sede_condicion = "
        WHERE EXISTS (
            SELECT 1
            FROM docente_unidad du
            JOIN unidad_curricular uc ON uc.id_unidad = du.id_unidad
            WHERE du.id_docente = f.id_docente AND uc.id_sede = $sede_id
        )
    ";
}

// Total Facturado
$sql_total_facturado = "
    SELECT IFNULL(SUM(f.total_pago), 0)
    FROM factura f
    $filtro_sede_condicion
";

// Total Pagado
$sql_total_pagado = "
    SELECT IFNULL(SUM(pf.monto), 0)
    FROM pago_factura pf
    JOIN factura f ON pf.id_factura = f.id_factura
    $filtro_sede_condicion
";

// Top 5 Docentes
$sql_top_docentes = "
    SELECT d.nombre, SUM(f.total_pago) AS total
    FROM factura f
    JOIN docente d ON f.id_docente = d.id_docente
    $filtro_sede_condicion
    GROUP BY d.id_docente
    ORDER BY total DESC
    LIMIT 5
";

// Evolución de Facturas por Mes
$sql_facturas_por_mes = "
    SELECT DATE_FORMAT(f.fecha, '%Y-%m') AS mes, SUM(f.total_pago) as total
    FROM factura f
    $filtro_sede_condicion
    GROUP BY mes
    ORDER BY mes
";

// Evolución de Pagos por Mes
$sql_pagos_por_mes = "
    SELECT DATE_FORMAT(p.fecha, '%Y-%m') AS mes, SUM(p.monto) AS total
    FROM pago_factura p
    JOIN factura f ON p.id_factura = f.id_factura
    $filtro_sede_condicion
    GROUP BY mes
    ORDER BY mes
";

// Ejecutar consultas y obtener resultados
$total_facturado = $conn->query($sql_total_facturado)->fetch_row()[0] ?: 0;
$total_pagado = $conn->query($sql_total_pagado)->fetch_row()[0] ?: 0;
$total_pendiente = $total_facturado - $total_pagado;

$top_docentes = $conn->query($sql_top_docentes);
$facturas_por_mes = $conn->query($sql_facturas_por_mes);
$pago_por_mes = $conn->query($sql_pagos_por_mes);

?>


<?php include '../includes/header.php'; ?>
<?php include '../includes/navbar.php'; ?>


<div class="container py-5">
    <h2 class="mb-4 fw-bold text-dark">📊 Panel de Control Financiero</h2>

    <!-- Contenedor flotante -->
<div class="card shadow-sm mb-4" style="position: sticky; top: 0; z-index: 1030; background-color: #fff;">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <!-- Filtro por sede -->
            <div class="col-auto">
                <label for="sede_id" class="col-form-label fw-bold">
                    <i class="bi bi-building"></i> Filtrar por sede:
                </label>
            </div>
            <div class="col-auto">
                <select name="sede_id" id="sede_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">Todas las sedes</option>
                    <?php while ($s = $sedes->fetch_assoc()): ?>
                    <option value="<?= $s['id_sede'] ?>" <?= ($sede_id == $s['id_sede']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nombre']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Botón Reset -->
            <?php if ($sede_id && $sede_id != 0): ?>
            <div class="col-auto">
                <a href="?sede_id=0" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            </div>
            <?php endif; ?>

            <!-- Botón Exportar -->
            <div class="col-auto ms-auto">
                <a href="../utils/exportar.php<?= ($sede_id && $sede_id != 0) ? '?sede_id=' . $sede_id : '' ?>"
                    class="btn btn-success btn-sm">
                    <i class="bi bi-file-earmark-excel"></i> Exportar
                </a>
            </div>

        </form>
    </div>
</div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-gradient-light-blue text-white h-100">
                <div class="card-body d-flex flex-column align-items-center text-center">
                    <i class="bi bi-cash-coin display-4 mb-2"></i>
                    <h5 class="card-title">Total Facturado</h5>
                    <h3 class="fw-bold">$<?= number_format($total_facturado, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-gradient-success text-white h-100">
                <div class="card-body d-flex flex-column align-items-center text-center">
                    <i class="bi bi-check2-circle display-4 mb-2"></i>
                    <h5 class="card-title">Total Pagado</h5>
                    <h3 class="fw-bold">$<?= number_format($total_pagado, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-gradient-danger text-white h-100">
                <div class="card-body d-flex flex-column align-items-center text-center">
                    <i class="bi bi-exclamation-triangle display-4 mb-2"></i>
                    <h5 class="card-title">Total Pendiente</h5>
                    <h3 class="fw-bold">$<?= number_format($total_pendiente, 2) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3">🏅 Top 5 Docentes con Mayor Facturación</h5>
                    <canvas id="chartTop" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3">📈 Evolución de Facturas vs Pagos</h5>
                    <canvas id="chartTrend" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />

<!-- Estilos personalizados opcionales -->
<style>
.bg-gradient-light-blue {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #38ef7d 0%, #11998e 100%);
}

.bg-gradient-danger {
    background: linear-gradient(135deg, #f85032 0%, #e73827 100%);
}
</style>

<!-- Scripts Chart.js -->
<script>
const topLabels = [],
    topData = [];
<?php while($r = $top_docentes->fetch_assoc()): ?>
topLabels.push(<?= json_encode($r['nombre']) ?>);
topData.push(<?= $r['total'] ?>);
<?php endwhile; ?>

const trendLabels = [],
    facturasData = [],
    pagosData = [];
<?php while($r = $facturas_por_mes->fetch_assoc()):
  $r2 = $pago_por_mes->fetch_assoc() ?: ['mes' => $r['mes'], 'total' => 0];
?>
trendLabels.push(<?= json_encode($r['mes']) ?>);
facturasData.push(<?= $r['total'] ?>);
pagosData.push(<?= $r2['total'] ?>);
<?php endwhile; ?>

const ctxTop = document.getElementById('chartTop').getContext('2d');
new Chart(ctxTop, {
    type: 'bar',
    data: {
        labels: topLabels,
        datasets: [{
            label: 'Facturado ($)',
            data: topData,
            backgroundColor: 'rgba(13, 110, 253, 0.7)',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: context => `$${context.parsed.y.toLocaleString()}`
                }
            }
        }
    }
});

const ctxTrend = document.getElementById('chartTrend').getContext('2d');
new Chart(ctxTrend, {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
                label: 'Facturas',
                data: facturasData,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Pagos',
                data: pagosData,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: context => `$${context.parsed.y.toLocaleString()}`
                }
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>