<?php
/* =========================================================
   CONFIRMAR PAGO (PayPal)
   Lo llama pago.html cuando el cliente aprueba el pago en PayPal.
     1. Revisa que el pedido sea el de ESTA sesión
     2. Revisa que haya stock
     3. CAPTURA el cobro en PayPal desde el servidor y verifica monto y estado
     4. Marca el pedido como "pagado", DESCUENTA el stock y SUMA a "vendidos"
   ========================================================= */

session_start();
require_once 'conexion.php';
require_once 'config_paypal.php';

header('Content-Type: application/json; charset=utf-8');

function responder($ok, $mensaje = '', $codigo = 200) {
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'error' => $mensaje]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método no permitido.', 405);
}

$folio    = trim($_POST['folio'] ?? '');
$order_id = trim($_POST['orderID'] ?? '');

// Solo se puede pagar el pedido que se hizo en ESTA sesión
if (!isset($_SESSION['pedido_actual']) || $_SESSION['pedido_actual']['folio'] !== $folio) {
    responder(false, 'No encontramos tu pedido. Vuelve a hacerlo desde el formulario.', 403);
}
if ($order_id === '' || !preg_match('/^[A-Za-z0-9-]{5,40}$/', $order_id)) {
    responder(false, 'Falta la orden de PayPal.', 400);
}

try {
    $conexion->begin_transaction();

    // Buscar el pedido (y bloquearlo mientras se procesa)
    $stmt = $conexion->prepare("SELECT id, estado, total FROM pedidos WHERE folio = ? FOR UPDATE");
    $stmt->bind_param('s', $folio);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pedido) {
        throw new Exception('Pedido inexistente.');
    }

    // Si ya estaba pagado, no se vuelve a cobrar ni a descontar nada
    if ($pedido['estado'] === 'pagado') {
        $conexion->commit();
        responder(true);
    }
    if ($pedido['estado'] !== 'pendiente') {
        throw new Exception('Este pedido ya no se puede pagar.');
    }

    // Líneas del pedido y revisión de stock (antes de cobrar)
    $stmt = $conexion->prepare("SELECT producto_id, cantidad FROM detalle_pedido WHERE pedido_id = ?");
    $stmt->bind_param('i', $pedido['id']);
    $stmt->execute();
    $lineas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmtRev = $conexion->prepare("SELECT stock FROM productos WHERE id = ? FOR UPDATE");
    foreach ($lineas as $l) {
        $pid = (int) $l['producto_id'];
        $stmtRev->bind_param('i', $pid);
        $stmtRev->execute();
        $p = $stmtRev->get_result()->fetch_assoc();
        if (!$p || (int) $p['stock'] < (int) $l['cantidad']) {
            throw new Exception('Se agotó uno de los productos.');
        }
    }
    $stmtRev->close();

    // Capturar el pago en PayPal y verificarlo
    $token = paypal_token();
    list($codigo, $r) = paypal_api('POST', '/v2/checkout/orders/' . $order_id . '/capture', null, $token);

    if ($codigo < 200 || $codigo >= 300 || ($r['status'] ?? '') !== 'COMPLETED') {
        throw new Exception('PayPal no completó el pago.');
    }
    $captura = $r['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (!$captura || ($captura['status'] ?? '') !== 'COMPLETED') {
        throw new Exception('PayPal no completó el pago.');
    }
    $monto_ok = abs((float) $captura['amount']['value'] - (float) $pedido['total']) < 0.01
                && $captura['amount']['currency_code'] === PAYPAL_MONEDA;
    if (!$monto_ok) {
        throw new Exception('El monto pagado no coincide con el pedido.');
    }
    $paypal_id = mb_substr($captura['id'], 0, 60);      // id de la transacción

    // Marcar como pagado
    $stmt = $conexion->prepare("UPDATE pedidos SET estado = 'pagado', paypal_id = ? WHERE id = ?");
    $stmt->bind_param('si', $paypal_id, $pedido['id']);
    $stmt->execute();
    $stmt->close();

    // Descontar stock y sumar vendidos, producto por producto
    $stmtStock = $conexion->prepare(
        "UPDATE productos SET stock = stock - ?, vendidos = vendidos + ? WHERE id = ? AND stock >= ?"
    );
    foreach ($lineas as $l) {
        $cant = (int) $l['cantidad'];
        $pid  = (int) $l['producto_id'];
        $stmtStock->bind_param('iiii', $cant, $cant, $pid, $cant);
        $stmtStock->execute();
        if ($stmtStock->affected_rows !== 1) {
            throw new Exception('Se agotó uno de los productos.');
        }
    }
    $stmtStock->close();

    $conexion->commit();
    responder(true);

} catch (Throwable $e) {
    $conexion->rollback();
    responder(false, 'No se pudo confirmar el pago: ' . $e->getMessage(), 409);
}
