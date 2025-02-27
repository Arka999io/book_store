# book_store
sql запрос сразу создает таблицу и заполняет ее.
Примеры запросов api
http://arka999io.yzz.me/api/largest-sales?from_date=2023-01-01&to_date=2025-12-31&genre_id=2&limit=5
http://arka999io.yzz.me/api/popular-authors?from_date=2023-01-01&to_date=2024-12-31&genre_id=1&limit=5

Так как на моем сервере стоит защита, которую нельзя отключить, то дополнительно был создан скрипт file_get_contents.php который отправляет POST запрос и создает книгу

Для того чтобы все работало нужно изменить db-connect.php и ввести актуальные данные для подключения к БД
