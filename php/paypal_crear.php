<?php
/* =========================================================
   Crea la orden en PayPal. El monto se lee de la BASE DE DATOS
   (nunca de lo que mande el navegador) y el pedido debe ser
   el de la SESIÓN actual.
   ========================================================= */
session_start();
require_once 'conexion.php';
require_once 'config_paypal.php';
header('Content-Type: application/json; charset=utf-8');

function fallo($msg, $codigo = 400) {
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fallo('Método no permitido.', 405);
if (!isset($_SESSION['pedido_actual'])) fallo('No encontramos tu pedido. Hazlo de nuevo desde el formulario.', 403);

$folio = $_SESSION['pedido_actual']['folio'];

$stmt = $conexion->prepare("SELECT total, estado FROM pedidos WHERE folio = ?");
$stmt->bind_param('s', $folio);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido || $pedido['estado'] !== 'pendiente') fallo('Este pedido ya no se puede pagar.', 409);

try {
    $token = paypal_token();
    list($codigo, $r) = paypal_api('POST', '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $folio,
            'description'  => 'Pedido ' . $folio . ' - La Gordiflona',
            'amount' => [
                'currency_code' => PAYPAL_MONEDA,
                'value'         => number_format((float) $pedido['total'], 2, '.', ''),
            ],
        ]],
    ], $token);

    if ($codigo < 200 || $codigo >= 300 || empty($r['id'])) fallo('PayPal no pudo crear la orden.', 502);
    echo json_encode(['ok' => true, 'id' => $r['id']]);
} catch (Throwable $e) {
    fallo($e->getMessage(), 502);
}
