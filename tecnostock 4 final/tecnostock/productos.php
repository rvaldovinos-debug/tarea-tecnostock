<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/consultas.php';
require_login();

$db = db();
$q = trim((string)($_GET['q'] ?? ''));
$verInactivos = isset($_GET['inactivos']);
$soloBajo = isset($_GET['bajo']);

$productos = buscar_productos($db, $q, $verInactivos, $soloBajo);

$bajos = (int)$db->query('SELECT COUNT(*) FROM productos WHERE estado = 1 AND stock_actual < stock_minimo')->fetchColumn();

$titulo = 'Productos';
$nav = 'productos';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Productos</h1>
  <a class="btn btn-primary" href="producto_form.php">+ Nuevo producto</a>
</div>

<?php if ($bajos > 0): ?>
  <div class="alert alert-warning d-flex justify-content-between align-items-center">
    <span>⚠ Hay <strong><?= $bajos ?></strong> producto(s) con stock por debajo del mínimo.</span>
    <?php if (!$soloBajo): ?><a class="btn btn-sm btn-warning" href="productos.php?bajo=1">Ver solo esos</a><?php endif; ?>
  </div>
<?php endif; ?>

<form method="get" class="row g-2 align-items-center mb-3">
  <div class="col-12 col-md">
    <input type="search" class="form-control" name="q" value="<?= e($q) ?>"
           placeholder="Buscar por nombre, código o categoría">
  </div>
  <div class="col-auto form-check ms-2">
    <input class="form-check-input" type="checkbox" id="inactivos" name="inactivos" <?= $verInactivos ? 'checked' : '' ?>>
    <label class="form-check-label" for="inactivos">Incluir inactivos</label>
  </div>
  <div class="col-auto form-check ms-2">
    <input class="form-check-input" type="checkbox" id="bajo" name="bajo" <?= $soloBajo ? 'checked' : '' ?>>
    <label class="form-check-label" for="bajo">Solo stock bajo</label>
  </div>
  <div class="col-auto">
    <button class="btn btn-outline-primary" type="submit">Buscar</button>
    <a class="btn btn-outline-secondary" href="productos.php">Limpiar</a>
    <a class="btn btn-outline-success" href="exportar.php?<?= e(http_build_query(array_filter([
        'tipo' => 'productos', 'q' => $q, 'inactivos' => $verInactivos ? 1 : null, 'bajo' => $soloBajo ? 1 : null]))) ?>">Exportar CSV</a>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Código</th><th>Nombre</th><th>Categoría</th>
        <th class="text-end">Precio</th><th class="text-end">Stock</th><th class="text-end">Mínimo</th>
        <th>Estado</th><th>Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$productos): ?>
      <tr><td colspan="8" class="text-center text-muted py-4">No se encontraron productos.</td></tr>
    <?php endif; ?>
    <?php foreach ($productos as $p):
        $bajo = $p['estado'] && $p['stock_actual'] < $p['stock_minimo']; ?>
      <tr class="<?= $bajo ? 'table-warning' : '' ?> <?= $p['estado'] ? '' : 'text-muted' ?>">
        <td><?= e($p['codigo']) ?></td>
        <td><?= e($p['nombre']) ?></td>
        <td><?= e($p['categoria']) ?></td>
        <td class="text-end"><?= e(dinero($p['precio'])) ?></td>
        <td class="text-end fw-bold"><?= (int)$p['stock_actual'] ?></td>
        <td class="text-end"><?= (int)$p['stock_minimo'] ?></td>
        <td>
          <?php if (!$p['estado']): ?><span class="badge text-bg-secondary">Inactivo</span>
          <?php elseif ($bajo): ?><span class="badge text-bg-danger">Stock bajo</span>
          <?php else: ?><span class="badge text-bg-success">OK</span><?php endif; ?>
        </td>
        <td class="text-nowrap">
          <a class="btn btn-sm btn-outline-secondary" href="producto_form.php?id=<?= (int)$p['id_producto'] ?>">Editar</a>
          <?php if ($p['estado']): ?>
            <a class="btn btn-sm btn-outline-primary" href="movimientos.php?producto=<?= (int)$p['id_producto'] ?>">Mover stock</a>
            <form method="post" action="producto_desactivar.php" class="d-inline"
                  onsubmit="return confirm('¿Desactivar este producto? Podrás verlo en \'Incluir inactivos\'.');">
              <?= csrf_field() ?>
              <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit">Desactivar</button>
            </form>
          <?php else: ?>
            <form method="post" action="producto_reactivar.php" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
              <button class="btn btn-sm btn-outline-success" type="submit">Reactivar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
