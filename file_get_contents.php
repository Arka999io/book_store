<?php
// POST запрос
$data = json_encode([
    'title' => 'Новая книга2',
    'publication_year' => 2025,
    'author_ids' => [2],
    'genre_ids' => [1]
]);

$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => $data
    ]
];

$context = stream_context_create($options);
$url = 'http://arka999io.yzz.me/api/books';
$response = file_get_contents($url, false, $context);
echo $response;
?>