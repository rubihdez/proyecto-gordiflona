<?php
/* =========================================================
   GUARDIÁN DE SESIÓN
   Se pone al inicio de cada página del panel:
       require 'proteger.php';
   Si no hay sesión de administrador, te manda al login.
   ========================================================= */

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Evita que el botón "atrás" muestre el panel después de cerrar sesión
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
