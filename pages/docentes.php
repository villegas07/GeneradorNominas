<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');

    if ($_POST['accion'] === 'editar') {
        $id = intval($_POST['id_docente']);
        $identificacion = $_POST['identificacion'];

        $stmt = $conn->prepare("SELECT COUNT(*) FROM docente WHERE identificacion = ? AND id_docente != ?");
        $stmt->bind_param("si", $identificacion, $id);
        $stmt->execute();
        $stmt->bind_result($existe);
        $stmt->fetch();
        $stmt->close();

        if ($existe > 0) {
            echo json_encode(['success' => false, 'duplicado' => true]);
        } else {
            $stmt = $conn->prepare("UPDATE docente SET nombre=?, identificacion=?, nit=? WHERE id_docente=?");
            $stmt->bind_param("sssi", $_POST['nombre'], $identificacion, $_POST['nit'], $id);
            $ok = $stmt->execute();
            echo json_encode(['success' => $ok]);
            $stmt->close();
        }
        exit;
    }

    if ($_POST['accion'] === 'eliminar') {
        $id = intval($_POST['id_docente']);
        $stmt = $conn->prepare("DELETE FROM docente WHERE id_docente = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        echo json_encode(['success' => $affected > 0]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'crear') {
    $identificacion = $_POST['identificacion'];

    $stmt = $conn->prepare("SELECT COUNT(*) FROM docente WHERE identificacion = ?");
    $stmt->bind_param("s", $identificacion);
    $stmt->execute();
    $stmt->bind_result($existe);
    $stmt->fetch();
    $stmt->close();

    if ($existe > 0) {
        $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'Ya existe un docente con esa identificación'];
    } else {
        $stmt = $conn->prepare("INSERT INTO docente (nombre, identificacion, nit) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $_POST['nombre'], $identificacion, $_POST['nit']);
        $ok = $stmt->execute();
        $_SESSION['mensaje'] = [
            'tipo' => $ok ? 'success' : 'error',
            'texto' => $ok ? 'Docente registrado exitosamente' : 'No se pudo registrar el docente'
        ];
        $stmt->close();
    }
    header("Location: docentes.php");
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';

$result = $conn->query("SELECT * FROM docente ORDER BY nombre");
?>

<div class="container mt-5">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center rounded-top-4">
            <h5 class="mb-0">Gestión de Docentes</h5>
            <button class="btn btn-light btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#formRegistro">
                ➕ Nuevo Docente
            </button>
        </div>
        <div class="collapse" id="formRegistro"> <!-- Ahora colapsado por defecto -->
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="accion" value="crear">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Identificación</label>
                        <input type="text" name="identificacion" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">NIT</label>
                        <input type="text" name="nit" class="form-control">
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-success fw-semibold">Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mt-4 shadow-sm border-0 rounded-4">
        <div class="card-body">
            <div class="mb-3">
                <input type="text" id="buscarDocente" class="form-control shadow-sm" placeholder="🔍 Buscar por nombre, identificación o NIT...">
            </div>
            <div class="table-responsive">
                <table id="tablaDocentes" class="table table-bordered table-hover align-middle rounded-4 overflow-hidden">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Identificación</th>
                            <th>NIT</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($d = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center"><?= $i++ ?></td>
                                <td><?= htmlspecialchars($d['nombre']) ?></td>
                                <td><?= htmlspecialchars($d['identificacion']) ?></td>
                                <td><?= htmlspecialchars($d['nit']) ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-warning" onclick='editarDocente(<?= json_encode($d) ?>)'>Editar</button>
                                        <button class="btn btn-outline-danger" onclick="eliminarDocente(<?= $d['id_docente'] ?>)">Eliminar</button>
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

<!-- Modal de Edición -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <form id="formEditar" class="modal-content rounded-4 border-0 shadow-sm">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id_docente" id="edit_id_docente">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title">Editar Docente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                <label class="form-label mt-2">Identificación</label>
                <input type="text" name="identificacion" id="edit_identificacion" class="form-control" required>
                <label class="form-label mt-2">NIT</label>
                <input type="text" name="nit" id="edit_nit" class="form-control">
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#tablaDocentes tbody');
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

    document.getElementById('buscarDocente').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        filteredRows = rows.filter(r => {
            const tds = r.getElementsByTagName('td');
            return [tds[1], tds[2], tds[3]].some(td => td.textContent.toLowerCase().includes(term));
        });
        currentPage = 1;
        displayRows(currentPage);
        setupPagination();
    });

    displayRows(currentPage);
    setupPagination();
});

function editarDocente(d) {
    document.getElementById('edit_id_docente').value = d.id_docente;
    document.getElementById('edit_nombre').value = d.nombre;
    document.getElementById('edit_identificacion').value = d.identificacion;
    document.getElementById('edit_nit').value = d.nit;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

document.getElementById('formEditar').addEventListener('submit', e => {
    e.preventDefault();
    const form = new FormData(e.target);
    fetch('docentes.php', { method: 'POST', body: form })
        .then(res => res.json())
        .then(data => {
            if (data.duplicado) Swal.fire('Error', 'Ya existe otro docente con esa identificación', 'error');
            else if (data.success) Swal.fire('Actualizado', 'Docente actualizado con éxito', 'success').then(() => location.reload());
            else Swal.fire('Error', 'No se pudo actualizar el docente', 'error');
        });
});

function eliminarDocente(id) {
    Swal.fire({
        title: '¿Eliminar docente?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            const form = new FormData();
            form.append('accion', 'eliminar');
            form.append('id_docente', id);
            fetch('docentes.php', { method: 'POST', body: form })
                .then(res => res.json())
                .then(data => {
                    if (data.success) Swal.fire('Eliminado', 'Docente eliminado con éxito', 'success').then(() => location.reload());
                    else Swal.fire('Error', 'No se pudo eliminar el docente', 'error');
                });
        }
    });
}

<?php if (isset($_SESSION['mensaje'])): ?>
Swal.fire({
    icon: '<?= $_SESSION['mensaje']['tipo'] ?>',
    title: '<?= $_SESSION['mensaje']['tipo'] === 'success' ? 'Éxito' : 'Error' ?>',
    text: '<?= $_SESSION['mensaje']['texto'] ?>',
});
<?php unset($_SESSION['mensaje']); endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
