<?php
/* 
   ORDEN.PHP  -  
   Recibe el formulario de pedido
   1. Valida los datos
   2. Lee los PRECIOS de la base de datos (no confía en el HTML)
   3. Revisa que haya stock
   4. Guarda el pedido (estado "pendiente") y su detalle
   5. Guarda el pedido en la SESIÓN y manda al cliente a pago.html
   El stock se descuenta hasta que se confirma el pago (confirmar_pago.php)
    */

session_start();
require_once 'conexion.php';

// Mensaje de error sencillo con enlace para regresar
function volver_con_error($mensaje) {
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Pedido</title></head>';
    echo '<body style="font-family:sans-serif;text-align:center;padding:3rem">';
    echo '<h2>No se pudo hacer tu pedido</h2>';
    echo '<p>' . htmlspecialchars($mensaje) . '</p>';
    echo '<a href="../proyecto.html#pedido">← Volver al formulario</a>';
    echo '</body></html>';
    exit;
}

// Solo se aceptan pedidos que vengan del formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../proyecto.html#pedido');
    exit;
}

//  Datos del cliente 
$nombre      = mb_substr(trim($_POST['nombre'] ?? ''), 0, 100);
$telefono    = mb_substr(trim($_POST['telefono'] ?? ''), 0, 20);
$comentarios = mb_substr(trim($_POST['comentarios'] ?? ''), 0, 255);
$tipo        = (($_POST['tipo'] ?? '') === 'llevar') ? 'llevar' : 'aqui';

if ($nombre === '' || $telefono === '') {
    volver_con_error('Escribe tu nombre y tu teléfono.');
}

//  Productos y precios desde la base 
$productos = [];
$resultado = $conexion->query("SELECT id, clave, nombre, precio, stock FROM productos WHERE activo = 1");
while ($fila = $resultado->fetch_assoc()) {
    $productos[$fila['clave']] = $fila;     // gordita, tortillon, burrito
}

//  Armar la lista de lo que pidió 
$items = [];
$total = 0;

foreach ($productos as $clave => $prod) {
    $cantidad = (int) ($_POST['cantidad_' . $clave] ?? 0);
    if ($cantidad <= 0) {
        continue;                              // no pidió este producto
    }
    if ($cantidad > 50) {
        $cantidad = 50;                        // tope por pedido
    }
    if ($cantidad > (int) $prod['stock']) {
        volver_con_error('Por ahora solo nos quedan ' . (int) $prod['stock'] . ' de ' . $prod['nombre'] . '.');
    }

    $guiso    = mb_substr(trim($_POST['guiso_' . $clave] ?? ''), 0, 60);
    $precio   = (float) $prod['precio'];
    $subtotal = $cantidad * $precio;

    $items[] = [
        'id'       => (int) $prod['id'],
        'nombre'   => $prod['nombre'],
        'guiso'    => $guiso,
        'cantidad' => $cantidad,
        'precio'   => $precio,
        'subtotal' => $subtotal,
    ];
    $total += $subtotal;
}

if (count($items) === 0) {
    volver_con_error('Agrega al menos una pieza a tu pedido.');
}
$total = round($total, 2);

// Guardar pedido y detalle (todo o nada)
$folio = 'GF-' . date('ymd') . '-' . random_int(1000, 9999);

try {
    $conexion->begin_transaction();

    $stmt = $conexion->prepare(
        "INSERT INTO pedidos (folio, cliente, telefono, tipo, comentarios, total) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssssd', $folio, $nombre, $telefono, $tipo, $comentarios, $total);
    $stmt->execute();
    $pedido_id = $conexion->insert_id;
    $stmt->close();

    $stmtDet = $conexion->prepare(
        "INSERT INTO detalle_pedido (pedido_id, producto_id, producto, guiso, cantidad, precio_unitario, subtotal)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($items as $it) {
        $stmtDet->bind_param(
            'iissidd',
            $pedido_id, $it['id'], $it['nombre'], $it['guiso'], $it['cantidad'], $it['precio'], $it['subtotal']
        );
        $stmtDet->execute();
    }
    $stmtDet->close();

    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
    volver_con_error('Ocurrió un problema al guardar tu pedido. Intenta de nuevo.');
}

//  Variables de SESIÓN y paso a la página de pago 
$_SESSION['pedido_actual'] = [
    'id'     => $pedido_id,
    'folio'  => $folio,
    'total'  => $total,
    'cliente'=> $nombre,
];

$lineas = [];
foreach ($items as $it) {
    $lineas[] = $it['cantidad'] . ' × ' . $it['nombre'] . ' (' . $it['guiso'] . ') — $' . number_format($it['subtotal'], 2);
}

$datos = http_build_query([
    'folio'   => $folio,
    'total'   => number_format($total, 2, '.', ''),
    'nombre'  => $nombre,
    'tipo'    => ($tipo === 'llevar') ? 'Para llevar' : 'Comer aquí',
    'detalle' => implode('|', $lineas),
]);

header('Location: ../pago.html?' . $datos);
exit;
