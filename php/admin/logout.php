<?php
/* =========================================================
   CERRAR SESIÓN
   ========================================================= */

session_start();

$_SESSION = [];                                   // borra las variables de sesión

if (ini_get('session.use_cookies')) {             // borra la cookie de sesión
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();                                // destruye la sesión

header('Location: login.php');
exit;
