<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_login();

$db = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editando = $id !== false && $id !== null;

$datos = ['codigo' => '', 'nombre' => '', 'id_categoria' => '', 'descripcion' => '',
          'precio' => '', 'stock_actual' => '0', 'stock_minimo' => '0', 'estado' => 1];

if ($editando) {
    $st = $db->prepare('SELECT * FROM productos WHERE id_producto = :id');
    $st->execute([':id' => $id]);
    $existente = $st->fetch();
    if (!$existente) {
        flash('danger', 'El producto no existe.');
        header('Location: productos.php');
        exit;
    }
    $datos = $existente;
    $datos['descripcion'] = $datos['descripcion'] ?? '';
}
$categoriaActual = $editando ? (int)$datos['id_categoria'] : 0;

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $datos['nombre']       = trim((string)($_POST['nombre'] ?? ''));
    $datos['id_categoria'] = (string)($_POST['id_categoria'] ?? '');
    $datos['descripcion']  = trim((string)($_POST['descripcion'] ?? ''));
    $datos['precio']       = trim((string)($_POST['precio'] ?? ''));
    $datos['stock_actual'] = trim((string)($_POST['stock_actual'] ?? ''));
    $datos['stock_minimo'] = trim((string)($_POST['stock_minimo'] ?? ''));

    if (!$editando) {
        $datos['codigo'] = trim((string)($_POST['codigo'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{3,30}$/', $datos['codigo'])) {
            $errores[] = 'El código debe tener entre 3 y 30 caracteres (letras, números, guion o guion bajo).';
        }
    }

    $len = largo($datos['nombre']);
    if ($len < 2 || $len > 120) { $errores[] = 'El nombre debe tener entre 2 y 120 caracteres.'; }
    if (largo($datos['descripcion']) > 1000) { $errores[] = 'La descripción no puede superar los 1000 caracteres.'; }

    $idCat = filter_var($datos['id_categoria'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idCat === false) {
        $errores[] = 'Selecciona una categoría.';
    } else {
        $st = $db->prepare('SELECT 1 FROM categorias WHERE id_categoria = :id AND (estado = 1 OR id_categoria = :actual)');
        $st->execute([':id' => $idCat, ':actual' => $categoriaActual]);
        if (!$st->fetchColumn()) { $errores[] = 'La categoría seleccionada no existe o está inactiva.'; }
    }

    $precioTxt = str_replace(',', '.', $datos['precio']);
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $precioTxt)) {
        $errores[] = 'El precio debe ser un número no negativo, sin separador de miles (ej: 12990 o 12990.50).';
    }
    $stockActual = filter_var($datos['stock_actual'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
    $stockMinimo = filter_var($datos['stock_minimo'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
    if ($stockActual === false) { $errores[] = 'El stock actual debe ser un número entero mayor o igual a 0.'; }
    if ($stockMinimo === false) { $errores[] = 'El stock mínimo debe ser un número entero mayor o igual a 0.'; }

    if (!$editando && !$errores) {
        $st = $db->prepare('SELECT 1 FROM productos WHERE codigo = :c');
        $st->execute([':c' => $datos['codigo']]);
        if ($st->fetchColumn()) { $errores[] = 'Ya existe un producto con ese código.'; }
    }

    if (!$errores) {
        $precio = (float)$precioTxt;
        $uid = (int)$_SESSION['id_usuario'];
        $desc = $datos['descripcion'] === '' ? null : $datos['descripcion'];
        try {
            $db->beginTransaction();
            if ($editando) {
                $st = $db->prepare('SELECT stock_actual FROM productos WHERE id_producto = :id FOR UPDATE');
                $st->execute([':id' => $id]);
                $anterior = (int)$st->fetchColumn();

                $db->prepare('UPDATE productos
                              SET nombre = :n, id_categoria = :c, descripcion = :d, precio = :p,
                                  stock_actual = :sa, stock_minimo = :sm
                              WHERE id_producto = :id')
                   ->execute([':n' => $datos['nombre'], ':c' => $idCat, ':d' => $desc, ':p' => $precio,
                              ':sa' => $stockActual, ':sm' => $stockMinimo, ':id' => $id]);

                // Si cambió el stock manualmente, queda registrado como movimiento de ajuste
                if ($stockActual !== $anterior) {
                    $db->prepare('INSERT INTO movimientos (tipo, cantidad, observacion, id_producto, id_usuario)
                                  VALUES (:t, :c, :o, :p, :u)')
                       ->execute([':t' => $stockActual > $anterior ? 'entrada' : 'salida',
                                  ':c' => abs($stockActual - $anterior),
                                  ':o' => 'Ajuste manual desde edición de producto',
                                  ':p' => $id, ':u' => $uid]);
                }
                $db->commit();
                flash('success', 'Producto actualizado correctamente.');
            } else {
                $db->prepare('INSERT INTO productos (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, id_categoria)
                              VALUES (:cod, :n, :d, :p, :sa, :sm, :c)')
                   ->execute([':cod' => $datos['codigo'], ':n' => $datos['nombre'], ':d' => $desc, ':p' => $precio,
                              ':sa' => $stockActual, ':sm' => $stockMinimo, ':c' => $idCat]);
                $nuevoId = (int)$db->lastInsertId();

                if ($stockActual > 0) {
                    $db->prepare("INSERT INTO movimientos (tipo, cantidad, observacion, id_producto, id_usuario)
                                  VALUES ('entrada', :c, 'Stock inicial', :p, :u)")
                       ->execute([':c' => $stockActual, ':p' => $nuevoId, ':u' => $uid]);
                }
                $db->commit();
                flash('success', 'Producto registrado correctamente.');
            }
            header('Location: productos.php');
            exit;
        } catch (PDOException $ex) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $errores[] = mysql_errno($ex) === 1062
                ? 'Ya existe un producto con ese código.'
                : 'No se pudo guardar el producto. Intenta nuevamente.';
        }
    }
}

$categorias = $db->prepare('SELECT id_categoria, nombre FROM categorias WHERE estado = 1 OR id_categoria = :actual ORDER BY nombre');
$categorias->execute([':actual' => $categoriaActual]);
$categorias = $categorias->fetchAll();

$titulo = $editando ? 'Editar producto' : 'Nuevo producto';
$nav = 'productos';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3"><?= e($titulo) ?></h1>

<?php if ($editando && !$datos['estado']): ?>
  <div class="alert alert-secondary">Este producto está inactivo.</div>
<?php endif; ?>
<?php foreach ($errores as $err): ?>
  <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" class="card card-body needs-validation">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label" for="codigo">Código</label>
      <input type="text" class="form-control" id="codigo" name="codigo" value="<?= e($datos['codigo']) ?>"
             maxlength="30" placeholder="Ej: AUD-002"
             <?= $editando ? 'readonly' : 'required pattern="[A-Za-z0-9_\-]{3,30}"' ?>
             data-msg="3 a 30 caracteres: letras, números, guion o guion bajo.">
      <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-8">
      <label class="form-label" for="nombre">Nombre</label>
      <input type="text" class="form-control" id="nombre" name="nombre" value="<?= e($datos['nombre']) ?>"
             required minlength="2" maxlength="120" data-msg="El nombre debe tener entre 2 y 120 caracteres.">
      <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-6">
      <label class="form-label" for="id_categoria">Categoría</label>
      <select class="form-select" id="id_categoria" name="id_categoria" required data-msg="Selecciona una categoría.">
        <option value="">Selecciona…</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= (int)$c['id_categoria'] ?>" <?= (string)$c['id_categoria'] === (string)$datos['id_categoria'] ? 'selected' : '' ?>>
            <?= e($c['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-6">
      <label class="form-label" for="precio">Precio</label>
      <input type="text" class="form-control" id="precio" name="precio" value="<?= e($datos['precio']) ?>"
             inputmode="decimal" required pattern="\d{1,8}([.,]\d{1,2})?" placeholder="Ej: 12990"
             data-msg="Ingresa un precio válido, sin separador de miles (ej: 12990 o 12990.50).">
      <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-6">
      <label class="form-label" for="stock_actual">Stock actual</label>
      <input type="number" class="form-control" id="stock_actual" name="stock_actual" value="<?= e($datos['stock_actual']) ?>"
             min="0" step="1" required data-msg="Ingresa un entero mayor o igual a 0.">
      <div class="invalid-feedback"></div>
    </div>

    <div class="col-md-6">
      <label class="form-label" for="stock_minimo">Stock mínimo</label>
      <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" value="<?= e($datos['stock_minimo']) ?>"
             min="0" step="1" required data-msg="Ingresa un entero mayor o igual a 0.">
      <div class="invalid-feedback"></div>
    </div>

    <div class="col-12">
      <label class="form-label" for="descripcion">Descripción</label>
      <textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="1000"
                data-msg="Máximo 1000 caracteres."><?= e($datos['descripcion']) ?></textarea>
      <div class="invalid-feedback"></div>
    </div>
  </div>

  <div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">Guardar</button>
    <a class="btn btn-outline-secondary" href="productos.php">Cancelar</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
