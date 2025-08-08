<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

// =======================
// ACCIONES AJAX
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $accion = $_POST['accion'];

    if ($accion === 'crear_pais') {
        $nombre = trim($_POST['nombre']);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pais WHERE nombre = ?");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $stmt->bind_result($existe);
        $stmt->fetch();
        $stmt->close();

        if ($existe > 0) {
            echo json_encode(['success' => false, 'duplicado' => true]);
        } else {
            $stmt = $conn->prepare("INSERT INTO pais (nombre) VALUES (?)");
            $stmt->bind_param("s", $nombre);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $stmt->insert_id, 'nombre' => $nombre]);
            } else {
                echo json_encode(['success' => false]);
            }
            $stmt->close();
        }
        exit;
    }

    if ($accion === 'editar') {
        $id = intval($_POST['id_sede']);
        $nombre = trim($_POST['nombre']);
        $id_pais = intval($_POST['id_pais']);

        $stmt = $conn->prepare("SELECT COUNT(*) FROM sede WHERE nombre = ? AND id_pais = ? AND id_sede != ?");
        $stmt->bind_param("sii", $nombre, $id_pais, $id);
        $stmt->execute();
        $stmt->bind_result($existe);
        $stmt->fetch();
        $stmt->close();

        if ($existe > 0) {
            echo json_encode(['success' => false, 'duplicado' => true]);
            exit;
        }

        $stmt = $conn->prepare("UPDATE sede SET nombre = ?, id_pais = ? WHERE id_sede = ?");
        $stmt->bind_param("sii", $nombre, $id_pais, $id);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $success]);
        exit;
    }

    if ($accion === 'eliminar') {
        $id = intval($_POST['id_sede']);
        $stmt = $conn->prepare("DELETE FROM sede WHERE id_sede = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        echo json_encode(['success' => $affected > 0]);
        exit;
    }
}

// =======================
// CREAR SEDE NORMAL (form POST)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre = trim($_POST['nombre']);
    $id_pais = intval($_POST['id_pais']);
    $stmt = $conn->prepare("SELECT COUNT(*) FROM sede WHERE nombre = ? AND id_pais = ?");
    $stmt->bind_param("si", $nombre, $id_pais);
    $stmt->execute();
    $stmt->bind_result($existe);
    $stmt->fetch();
    $stmt->close();

    if ($existe > 0) {
        $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'Ya existe esa sede para el país seleccionado'];
    } else {
        $stmt = $conn->prepare("INSERT INTO sede (nombre, id_pais) VALUES (?, ?)");
        $stmt->bind_param("si", $nombre, $id_pais);
        $ok = $stmt->execute();
        $_SESSION['mensaje'] = [
            'tipo' => $ok ? 'success' : 'error',
            'texto' => $ok ? 'Sede registrada exitosamente' : 'No se pudo registrar la sede'
        ];
        $stmt->close();
    }
    header("Location: sedes.php");
    exit;
}

// =======================
// OBTENER DATOS
// =======================
$paises = $conn->query("SELECT id_pais, nombre FROM pais ORDER BY nombre");
$sedes = $conn->query("SELECT s.id_sede, s.nombre, s.id_pais, p.nombre AS pais FROM sede s JOIN pais p ON s.id_pais = p.id_pais ORDER BY s.nombre");

// =======================
// INCLUYE HEADER Y NAVBAR
// =======================
include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center rounded-top-4">
            <h5 class="mb-0">Gestión de Sedes</h5>
            <button class="btn btn-light btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#formRegistro">
                ➕ Nueva Sede
            </button>
        </div>

        <div class="collapse" id="formRegistro">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="accion" value="crear">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Sede</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">País</label>
                        <div class="d-flex">
                            <select id="select_pais" name="id_pais" class="form-select me-2" required>
                                <option value="">Seleccione país</option>
                                <?php while($pais = $paises->fetch_assoc()): ?>
                                    <option value="<?= $pais['id_pais'] ?>"><?= htmlspecialchars($pais['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#modalPais" id="btnPaisModal">+</button>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100 fw-semibold">Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mt-4 shadow-sm border-0 rounded-4">
        <div class="card-body">
            <div class="mb-3">
                <input type="text" id="buscarSede" class="form-control shadow-sm" placeholder="🔍 Buscar por sede o país...">
            </div>
            <div class="table-responsive">
                <table id="tablaSedes" class="table table-bordered table-hover align-middle rounded-4 overflow-hidden">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>#</th>
                            <th>Sede</th>
                            <th>País</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=1; while ($s = $sedes->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center"><?= $i++ ?></td>
                                <td><?= htmlspecialchars($s['nombre']) ?></td>
                                <td><?= htmlspecialchars($s['pais']) ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-warning" onclick='editarSede(<?= json_encode($s) ?>)'>Editar</button>
                                        <button class="btn btn-outline-danger" onclick="eliminarSede(<?= $s['id_sede'] ?>)">Eliminar</button>
                                    </div>
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

<!-- Modal Editar Sede -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formEditar" class="modal-content rounded-4 border-0 shadow-sm">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id_sede" id="edit_id_sede">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title">Editar Sede</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Sede</label>
                <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                <label class="form-label mt-3">País</label>
                <div class="d-flex">
                    <select name="id_pais" id="edit_id_pais" class="form-select me-2" required></select>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('btnPaisModal').click()">+</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary fw-semibold">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Crear País -->
<div class="modal fade" id="modalPais" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formPais" class="modal-content rounded-4 border-0 shadow-sm">
            <input type="hidden" name="accion" value="crear_pais">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title">Registrar País</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Nombre del País</label>
                <input type="text" name="nombre" class="form-control" required>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary fw-semibold">Guardar País</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#tablaSedes tbody');
    const rows = Array.from(table.getElementsByTagName('tr'));
    const pagination = document.getElementById('pagination');
    let currentPage = 1, rowsPerPage = 10, filteredRows = [...rows];

    function displayRows(page) {
        rows.forEach(r => r.style.display = 'none');
        const start = (page - 1) * rowsPerPage;
        filteredRows.slice(start, start + rowsPerPage).forEach(r => r.style.display = '');
    }

    function setupPagination() {
        pagination.innerHTML = '';
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);

        if (totalPages <= 1) return;

        const buildItem = (label, disabled = false, active = false) => `
            <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                <a class="page-link" href="#">${label}</a>
            </li>
        `;

        pagination.insertAdjacentHTML('beforeend', buildItem('Anterior', currentPage === 1));
        for (let i = 1; i <= totalPages; i++) {
            pagination.insertAdjacentHTML('beforeend', buildItem(i, false, i === currentPage));
        }
        pagination.insertAdjacentHTML('beforeend', buildItem('Siguiente', currentPage === totalPages));

        pagination.querySelectorAll('.page-link').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                const txt = btn.textContent;
                if (txt === 'Anterior' && currentPage > 1) currentPage--;
                else if (txt === 'Siguiente' && currentPage < totalPages) currentPage++;
                else if (!isNaN(txt)) currentPage = parseInt(txt);
                displayRows(currentPage);
                setupPagination();
            });
        });
    }

    document.getElementById('buscarSede').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        filteredRows = rows.filter(r => {
            const tds = r.getElementsByTagName('td');
            return [tds[1], tds[2]].some(td => td.textContent.toLowerCase().includes(term));
        });
        currentPage = 1;
        displayRows(currentPage);
        setupPagination();
    });

    displayRows(currentPage);
    setupPagination();
});

function editarSede(s) {
    document.getElementById('edit_id_sede').value = s.id_sede;
    document.getElementById('edit_nombre').value = s.nombre;
    const sel = document.getElementById('edit_id_pais');
    sel.innerHTML = document.getElementById('select_pais').innerHTML;
    sel.value = s.id_pais;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

document.getElementById('formEditar').addEventListener('submit', e => {
    e.preventDefault();
    fetch('sedes.php', { method: 'POST', body: new FormData(e.target) })
        .then(res => res.json())
        .then(data => {
            if (data.duplicado) Swal.fire('Error', 'Sede ya existe en ese país', 'error');
            else if (data.success) Swal.fire('Actualizado', 'Sede actualizada con éxito', 'success').then(() => location.reload());
            else Swal.fire('Error', 'No se pudo actualizar la sede', 'error');
        });
});

function eliminarSede(id) {
    Swal.fire({
        title: '¿Eliminar sede?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            const form = new FormData();
            form.append('accion', 'eliminar');
            form.append('id_sede', id);
            fetch('sedes.php', { method: 'POST', body: form })
                .then(res => res.json())
                .then(data => {
                    if (data.success) Swal.fire('Eliminado', 'Sede eliminada', 'success').then(() => location.reload());
                    else Swal.fire('Error', 'No se pudo eliminar la sede', 'error');
                });
        }
    });
}

document.getElementById('formPais').addEventListener('submit', e => {
    e.preventDefault();
    fetch('sedes.php', { method: 'POST', body: new FormData(e.target) })
        .then(res => res.json())
        .then(data => {
            if (data.duplicado) Swal.fire('Error', 'País ya existe', 'error');
            else if (data.success) {
                const op = document.createElement('option');
                op.value = data.id; op.text = data.nombre; op.selected = true;
                document.getElementById('select_pais').appendChild(op);
                document.getElementById('edit_id_pais').appendChild(op.cloneNode(true));
                bootstrap.Modal.getInstance(document.getElementById('modalPais')).hide();
                Swal.fire('Registrado', 'País añadido', 'success');
            } else Swal.fire('Error', 'No se pudo registrar país', 'error');
        });
});
</script>

<?php include '../includes/footer.php'; ?>
