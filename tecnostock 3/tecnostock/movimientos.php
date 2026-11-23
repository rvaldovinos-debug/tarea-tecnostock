<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/consultas.php';
require_login();

$db = db();
$errores = [];
$sel = filter_var($_GET['producto'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$tipoUrl = (string)($_GET['tipo'] ?? '');
$cantUrl = filter_var($_GET['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
$obsUrl  = trim((string)($_GET['observacion'] ?? ''));
$form = [
    'id_producto' => $sel ?: '',
    'tipo'        => in_array($tipoUrl, ['entrada', 'salida'], true) ? $tipoUrl : 'entrada',
    'cantidad'    => $cantUrl === false || $cantUrl === null ? '' : (string)$cantUrl,
    'observacion' => largo($obsUrl) <= 255 ? $obsUrl : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form['id_producto'] = (string)($_POST['id_producto'] ?? '');
    $form['tipo']        = (string)($_POST['tipo'] ?? '');
    $form['cantidad']    = trim((string)($_POST['cantidad'] ?? ''));
    $form['observacion'] = trim((string)($_POST['observacion'] ?? ''));

    $idp  = filter_var($form['id_producto'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $cant = filter_var($form['cantidad'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);

    if ($idp === false)                                        { $errores[] = 'Selecciona un producto.'; }
    if (!in_array($form['tipo'], ['entrada', 'salida'], true)) { $errores[] = 'Tipo de movimiento inválido.'; }
    if ($cant === false)                                       { $errores[] = 'La cantidad debe ser un número entero mayor a 0.'; }
    if (largo($form['observacion']) > 255)                     { $errores[] = 'La observación no puede superar los 255 caracteres.'; }

    if (!$errores) {
        $uid = (int)$_SESSION['id_usuario'];
        $obs = $form['observacion'] === '' ? null : $form['observacion'];
        try {
            $db->beginTransaction();

            // Se bloquea la fila para que dos movimientos simultáneos no corrompan el stock
            $st = $db->prepare('SELECT nombre, stock_actual, stock_minimo FROM productos
                                WHERE id_producto = :id AND estado = 1 FOR UPDATE');
            $st->execute([':id' => $idp]);
            $p = $st->fetch();
            if (!$p) { throw new ErrorNegocio('El producto no existe o está inactivo.'); }

            $nuevoStock = $form['tipo'] === 'entrada' ? $p['stock_actual'] + $cant : $p['stock_actual'] - $cant;
            if ($nuevoStock < 0) {
                throw new ErrorNegocio('Stock insuficiente: hay ' . (int)$p['stock_actual'] . ' unidad(es) disponibles.');
            }

            // Regla de negocio: el stock se actualiza automáticamente con cada movimiento válido
            $db->prepare('UPDATE productos SET stock_actual = :s WHERE id_producto = :id')
               ->execute([':s' => $nuevoStock, ':id' => $idp]);

            $db->prepare('INSERT INTO movimientos (tipo, cantidad, observacion, id_producto, id_usuario)
                          VALUES (:t, :c, :o, :p, :u)')
               ->execute([':t' => $form['tipo'], ':c' => $cant, ':o' => $obs, ':p' => $idp, ':u' => $uid]);

            $db->commit();
            flash('success', 'Movimiento registrado. Stock de "' . $p['nombre'] . '": ' . $nuevoStock . '.');
            if ($nuevoStock < $p['stock_minimo']) {
                flash('warning', '⚠ "' . $p['nombre'] . '" quedó bajo el stock mínimo (' . (int)$p['stock_minimo'] . ').');
            }
            header('Location: movimientos.php');
            exit;
        } catch (ErrorNegocio $ex) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $errores[] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $errores[] = 'No se pudo registrar el movimiento. Intenta nuevamente.';
        }
    }
}

$productos = $db->query('SELECT id_producto, codigo, nombre, stock_actual FROM productos WHERE estado = 1 ORDER BY nombre')->fetchAll();

$filtros = filtros_movimientos($_GET);
$historial = buscar_movimientos($db, $filtros, 100);
$todosProductos = $db->query('SELECT id_producto, codigo, nombre FROM productos ORDER BY nombre')->fetchAll();

$titulo = 'Movimientos';
$nav = 'movimientos';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3">Movimientos de inventario</h1>

<?php foreach ($errores as $err): ?>
  <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" class="card card-body needs-validation mb-4" data-movimiento>
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-12">
      <label class="form-label" for="id_producto">Producto</label>
      <select class="form-select" id="id_producto" name="id_producto" required data-msg="Selecciona un producto.">
        <option value="">Selecciona…</option>
        <?php foreach ($productos as $p): ?>
          <option value="<?= (int)$p['id_producto'] ?>" data-stock="<?= (int)$p['stock_actual'] ?>"
                  <?= (string)$p['id_producto'] === (string)$form['id_producto'] ? 'selected' : '' ?>>
            <?= e($p['codigo']) ?> — <?= e($p['nombre']) ?> (stock: <?= (int)$p['stock_actual'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-6">
      <label class="form-label" for="tipo">Tipo</label>
      <select class="form-select" id="tipo" name="tipo" required>
        <option value="entrada" <?= $form['tipo'] === 'entrada' ? 'selected' : '' ?>>Entrada</option>
        <option value="salida"  <?= $form['tipo'] === 'salida'  ? 'selected' : '' ?>>Salida</option>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label" for="cantidad">Cantidad</label>
      <input type="number" class="form-control" id="cantidad" name="cantidad" value="<?= e($form['cantidad']) ?>"
             min="1" step="1" required data-msg="Ingresa un entero mayor a 0.">
      <div class="invalid-feedback"></div>
    </div>

    <div class="col-12">
      <label class="form-label" for="observacion">Observación (opcional)</label>
      <input type="text" class="form-control" id="observacion" name="observacion" value="<?= e($form['observacion']) ?>"
             maxlength="255" placeholder="Ej: compra a proveedor, venta, merma"
             data-msg="Máximo 255 caracteres.">
      <div class="invalid-feedback"></div>
    </div>
  </div>
  <div class="mt-4">
    <button class="btn btn-primary" type="submit">Registrar movimiento</button>
  </div>
</form>

<h2 class="h5">Historial de movimientos</h2>
<form method="get" class="row g-2 align-items-end mb-3">
  <div class="col-md-4">
    <label class="form-label small" for="f_producto">Producto</label>
    <select class="form-select" id="f_producto" name="f_producto">
      <option value="">Todos</option>
      <?php foreach ($todosProductos as $tp): ?>
        <option value="<?= (int)$tp['id_producto'] ?>" <?= $filtros['producto'] === (int)$tp['id_producto'] ? 'selected' : '' ?>>
          <?= e($tp['codigo']) ?> — <?= e($tp['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label small" for="f_tipo">Tipo</label>
    <select class="form-select" id="f_tipo" name="f_tipo">
      <option value="">Todos</option>
      <option value="entrada" <?= $filtros['tipo'] === 'entrada' ? 'selected' : '' ?>>Entrada</option>
      <option value="salida"  <?= $filtros['tipo'] === 'salida'  ? 'selected' : '' ?>>Salida</option>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label small" for="f_desde">Desde</label>
    <input type="date" class="form-control" id="f_desde" name="f_desde" value="<?= e($filtros['desde']) ?>">
  </div>
  <div class="col-md-2">
    <label class="form-label small" for="f_hasta">Hasta</label>
    <input type="date" class="form-control" id="f_hasta" name="f_hasta" value="<?= e($filtros['hasta']) ?>">
  </div>
  <div class="col-md-2 d-flex gap-2">
    <button class="btn btn-outline-primary" type="submit">Filtrar</button>
    <a class="btn btn-outline-secondary" href="movimientos.php">Limpiar</a>
    <a class="btn btn-outline-success" href="exportar.php?<?= e(http_build_query(array_filter([
        'tipo' => 'movimientos', 'f_producto' => $filtros['producto'], 'f_tipo' => $filtros['tipo'],
        'f_desde' => $filtros['desde'], 'f_hasta' => $filtros['hasta']]))) ?>">CSV</a>
  </div>
</form>
<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr><th>Fecha y hora</th><th>Producto</th><th>Tipo</th><th class="text-end">Cantidad</th><th>Observación</th><th>Usuario</th></tr>
    </thead>
    <tbody>
    <?php if (!$historial): ?>
      <tr><td colspan="6" class="text-center text-muted py-4">Aún no hay movimientos.</td></tr>
    <?php endif; ?>
    <?php foreach ($historial as $m): ?>
      <tr>
        <td class="text-nowrap"><?= e($m['fecha_hora']) ?></td>
        <td><?= e($m['codigo']) ?> — <?= e($m['producto']) ?></td>
        <td><span class="badge <?= $m['tipo'] === 'entrada' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e(ucfirst($m['tipo'])) ?></span></td>
        <td class="text-end"><?= (int)$m['cantidad'] ?></td>
        <td><?= e($m['observacion'] ?? '—') ?></td>
        <td><?= e($m['usuario']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
