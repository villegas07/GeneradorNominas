<?php
session_start();
include '../config/db.php';

// Crear liquidación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_liquidacion') {
    header('Content-Type: application/json');

    $id_docente = intval($_POST['id_docente']);
    $id_unidad = intval($_POST['id_unidad']);
    $n_estudiantes = intval($_POST['numero_estudiantes']);
    $valor_unit = floatval($_POST['valor_unit']);
    $valor_total = $valor_unit;
    $primer_pago = round($valor_total * 0.5, 2);
    $segundo_pago = round($valor_total - $primer_pago, 2);
    $observacion = trim($_POST['observacion']);

    $stmt = $conn->prepare("SELECT COUNT(*) FROM liquidacion WHERE id_unidad = ?");
    $stmt->bind_param("i", $id_unidad);
    $stmt->execute();
    $stmt->bind_result($ya_liquidada);
    $stmt->fetch();
    $stmt->close();

    if ($ya_liquidada > 0) {
        echo json_encode(['success' => false, 'msg' => 'Esta unidad ya fue liquidada para este docente.']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO liquidacion (id_docente, id_unidad, numero_estudiantes, valor_total, primer_pago, segundo_pago, observacion)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiiddds", $id_docente, $id_unidad, $n_estudiantes, $valor_total, $primer_pago, $segundo_pago, $observacion);
    $success = $stmt->execute();
    echo json_encode(['success' => $success]);
    exit;
}

// Eliminar liquidación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_liquidacion') {
    header('Content-Type: application/json');
    $id = intval($_POST['id_liquidacion']);
    $stmt = $conn->prepare("DELETE FROM liquidacion WHERE id_liquidacion = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['success' => $stmt->affected_rows > 0]);
    exit;
}

$docentes = $conn->query("
    SELECT DISTINCT d.id_docente, d.nombre 
    FROM docente d
    JOIN docente_unidad du ON du.id_docente = d.id_docente
    JOIN unidad_curricular u ON du.id_unidad = u.id_unidad
    WHERE du.id_unidad NOT IN (SELECT id_unidad FROM liquidacion)
    ORDER BY d.nombre
");

$liquidaciones = $conn->query("
    SELECT l.*, d.nombre AS docente_nombre, u.nombre AS unidad_nombre, u.grupo, p.codigo AS cohorte
    FROM liquidacion l
    JOIN docente d ON l.id_docente = d.id_docente
    JOIN unidad_curricular u ON l.id_unidad = u.id_unidad
    JOIN periodo p ON u.id_periodo = p.id_periodo
    ORDER BY l.id_liquidacion DESC
");

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center rounded-top-4">
            <h5 class="mb-0">Gestión de Liquidaciones</h5>
            <button class="btn btn-light btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#formRegistro">
                ➕ Nueva Liquidación
            </button>
        </div>

        <div class="collapse" id="formRegistro">
            <div class="card-body">
                <form id="formLiquidar" class="row g-3">
                    <input type="hidden" name="accion" value="crear_liquidacion">

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Docente</label>
                        <select name="id_docente" id="select_docente" class="form-select" required>
                            <option value="">Seleccione docente</option>
                            <?php while($d = $docentes->fetch_assoc()): ?>
                            <option value="<?= $d['id_docente'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Unidad</label>
                        <select name="id_unidad" id="select_unidad" class="form-select" required disabled>
                            <option value="">Seleccione unidad</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">N° Estudiantes</label>
                        <input type="number" name="numero_estudiantes" id="numero_estudiantes" class="form-control" required disabled>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Valor unitario</label>
                        <input type="text" name="valor_unit" id="valor_unit" class="form-control" readonly>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">&nbsp;</label>
                        <button type="submit" class="btn btn-success w-100 fw-semibold" disabled id="btn_liquidar">Liquidar</button>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Observación (opcional)</label>
                        <textarea name="observacion" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mt-4 shadow-sm border-0 rounded-4">
        <div class="card-body">
            <div class="mb-3">
                <input type="text" id="buscar" class="form-control shadow-sm" placeholder="🔍 Buscar por docente, cohorte o unidad...">
            </div>

            <div class="table-responsive">
                <table id="tablaLiquidaciones" class="table table-bordered table-hover align-middle rounded-4 overflow-hidden">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>#</th>
                            <th>Docente</th>
                            <th>Unidad</th>
                            <th>Grupo</th>
                            <th>Cohorte</th>
                            <th>Estudiantes</th>
                            <th>Total</th>
                            <th>50% / 50%</th>
                            <th>Observación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=1; while($l = $liquidaciones->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?= $i++ ?></td>
                            <td><?= htmlspecialchars($l['docente_nombre']) ?></td>
                            <td><?= htmlspecialchars($l['unidad_nombre']) ?></td>
                            <td><?= htmlspecialchars($l['grupo']) ?></td>
                            <td><?= htmlspecialchars($l['cohorte']) ?></td>
                            <td class="text-center"><?= intval($l['numero_estudiantes']) ?></td>
                            <td>$<?= number_format($l['valor_total'],2) ?></td>
                            <td>$<?= number_format($l['primer_pago'],2) ?> / $<?= number_format($l['segundo_pago'],2) ?></td>
                            <td><?= htmlspecialchars($l['observacion']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-outline-danger btn-sm" onclick="eliminarLiquidacion(<?= $l['id_liquidacion'] ?>)">Eliminar</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-3">
                <ul class="pagination" id="pagination"></ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const rowsPerPage = 10;
let currentPage = 1;
let filteredRows = [];

document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#tablaLiquidaciones tbody');
    const rows = Array.from(table.getElementsByTagName('tr'));
    filteredRows = [...rows];
    const pagination = document.getElementById('pagination');

    function displayRows(page) {
        rows.forEach(row => row.style.display = 'none');
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        filteredRows.slice(start, end).forEach(row => row.style.display = '');
    }

    function setupPagination() {
        pagination.innerHTML = '';
        const pageCount = Math.ceil(filteredRows.length / rowsPerPage);
        if (pageCount === 0) return;

        const prev = `<li class="page-item ${currentPage===1?'disabled':''}">
                        <a class="page-link" href="#">Anterior</a>
                      </li>`;
        pagination.insertAdjacentHTML('beforeend', prev);

        for (let i = 1; i <= pageCount; i++) {
            pagination.insertAdjacentHTML('beforeend',
                `<li class="page-item ${i===currentPage?'active':''}">
                    <a class="page-link" href="#">${i}</a>
                 </li>`);
        }

        const next = `<li class="page-item ${currentPage===pageCount?'disabled':''}">
                        <a class="page-link" href="#">Siguiente</a>
                      </li>`;
        pagination.insertAdjacentHTML('beforeend', next);

        pagination.querySelectorAll('.page-item a').forEach((btn, idx) => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                if (btn.textContent === 'Anterior' && currentPage>1) currentPage--;
                else if (btn.textContent === 'Siguiente' && currentPage<pageCount) currentPage++;
                else if (!isNaN(parseInt(btn.textContent))) currentPage = parseInt(btn.textContent);
                displayRows(currentPage);
                setupPagination();
            });
        });
    }

    document.getElementById('buscar').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        filteredRows = rows.filter(row => {
            const cols = row.getElementsByTagName('td');
            return (
                cols[1].textContent.toLowerCase().includes(term) ||
                cols[2].textContent.toLowerCase().includes(term) ||
                cols[4].textContent.toLowerCase().includes(term)
            );
        });
        currentPage = 1;
        displayRows(currentPage);
        setupPagination();
    });

    displayRows(currentPage);
    setupPagination();
});

document.getElementById('select_docente').addEventListener('change', function() {
    const id = this.value;
    const selUn = document.getElementById('select_unidad');
    selUn.innerHTML = '<option value="">Seleccione unidad</option>';
    document.getElementById('numero_estudiantes').value = '';
    document.getElementById('valor_unit').value = '';
    document.getElementById('btn_liquidar').disabled = true;
    document.getElementById('numero_estudiantes').disabled = true;
    if (!id) {
        selUn.disabled = true;
        return;
    }
    fetch('get_unidades_docente.php?id_docente=' + id)
        .then(r => r.json())
        .then(data => {
            data.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id_unidad;
                opt.text = `${u.unidad} (${u.cohorte} / Grupo ${u.grupo}) - $${parseFloat(u.valor).toFixed(2)}`;
                selUn.appendChild(opt);
            });
            selUn.disabled = false;
        });
});

document.getElementById('select_unidad').addEventListener('change', function() {
    const sel = this;
    const id = sel.value;
    const valor = sel.selectedOptions[0]?.text.match(/\$\d+(\.\d+)?$/);
    document.getElementById('valor_unit').value = valor ? valor[0].replace('$', '') : '';
    document.getElementById('numero_estudiantes').disabled = !id;
    document.getElementById('btn_liquidar').disabled = !id;
});

document.getElementById('formLiquidar').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('liquidacion.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            if (data.success) Swal.fire('Generado', 'Liquidación creada', 'success').then(() => location.reload());
            else Swal.fire('Error', data.msg || 'No se pudo generar liquidación', 'error');
        })
        .catch(() => Swal.fire('Error', 'Fallo en la solicitud', 'error'));
});

function eliminarLiquidacion(id) {
    Swal.fire({
        title: '¿Eliminar liquidación?',
        text: 'No se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí'
    }).then(res => {
        if (res.isConfirmed) {
            const f = new FormData();
            f.append('accion', 'eliminar_liquidacion');
            f.append('id_liquidacion', id);
            fetch('liquidacion.php', { method: 'POST', body: f })
                .then(r => r.json()).then(d => {
                    if (d.success) Swal.fire('Eliminado', 'Liquidación eliminada', 'success').then(() => location.reload());
                    else Swal.fire('Error', 'No se pudo eliminar', 'error');
                });
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
