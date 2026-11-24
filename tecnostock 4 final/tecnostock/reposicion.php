<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/consultas.php';
require_login();

$lista = productos_a_reponer(db());
$unidades = array_sum(array_map(static fn($p) => (int)$p['sugerido'], $lista));
$agotados = count(array_filter($lista, static fn($p) => (int)$p['stock_actual'] === 0));

$titulo = 'Reposición sugerida';
$nav = 'reposicion';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Reposición sugerida</h1>
  <?php if ($lista): ?><a class="btn btn-outline-success" href="exportar.php?tipo=reposicion">Exportar lista de compra (CSV)</a><?php endif; ?>
</div>
<p class="text-muted">Productos activos con stock bajo el mínimo. La cantidad sugerida repone hasta el <strong>doble del stock mínimo</strong>.</p>

<?php if (!$lista): ?>
  <div class="alert alert-success">✔ Todos los productos están sobre su stock mínimo. No hay nada que reponer.</div>
<?php else: ?>
  <div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="card card-body text-center"><div class="fs-3 fw-bold"><?= count($lista) ?></div><div class="text-muted small">Productos a reponer</div></div></div>
    <div class="col-sm-4"><div class="card card-body text-center"><div class="fs-3 fw-bold text-danger"><?= $agotados ?></div><div class="text-muted small">Agotados (stock 0)</div></div></div>
    <div class="col-sm-4"><div class="card card-body text-center"><div class="fs-3 fw-bold"><?= $unidades ?></div><div class="text-muted small">Unidades sugeridas en total</div></div></div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>Código</th><th>Producto</th><th>Categoría</th><th class="text-end">Stock</th><th class="text-end">Mínimo</th>
            <th>Situación</th><th class="text-end">Sugerido</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($lista as $p): ?>
        <tr>
          <td><?= e($p['codigo']) ?></td>
          <td><?= e($p['nombre']) ?></td>
          <td><?= e($p['categoria']) ?></td>
          <td class="text-end fw-bold"><?= (int)$p['stock_actual'] ?></td>
          <td class="text-end"><?= (int)$p['stock_minimo'] ?></td>
          <td>
            <?php if ((int)$p['stock_actual'] === 0): ?><span class="badge text-bg-danger">Agotado</span>
            <?php else: ?><span class="badge text-bg-warning">Stock bajo</span><?php endif; ?>
          </td>
          <td class="text-end">+<?= (int)$p['sugerido'] ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-primary"
               href="movimientos.php?<?= e(http_build_query([
                   'producto' => (int)$p['id_producto'], 'tipo' => 'entrada',
                   'cantidad' => (int)$p['sugerido'], 'observacion' => 'Reposición sugerida'])) ?>">
              Registrar entrada
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
