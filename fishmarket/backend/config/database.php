<?php
function getDB() {
    $url = getenv('DATABASE_URL');

    if ($url) {
        $params = parse_url($url);
        $host   = $params['host'];
        $port   = $params['port'] ?? 5432;
        $user   = $params['user'];
        $pass   = $params['pass'];
        $dbname = ltrim($params['path'], '/');
    } else {
        $host   = getenv('DB_HOST') ?: 'localhost';
        $port   = getenv('DB_PORT') ?: 5432;
        $user   = getenv('DB_USER') ?: 'postgres';
        $pass   = getenv('DB_PASS') ?: '';
        $dbname = getenv('DB_NAME') ?: 'fish_market';
    }

    $dsn = "host=$host port=$port dbname=$dbname user=$user password=$pass sslmode=require";
    $conn = pg_connect($dsn);

    if (!$conn) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed']));
    }

    return $conn;
}
