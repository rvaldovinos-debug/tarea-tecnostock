<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/consultas.php';
require_login();

$db = db();

$k = $db->query(
    'SELECT COUNT(*) AS productos,
            COALESCE(SUM(stock_actual), 0) AS unidades,
            COALESCE(SUM(precio * stock_actual), 0) AS valor,
            COALESCE(SUM(stock_actual < stock_minimo), 0) AS bajos,
            COALESCE(SUM(stock_actual = 0), 0) AS agotados
     FROM productos WHERE estado = 1'
)->fetch();

$m = $db->query(
    "SELECT COALESCE(SUM(DATE(fecha_hora) = CURDATE()), 0) AS hoy,
            COALESCE(SUM(tipo = 'entrada' AND fecha_hora >= NOW() - INTERVAL 7 DAY), 0) AS entradas7,
            COALESCE(SUM(tipo = 'salida'  AND fecha_hora >= NOW() - INTERVAL 7 DAY), 0) AS salidas7
     FROM movimientos"
)->fetch();

$porCategoria = $db->query(
    'SELECT c.nombre, COUNT(p.id_producto) AS productos, COALESCE(SUM(p.stock_actual), 0) AS unidades
     FROM categorias c
     JOIN productos p ON p.id_categoria = c.id_categoria AND p.estado = 1
     GROUP BY c.id_categoria, c.nombre
     ORDER BY unidades DESC'
)->fetchAll();
$maxUnidades = max(1, ...array_map(static fn($c) => (int)$c['unidades'], $porCategoria ?: [['unidades' => 1]]));

$criticos = productos_a_reponer($db, 5);
$ultimos = buscar_movimientos($db, ['producto' => 0, 'tipo' => '', 'desde' => '', 'hasta' => ''], 5);

$titulo = 'Inicio';
$nav = 'inicio';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-1">Hola, <?= e(explode(' ', (string)$_SESSION['nombre'])[0]) ?> 👋</h1>
<p class="text-muted">Resumen del inventario de TecnoStock.</p>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="card card-body h-100">
    <div class="text-muted small">Productos activos</div><div class="fs-3 fw-bold"><?= (int)$k['productos'] ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="card card-body h-100">
    <div class="text-muted small">Unidades en stock</div><div class="fs-3 fw-bold"><?= number_format((int)$k['unidades'], 0, ',', '.') ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="card card-body h-100">
    <div class="text-muted small">Valor del inventario (a precio de venta)</div><div class="fs-3 fw-bold"><?= e(dinero($k['valor'])) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="card card-body h-100 <?= (int)$k['bajos'] > 0 ? 'border-danger' : '' ?>">
    <div class="text-muted small">Bajo el mínimo</div>
    <div class="fs-3 fw-bold <?= (int)$k['bajos'] > 0 ? 'text-danger' : '' ?>"><?= (int)$k['bajos'] ?>
      <span class="fs-6 text-muted fw-normal">(<?= (int)$k['agotados'] ?> agotados)</span></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card card-body"><div class="text-muted small">Movimientos hoy</div><div class="fs-4 fw-bold"><?= (int)$m['hoy'] ?></div></div></div>
  <div class="col-md-4"><div class="card card-body"><div class="text-muted small">Entradas (últimos 7 días)</div><div class="fs-4 fw-bold text-success"><?= (int)$m['entradas7'] ?></div></div></div>
  <div class="col-md-4"><div class="card card-body"><div class="text-muted small">Salidas (últimos 7 días)</div><div class="fs-4 fw-bold text-warning"><?= (int)$m['salidas7'] ?></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-body">
      <h2 class="h6 mb-3">Unidades en stock por categoría</h2>
      <?php foreach ($porCategoria as $c): ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small">
            <span><?= e($c['nombre']) ?> <span class="text-muted">(<?= (int)$c['productos'] ?> prod.)</span></span>
            <strong><?= (int)$c['unidades'] ?></strong>
          </div>
          <div class="progress" style="height:8px">
            <div class="progress-bar" role="progressbar" style="width: <?= round((int)$c['unidades'] / $maxUnidades * 100) ?>%"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div></div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Productos más críticos</h2>
        <a class="small" href="reposicion.php">Ver reposición</a>
      </div>
      <?php if (!$criticos): ?>
        <p class="text-success mb-0">✔ Todo el stock está sobre el mínimo.</p>
      <?php else: ?>
        <ul class="list-group list-group-flush">
        <?php foreach ($criticos as $p): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span><?= e($p['nombre']) ?> <span class="text-muted small">(<?= e($p['codigo']) ?>)</span></span>
            <span><span class="badge <?= (int)$p['stock_actual'] === 0 ? 'text-bg-danger' : 'text-bg-warning' ?>">
              <?= (int)$p['stock_actual'] ?> / <?= (int)$p['stock_minimo'] ?></span></span>
          </li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div></div>
  </div>

  <div class="col-12">
    <div class="card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Últimos movimientos</h2>
        <a class="small" href="movimientos.php">Ver historial</a>
      </div>
      <?php if (!$ultimos): ?><p class="text-muted mb-0">Aún no hay movimientos.</p><?php else: ?>
      <div class="table-responsive border-0"><table class="table table-sm align-middle mb-0">
        <tbody>
        <?php foreach ($ultimos as $mv): ?>
          <tr>
            <td class="text-nowrap text-muted small"><?= e($mv['fecha_hora']) ?></td>
            <td><?= e($mv['codigo']) ?> — <?= e($mv['producto']) ?></td>
            <td><span class="badge <?= $mv['tipo'] === 'entrada' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e(ucfirst($mv['tipo'])) ?></span></td>
            <td class="text-end"><?= (int)$mv['cantidad'] ?></td>
            <td class="text-muted small"><?= e($mv['usuario']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
