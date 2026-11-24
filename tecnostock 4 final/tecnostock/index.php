<?php
require_once __DIR__ . '/auth.php';
header('Location: ' . (empty($_SESSION['id_usuario']) ? 'login.php' : 'inicio.php'));
exit;
