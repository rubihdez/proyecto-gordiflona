<?php
/* =========================================================
   PANEL DEL ADMINISTRADOR (página protegida)
   Muestra: resumen de ventas, tabla de productos y tabla de pedidos
   ========================================================= */

require 'proteger.php';          // si no hay sesión, te manda al login
require_once '../conexion.php';

// Función corta para escribir texto de forma segura en el HTML
function e($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// 1) Resumen de ventas
$resumen = $conexion->query(
    "SELECT COALESCE(SUM(estado = 'pagado'), 0)    AS pagados,
            COALESCE(SUM(estado = 'pendiente'), 0) AS pendientes,
            COALESCE(SUM(CASE WHEN estado = 'pagado' THEN total END), 0) AS ventas
     FROM pedidos"
)->fetch_assoc();

// 2) Productos con todos sus datos
$productos = $conexion->query(
    "SELECT id, nombre, descripcion, precio, stock, vendidos, imagen, activo
     FROM productos
     ORDER BY id"
);

// 3) Los 30 pedidos más recientes, con lo que pidió cada cliente
$pedidos = $conexion->query(
    "SELECT p.id, p.folio, p.cliente, p.telefono, p.tipo, p.total, p.estado, p.fecha, p.paypal_id,
            GROUP_CONCAT(CONCAT(d.cantidad, ' x ', d.producto, ' (', COALESCE(d.guiso, '-'), ')') SEPARATOR '||') AS detalle
     FROM pedidos p
     LEFT JOIN detalle_pedido d ON d.pedido_id = p.id
     GROUP BY p.id
     ORDER BY p.id DESC
     LIMIT 30"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel | La Gordiflona</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400..900&family=Cormorant+Garamond:ital,wght@0,300..700;1,300..700&family=Truculenta:opsz,wght@12..72,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body>

    <header class="panel-barra">
        <div class="panel-barra__contenido">
            <a href="../../proyecto.html" class="panel-logo">
                <img src="../../imagenes/Gordilonalogo.png" alt="La Gordiflona">
            </a>
            <span class="panel-titulo">PANEL DE ADMINISTRACIÓN</span>
            <div class="panel-usuario">
                <span>Hola, <?= e($_SESSION['admin_nombre']) ?></span>
                <a href="logout.php" class="btn-salir">Cerrar sesión</a>
            </div>
        </div>
    </header>

    <main class="panel-contenido">

        <section class="panel-bienvenida">
            <span class="login-etiqueta">BIENVENIDO</span>
            <h1>Hola, <?= e($_SESSION['admin_nombre']) ?></h1>
            <p>Aquí ves los productos, el inventario y los pedidos de La Gordiflona.</p>
        </section>

        <!-- Resumen -->
        <div class="stats">
            <article class="stat">
                <span class="stat-num"><?= (int) $resumen['pagados'] ?></span>
                <span class="stat-txt">Pedidos pagados</span>
            </article>
            <article class="stat">
                <span class="stat-num"><?= (int) $resumen['pendientes'] ?></span>
                <span class="stat-txt">Pedidos pendientes</span>
            </article>
            <article class="stat">
                <span class="stat-num">$<?= number_format((float) $resumen['ventas'], 2) ?></span>
                <span class="stat-txt">Ventas totales</span>
            </article>
        </div>

        <!-- Tabla de productos -->
        <section class="panel-seccion">
            <h2>Productos</h2>
            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Foto</th>
                            <th>Producto</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Vendidos</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($prod = $productos->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int) $prod['id'] ?></td>
                            <td>
                                <?php if ($prod['imagen']): ?>
                                    <img class="miniatura" src="../../<?= e($prod['imagen']) ?>" alt="<?= e($prod['nombre']) ?>">
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($prod['nombre']) ?></strong><br>
                                <small><?= e($prod['descripcion']) ?></small>
                            </td>
                            <td>$<?= number_format((float) $prod['precio'], 2) ?></td>
                            <td class="<?= (int) $prod['stock'] <= 10 ? 'stock-bajo' : '' ?>"><?= (int) $prod['stock'] ?></td>
                            <td><?= (int) $prod['vendidos'] ?></td>
                            <td>
                                <span class="etiqueta-estado <?= $prod['activo'] ? 'ok' : 'off' ?>">
                                    <?= $prod['activo'] ? 'Activo' : 'Oculto' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Tabla de pedidos -->
        <section class="panel-seccion">
            <h2>Pedidos recientes</h2>
            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Tipo</th>
                            <th>Pedido</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>ID PayPal</th>
                            <th>Recibo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($pedidos->num_rows === 0): ?>
                        <tr><td colspan="10" class="tabla-vacia">Todavía no hay pedidos.</td></tr>
                    <?php endif; ?>

                    <?php while ($ped = $pedidos->fetch_assoc()): ?>
                        <?php $lineas = $ped['detalle'] ? explode('||', $ped['detalle']) : []; ?>
                        <tr>
                            <td><strong><?= e($ped['folio']) ?></strong></td>
                            <td><?= e(date('d/m/Y H:i', strtotime($ped['fecha']))) ?></td>
                            <td><?= e($ped['cliente']) ?></td>
                            <td><?= e($ped['telefono']) ?></td>
                            <td><?= $ped['tipo'] === 'llevar' ? 'Para llevar' : 'Comer aquí' ?></td>
                            <td><?= implode('<br>', array_map('e', $lineas)) ?></td>
                            <td>$<?= number_format((float) $ped['total'], 2) ?></td>
                            <td><span class="etiqueta-estado <?= e($ped['estado']) ?>"><?= e(ucfirst($ped['estado'])) ?></span></td>
                            <td><?= e($ped['paypal_id'] ?? '-') ?></td>
                            <td><?php if ($ped['estado'] === 'pagado'): ?><a href="../recibo.php?folio=<?= urlencode($ped['folio']) ?>">PDF</a><?php else: ?>-<?php endif; ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Variables de sesión activas -->
        <section class="panel-seccion">
            <article class="panel-tarjeta">
                <h2>Sesión activa</h2>
                <ul class="datos-sesion">
                    <li><strong>ID de administrador:</strong> <?= (int) $_SESSION['admin_id'] ?></li>
                    <li><strong>Nombre:</strong> <?= e($_SESSION['admin_nombre']) ?></li>
                    <li><strong>Inicio de sesión:</strong> <?= e($_SESSION['admin_inicio']) ?></li>
                </ul>
            </article>
        </section>

    </main>

</body>
</html>
