<?php
// Variables esperadas: $titulo (string), $nav (string: productos|categorias|movimientos)
$nav = $nav ?? '';
$flashes = flashes();
$bajosNav = (int)db()->query('SELECT COUNT(*) FROM productos WHERE estado = 1 AND stock_actual < stock_minimo')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo ?? 'TecnoStock') ?> · TecnoStock</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-md navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand fw-bold" href="inicio.php">TecnoStock</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
            aria-controls="menu" aria-expanded="false" aria-label="Menú">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link <?= $nav === 'inicio' ? 'active' : '' ?>" href="inicio.php">Inicio</a></li>
        <li class="nav-item"><a class="nav-link <?= $nav === 'productos' ? 'active' : '' ?>" href="productos.php">Productos</a></li>
        <li class="nav-item"><a class="nav-link <?= $nav === 'categorias' ? 'active' : '' ?>" href="categorias.php">Categorías</a></li>
        <li class="nav-item"><a class="nav-link <?= $nav === 'movimientos' ? 'active' : '' ?>" href="movimientos.php">Movimientos</a></li>
        <li class="nav-item">
          <a class="nav-link <?= $nav === 'reposicion' ? 'active' : '' ?>" href="reposicion.php">Reposición
            <?php if ($bajosNav > 0): ?><span class="badge text-bg-danger ms-1"><?= $bajosNav ?></span><?php endif; ?>
          </a>
        </li>
      </ul>
      <span class="navbar-text me-3"><?= e($_SESSION['nombre'] ?? '') ?></span>
      <a class="btn btn-outline-light btn-sm" href="logout.php">Salir</a>
    </div>
  </div>
</nav>
<main class="container py-4">
<?php foreach ($flashes as [$tipo, $msg]): ?>
  <div class="alert alert-<?= e($tipo) ?> alert-dismissible fade show" role="alert">
    <?= e($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endforeach; ?>
