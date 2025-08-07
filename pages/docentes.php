<?php
session_start();
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && in_array($_POST['accion'], ['editar', 'eliminar'])) {
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

// Crear docente con PRG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
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
    <h2>Gestión de Docentes</h2>
    <form method="POST" class="row g-3 mb-4">
        <input type="hidden" name="accion" value="crear">
        <div class="col-md-4">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label>Identificación</label>
            <input type="text" name="identificacion" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label>NIT</label>
            <input type="text" name="nit" class="form-control">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-success">Registrar Docente</button>
        </div>
    </form>

    <!-- 🔎 Campo de búsqueda -->
    <div class="mb-3">
        <input type="text" id="buscarDocente" class="form-control" placeholder="Buscar por nombre, identificación o NIT...">
    </div>

    <div class="table-responsive">
        <table id="tablaDocentes" class="table table-bordered">
            <thead class="table-dark">
                <tr><th>#</th><th>Nombre</th><th>Identificación</th><th>NIT</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php $i = 1; while ($d = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($d['nombre']) ?></td>
                        <td><?= htmlspecialchars($d['identificacion']) ?></td>
                        <td><?= htmlspecialchars($d['nit']) ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning" onclick='editarDocente(<?= json_encode($d) ?>)'>Editar</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="eliminarDocente(<?= $d['id_docente'] ?>)">Eliminar</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <div class="d-flex justify-content-center mt-3">
        <nav>
            <ul class="pagination" id="pagination"></ul>
        </nav>
    </div>
</div>


<!-- Modal edición -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <form id="formEditar" class="modal-content">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id_docente" id="edit_id_docente">
            <div class="modal-header">
                <h5 class="modal-title">Editar Docente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label>Nombre</label>
                <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                <label class="mt-2">Identificación</label>
                <input type="text" name="identificacion" id="edit_identificacion" class="form-control" required>
                <label class="mt-2">NIT</label>
                <input type="text" name="nit" id="edit_nit" class="form-control">
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// PAGINACIÓN DE TABLA DE DOCENTES
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#tablaDocentes tbody');
    const rows = Array.from(table.getElementsByTagName('tr'));
    const rowsPerPage = 10;
    const pagination = document.getElementById('pagination');
    let filteredRows = [...rows];
    let currentPage = 1;

    function displayRows(page) {
        rows.forEach(r => r.style.display = 'none');
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        filteredRows.slice(start, end).forEach(r => r.style.display = '');
    }

    function setupPagination() {
        pagination.innerHTML = '';
        const pageCount = Math.ceil(filteredRows.length / rowsPerPage);
        if (pageCount === 0) return;

        const prev = `<li class="page-item ${currentPage===1?'disabled':''}">
                        <a class="page-link" href="#">Anterior</a></li>`;
        pagination.insertAdjacentHTML('beforeend', prev);

        for (let i=1; i<=pageCount; i++) {
            pagination.insertAdjacentHTML('beforeend', 
                `<li class="page-item ${i===currentPage?'active':''}">
                    <a class="page-link" href="#">${i}</a>
                </li>`);
        }

        const next = `<li class="page-item ${currentPage===pageCount?'disabled':''}">
                        <a class="page-link" href="#">Siguiente</a></li>`;
        pagination.insertAdjacentHTML('beforeend', next);

        pagination.querySelectorAll('.page-link').forEach(btn => {
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

    // 🔎 Búsqueda en tiempo real
    document.getElementById('buscarDocente').addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        filteredRows = rows.filter(row => {
            const cols = row.getElementsByTagName('td');
            return (
                cols[1].textContent.toLowerCase().includes(term) || // Nombre
                cols[2].textContent.toLowerCase().includes(term) || // Identificación
                cols[3].textContent.toLowerCase().includes(term)    // NIT
            );
        });
        currentPage = 1;
        displayRows(currentPage);
        setupPagination();
    });

    displayRows(currentPage);
    setupPagination();
});

// EDITAR DOCENTE
function editarDocente(d) {
    document.getElementById('edit_id_docente').value = d.id_docente;
    document.getElementById('edit_nombre').value = d.nombre;
    document.getElementById('edit_identificacion').value = d.identificacion;
    document.getElementById('edit_nit').value = d.nit;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

document.getElementById('formEditar').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = new FormData(this);
    fetch('docentes.php', { method: 'POST', body: form })
    .then(res => res.json())
    .then(data => {
        if (data.duplicado) Swal.fire('Error', 'Ya existe otro docente con esa identificación', 'error');
        else if (data.success) Swal.fire('Actualizado', 'Docente actualizado con éxito', 'success').then(() => location.reload());
        else Swal.fire('Error', 'No se pudo actualizar el docente', 'error');
    })
    .catch(() => Swal.fire('Error', 'Fallo en la solicitud', 'error'));
});

function eliminarDocente(id) {
    Swal.fire({
        title: '¿Eliminar docente?',
        text: "Esta acción no se puede deshacer",
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
            })
            .catch(() => Swal.fire('Error', 'Fallo en la solicitud', 'error'));
        }
    });
}

<?php if (isset($_SESSION['mensaje'])): ?>
Swal.fire({
    icon: '<?= $_SESSION['mensaje']['tipo'] ?>',
    title: '<?= $_SESSION['mensaje']['tipo'] === 'success' ? 'Éxito' : 'Error' ?>',
    text: '<?= $_SESSION['mensaje']['texto'] ?>',
    confirmButtonText: 'Aceptar'
});
<?php unset($_SESSION['mensaje']); endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
