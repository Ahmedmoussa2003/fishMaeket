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
        SELECT c.fish_id, c.quantity,
               f.name, f.price, f.image_url, f.stock, f.unit
        FROM cart c
        JOIN fish f ON f.id = c.fish_id
        WHERE c.user_id = $1 AND f.is_available = true
    ", [$userId]);

    $items = pgFetchAll($result);
    $total = 0;
    foreach ($items as &$item) {
        $item['subtotal'] = $item['price'] * $item['quantity'];
        $total += $item['subtotal'];
    }

    success(['items' => $items, 'total' => $total, 'count' => count($items)]);
}

if ($method === 'POST') {
    $body     = getBody();
    $fishId   = $body['fish_id']  ?? null;
    $quantity = $body['quantity'] ?? null;

    if (!$fishId || $quantity === null) error('fish_id and quantity are required');
    if ($quantity <= 0) error('Quantity must be greater than zero');

    $fishResult = pg_query_params($db, "SELECT id, stock FROM fish WHERE id = $1 AND is_available = true", [$fishId]);
    $fish       = pg_fetch_assoc($fishResult);
    if (!$fish) error('Fish not found', 404);
    if ($fish['stock'] < $quantity) error('Not enough stock');

    $exists = pg_fetch_assoc(pg_query_params($db,
        "SELECT id FROM cart WHERE user_id = $1 AND fish_id = $2", [$userId, $fishId]
    ));

    if ($exists) {
        pg_query_params($db, "UPDATE cart SET quantity = $1 WHERE user_id = $2 AND fish_id = $3",
            [$quantity, $userId, $fishId]);
    } else {
        pg_query_params($db, "INSERT INTO cart (user_id, fish_id, quantity) VALUES ($1,$2,$3)",
            [$userId, $fishId, $quantity]);
    }

    success(null, 'Cart updated successfully');
}

if ($method === 'DELETE') {
    $fishId = $_GET['fish_id'] ?? null;
    $clear  = $_GET['clear']   ?? null;

    if ($clear === '1') {
        pg_query_params($db, "DELETE FROM cart WHERE user_id = $1", [$userId]);
        success(null, 'Cart cleared');
    }

    if (!$fishId) error('fish_id required');
    pg_query_params($db, "DELETE FROM cart WHERE user_id = $1 AND fish_id = $2", [$userId, $fishId]);
    success(null, 'Item removed from cart');
}
