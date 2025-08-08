<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top border-bottom">
  <div class="container-fluid px-4">

    <!-- Logo y título -->
    <a class="navbar-brand d-flex align-items-center fw-bold text-primary" href="/nomina/pages/dashboard.php">
      <img src="/nomina/assets/images/polinorte.png" alt="Logo" width="160" height="45" class="me-2">
    </a>

    <!-- Menú hamburguesa móvil -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Contenido del menú -->
    <div class="collapse navbar-collapse" id="mainNavbar">

      <!-- Enlaces principales -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="/nomina/pages/dashboard.php">🏠 Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/nomina/pages/docentes.php">👩‍🏫 Docentes</a></li>
        <li class="nav-item"><a class="nav-link" href="/nomina/pages/liquidacion.php">🧾 Liquidaciones</a></li>
        <li class="nav-item"><a class="nav-link" href="/nomina/pages/unid_curricular.php">📘 Unidades Curriculares</a></li>
        <li class="nav-item"><a class="nav-link" href="/nomina/pages/catalogo_unidades.php">📚 Catálogo de Unidades</a></li>
        <li class="nav-item"><a class="nav-link" href="/nomina/pages/asignar_unidad.php">📝 Asignar Unidad</a></li>
        <li class="nav-item"><a class="nav-link" href="/nomina/pages/sedes.php">🏫 Sedes</a></li>
        <li class="nav-item"><a class="nav-link" href="/nomina/pages/facturas.php">💼 Facturas</a></li>
        <li class="nav-item"><a class="nav-link" href="/nomina/pages/pagos_recibidos.php">💳 Pagos Recibidos</a></li>
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
