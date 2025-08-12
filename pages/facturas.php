<?php
session_start();
require_once '../utils/verificar_sesion.php';
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

            $monto = 0;
            $porcentaje_pago = '';

            if ($modo === 'inicial' && $pendIni > 0) {
                $monto = $pendIni;
                $porcentaje_pago = '50% Inicial';
            } elseif ($modo === 'final' && $pendFin > 0) {
                $monto = $pendFin;
                $porcentaje_pago = '50% Final';
            } elseif ($modo === 'completo' && ($pendIni > 0 || $pendFin > 0)) {
                $monto = $pendIni + $pendFin;
                $porcentaje_pago = '100% Completo';
            }

            if ($monto <= 0) continue;
            $total += $monto;
            $desc = "{$r['unidad']} (Grupo {$r['grupo']} - {$r['cohorte']})";
            $conn->query("INSERT INTO detalle_factura (id_factura, porcentaje_pago, id_periodo, id_unidad, tipo_concepto, descripcion, monto, observacion)
    VALUES ($id_fact, '$porcentaje_pago', {$r['id_periodo']}, {$r['id_unidad']}, 'Curso', '" . addslashes($desc) . "', $monto, '" . addslashes($r['observacion']) . "')");


            if ($modo === 'inicial') {
                $conn->query("UPDATE liquidacion SET pago_inicial_pagado = 1, primer_pago = 0 WHERE id_liquidacion=" . intval($lid));
            } elseif ($modo === 'final') {
                $conn->query("UPDATE liquidacion SET segundo_pago = 0 WHERE id_liquidacion=" . intval($lid));
            } elseif ($modo === 'completo') {
                $conn->query("UPDATE liquidacion SET pago_inicial_pagado = 1, primer_pago = 0, segundo_pago = 0 WHERE id_liquidacion=" . intval($lid));
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
// Facturas emitidas
$facturas = $conn->query("
  SELECT f.*, d.nombre AS docente_nombre,
    (SELECT GROUP_CONCAT(df.descripcion ORDER BY df.descripcion SEPARATOR ', ') 
     FROM detalle_factura df WHERE df.id_factura = f.id_factura) AS cursos_incluidos
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
                            <option value="final" disabled>50% Final</option>
                            <option value="completo">100% Completo</option>
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
                                    <th class="text-end">Monto a Pagar</th>
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
                            <th style="width: 50px;">N°</th>
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
                            <td>
                                <?= htmlspecialchars($f['docente_nombre']) ?><br>
                                <small
                                    class="text-muted fst-italic"><?= htmlspecialchars($f['cursos_incluidos']) ?></small>
                            </td>
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

    let liquidacionesData = [];
    let isPostPaymentReload = false;

    function formatCurrency(num) {
        return `$${parseFloat(num).toFixed(2)}`;
    }

    function getSelectedLiquidaciones() {
        const selectedIds = Array.from(document.querySelectorAll(
                '#tabla_liq input[name="seleccionados[]"]:checked'))
            .map(cb => parseInt(cb.value));
        return liquidacionesData.filter(l => selectedIds.includes(parseInt(l.id_liquidacion)));
    }

    function updatePaymentModeOptions() {
        const selected = getSelectedLiquidaciones();
        const optInicial = selectModo.querySelector('option[value="inicial"]');
        const optFinal = selectModo.querySelector('option[value="final"]');
        const optCompleto = selectModo.querySelector('option[value="completo"]');

        if (selected.length === 0) {
            optInicial.disabled = false;
            optFinal.disabled = true;
            optCompleto.disabled = false;
            selectModo.value = 'inicial';
            return;
        }

        const hasPartialPayment = selected.some(l => l.pago_inicial_pagado == 1);

        optInicial.disabled = hasPartialPayment;
        optCompleto.disabled = hasPartialPayment;
        optFinal.disabled = !hasPartialPayment;

        if (hasPartialPayment) {
            selectModo.value = 'final';
        } else {
            selectModo.value = 'inicial';
        }
    }

    function updateInvoiceDetails() {
        let totalSum = 0;
        const selectedLiquidaciones = getSelectedLiquidaciones();
        const modo = selectModo.value;

        // Update the amount for each row in the table
        tbodyLiq.querySelectorAll('tr').forEach(tr => {
            const liqId = parseInt(tr.querySelector('input[type="checkbox"]').value);
            const liq = liquidacionesData.find(l => parseInt(l.id_liquidacion) === liqId);
            if (!liq) return;

            const primerPago = parseFloat(liq.primer_pago);
            const segundoPago = parseFloat(liq.segundo_pago);
            let amountForThisRow = 0;

            if (modo === 'inicial') {
                amountForThisRow = primerPago;
            } else if (modo === 'final') {
                amountForThisRow = segundoPago;
            } else if (modo === 'completo') {
                amountForThisRow = primerPago + segundoPago;
            }
            tr.cells[4].textContent = formatCurrency(amountForThisRow);
        });

        // Calculate total for selected items
        selectedLiquidaciones.forEach(l => {
            const primerPago = parseFloat(l.primer_pago);
            const segundoPago = parseFloat(l.segundo_pago);

            if (modo === 'inicial') {
                totalSum += primerPago;
            } else if (modo === 'final') {
                totalSum += segundoPago;
            } else if (modo === 'completo') {
                totalSum += primerPago + segundoPago;
            }
        });

        inputTotal.value = formatCurrency(totalSum);
    }

    function handleSelectionChange() {
        updatePaymentModeOptions();
        updateInvoiceDetails();
    }

    selectModo.addEventListener('change', updateInvoiceDetails);
    tbodyLiq.addEventListener('change', e => {
        if (e.target.type === 'checkbox') {
            handleSelectionChange();
        }
    });
    chkAll.addEventListener('change', () => {
        const isChecked = chkAll.checked;
        tbodyLiq.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            const liqId = parseInt(cb.value);
            const liq = liquidacionesData.find(l => parseInt(l.id_liquidacion) === liqId);
            if (liq && liq.pago_inicial_pagado == 1) {
                cb.checked = false;
            } else {
                cb.checked = isChecked;
            }
        });
        handleSelectionChange();
    });

    selectDoc.addEventListener('change', () => {
        tbodyLiq.innerHTML = '';
        tablaLiq.classList.add('d-none');
        liquidacionesData = [];
        handleSelectionChange();

        if (!selectDoc.value) return;
        fetch(`get_liquidaciones_docente.php?id_docente=${selectDoc.value}`)
            .then(r => r.json()).then(data => {
                if (!data.length) {
                    if (!isPostPaymentReload) {
                        Swal.fire('Información', 'El docente no tiene liquidaciones pendientes.',
                            'info');
                    }
                    return;
                };
                liquidacionesData = data;

                data.forEach(l => {
                    const tr = document.createElement('tr');
                    const isFinalPayment = l.pago_inicial_pagado == 1;
                    tr.innerHTML =
                        `<td class="text-center"><input type="checkbox" name="seleccionados[]" value="${l.id_liquidacion}"></td>
                            <td>${l.unidad}</td><td class="text-center">${l.grupo}</td><td class="text-center">${l.cohorte}</td>
                            <td class="text-end"></td>
                            <td>${l.observacion||''}</td>`;

                    if (isFinalPayment) {
                        tr.style.opacity = '0.6';
                        tr.title = 'Este item corresponde a un segundo pago.';
                    }
                    tbodyLiq.appendChild(tr);
                });
                tablaLiq.classList.remove('d-none');
                chkAll.checked = false;
                handleSelectionChange();
            }).catch(() => Swal.fire('Error', 'No se pudieron cargar las liquidaciones', 'error'))
            .finally(() => {
                isPostPaymentReload = false;
            });
    });

    document.getElementById('formFactura').addEventListener('submit', e => {
        e.preventDefault();
        if (getSelectedLiquidaciones().length === 0) {
            Swal.fire('Error', 'Debe seleccionar al menos una liquidación.', 'error');
            return;
        }
        fetch('facturas.php', {
                method: 'POST',
                body: new FormData(e.target)
            })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Factura generada!',
                        text: `Factura #${data.id_factura} emitida para ${data.docente} por ${data.total}`,
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.msg || 'Error al generar la factura', 'error');
                }
            }).catch(() => Swal.fire('Error', 'Fallo en la solicitud', 'error'));
    });

    // --- Lógica de paginación y búsqueda (sin cambios) ---
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
            if (pageCount <= 1) return;
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
        displayRows(currentPage);
        renderPagination();
    }
    paginate();
    search.addEventListener('input', () => {
        const term = search.value.toLowerCase();
        Array.from(bodyFact.rows).forEach(r => {
            r.style.display = r.cells[1].textContent.toLowerCase().includes(term) ? '' : 'none';
        });
        paginate();
    });
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-imprimir')) {
            const facturaId = e.target.dataset.id;
            window.location.href = `vista_factura.php?id=${facturaId}&print=1`;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
