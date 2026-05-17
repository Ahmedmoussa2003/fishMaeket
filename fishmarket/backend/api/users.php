<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../config/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];

$token = getBearerToken();
if (!$token) error('Unauthorized', 401);
$auth = verifyToken($token);
if (!$auth) error('Invalid token', 401);

$userId = $auth['id'];
$db     = getDB();

if ($method === 'GET') {
    $result = pg_query_params($db,
        "SELECT id, name, email, phone, city, role, created_at FROM users WHERE id = $1", [$userId]);
    $user = pg_fetch_assoc($result);
    if (!$user) error('User not found', 404);
    success($user);
}

if ($method === 'PUT') {
    $body  = getBody();
    $name  = trim($body['name']  ?? '');
    $phone = trim($body['phone'] ?? '');
    $city  = trim($body['city']  ?? '');

    if (!$name) error('Name is required');

    pg_query_params($db,
        "UPDATE users SET name = $1, phone = $2, city = $3 WHERE id = $4",
        [$name, $phone, $city, $userId]
    );

    $result = pg_query_params($db,
        "SELECT id, name, email, phone, city, role FROM users WHERE id = $1", [$userId]);
    success(pg_fetch_assoc($result), 'Profile updated successfully');
}
