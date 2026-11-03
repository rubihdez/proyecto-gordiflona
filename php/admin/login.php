<?php
/* =========================================================
   LOGIN DEL ADMINISTRADOR
   ========================================================= */

session_start();
require_once '../conexion.php';

// Si ya iniciaste sesión, no hace falta volver a entrar
if (isset($_SESSION['admin_id'])) {
    header('Location: panel.php');
    exit;
}

$error  = '';
$correo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo   = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($correo === '' || $password === '') {
        $error = 'Escribe tu correo y tu contraseña.';
    } else {
        // Consulta preparada: protege contra inyección SQL
        $stmt = $conexion->prepare(
            "SELECT id, nombre, password FROM usuarios WHERE correo = ? AND rol = 'admin' LIMIT 1"
        );
        $stmt->bind_param('s', $correo);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // password_verify compara lo escrito contra la contraseña encriptada
        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);                      // nuevo ID de sesión por seguridad
            $_SESSION['admin_id']     = $admin['id'];         // ← variables de sesión
            $_SESSION['admin_nombre'] = $admin['nombre'];
            $_SESSION['admin_inicio'] = date('d/m/Y H:i');
            header('Location: panel.php');
            exit;
        }

        // Mismo mensaje si falla el correo o la contraseña (no damos pistas)
        $error = 'Correo o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso administrador | La Gordiflona</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400..900&family=Cormorant+Garamond:ital,wght@0,300..700;1,300..700&family=Truculenta:opsz,wght@12..72,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="pagina-login">

    <main class="login-caja">
        <a href="../../proyecto.html" class="login-logo">
            <img src="../../imagenes/Gordilonalogo.png" alt="La Gordiflona">
        </a>

        <span class="login-etiqueta">ÁREA PRIVADA</span>
        <h1>Acceso administrador</h1>

        <?php if ($error !== ''): ?>
            <p class="alerta-error" role="alert"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form action="login.php" method="post" class="login-form">
            <label for="correo">Correo</label>
            <input type="email" id="correo" name="correo" placeholder="admin@gordiflona.com"
                   value="<?php echo htmlspecialchars($correo); ?>" required autofocus>

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="Tu contraseña" required>

            <button type="submit" class="btn-login">Entrar</button>
        </form>

        <a href="../../proyecto.html" class="login-volver">← Volver al sitio</a>
    </main>

</body>
</html>
