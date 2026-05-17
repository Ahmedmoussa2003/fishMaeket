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
    $result = pg_query_params($db, "
        SELECT id, title, message, is_read, created_at
        FROM notifications
        WHERE user_id = $1
        ORDER BY created_at DESC
        LIMIT 50
    ", [$userId]);

    $notifications = pgFetchAll($result);
    $unread = count(array_filter($notifications, fn($n) => !$n['is_read']));

    success(['notifications' => $notifications, 'unread' => $unread]);
}

if ($method === 'PUT') {
    if (($_GET['all'] ?? null) === '1') {
        pg_query_params($db, "UPDATE notifications SET is_read = true WHERE user_id = $1", [$userId]);
        success(null, 'All notifications marked as read');
    }
    error('Invalid request');
}
