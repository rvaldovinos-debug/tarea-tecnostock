<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/** Error de regla de negocio: el mensaje se puede mostrar al usuario. */
class ErrorNegocio extends Exception {}

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function require_login(): void
{
    if (empty($_SESSION['id_usuario'])) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve atrás y recarga la página.');
    }
}

/** Mensajes temporales: tipos success | danger | warning | info */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = [$tipo, $mensaje];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function dinero($valor): string
{
    $v = (float)$valor;
    $dec = floor($v) == $v ? 0 : 2;
    return '$' . number_format($v, $dec, ',', '.');
}

/** Largo en caracteres (UTF-8) sin depender de mbstring. */
function largo(string $texto): int
{
    return (int)preg_match_all('/./su', $texto);
}

/** Código de error MySQL de una PDOException (1062 = duplicado). */
function mysql_errno(PDOException $ex): int
{
    return (int)($ex->errorInfo[1] ?? 0);
}
