<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

// Crear liquidación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_liquidacion') {
    header('Content-Type: application/json');

    // Para que mysqli lance excepciones y podamos capturarlas
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $id_docente = intval($_POST['id_docente'] ?? 0);
    $id_unidad = intval($_POST['id_unidad'] ?? 0);
    $numero_estudiantes = intval($_POST['numero_estudiantes'] ?? 0);
    $valor_total = floatval($_POST['valor_total'] ?? 0);

    $primer_pago = (isset($_POST['primer_pago']) && $_POST['primer_pago'] !== '') ? floatval($_POST['primer_pago']) : null;
    $segundo_pago = (isset($_POST['segundo_pago']) && $_POST['segundo_pago'] !== '') ? floatval($_POST['segundo_pago']) : null;
    $observacion = isset($_POST['observacion']) && $_POST['observacion'] !== '' ? trim($_POST['observacion']) : null;

    // Forzar que llegue como array
    $nombres_estudiantes = (isset($_POST['nombres_estudiantes']) && is_array($_POST['nombres_estudiantes']))
        ? $_POST['nombres_estudiantes']
        : [];

    try {
        $conn->begin_transaction();

        // Build dinámico para permitir NULL en campos opcionales
        $columns = ['id_docente','id_unidad','numero_estudiantes','valor_total'];
        $placeholders = ['?','?','?','?'];
        $types = 'iiid';
        $params = [$id_docente, $id_unidad, $numero_estudiantes, $valor_total];

        if ($primer_pago !== null) {
            $columns[] = 'primer_pago';
            $placeholders[] = '?';
            $types .= 'd';
            $params[] = $primer_pago;
        } else {
            $columns[] = 'primer_pago';
            $placeholders[] = 'NULL';
        }

        if ($segundo_pago !== null) {
            $columns[] = 'segundo_pago';
            $placeholders[] = '?';
            $types .= 'd';
            $params[] = $segundo_pago;
        } else {
            $columns[] = 'segundo_pago';
            $placeholders[] = 'NULL';
        }

        if ($observacion !== null) {
            $columns[] = 'observacion';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $observacion;
        } else {
            $columns[] = 'observacion';
            $placeholders[] = 'NULL';
        }

        $sql = "INSERT INTO liquidacion (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $conn->prepare($sql);

        // Bind dinámico (con referencias, requerido por bind_param)
        if (count($params) > 0) {
            $bind_names = [];
            $bind_names[] = &$types;
            for ($i = 0; $i < count($params); $i++) {
                $bind_names[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_names);
        }

        $stmt->execute();
        $id_liquidacion = $stmt->insert_id;
        $stmt->close();

        // Preparar statements para insertar estudiantes y asociaciones (eficiente reutilizar prepare)
        $stmtInsertEst = $conn->prepare("INSERT INTO estudiante (nombre) VALUES (?)");
        $stmtAssoc = $conn->prepare("INSERT INTO liquidacion_estudiante (id_liquidacion, id_estudiante) VALUES (?, ?)");

        foreach ($nombres_estudiantes as $nombre) {
            $nombre = trim($nombre);
            if ($nombre === "") continue;

            // Insertar estudiante
            $nameVar = $nombre;
            $stmtInsertEst->bind_param("s", $nameVar);
            $stmtInsertEst->execute();
            $id_estudiante = $stmtInsertEst->insert_id;

            // Asociar estudiante a la liquidación
            $stmtAssoc->bind_param("ii", $id_liquidacion, $id_estudiante);
            $stmtAssoc->execute();
        }

        $stmtInsertEst->close();
        $stmtAssoc->close();

        $conn->commit();

        echo json_encode([
            "success" => true,
            "msg" => "Liquidación y estudiantes guardados correctamente",
            "id_liquidacion" => $id_liquidacion
        ]);
        exit; // <-- IMPORTANTE: evitar que se imprima HTML extra después del JSON
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error crear_liquidacion: " . $e->getMessage());
        echo json_encode([
            "success" => false,
            "msg" => "Error al guardar la liquidación: " . $e->getMessage()
        ]);
        exit;
    }
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
                        <input type="number" name="numero_estudiantes" id="numero_estudiantes" class="form-control"
                            required disabled>
                    </div>

                    <div class="col-md-12" id="contenedor_estudiantes"></div>

                      <script>
document.getElementById('numero_estudiantes').addEventListener('input', function () {
    const contenedor = document.getElementById('contenedor_estudiantes');
    contenedor.innerHTML = '';

    const n = parseInt(this.value);
    if (!isNaN(n) && n > 0) {
        for (let i = 1; i <= n; i++) {
            contenedor.innerHTML += `
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Estudiante ${i}</label>
                    <input type="text" 
                           name="nombres_estudiantes[]" 
                           class="form-control" 
                           placeholder="Nombre estudiante ${i}" 
                           required>
                </div>
            `;
        }
    }
});
</script>



                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Valor unitario</label>
                        <input type="text" name="valor_unit" id="valor_unit" class="form-control" readonly>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Total</label>
                        <input type="text" id="valor_total_preview" class="form-control" readonly>
                    </div>

                    <!-- Campos ocultos para enviar los valores -->
                    <input type="hidden" name="valor_total" id="valor_total">
                    <input type="hidden" name="primer_pago" id="primer_pago">
                    <input type="hidden" name="segundo_pago" id="segundo_pago">


                    <div class="col-md-2">
                        <label class="form-label fw-semibold">&nbsp;</label>
                        <button type="submit" class="btn btn-success w-100 fw-semibold" disabled
                            id="btn_liquidar">Liquidar</button>
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
                <input type="text" id="buscar" class="form-control shadow-sm"
                    placeholder="🔍 Buscar por docente, cohorte o unidad...">
            </div>

            <div class="table-responsive">
                <table id="tablaLiquidaciones"
                    class="table table-bordered table-hover align-middle rounded-4 overflow-hidden">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>N°</th>
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
                            <td>$<?= number_format($l['primer_pago'],2) ?> / $<?= number_format($l['segundo_pago'],2) ?>
                            </td>
                            <td><?= htmlspecialchars($l['observacion']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-outline-danger btn-sm"
                                    onclick="eliminarLiquidacion(<?= $l['id_liquidacion'] ?>)">Eliminar</button>
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
                if (btn.textContent === 'Anterior' && currentPage > 1) currentPage--;
                else if (btn.textContent === 'Siguiente' && currentPage < pageCount)
                    currentPage++;
                else if (!isNaN(parseInt(btn.textContent))) currentPage = parseInt(btn
                    .textContent);
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
                opt.text =
                    `${u.unidad} (${u.cohorte} / Grupo ${u.grupo}) - $${parseFloat(u.valor).toFixed(2)}`;
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
    fetch('liquidacion.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) Swal.fire('Generado', 'Liquidación creada', 'success').then(() => location
                .reload());
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
            fetch('liquidacion.php', {
                    method: 'POST',
                    body: f
                })
                .then(r => r.json()).then(d => {
                    if (d.success) Swal.fire('Eliminado', 'Liquidación eliminada', 'success').then(() =>
                        location.reload());
                    else Swal.fire('Error', 'No se pudo eliminar', 'error');
                });
        }
    });
}

function actualizarTotalPreview() {
    const valorUnit = parseFloat(document.getElementById('valor_unit').value) || 0;
    const numEst = parseInt(document.getElementById('numero_estudiantes').value) || 0;
    const total = valorUnit * numEst;

    // Mostrar en el campo de vista previa
    document.getElementById('valor_total_preview').value = total > 0 ? total.toFixed(2) : '';

    // Asignar a inputs ocultos (para enviar al backend)
    document.getElementById('valor_total').value = total.toFixed(2);

    // Calcular 50% y asignar
    const mitad = total / 2;
    document.getElementById('primer_pago').value = mitad.toFixed(2);
    document.getElementById('segundo_pago').value = mitad.toFixed(2);
}


document.getElementById('numero_estudiantes').addEventListener('input', actualizarTotalPreview);
document.getElementById('select_unidad').addEventListener('change', function() {
    const sel = this;
    const id = sel.value;
    const valor = sel.selectedOptions[0]?.text.match(/\$\d+(\.\d+)?$/);
    document.getElementById('valor_unit').value = valor ? valor[0].replace('$', '') : '';
    document.getElementById('numero_estudiantes').disabled = !id;
    document.getElementById('btn_liquidar').disabled = !id;
    actualizarTotalPreview(); // recalcula cuando cambia unidad
});
</script>

<?php include '../includes/footer.php'; ?>