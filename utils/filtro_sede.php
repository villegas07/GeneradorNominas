<?php
//session_start();
require_once '../utils/verificar_sesion.php';
include '../config/db.php';

// Obtener todas las sedes para el select
$sedes = $conn->query("SELECT id_sede, nombre FROM sede ORDER BY nombre");

// Obtener sede seleccionada (o 0 para todas)
$sede_id = isset($_GET['sede_id']) ? intval($_GET['sede_id']) : 0;

$where_sede = "";
if ($sede_id > 0) {
    // Filtrar por sede en las consultas
    $where_sede = " WHERE uc.id_sede = $sede_id ";
}

// Total facturado (suma monto detalle_factura filtrado por sede)
$sql_total_facturado = "
    SELECT IFNULL(SUM(df.monto), 0) AS total_facturado
    FROM detalle_factura df
    JOIN unidad_curricular uc ON df.id_unidad = uc.id_unidad
    $where_sede
";

$result = $conn->query($sql_total_facturado);
$total_facturado = $result ? floatval($result->fetch_assoc()['total_facturado']) : 0;

// Total pagado proporcional (repartiendo pagos según % monto por sede en la factura)
$sql_total_pagado = "
    SELECT IFNULL(SUM(pf.monto * (df.monto / f.total_pago)), 0) AS total_pagado
    FROM detalle_factura df
    JOIN factura f ON df.id_factura = f.id_factura
    JOIN unidad_curricular uc ON df.id_unidad = uc.id_unidad
    LEFT JOIN pago_factura pf ON pf.id_factura = f.id_factura
    $where_sede
";

$result = $conn->query($sql_total_pagado);
$total_pagado = $result ? floatval($result->fetch_assoc()['total_pagado']) : 0;

// Total pendiente
$total_pendiente = $total_facturado - $total_pagado;

// Top 5 docentes con mayor facturación (sumando detalle_factura filtrado por sede)
$sql_top_docentes = "
    SELECT d.nombre, IFNULL(SUM(df.monto), 0) AS total
    FROM detalle_factura df
    JOIN factura f ON df.id_factura = f.id_factura
    JOIN docente d ON f.id_docente = d.id_docente
    JOIN unidad_curricular uc ON df.id_unidad = uc.id_unidad
    $where_sede
    GROUP BY d.id_docente
    ORDER BY total DESC
    LIMIT 5
";

$top_docentes = $conn->query($sql_top_docentes);

// Facturas por mes (suma monto detalle_factura por mes filtrado por sede)
$sql_facturas_por_mes = "
    SELECT DATE_FORMAT(f.fecha, '%Y-%m') AS mes, IFNULL(SUM(df.monto), 0) AS total
    FROM detalle_factura df
    JOIN factura f ON df.id_factura = f.id_factura
    JOIN unidad_curricular uc ON df.id_unidad = uc.id_unidad
    $where_sede
    GROUP BY mes
    ORDER BY mes
";

$facturas_por_mes = $conn->query($sql_facturas_por_mes);

// Pagos por mes (distribuyendo proporcionalmente pagos de factura, agrupados por mes)
$sql_pagos_por_mes = "
    SELECT
        DATE_FORMAT(pf.fecha, '%Y-%m') AS mes,
        IFNULL(SUM(pf.monto * (df.monto / f.total_pago)), 0) AS total
    FROM pago_factura pf
    JOIN factura f ON pf.id_factura = f.id_factura
    JOIN detalle_factura df ON f.id_factura = df.id_factura
    JOIN unidad_curricular uc ON df.id_unidad = uc.id_unidad
    $where_sede
    GROUP BY mes
    ORDER BY mes
";

$pago_por_mes = $conn->query($sql_pagos_por_mes);
?>