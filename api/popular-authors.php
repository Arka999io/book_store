<?php
require_once '../db-connect.php';
header('Content-Type: application/json');

$fromDate = $_GET['from_date'] ?? null;
$toDate = $_GET['to_date'] ?? null;
$genreId = isset($_GET['genre_id']) ? intval($_GET['genre_id']) : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;

if (!$fromDate || !$toDate) {
    echo json_encode(['error' => 'Параметры from_date и to_date обязательны']);
    exit;
}

$fromDateTime = DateTime::createFromFormat('Y-m-d\TH:i:s', $fromDate) ?: DateTime::createFromFormat('Y-m-d', $fromDate);
$toDateTime = DateTime::createFromFormat('Y-m-d\TH:i:s', $toDate) ?: DateTime::createFromFormat('Y-m-d', $toDate);

if (!$fromDateTime || !$toDateTime || $fromDateTime > $toDateTime) {
    echo json_encode(['error' => 'Неверный формат даты или период указан неверно (from_date должен быть раньше to_date).']);
    exit;
}

if ($genreId !== null) {
    $checkGenreSql = "SELECT id FROM genres WHERE id = :genre_id";
    $checkStmt = $pdo->prepare($checkGenreSql);
    $checkStmt->bindParam(':genre_id', $genreId);
    $checkStmt->execute();
    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['error' => "Жанр с ID $genreId не существует."]);
        exit;
    }
}

if ($limit <= 0) {
    echo json_encode(['error' => 'Параметр limit должен быть положительным числом.']);
    exit;
}

$sql = "SELECT a.id, a.name, a.date_of_birth, SUM(s.quantity) AS total_sold
        FROM authors a
        JOIN book_authors ba ON a.id = ba.author_id
        JOIN books b ON ba.book_id = b.id
        JOIN sales s ON b.id = s.book_id
        LEFT JOIN book_genres bg ON b.id = bg.book_id
        WHERE s.sale_datetime BETWEEN :from_date AND :to_date
        AND (:genre_id IS NULL OR bg.genre_id = :genre_id)
        GROUP BY a.id
        ORDER BY total_sold DESC
        LIMIT :limit";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':from_date', $fromDate);
$stmt->bindParam(':to_date', $toDate);
if ($genreId !== null) {
    $stmt->bindParam(':genre_id', $genreId);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);