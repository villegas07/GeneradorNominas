<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

// Obtener todas las sedes para el select
$sedes = $conn->query("SELECT id_sede, nombre FROM sede ORDER BY nombre");

// Obtener sede seleccionada (o 0 para todas)
$sede_id = isset($_GET['sede_id']) ? intval($_GET['sede_id']) : 0;

// Incluir la lógica con consultas filtradas
include '../utils/filtro_sede.php';
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/navbar.php'; ?>

<div class="container py-5">
    <h2 class="mb-4 fw-bold text-dark">📊 Panel de Control Financiero</h2>

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

                <?php if ($sede_id && $sede_id != 0): ?>
                <div class="col-auto">
                    <a href="?sede_id=0" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle"></i> Reset
                    </a>
                </div>
                <?php endif; ?>

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

<!-- Estilos personalizados -->
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const topLabels = [], topData = [];
<?php while($r = $top_docentes->fetch_assoc()): ?>
topLabels.push(<?= json_encode($r['nombre']) ?>);
topData.push(<?= $r['total'] ?>);
<?php endwhile; ?>

const trendLabels = [], facturasData = [], pagosData = [];
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
            legend: { display: false },
            tooltip: { callbacks: { label: context => `$${context.parsed.y.toLocaleString()}` } }
        }
    }
});

const ctxTrend = document.getElementById('chartTrend').getContext('2d');
new Chart(ctxTrend, {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [
            {
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
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: context => `$${context.parsed.y.toLocaleString()}` } }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
