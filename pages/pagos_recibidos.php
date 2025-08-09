<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

// Registrar pago (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registrar_pago') {
    header('Content-Type: application/json');

    $id_factura = intval($_POST['id_factura']);
    $fecha = $_POST['fecha'] ?? '';
    $monto = floatval($_POST['monto']);
    $obs = trim($_POST['observacion'] ?? '');

    if (!$id_factura || $monto <= 0) {
        echo json_encode(['success' => false, 'msg' => 'Datos inválidos']);
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        echo json_encode(['success' => false, 'msg' => 'Fecha inválida']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO pago_factura (id_factura, fecha, monto, observacion) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $id_factura, $fecha, $monto, $obs);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $ok]);
    exit;
}

// Consultar datos
$pendientes = $conn->query("SELECT f.id_factura, f.fecha, f.total_pago, d.nombre AS docente, COALESCE(SUM(p.monto),0) AS total_pagado
  FROM factura f 
  JOIN docente d ON f.id_docente = d.id_docente 
  LEFT JOIN pago_factura p ON p.id_factura = f.id_factura
  GROUP BY f.id_factura 
  HAVING total_pagado < f.total_pago 
  ORDER BY f.fecha DESC")->fetch_all(MYSQLI_ASSOC);

$realizados = $conn->query("SELECT p.id_pago, p.fecha, p.monto, p.observacion, d.nombre AS docente
  FROM pago_factura p 
  JOIN factura f ON p.id_factura = f.id_factura 
  JOIN docente d ON f.id_docente = d.id_docente
  ORDER BY p.fecha DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center rounded-top-4">
            <h5 class="mb-0">Control de Pagos Recibidos</h5>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center mb-3">
                <label for="selectVista" class="form-label fw-semibold me-2 mb-0">Vista:</label>
                <select id="selectVista" class="form-select w-auto rounded-3 border-primary shadow-sm">
                    <option value="pendientes" selected>Pagos Pendientes</option>
                    <option value="realizados">Pagos Realizados</option>
                </select>
            </div>

            <!-- Tabla Pagos Pendientes -->
            <div id="tablaPendientes">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle rounded-3 overflow-hidden mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>N°</th>
                                        <th>Docente</th>
                                        <th>Fecha Factura</th>
                                        <th>Total</th>
                                        <th>Pagado</th>
                                        <th>Saldo</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyPendientes"></tbody>
                            </table>
                        </div>
                        <nav class="mt-3">
                            <ul class="pagination justify-content-center" id="pagPendientes"></ul>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Tabla Pagos Realizados -->
            <div id="tablaRealizados" style="display:none;">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle rounded-3 overflow-hidden mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>N°</th>
                                        <th>Docente</th>
                                        <th>Fecha Pago</th>
                                        <th>Monto</th>
                                        <th>Observación</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyRealizados"></tbody>
                            </table>
                        </div>
                        <nav class="mt-3">
                            <ul class="pagination justify-content-center" id="pagRealizados"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalPago" tabindex="-1">
    <div class="modal-dialog">
        <form id="formPago" class="modal-content rounded-4 border-0 shadow">
            <input type="hidden" name="accion" value="registrar_pago">
            <input type="hidden" name="id_factura" id="id_factura_pago">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title">Registrar Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Fecha</label>
                <input type="date" name="fecha" id="input_fecha_pago" class="form-control" required>
                <label class="form-label mt-2">Monto</label>
                <input type="number" name="monto" id="input_monto_pago" class="form-control" step="0.01" required>
                <label class="form-label mt-2">Observación</label>
                <textarea name="observacion" class="form-control"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary rounded-3 shadow-sm">Guardar Pago</button>
            </div>
        </form>
    </div>
</div>

<script>
const pendientes = <?= json_encode($pendientes) ?>;
const realizados = <?= json_encode($realizados) ?>;
let curPend = 1, curReal = 1, perPage = 10;

function render(tipo) {
    const data = tipo === 'pend' ? pendientes : realizados;
    const body = document.getElementById(tipo === 'pend' ? 'bodyPendientes' : 'bodyRealizados');
    body.innerHTML = '';
    const page = tipo === 'pend' ? curPend : curReal;

    data.slice((page - 1) * perPage, page * perPage).forEach((r, i) => {
        if (tipo === 'pend') {
            const pag = parseFloat(r.total_pagado), sal = parseFloat(r.total_pago) - pag;
            body.innerHTML += `<tr>
                <td>${(page - 1) * perPage + i + 1}</td>
                <td>${r.docente}</td>
                <td>${r.fecha}</td>
                <td>$${parseFloat(r.total_pago).toFixed(2)}</td>
                <td>$${pag.toFixed(2)}</td>
                <td>$${sal.toFixed(2)}</td>
                <td>
                    <button class="btn btn-outline-primary btn-sm rounded-3 shadow-sm" onclick="abrirModal(${r.id_factura}, ${sal.toFixed(2)})">
                        Registrar
                    </button>
                </td>
            </tr>`;
        } else {
            body.innerHTML += `<tr>
                <td>${(page - 1) * perPage + i + 1}</td>
                <td>${r.docente}</td>
                <td>${r.fecha}</td>
                <td>$${parseFloat(r.monto).toFixed(2)}</td>
                <td>${r.observacion || ''}</td>
            </tr>`;
        }
    });

    renderPagination(tipo);
}

function renderPagination(tipo) {
    const data = tipo === 'pend' ? pendientes : realizados;
    const pages = Math.ceil(data.length / perPage);
    const ul = document.getElementById(tipo === 'pend' ? 'pagPendientes' : 'pagRealizados');
    const page = tipo === 'pend' ? curPend : curReal;
    ul.innerHTML = '';

    const prev = `<li class="page-item ${page <= 1 ? 'disabled' : ''}"><a class="page-link">Anterior</a></li>`;
    const next = `<li class="page-item ${page >= pages ? 'disabled' : ''}"><a class="page-link">Siguiente</a></li>`;

    ul.insertAdjacentHTML('beforeend', prev);
    for (let p = 1; p <= pages; p++) {
        ul.insertAdjacentHTML('beforeend',
            `<li class="page-item ${p === page ? 'active' : ''}"><a class="page-link">${p}</a></li>`);
    }
    ul.insertAdjacentHTML('beforeend', next);

    Array.from(ul.querySelectorAll('a')).forEach(btn => {
        btn.onclick = e => {
            e.preventDefault();
            let newp = page;
            if (btn.textContent === 'Anterior' && page > 1) newp = page - 1;
            else if (btn.textContent === 'Siguiente' && page < pages) newp = page + 1;
            else if (!isNaN(btn.textContent)) newp = parseInt(btn.textContent);
            if (tipo === 'pend') curPend = newp;
            else curReal = newp;
            render(tipo);
        };
    });
}

document.getElementById('selectVista').addEventListener('change', e => {
    document.getElementById('tablaPendientes').style.display = e.target.value === 'pendientes' ? 'block' : 'none';
    document.getElementById('tablaRealizados').style.display = e.target.value === 'realizados' ? 'block' : 'none';
});

function abrirModal(id, saldo) {
    document.getElementById('id_factura_pago').value = id;
    const montoFld = document.getElementById('input_monto_pago');
    montoFld.value = saldo.toFixed(2);
    montoFld.max = saldo.toFixed(2);
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('input_fecha_pago').value = hoy;
    new bootstrap.Modal(document.getElementById('modalPago')).show();
}

document.getElementById('formPago').addEventListener('submit', e => {
    e.preventDefault();
    const fecha = document.getElementById('input_fecha_pago').value;
    if (!fecha) {
        Swal.fire('Error', 'Debes ingresar una fecha válida', 'error');
        return;
    }

    fetch('pagos_recibidos.php', {
        method: 'POST',
        body: new FormData(e.target)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) Swal.fire('Éxito', 'Pago registrado', 'success').then(() => location.reload());
        else Swal.fire('Error', d.msg || 'No se guardó', 'error');
    })
    .catch(() => Swal.fire('Error', 'Fallo solicitud', 'error'));
});

render('pend');
render('real');
</script>

<?php include '../includes/footer.php'; ?>
