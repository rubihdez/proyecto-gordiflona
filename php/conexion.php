<?php
/* =========================================================
   CONEXIÓN A LA BASE DE DATOS
   Funciona en los dos lugares:
     - En tu computadora (XAMPP / localhost): usa los datos locales
     - En el hosting (InfinityFree): usa los datos de abajo
   ========================================================= */

date_default_timezone_set('America/Monterrey'); // hora

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$es_local = (strpos($host, 'localhost') === 0 || strpos($host, '127.0.0.1') === 0);

if ($es_local) {
    // XAMPP
    $servidor = 'localhost';
    $usuario  = 'root';
    $clave    = '';
    $base     = 'gordiflona';
} else {
    $servidor = 'ssql308.infinityfree.com';
$usuario  = 'if0_43341638';
$clave    = 'NQgIOqP2Iwn';
$base     = 'if0_43341638_gordiflona';
}

// Que MySQL avise de errores para poder atraparlos
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexion = new mysqli($servidor, $usuario, $clave, $base);
    $conexion->set_charset('utf8mb4'); // acentos y ñ
} catch (mysqli_sql_exception $e) {
    // No mostramos el error real para no enseñar datos privados
    die('No se pudo conectar a la base de datos.');
}