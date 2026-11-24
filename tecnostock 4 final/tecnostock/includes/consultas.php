<?php
declare(strict_types=1);

/**
 * Consultas compartidas entre páginas (listados y exportaciones).
 */

/** Busca productos por nombre, código o categoría. */
function buscar_productos(PDO $db, string $q, bool $inactivos, bool $soloBajo): array
{
    $sql = 'SELECT p.id_producto, p.codigo, p.nombre, p.descripcion, p.precio, p.stock_actual, p.stock_minimo,
                   p.estado, c.nombre AS categoria
            FROM productos p
            JOIN categorias c ON c.id_categoria = p.id_categoria
            WHERE (p.nombre LIKE :q1 OR p.codigo LIKE :q2 OR c.nombre LIKE :q3)';
    if (!$inactivos) { $sql .= ' AND p.estado = 1'; }
    if ($soloBajo)   { $sql .= ' AND p.estado = 1 AND p.stock_actual < p.stock_minimo'; }
    $sql .= ' ORDER BY p.estado DESC, p.nombre ASC';

    $like = '%' . addcslashes($q, '%_\\') . '%';
    $st = $db->prepare($sql);
    $st->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
    return $st->fetchAll();
}

/** Lee y valida los filtros del historial desde $_GET (f_producto, f_tipo, f_desde, f_hasta). */
function filtros_movimientos(array $get): array
{
    $producto = filter_var($get['f_producto'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $tipo = (string)($get['f_tipo'] ?? '');
    $fecha = static function ($v): string {
        $v = (string)$v;
        $d = DateTime::createFromFormat('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : '';
    };
    return [
        'producto' => $producto === false ? 0 : $producto,
        'tipo'     => in_array($tipo, ['entrada', 'salida'], true) ? $tipo : '',
        'desde'    => $fecha($get['f_desde'] ?? ''),
        'hasta'    => $fecha($get['f_hasta'] ?? ''),
    ];
}

/** Historial de movimientos con filtros opcionales ($limite = 0 → sin límite). */
function buscar_movimientos(PDO $db, array $f, int $limite = 100): array
{
    $where = [];
    $par = [];
    if ($f['producto'] > 0) { $where[] = 'm.id_producto = :p'; $par[':p'] = $f['producto']; }
    if ($f['tipo'] !== '')  { $where[] = 'm.tipo = :t';        $par[':t'] = $f['tipo']; }
    if ($f['desde'] !== '') { $where[] = 'm.fecha_hora >= :d'; $par[':d'] = $f['desde'] . ' 00:00:00'; }
    if ($f['hasta'] !== '') { $where[] = 'm.fecha_hora <= :h'; $par[':h'] = $f['hasta'] . ' 23:59:59'; }

    $sql = 'SELECT m.fecha_hora, m.tipo, m.cantidad, m.observacion, p.codigo, p.nombre AS producto, u.nombre AS usuario
            FROM movimientos m
            JOIN productos p ON p.id_producto = m.id_producto
            JOIN usuarios u ON u.id_usuario = m.id_usuario'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY m.id_movimiento DESC'
         . ($limite > 0 ? ' LIMIT ' . (int)$limite : '');
    $st = $db->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

/**
 * Productos activos bajo el stock mínimo, del más crítico al menos crítico.
 * La cantidad sugerida repone hasta el doble del stock mínimo.
 */
function productos_a_reponer(PDO $db, int $limite = 0): array
{
    $sql = 'SELECT p.id_producto, p.codigo, p.nombre, p.stock_actual, p.stock_minimo, c.nombre AS categoria,
                   GREATEST(p.stock_minimo * 2 - p.stock_actual, 1) AS sugerido
            FROM productos p
            JOIN categorias c ON c.id_categoria = p.id_categoria
            WHERE p.estado = 1 AND p.stock_actual < p.stock_minimo
            ORDER BY (p.stock_actual - p.stock_minimo) ASC, p.nombre ASC'
         . ($limite > 0 ? ' LIMIT ' . (int)$limite : '');
    return $db->query($sql)->fetchAll();
}
