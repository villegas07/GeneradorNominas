<?php
session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');

    if ($_POST['accion'] === 'editar') {
        $id = intval($_POST['id_usuario']);
         // Verificar si es super admin
    $verificar = $conn->prepare("SELECT super_admin FROM usuario WHERE id_usuario = ?");
    $verificar->bind_param("i", $id);
    $verificar->execute();
    $verificar->bind_result($es_super_admin);
    $verificar->fetch();
    $verificar->close();

    if ($es_super_admin) {
        echo json_encode(['success' => false, 'error' => 'No se puede editar el super administrador']);
        exit;
    }
        $username = $_POST['username'];
        $estado = $_POST['estado'];
        $rol = $_POST['rol'];

        $stmt = $conn->prepare("SELECT COUNT(*) FROM usuario WHERE username = ? AND id_usuario != ?");
        $stmt->bind_param("si", $username, $id);
        $stmt->execute();
        $stmt->bind_result($existe);
        $stmt->fetch();
        $stmt->close();

        if ($existe > 0) {
            echo json_encode(['success' => false, 'duplicado' => true]);
        } else {
            $stmt = $conn->prepare("UPDATE usuario SET username=?, rol=?, estado=? WHERE id_usuario=?");
            $stmt->bind_param("ssii", $username, $rol, $estado, $id);
            $ok = $stmt->execute();
            echo json_encode(['success' => $ok]);
            $stmt->close();
        }
        exit;
    }

    if ($_POST['accion'] === 'eliminar') {
        $id = intval($_POST['id_usuario']);

        // Verificar si el usuario es super admin
    $verificar = $conn->prepare("SELECT super_admin FROM usuario WHERE id_usuario = ?");
    $verificar->bind_param("i", $id);
    $verificar->execute();
    $verificar->bind_result($es_super_admin);
    $verificar->fetch();
    $verificar->close();

    if ($es_super_admin) {
        echo json_encode(['success' => false, 'error' => 'No se puede eliminar el super administrador']);
        exit;
    }

        $stmt = $conn->prepare("DELETE FROM usuario WHERE id_usuario = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        echo json_encode(['success' => $affected > 0]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'crear') {
    $username = $_POST['username'];
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    $rol = $_POST['rol'];
    $estado = intval($_POST['estado']);

    $stmt = $conn->prepare("SELECT COUNT(*) FROM usuario WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($existe);
    $stmt->fetch();
    $stmt->close();

    if ($existe > 0) {
        $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'El nombre de usuario ya está registrado'];
    } else {
        $stmt = $conn->prepare("INSERT INTO usuario (username, password_hash, rol, estado) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $username, $contrasena, $rol, $estado);
        $ok = $stmt->execute();
        $_SESSION['mensaje'] = [
            'tipo' => $ok ? 'success' : 'error',
            'texto' => $ok ? 'Usuario registrado exitosamente' : 'No se pudo registrar el usuario'
        ];
        $stmt->close();
    }

    header("Location: gestionar_usuarios.php");
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';

$result = $conn->query("SELECT * FROM usuario ORDER BY id_usuario DESC");
?>

<div class="container mt-5">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center rounded-top-4">
            <h5 class="mb-0">Gestión de Usuarios</h5>
            <button class="btn btn-light btn-sm fw-semibold" data-bs-toggle="collapse" data-bs-target="#formRegistro">
                ➕ Nuevo Usuario
            </button>
        </div>
        <div class="collapse" id="formRegistro">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="accion" value="crear">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Usuario</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Contraseña</label>
                        <input type="password" name="contrasena" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Rol</label>
                        <select name="rol" class="form-select" required>
                            <option value="admin">Administrador</option>
                            <option value="contabilidad">Contabilidad</option>
                            <option value="direccion">Dirección</option>
                            <option value="docente">Docente</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Estado</label>
                        <select name="estado" class="form-select" required>
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
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
                <input type="text" id="buscarUsuario" class="form-control shadow-sm"
                    placeholder="🔍 Buscar usuario o rol...">
            </div>
            <div class="table-responsive">
                <table id="tablaUsuarios"
                    class="table table-bordered table-hover align-middle rounded-4 overflow-hidden">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($u = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?= $u['id_usuario'] ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= ucfirst($u['rol']) ?></td>
                            <td><?= $u['estado'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning"
                                        onclick='editarUsuario(<?= json_encode($u) ?>)'>Editar</button>
                                    <button class="btn btn-outline-danger"
                                        onclick="eliminarUsuario(<?= $u['id_usuario'] ?>)">Eliminar</button>
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

<!-- Modal -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <form id="formEditar" class="modal-content rounded-4 border-0 shadow-sm">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id_usuario" id="edit_id_usuario">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title">Editar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Usuario</label>
                <input type="text" name="username" id="edit_username" class="form-control" required>
                <label class="form-label mt-2">Rol</label>
                <select name="rol" id="edit_rol" class="form-select" required>
                    <option value="admin">Administrador</option>
                    <option value="contabilidad">Contabilidad</option>
                    <option value="direccion">Dirección</option>
                    <option value="docente">Docente</option>
                </select>
                <label class="form-label mt-2">Estado</label>
                <select name="estado" id="edit_estado" class="form-select" required>
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#tablaUsuarios tbody');
    const rows = Array.from(table.getElementsByTagName('tr'));
    const pagination = document.getElementById('pagination');
    let currentPage = 1,
        rowsPerPage = 10,
        filteredRows = [...rows];

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

    document.getElementById('buscarUsuario').addEventListener('input', function() {
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

function editarUsuario(u) {
    document.getElementById('edit_id_usuario').value = u.id_usuario;
    document.getElementById('edit_username').value = u.username;
    document.getElementById('edit_rol').value = u.rol;
    document.getElementById('edit_estado').value = u.estado;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

document.getElementById('formEditar').addEventListener('submit', e => {
    e.preventDefault();
    const form = new FormData(e.target);
    fetch('gestionar_usuarios.php', {
            method: 'POST',
            body: form
        })
        .then(res => res.json())
        .then(data => {
            if (data.duplicado) Swal.fire('Error', 'El usuario ya existe', 'error');
            else if (data.error) Swal.fire('Error', data.error, 'error');
            else if (data.success) Swal.fire('Actualizado', 'Usuario actualizado con éxito', 'success')
                .then(() => location.reload());
            else Swal.fire('Error', 'No se pudo actualizar el usuario', 'error');
        });
});

function eliminarUsuario(id) {
    Swal.fire({
        title: '¿Eliminar usuario?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            const form = new FormData();
            form.append('accion', 'eliminar');
            form.append('id_usuario', id);
            fetch('gestionar_usuarios.php', {
                    method: 'POST',
                    body: form
                })
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        Swal.fire('Error', data.error, 'error');
                    } else if (data.success) {
                        Swal.fire('Correcto', 'Operación realizada con éxito', 'success').then(() =>
                            location.reload());
                    } else {
                        Swal.fire('Error', 'Ocurrió un problema inesperado', 'error');
                    }
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