<?php
/* Entrega solo el Client ID (público) a pago.html. El Secret nunca sale del servidor. */
require_once 'config_paypal.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['client_id' => PAYPAL_CLIENT_ID, 'moneda' => PAYPAL_MONEDA]);
