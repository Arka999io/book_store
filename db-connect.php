<?php
// Параметры подключения
$host = 'sql200.yzz.me';
$dbname = 'dbname';
$username = 'user';
$password = 'pass';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Не удалось подключиться к базе данных: ' . $e->getMessage()]));
}
?>