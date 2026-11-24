<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['id_usuario'])) {
    header('Location: inicio.php');
    exit;
}

$error = '';
$correo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $correo = trim((string)($_POST['correo'] ?? ''));
    $clave  = (string)($_POST['clave'] ?? '');

    if ($correo === '' || $clave === '') {
        $error = 'Ingresa tu correo y tu clave.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo válido.';
    } else {
        $st = db()->prepare('SELECT id_usuario, nombre, clave FROM usuarios WHERE correo = :correo AND estado = 1');
        $st->execute([':correo' => $correo]);
        $u = $st->fetch();

        if ($u && password_verify($clave, $u['clave'])) {
            session_regenerate_id(true);
            $_SESSION['id_usuario'] = (int)$u['id_usuario'];
            $_SESSION['nombre'] = $u['nombre'];
            header('Location: inicio.php');
            exit;
        }
        $error = 'Correo o clave incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesión · TecnoStock</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
  <div class="card shadow-sm login-card">
    <div class="card-body p-4">
      <h1 class="h3 fw-bold mb-1">TecnoStock</h1>
      <p class="text-muted mb-4">Sistema de inventario</p>

      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

      <form method="post" class="needs-validation" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="correo">Correo</label>
          <input type="email" class="form-control" id="correo" name="correo" value="<?= e($correo) ?>"
                 required autofocus data-msg="Ingresa un correo válido.">
          <div class="invalid-feedback"></div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="clave">Clave</label>
          <input type="password" class="form-control" id="clave" name="clave" required
                 data-msg="Ingresa tu clave.">
          <div class="invalid-feedback"></div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Ingresar</button>
      </form>
    </div>
  </div>
  <script src="js/validaciones.js"></script>
</body>
</html>
