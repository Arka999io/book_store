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
} else {
    echo json_encode(['error' => 'Укажите жанр.']);
    exit;
}

// Проверка limit
if ($limit <= 0) {
    echo json_encode(['error' => 'Параметр limit должен быть положительным числом.']);
    exit;
}

$sql = "WITH ranked_sales AS (
    SELECT 
        s.book_id,
        s.sale_datetime,
        s.quantity * s.price_per_unit AS total_price,
        ROW_NUMBER() OVER (PARTITION BY s.book_id 
                          ORDER BY 
                            (s.quantity * s.price_per_unit) DESC, 
                            s.sale_datetime DESC) AS rn
    FROM sales s
    WHERE s.sale_datetime BETWEEN :from_date AND :to_date
),
filtered_sales AS (
    SELECT 
        rs.book_id,
        rs.sale_datetime,
        rs.total_price
    FROM ranked_sales rs
    WHERE rs.rn = 1 -- Выбираем только самую крупную и свежую продажу
)
SELECT 
    b.id, 
    b.title, 
    b.publication_year, 
    GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') AS genres, 
    GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') AS authors,
    fs.sale_datetime, 
    fs.total_price
FROM filtered_sales fs
JOIN books b ON fs.book_id = b.id
LEFT JOIN book_genres bg ON b.id = bg.book_id
LEFT JOIN genres g ON bg.genre_id = g.id
LEFT JOIN book_authors ba ON b.id = ba.book_id
LEFT JOIN authors a ON ba.author_id = a.id
WHERE 
    (:genre_id IS NULL OR bg.genre_id = :genre_id)
GROUP BY b.id, fs.sale_datetime, fs.total_price
ORDER BY fs.total_price DESC, fs.sale_datetime DESC
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
?>