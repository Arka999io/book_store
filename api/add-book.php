<?php
require_once '../db-connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Неверный метод запроса. Используйте POST.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Проверка наличия обязательных полей
$requiredFields = ['title', 'publication_year', 'author_ids', 'genre_ids'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(['error' => "Поле '$field' обязательно для заполнения."]);
        exit;
    }
}

// Валидация данных
$title = trim($data['title']);
if (strlen($title) < 1 || strlen($title) > 255) {
    echo json_encode(['error' => 'Название книги должно содержать от 1 до 255 символов.']);
    exit;
}

$publicationYear = intval($data['publication_year']);
if ($publicationYear < 0 || $publicationYear > date('Y')) {
    echo json_encode(['error' => 'Год издания должен быть положительным числом и не больше текущего года.']);
    exit;
}

//IDs в целые числа и удаляем дубликаты
$authorIds = array_unique(array_map('intval', $data['author_ids']));
$genreIds = array_unique(array_map('intval', $data['genre_ids']));

try {
    $pdo->beginTransaction();

    foreach ($authorIds as $authorId) {
        $checkAuthorSql = "SELECT id FROM authors WHERE id = :author_id";
        $checkStmt = $pdo->prepare($checkAuthorSql);
        $checkStmt->bindParam(':author_id', $authorId);
        $checkStmt->execute();
        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception("Автор с ID $authorId не существует.");
        }
    }

    foreach ($genreIds as $genreId) {
        $checkGenreSql = "SELECT id FROM genres WHERE id = :genre_id";
        $checkStmt = $pdo->prepare($checkGenreSql);
        $checkStmt->bindParam(':genre_id', $genreId);
        $checkStmt->execute();
        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception("Жанр с ID $genreId не существует.");
        }
    }

    $insertBookSql = "INSERT INTO books (title, publication_year) VALUES (:title, :publication_year)";
    $stmt = $pdo->prepare($insertBookSql);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':publication_year', $publicationYear);
    $stmt->execute();
    $bookId = $pdo->lastInsertId();

    if (!$bookId) {
        throw new Exception('Не удалось создать книгу.');
    }

    foreach ($authorIds as $authorId) {
        $insertAuthorSql = "INSERT INTO book_authors (book_id, author_id) VALUES (:book_id, :author_id)";
        $stmt = $pdo->prepare($insertAuthorSql);
        $stmt->bindParam(':book_id', $bookId);
        $stmt->bindParam(':author_id', $authorId);
        $stmt->execute();
    }

    foreach ($genreIds as $genreId) {
        $insertGenreSql = "INSERT INTO book_genres (book_id, genre_id) VALUES (:book_id, :genre_id)";
        $stmt = $pdo->prepare($insertGenreSql);
        $stmt->bindParam(':book_id', $bookId);
        $stmt->bindParam(':genre_id', $genreId);
        $stmt->execute();
    }

    $selectBookSql = "SELECT b.id, b.title, b.publication_year, 
                             GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') AS genres, 
                             GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') AS authors
                      FROM books b
                      LEFT JOIN book_genres bg ON b.id = bg.book_id
                      LEFT JOIN genres g ON bg.genre_id = g.id
                      LEFT JOIN book_authors ba ON b.id = ba.book_id
                      LEFT JOIN authors a ON ba.author_id = a.id
                      WHERE b.id = :book_id
                      GROUP BY b.id";
    $stmt = $pdo->prepare($selectBookSql);
    $stmt->bindParam(':book_id', $bookId);
    $stmt->execute();
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
        throw new Exception('Не удалось получить информацию о созданной книге.');
    }

    $pdo->commit();
    echo json_encode($book, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка при добавлении книги: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}