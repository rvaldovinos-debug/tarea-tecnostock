<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/consultas.php';
require_login();

$db = db();
$tipo = (string)($_GET['tipo'] ?? '');

/** Escribe una fila CSV (separador ";" para que Excel en español lo abra en columnas). */
function fila($out, array $campos): void
{
    // Evita que Excel interprete texto como fórmula (=, +, -, @)
    $campos = array_map(static function ($v) {
        $v = (string)$v;
        return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false && !is_numeric($v)) ? "'" . $v : $v;
    }, $campos);
    fputcsv($out, $campos, ';', '"', '');
}

switch ($tipo) {
    case 'productos':
        $filas = buscar_productos($db, trim((string)($_GET['q'] ?? '')), isset($_GET['inactivos']), isset($_GET['bajo']));
        $cabecera = ['Código', 'Nombre', 'Categoría', 'Descripción', 'Precio', 'Stock actual', 'Stock mínimo', 'Estado'];
        $datos = array_map(static fn($p) => [
            $p['codigo'], $p['nombre'], $p['categoria'], $p['descripcion'] ?? '', $p['precio'],
            $p['stock_actual'], $p['stock_minimo'], $p['estado'] ? 'Activo' : 'Inactivo',
        ], $filas);
        break;

    case 'movimientos':
        $filas = buscar_movimientos($db, filtros_movimientos($_GET), 0);
        $cabecera = ['Fecha y hora', 'Código', 'Producto', 'Tipo', 'Cantidad', 'Observación', 'Usuario'];
        $datos = array_map(static fn($m) => [
            $m['fecha_hora'], $m['codigo'], $m['producto'], ucfirst($m['tipo']), $m['cantidad'],
            $m['observacion'] ?? '', $m['usuario'],
        ], $filas);
        break;

    case 'reposicion':
        $filas = productos_a_reponer($db);
        $cabecera = ['Código', 'Producto', 'Categoría', 'Stock actual', 'Stock mínimo', 'Cantidad sugerida'];
        $datos = array_map(static fn($p) => [
            $p['codigo'], $p['nombre'], $p['categoria'], $p['stock_actual'], $p['stock_minimo'], $p['sugerido'],
        ], $filas);
        break;

    default:
        http_response_code(400);
        exit('Tipo de exportación inválido.');
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="tecnostock_' . $tipo . '_' . date('Ymd_His') . '.csv"');
echo "\xEF\xBB\xBF"; // BOM para que Excel respete las tildes
$out = fopen('php://output', 'w');
fila($out, $cabecera);
foreach ($datos as $d) { fila($out, $d); }
fclose($out);
