<?php
$current_page = basename($_SERVER['PHP_SELF']); // Obtiene el nombre del archivo actual
?>
<style>
/* Hover */
.nav-link:hover {
  background-color: #f8f9fa;
  border-radius: 5px;
}

/* Página activa */
.nav-link.active {
  background-color: #e9ecef;
  font-weight: bold;
  border-radius: 5px;
}
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top border-bottom">
  <div class="container-fluid px-4">

    <!-- Logo -->
    <a class="navbar-brand d-flex align-items-center fw-bold text-primary" href="/nomina/pages/dashboard.php">
      <img src="/nomina/assets/images/polinorte.png" alt="Logo" width="160" height="45" class="me-2">
    </a>

    <!-- Menú hamburguesa -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Menú principal -->
    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="/nomina/pages/dashboard.php">🏠 Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page == 'docentes.php' ? 'active' : '' ?>" href="/nomina/pages/docentes.php">👩‍🏫 Docentes</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page == 'liquidacion.php' ? 'active' : '' ?>" href="/nomina/pages/liquidacion.php">🧾 Liquidaciones</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page == 'unid_curricular.php' ? 'active' : '' ?>" href="/nomina/pages/unid_curricular.php">📘 Unid Curriculares</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page == 'catalogo_unidades.php' ? 'active' : '' ?>" href="/nomina/pages/catalogo_unidades.php">📚 Catálogo de Unid</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page == 'asignar_unidad.php' ? 'active' : '' ?>" href="/nomina/pages/asignar_unidad.php">📝 Asignar Unidad</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page == 'sedes.php' ? 'active' : '' ?>" href="/nomina/pages/sedes.php">🏫 Sedes</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page == 'facturas.php' ? 'active' : '' ?>" href="/nomina/pages/facturas.php">💼 Facturas</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page == 'pagos_recibidos.php' ? 'active' : '' ?>" href="/nomina/pages/pagos_recibidos.php">💳 Pagos</a></li>
      </ul>

      <!-- Usuario y Logout -->
      <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle fw-semibold text-primary" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            👤 <?php echo $_SESSION['usuario'] ?? 'Invitado'; ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li><a class="dropdown-item" href="gestionar_usuarios.php">Gestión de usuarios</a></li>
            <li><a class="dropdown-item" href="configuracion.php">Configuración de Factura</a></li>
            <li>
              <form action="/nomina/logout.php" method="POST" class="d-inline">
                <button type="submit" class="dropdown-item text-danger"><a href="logout.php" class="dropdown-item text-danger">Cerrar sesión</a>
</button>
              </form>
            </li>
          </ul>
        </li>
      </ul>
    </div>

  </div>
</nav>
