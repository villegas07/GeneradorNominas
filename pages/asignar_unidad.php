<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

// ========== ACCIONES AJAX ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');

    if ($_POST['accion'] === 'asignar') {
        $id_docente = intval($_POST['id_docente']);
        $id_unidad = intval($_POST['id_unidad']);

        // Validar duplicado
        $stmt = $conn->prepare("SELECT COUNT(*) FROM docente_unidad WHERE id_unidad = ?");
        $stmt->bind_param("i", $id_unidad);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            echo json_encode(['success' => false, 'duplicado' => true]);
        } else {
            $stmt = $conn->prepare("INSERT INTO docente_unidad (id_docente, id_unidad) VALUES (?, ?)");
            $stmt->bind_param("ii", $id_docente, $id_unidad);
            $success = $stmt->execute();
            $stmt->close();

            echo json_encode(['success' => $success]);
        }
        exit;
    }

    if ($_POST['accion'] === 'eliminar_asignacion') {
        $id = intval($_POST['id_docente_unidad']);
        $stmt = $conn->prepare("DELETE FROM docente_unidad WHERE id_docente_unidad = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }
}

// ========== CONSULTAS ==========
$docentes = $conn->query("SELECT id_docente, nombre FROM docente ORDER BY nombre");
$unidades = $conn->query("
    SELECT u.id_unidad, u.nombre, u.grupo, p.codigo AS cohorte, s.nombre AS sede
    FROM unidad_curricular u
    JOIN periodo p ON u.id_periodo = p.id_periodo
    JOIN sede s ON u.id_sede = s.id_sede
    WHERE u.id_unidad NOT IN (SELECT id_unidad FROM docente_unidad)
    ORDER BY p.codigo DESC, u.nombre
");
$asignaciones = $conn->query("
    SELECT du.id_docente_unidad, d.nombre AS docente_nombre, u.nombre AS unidad_nombre, u.grupo, p.codigo AS cohorte
    FROM docente_unidad du
    JOIN docente d ON du.id_docente = d.id_docente
    JOIN unidad_curricular u ON du.id_unidad = u.id_unidad
    JOIN periodo p ON u.id_periodo = p.id_periodo
    ORDER BY d.nombre, p.codigo
");

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center rounded-top-4">
            <h5 class="mb-0">Asignación de Unidades Curriculares</h5>
            <button class="btn btn-light btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#formulario">
                ➕ Nueva asignación
            </button>
        </div>
        <div class="collapse" id="formulario">
            <div class="card-body">
                <form id="formAsignar" class="row g-3">
                    <input type="hidden" name="accion" value="asignar">

                    <div class="col-md-6">
                        <label for="docente" class="form-label fw-semibold">Docente</label>
                        <select name="id_docente" id="docente" class="form-select" required>
                            <option value="">Seleccione un docente</option>
                            <?php while($d = $docentes->fetch_assoc()): ?>
                                <option value="<?= $d['id_docente'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="unidad" class="form-label fw-semibold">Unidad Curricular</label>
                        <select name="id_unidad" id="unidad" class="form-select" required>
                            <option value="">Seleccione unidad</option>
                            <?php while($u = $unidades->fetch_assoc()): ?>
                                <option value="<?= $u['id_unidad'] ?>">
                                    <?= htmlspecialchars($u['nombre']) ?> (<?= $u['cohorte'] ?> - G<?= $u['grupo'] ?> / <?= $u['sede'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-success fw-semibold">Asignar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mt-4 shadow-sm border-0 rounded-4">
        <div class="card-body">
            <div class="mb-3">
                <input type="text" id="buscarAsignacion" class="form-control shadow-sm" placeholder="🔍 Buscar por docente, unidad o cohorte...">
            </div>
            <div class="table-responsive">
                <table id="tablaAsignaciones" class="table table-bordered table-hover align-middle rounded-4 overflow-hidden">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>N°</th>
                            <th>Docente</th>
                            <th>Unidad Curricular</th>
                            <th>Grupo</th>
                            <th>Cohorte</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php $i = 1; while($a = $asignaciones->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($a['docente_nombre']) ?></td>
                                <td><?= htmlspecialchars($a['unidad_nombre']) ?></td>
                                <td><?= $a['grupo'] ?></td>
                                <td><?= $a['cohorte'] ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarAsignacion(<?= $a['id_docente_unidad'] ?>)">
                                        Eliminar
                                    </button>
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
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#tablaAsignaciones tbody');
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

    document.getElementById('buscarAsignacion').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        filteredRows = rows.filter(r => {
            const tds = r.getElementsByTagName('td');
            return [tds[1], tds[2], tds[4]].some(td => td.textContent.toLowerCase().includes(term));
        });
        currentPage = 1;
        displayRows(currentPage);
        setupPagination();
    });

    displayRows(currentPage);
    setupPagination();
});

document.getElementById('formAsignar').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('asignar_unidad.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {
        if (data.duplicado) {
            Swal.fire('Error', 'Esta unidad ya fue asignada a un docente.', 'error');
        } else if (data.success) {
            Swal.fire('Éxito', 'Unidad asignada correctamente.', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', 'No se pudo asignar la unidad.', 'error');
        }
    });
});

function eliminarAsignacion(id) {
    Swal.fire({
        title: '¿Está seguro?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            const f = new FormData();
            f.append('accion', 'eliminar_asignacion');
            f.append('id_docente_unidad', id);

            fetch('asignar_unidad.php', {
                method: 'POST',
                body: f
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Eliminado', 'Asignación eliminada correctamente.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', 'No se pudo eliminar la asignación.', 'error');
                }
            });
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
