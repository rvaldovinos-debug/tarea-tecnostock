<?php
declare(strict_types=1);

// Datos de conexión (XAMPP por defecto: root sin clave)
const DB_HOST = 'localhost';
const DB_NAME = 'tecnostock';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('No se pudo conectar a la base de datos. Revisa los datos en config.php.');
        }
    }
    return $pdo;
}
