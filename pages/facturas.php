<?php
session_start();
include '../config/db.php';

// POST: emitir factura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'emitir_factura') {
    header('Content-Type: application/json');
    $id_docente = intval($_POST['id_docente']);
    $selecc = $_POST['seleccionados'] ?? [];
    $modo = $_POST['modo'] ?? 'inicial';
    if (empty($selecc)) {
        echo json_encode(['success' => false, 'msg' => 'No liquidaciones seleccionadas.']);
        exit;
    }
    $conn->begin_transaction();
    try {
        $conn->query("INSERT INTO factura (id_docente, fecha, total_pago, convenio) VALUES ($id_docente, CURDATE(), 0, '')");
        $id_fact = $conn->insert_id;
        $total = 0;
        foreach ($selecc as $lid) {
            $r = $conn->query("SELECT l.*, u.nombre unidad, u.grupo, u.id_periodo, p.codigo cohorte
                FROM liquidacion l
                JOIN unidad_curricular u ON u.id_unidad = l.id_unidad
                JOIN periodo p ON u.id_periodo = p.id_periodo
                WHERE l.id_liquidacion = " . intval($lid))->fetch_assoc();
            if (!$r) continue;
            $pendIni = !$r['pago_inicial_pagado'] ? $r['primer_pago'] : 0;
            $pendFin = $r['segundo_pago'];
            $monto = ($modo === 'inicial' && $pendIni > 0) ? $pendIni : $pendFin;
            if ($monto <= 0) continue;
            $total += $monto;
            $desc = "{$r['unidad']} (Grupo {$r['grupo']} - {$r['cohorte']})";
            $conn->query("INSERT INTO detalle_factura (id_factura, porcentaje_pago, id_periodo, tipo_concepto, descripcion, monto, observacion)
                VALUES ($id_fact, '50%', {$r['id_periodo']}, 'Curso', '" . addslashes($desc) . "', $monto, '" . addslashes($r['observacion']) . "')");
            if ($modo === 'inicial') {
                $conn->query("UPDATE liquidacion SET pago_inicial_pagado = 1 WHERE id_liquidacion=" . intval($lid));
            } elseif ($modo === 'final') {
                $conn->query("UPDATE liquidacion SET segundo_pago = 0 WHERE id_liquidacion=" . intval($lid));
            }
        }
        $conn->query("UPDATE factura SET total_pago = $total WHERE id_factura = $id_fact");
        $conn->commit();
        $nombre = $conn->query("SELECT nombre FROM docente WHERE id_docente=$id_docente")->fetch_assoc()['nombre'];
        echo json_encode(['success' => true, 'id_factura' => $id_fact, 'docente' => $nombre, 'total' => number_format($total, 2)]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// Sólo docentes con pendiente
$docentes = $conn->query("
  SELECT DISTINCT d.id_docente, d.nombre
  FROM docente d
  JOIN liquidacion l ON l.id_docente=d.id_docente
  WHERE l.pago_inicial_pagado=0 OR l.segundo_pago>0
  ORDER BY d.nombre
");

// Facturas emitidas
$facturas = $conn->query("
  SELECT f.*, d.nombre AS docente_nombre
  FROM factura f
  JOIN docente d ON f.id_docente=d.id_docente
  ORDER BY f.id_factura DESC
");
?>

<?php include '../includes/header.php'; include '../includes/navbar.php'; ?>

<!-- Agrega Bootstrap Icons para el icono + -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<div class="container mt-5">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center rounded-top-4">
            <h5 class="mb-0">Emisión de Factura</h5>
            <button class="btn btn-light btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#formCollapse"
                aria-expanded="false" aria-controls="formCollapse">
                ➕ Emitir Factura
            </button>
        </div>
        <div class="collapse" id="formCollapse">
            <div class="card-body">
                <form id="formFactura" class="row g-3">
                    <input type="hidden" name="accion" value="emitir_factura">
                    <div class="col-md-3">
                        <label for="select_doc" class="form-label fw-semibold">Docente</label>
                        <select name="id_docente" id="select_doc" class="form-select" required>
                            <option value="">Seleccione docente</option>
                            <?php while ($d = $docentes->fetch_assoc()): ?>
                            <option value="<?= $d['id_docente'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="select_modo" class="form-label fw-semibold">Modo de pago</label>
                        <select name="modo" id="select_modo" class="form-select">
                            <option value="inicial">50% Inicial</option>
                            <option value="final" selected>50% Final</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="input_total" class="form-label fw-semibold">Total a facturar</label>
                        <input id="input_total" type="text" class="form-control" readonly value="$0.00">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100 fw-semibold" type="submit">Emitir Factura</button>
                    </div>

                    <div class="col-12">
                        <table class="table table-striped table-bordered d-none mt-3" id="tabla_liq">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th style="width: 40px;"><input id="chk_all" type="checkbox"></th>
                                    <th>Unidad</th>
                                    <th>Grupo</th>
                                    <th>Cohorte</th>
                                    <th class="text-end">Pendiente</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 mt-4">
        <div class="card-body">
            <h5 class="mb-3">Facturas Emitidas</h5>
            <input type="text" id="search" placeholder="Buscar docente..." class="form-control mb-3 shadow-sm"
                autocomplete="off">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle rounded-4 overflow-hidden"
                    id="tabla_facturas">
                    <thead class="table-dark text-center">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Docente</th>
                            <th>Fecha</th>
                            <th class="text-end">Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="body_facturas">
                        <?php $i = 1; ?>
                        <?php while ($f = $facturas->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?= $i++ ?></td>
                            <td><?= htmlspecialchars($f['docente_nombre']) ?></td>
                            <td class="text-center"><?= $f['fecha'] ?></td>
                            <td class="text-end">$<?= number_format($f['total_pago'], 2) ?></td>
                            <td class="text-center">
                                <a href="vista_factura.php?id=<?= $f['id_factura'] ?>" target="_blank"
                                    class="btn btn-primary btn-sm fw-semibold me-2">Ver/Descargar</a>
                                <button type="button" data-id="<?= $f['id_factura'] ?>"
                                    class="btn btn-secondary btn-sm fw-semibold btn-imprimir">Imprimir
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Facturas nav">
                <ul class="pagination justify-content-center mt-3" id="pagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectDoc = document.getElementById('select_doc'),
        selectModo = document.getElementById('select_modo'),
        tablaLiq = document.getElementById('tabla_liq'),
        tbodyLiq = tablaLiq.querySelector('tbody'),
        chkAll = document.getElementById('chk_all'),
        inputTotal = document.getElementById('input_total'),
        bodyFact = document.getElementById('body_facturas'),
        pagination = document.getElementById('pagination'),
        search = document.getElementById('search');

    // Función para actualizar total seleccionado
    function updateTotal() {
        let sum = 0;
        document.querySelectorAll('#tabla_liq input[name="seleccionados[]"]:checked').forEach(cb => {
            sum += parseFloat(cb.closest('tr').children[4].textContent.replace(/[^0-9.-]+/g, ""));
        });
        inputTotal.value = `$${sum.toFixed(2)}`;
    }

    // Paginación para tabla facturas
    function paginate() {
        const rows = Array.from(bodyFact.querySelectorAll('tr')).filter(r => r.style.display !== 'none');
        const perPage = 10;
        let currentPage = 1;
        const pageCount = Math.ceil(rows.length / perPage);

        function displayRows(page) {
            rows.forEach((row, i) => {
                row.style.display = (i >= (page - 1) * perPage && i < page * perPage) ? '' : 'none';
            });
        }

        function renderPagination() {
            pagination.innerHTML = '';

            const prevLi = document.createElement('li');
            prevLi.className = 'page-item ' + (currentPage === 1 ? 'disabled' : '');
            prevLi.innerHTML = `<a href="#" class="page-link">Anterior</a>`;
            prevLi.onclick = e => {
                e.preventDefault();
                if (currentPage > 1) {
                    currentPage--;
                    displayRows(currentPage);
                    renderPagination();
                }
            };
            pagination.appendChild(prevLi);

            for (let p = 1; p <= pageCount; p++) {
                const li = document.createElement('li');
                li.className = 'page-item ' + (p === currentPage ? 'active' : '');
                li.innerHTML = `<a href="#" class="page-link">${p}</a>`;
                li.onclick = e => {
                    e.preventDefault();
                    currentPage = p;
                    displayRows(currentPage);
                    renderPagination();
                };
                pagination.appendChild(li);
            }

            const nextLi = document.createElement('li');
            nextLi.className = 'page-item ' + (currentPage === pageCount ? 'disabled' : '');
            nextLi.innerHTML = `<a href="#" class="page-link">Siguiente</a>`;
            nextLi.onclick = e => {
                e.preventDefault();
                if (currentPage < pageCount) {
                    currentPage++;
                    displayRows(currentPage);
                    renderPagination();
                }
            };
            pagination.appendChild(nextLi);
        }

        if (pageCount > 0) {
            displayRows(currentPage);
            renderPagination();
        } else {
            pagination.innerHTML = '';
        }
    }
    paginate();

    // Buscador facturas
    search.addEventListener('input', () => {
        const term = search.value.toLowerCase();
        Array.from(bodyFact.rows).forEach(r => {
            r.style.display = r.cells[1].textContent.toLowerCase().includes(term) ? '' : 'none';
        });
        paginate();
    });

    // Cambiar docente: cargar liquidaciones pendientes
    selectDoc.addEventListener('change', () => {
        tbodyLiq.innerHTML = '';
        tablaLiq.classList.add('d-none');
        inputTotal.value = '$0.00';
        chkAll.checked = false;

        if (!selectDoc.value) return;
        fetch(`get_liquidaciones_docente.php?id_docente=${selectDoc.value}`)
            .then(r => r.json()).then(data => {
                if (!data.length) return;
                data.forEach(l => {
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        `<td class="text-center"><input type="checkbox" name="seleccionados[]" value="${l.id_liquidacion}"></td>
                            <td>${l.unidad}</td><td class="text-center">${l.grupo}</td><td class="text-center">${l.cohorte}</td>
                            <td class="text-end">$${parseFloat(l.pendiente).toFixed(2)}</td><td>${l.observacion||''}</td>`;
                    tbodyLiq.appendChild(tr);
                });
                tablaLiq.classList.remove('d-none');
                chkAll.checked = false;
                const anyIni = data.some(l => l.pago_inicial_pagado == 0);
                selectModo.querySelector('option[value="inicial"]').disabled = !anyIni;
                if (!anyIni) selectModo.value = 'final';
                Array.from(tbodyLiq.querySelectorAll('input[name="seleccionados[]"]')).forEach(
                cb => {
                    const l = data.find(x => x.id_liquidacion == cb.value);
                    if (l.pago_inicial_pagado) {
                        cb.checked = false;
                        cb.closest('tr').style.opacity = '0.6';
                    }
                });
                updateTotal();
            }).catch(() => Swal.fire('Error', 'No se cargaron liquidaciones', 'error'));
    });

    // Seleccionar/deseleccionar todos
    chkAll.addEventListener('change', () => {
        document.querySelectorAll('#tabla_liq input[name="seleccionados[]"]').forEach(cb => cb.checked =
            chkAll.checked);
        updateTotal();
    });

    // Submit formulario factura
    document.getElementById('formFactura').addEventListener('submit', e => {
        e.preventDefault();
        document.querySelectorAll('#tabla_liq input[name="seleccionados[]"]').forEach(cb => cb
            .disabled = false);
        fetch('facturas.php', {
                method: 'POST',
                body: new FormData(e.target)
            })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Factura generada!',
                        text: `Factura #${data.id_factura} emitida para ${data.docente} por $${data.total}`,
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        const tr = document.createElement('tr');
                        tr.innerHTML =
                            `<td class="text-center">${bodyFact.children.length + 1}</td>
                         <td>${data.docente}</td>
                         <td class="text-center">${new Date().toISOString().slice(0,10)}</td>
                         <td class="text-end">$${data.total}</td>
                         <td class="text-center">
                            <a href="vista_factura.php?id=${data.id_factura}" target="_blank" class="btn btn-primary btn-sm fw-semibold me-2">Ver/Descargar</a>
                            <button type="button" data-id="${data.id_factura}" class="btn btn-secondary btn-sm fw-semibold btn-imprimir">Imprimir</button>
                         </td>`;
                        bodyFact.prepend(tr);
                        paginate();
                    });
                } else {
                    Swal.fire('Error', data.msg || 'Error al generar la factura', 'error');
                }
            }).catch(() => Swal.fire('Error', 'Fallo en la solicitud', 'error'));
    });

    // Imprimir sin abrir nueva pestaña
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-imprimir')) {
            const facturaId = e.target.dataset.id;
            // Se redirige a la vista con parámetro para que abra diálogo imprimir
            window.location.href = `vista_factura.php?id=${facturaId}&print=1`;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>