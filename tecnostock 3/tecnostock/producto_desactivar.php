<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: productos.php');
    exit;
}
csrf_check();

// Regla de negocio: los productos no se eliminan físicamente, solo se desactivan.
$id = filter_var($_POST['id_producto'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    flash('danger', 'Producto inválido.');
} else {
    $st = db()->prepare('UPDATE productos SET estado = 0 WHERE id_producto = :id AND estado = 1');
    $st->execute([':id' => $id]);
    if ($st->rowCount() > 0) {
        flash('success', 'Producto desactivado correctamente.');
    } else {
        flash('danger', 'No se encontró el producto o ya estaba inactivo.');
    }
}
header('Location: productos.php');
exit;
