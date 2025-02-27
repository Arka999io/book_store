<?php
require_once '../db-connect.php';

header('Content-Type: application/json');

// Получение параметров из запроса
$fromDate = $_GET['from_date'] ?? null;
$toDate = $_GET['to_date'] ?? null;
$genreId = $_GET['genre_id'] ?? null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;

if (!$fromDate || !$toDate) {
    echo json_encode(['error' => 'Параметры from_date и to_date обязательны']);
    exit;
}

// SQL-запрос
$sql = "SELECT b.id, b.title, b.publication_year, 
               GROUP_CONCAT(g.name SEPARATOR ', ') AS genres, 
               GROUP_CONCAT(a.name SEPARATOR ', ') AS authors,
               s.sale_datetime, s.quantity * s.price_per_unit AS total_price
        FROM books b
        JOIN sales s ON b.id = s.book_id
        LEFT JOIN book_genres bg ON b.id = bg.book_id
        LEFT JOIN genres g ON bg.genre_id = g.id
        LEFT JOIN book_authors ba ON b.id = ba.book_id
        LEFT JOIN authors a ON ba.author_id = a.id
        WHERE s.sale_datetime BETWEEN :from_date AND :to_date
        AND (:genre_id IS NULL OR bg.genre_id = :genre_id)
        GROUP BY b.id, s.sale_datetime
        ORDER BY total_price DESC, s.sale_datetime DESC
        LIMIT :limit";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':from_date', $fromDate);
$stmt->bindParam(':to_date', $toDate);
if ($genreId !== null) {
    $stmt->bindParam(':genre_id', $genreId);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));