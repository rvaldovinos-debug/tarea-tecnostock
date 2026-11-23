<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_login();

$db = db();

// Activar / desactivar categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = filter_var($_POST['id_categoria'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $accion = (string)($_POST['accion'] ?? '');

    if ($id === false || !in_array($accion, ['activar', 'desactivar'], true)) {
        flash('danger', 'Solicitud inválida.');
    } elseif ($accion === 'desactivar') {
        $st = $db->prepare('SELECT COUNT(*) FROM productos WHERE id_categoria = :id AND estado = 1');
        $st->execute([':id' => $id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('danger', 'No puedes desactivar una categoría con productos activos. Cambia o desactiva esos productos primero.');
        } else {
            $db->prepare('UPDATE categorias SET estado = 0 WHERE id_categoria = :id')->execute([':id' => $id]);
            flash('success', 'Categoría desactivada.');
        }
    } else {
        $db->prepare('UPDATE categorias SET estado = 1 WHERE id_categoria = :id')->execute([':id' => $id]);
        flash('success', 'Categoría activada.');
    }
    header('Location: categorias.php');
    exit;
}

$categorias = $db->query(
    'SELECT c.id_categoria, c.nombre, c.descripcion, c.estado,
            (SELECT COUNT(*) FROM productos p WHERE p.id_categoria = c.id_categoria AND p.estado = 1) AS productos_activos
     FROM categorias c
     ORDER BY c.estado DESC, c.nombre'
)->fetchAll();

$titulo = 'Categorías';
$nav = 'categorias';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Categorías</h1>
  <a class="btn btn-primary" href="categoria_form.php">+ Nueva categoría</a>
</div>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr><th>Nombre</th><th>Descripción</th><th class="text-end">Productos activos</th><th>Estado</th><th>Acciones</th></tr>
    </thead>
    <tbody>
    <?php if (!$categorias): ?>
      <tr><td colspan="5" class="text-center text-muted py-4">No hay categorías registradas.</td></tr>
    <?php endif; ?>
    <?php foreach ($categorias as $c): ?>
      <tr class="<?= $c['estado'] ? '' : 'text-muted' ?>">
        <td><?= e($c['nombre']) ?></td>
        <td><?= e($c['descripcion'] ?? '—') ?></td>
        <td class="text-end"><?= (int)$c['productos_activos'] ?></td>
        <td><span class="badge <?= $c['estado'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $c['estado'] ? 'Activa' : 'Inactiva' ?></span></td>
        <td class="text-nowrap">
          <a class="btn btn-sm btn-outline-secondary" href="categoria_form.php?id=<?= (int)$c['id_categoria'] ?>">Editar</a>
          <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id_categoria" value="<?= (int)$c['id_categoria'] ?>">
            <?php if ($c['estado']): ?>
              <input type="hidden" name="accion" value="desactivar">
              <button class="btn btn-sm btn-outline-danger" type="submit">Desactivar</button>
            <?php else: ?>
              <input type="hidden" name="accion" value="activar">
              <button class="btn btn-sm btn-outline-success" type="submit">Activar</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
