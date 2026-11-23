<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: productos.php');
    exit;
}
csrf_check();

$id = filter_var($_POST['id_producto'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    flash('danger', 'Producto inválido.');
} else {
    // Solo se reactiva si su categoría está activa (consistencia del catálogo)
    $st = db()->prepare('UPDATE productos p
                         JOIN categorias c ON c.id_categoria = p.id_categoria
                         SET p.estado = 1
                         WHERE p.id_producto = :id AND p.estado = 0 AND c.estado = 1');
    $st->execute([':id' => $id]);
    if ($st->rowCount() > 0) {
        flash('success', 'Producto reactivado correctamente.');
    } else {
        flash('danger', 'No se pudo reactivar: el producto no existe, ya está activo o su categoría está inactiva.');
    }
}
header('Location: productos.php');
exit;
