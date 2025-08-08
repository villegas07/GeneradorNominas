<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

// Estadísticas
$total_facturado = $conn->query("SELECT SUM(total_pago) FROM factura")->fetch_row()[0] ?: 0;
$total_pagado = $conn->query("SELECT SUM(monto) FROM pago_factura")->fetch_row()[0] ?: 0;
$total_pendiente = $total_facturado - $total_pagado;

$top_docentes = $conn->query("
  SELECT d.nombre, SUM(f.total_pago) AS total
  FROM factura f
  JOIN docente d ON f.id_docente = d.id_docente
  GROUP BY d.id_docente
  ORDER BY total DESC
  LIMIT 5
");

$facturas_por_mes = $conn->query("
  SELECT DATE_FORMAT(fecha,'%Y-%m') AS mes, SUM(total_pago) as total
  FROM factura
  GROUP BY mes ORDER BY mes
");

$pago_por_mes = $conn->query("
  SELECT DATE_FORMAT(p.fecha,'%Y-%m') AS mes, SUM(p.monto) AS total
  FROM pago_factura p
  GROUP BY mes ORDER BY mes
");
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/navbar.php'; ?>

<div class="container py-5">
  <h2 class="mb-4 fw-bold text-dark">📊 Panel de Control Financiero</h2>

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
