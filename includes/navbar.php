<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">

        <!-- Logo + Nombre -->
        <a class="navbar-brand d-flex align-items-center" href="/nomina/index.php">
            <img src="/nomina/assets/images/polinorte.png" alt="Logo" width="170" height="50" class="d-inline-block me-2">
        </a>

        <!-- Botón de hamburguesa para móviles -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menú colapsable -->
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- Enlaces de navegación -->
                <li class="nav-item">
                    <a class="nav-link" href="/nomina/pages/docentes.php">Docentes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/nomina/pages/liquidacion.php">Liquidaciones</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/nomina/pages/unid_curricular.php">Unidades Curriculares</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/nomina/pages/asignar_unidad.php">Asignar Unidade</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/nomina/pages/sedes.php">Sedes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/nomina/pages/facturas.php">Facturas</a>
                </li>
            </ul>

            <!-- Usuario logueado (simulado) -->
            <span class="navbar-text text-white">
                <?php echo $_SESSION['usuario'] ?? 'Invitado'; ?>
            </span>
        </div>
    </div>
</nav>
