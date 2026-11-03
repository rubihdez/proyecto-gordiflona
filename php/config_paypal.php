<?php
/* 
   CONFIGURACIÓN DE PAYPAL
    */

define('PAYPAL_MODO',      'sandbox');                 // 'sandbox' (pruebas) o 'live' (real)
define('PAYPAL_CLIENT_ID', 'AVrbu9kpChBZFrWT3M6Jy9gysa53BmUPISU6M7AjjQQHbSH0sMwzLuyZW6Wv9xvVN1SIY8NcLjsOsuZd');
define('PAYPAL_SECRET',    'EF9HD9_lgz_kAOG1fX9TWbVSzP_Ct-3CSG3xFX_yWmDcqD6-uupXq63PpFmoht5mKuri5zlQKws2v1hT');
define('PAYPAL_MONEDA',    'MXN');

define('PAYPAL_API', PAYPAL_MODO === 'live'
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com');

/* Llama a la API de PayPal. Devuelve [codigoHTTP, arrayRespuesta] */
function paypal_api($metodo, $ruta, $cuerpo = null, $token = null) {
    $ch = curl_init(PAYPAL_API . $ruta);
    $cabeceras = ['Accept: application/json'];
    if ($token === null) {                                   // pedir el token de acceso
        curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ':' . PAYPAL_SECRET);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    } else {
        $cabeceras[] = 'Authorization: Bearer ' . $token;
        $cabeceras[] = 'Content-Type: application/json';
        if ($cuerpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo));
        } elseif ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        }
    }
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $cabeceras);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $respuesta = curl_exec($ch);
    $codigo    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$codigo, json_decode((string) $respuesta, true) ?: []];
}

function paypal_token() {
    list($codigo, $r) = paypal_api('POST', '/v1/oauth2/token');
    if ($codigo !== 200 || empty($r['access_token'])) {
        throw new Exception('No se pudo conectar con PayPal (revisa tus credenciales).');
    }
    return $r['access_token'];
}
