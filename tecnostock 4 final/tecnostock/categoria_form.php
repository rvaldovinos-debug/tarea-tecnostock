<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_login();

$db = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editando = $id !== false && $id !== null;
$datos = ['nombre' => '', 'descripcion' => ''];

if ($editando) {
    $st = $db->prepare('SELECT nombre, descripcion FROM categorias WHERE id_categoria = :id');
    $st->execute([':id' => $id]);
    $datos = $st->fetch();
    if (!$datos) {
        flash('danger', 'La categoría no existe.');
        header('Location: categorias.php');
        exit;
    }
    $datos['descripcion'] = $datos['descripcion'] ?? '';
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $datos['nombre'] = trim((string)($_POST['nombre'] ?? ''));
    $datos['descripcion'] = trim((string)($_POST['descripcion'] ?? ''));

    $len = largo($datos['nombre']);
    if ($len < 2 || $len > 80) { $errores[] = 'El nombre debe tener entre 2 y 80 caracteres.'; }
    if (largo($datos['descripcion']) > 255) { $errores[] = 'La descripción no puede superar los 255 caracteres.'; }

    if (!$errores) {
        $st = $db->prepare('SELECT id_categoria FROM categorias WHERE nombre = :n');
        $st->execute([':n' => $datos['nombre']]);
        $otro = $st->fetchColumn();
        if ($otro !== false && (!$editando || (int)$otro !== $id)) {
            $errores[] = 'Ya existe una categoría con ese nombre.';
        }
    }

    if (!$errores) {
        $desc = $datos['descripcion'] === '' ? null : $datos['descripcion'];
        try {
            if ($editando) {
                $db->prepare('UPDATE categorias SET nombre = :n, descripcion = :d WHERE id_categoria = :id')
                   ->execute([':n' => $datos['nombre'], ':d' => $desc, ':id' => $id]);
                flash('success', 'Categoría actualizada.');
            } else {
                $db->prepare('INSERT INTO categorias (nombre, descripcion) VALUES (:n, :d)')
                   ->execute([':n' => $datos['nombre'], ':d' => $desc]);
                flash('success', 'Categoría registrada.');
            }
            header('Location: categorias.php');
            exit;
        } catch (PDOException $ex) {
            $errores[] = mysql_errno($ex) === 1062
                ? 'Ya existe una categoría con ese nombre.'
                : 'No se pudo guardar la categoría. Intenta nuevamente.';
        }
    }
}

$titulo = $editando ? 'Editar categoría' : 'Nueva categoría';
$nav = 'categorias';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3"><?= e($titulo) ?></h1>

<?php foreach ($errores as $err): ?>
  <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" class="card card-body needs-validation" style="max-width:640px">
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label" for="nombre">Nombre</label>
    <input type="text" class="form-control" id="nombre" name="nombre" value="<?= e($datos['nombre']) ?>"
           required minlength="2" maxlength="80" data-msg="El nombre debe tener entre 2 y 80 caracteres.">
    <div class="invalid-feedback"></div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="descripcion">Descripción</label>
    <textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="255"
              data-msg="Máximo 255 caracteres."><?= e($datos['descripcion']) ?></textarea>
    <div class="invalid-feedback"></div>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-primary" type="submit">Guardar</button>
    <a class="btn btn-outline-secondary" href="categorias.php">Cancelar</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
