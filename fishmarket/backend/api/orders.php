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
$id     = $_GET['id'] ?? null;

$token = getBearerToken();
if (!$token) error('Unauthorized', 401);
$auth = verifyToken($token);
if (!$auth) error('Invalid token', 401);

$userId = $auth['id'];
$db     = getDB();

if ($method === 'GET') {
    if ($id) {
        $result = pg_query_params($db, "
            SELECT o.*, oi.fish_id, oi.quantity, oi.price as item_price,
                   f.name as fish_name, f.image_url
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            JOIN fish f ON f.id = oi.fish_id
            WHERE o.id = $1 AND o.user_id = $2
        ", [$id, $userId]);

        $rows = pgFetchAll($result);
        if (!$rows) error('Order not found', 404);

        $order = [
            'id'               => $rows[0]['id'],
            'total'            => $rows[0]['total'],
            'status'           => $rows[0]['status'],
            'delivery_address' => $rows[0]['delivery_address'],
            'delivery_city'    => $rows[0]['delivery_city'],
            'notes'            => $rows[0]['notes'],
            'created_at'       => $rows[0]['created_at'],
            'items'            => array_map(fn($r) => [
                'fish_id'   => $r['fish_id'],
                'fish_name' => $r['fish_name'],
                'image_url' => $r['image_url'],
                'quantity'  => $r['quantity'],
                'price'     => $r['item_price'],
            ], $rows),
        ];
        success($order);
    } else {
        $result = pg_query_params($db,
            "SELECT * FROM orders WHERE user_id = $1 ORDER BY created_at DESC", [$userId]);
        success(pgFetchAll($result));
    }
}

if ($method === 'POST') {
    $body    = getBody();
    $address = $body['delivery_address'] ?? '';
    $city    = $body['delivery_city']    ?? 'Nouakchott';
    $notes   = $body['notes']            ?? '';

    if (!$address) error('Delivery address required');

    $cartResult = pg_query_params($db, "
        SELECT c.fish_id, c.quantity, f.price, f.stock, f.name
        FROM cart c
        JOIN fish f ON f.id = c.fish_id
        WHERE c.user_id = $1 AND f.is_available = true
    ", [$userId]);

    $cartItems = pgFetchAll($cartResult);
    if (empty($cartItems)) error('Cart is empty');

    $total = 0;
    foreach ($cartItems as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    $orderResult = pg_query_params($db,
        "INSERT INTO orders (user_id, total, delivery_address, delivery_city, notes) VALUES ($1,$2,$3,$4,$5) RETURNING id",
        [$userId, $total, $address, $city, $notes]
    );
    $orderId = pg_fetch_assoc($orderResult)['id'];

    foreach ($cartItems as $item) {
        pg_query_params($db,
            "INSERT INTO order_items (order_id, fish_id, quantity, price) VALUES ($1,$2,$3,$4)",
            [$orderId, $item['fish_id'], $item['quantity'], $item['price']]
        );
        pg_query_params($db,
            "UPDATE fish SET stock = stock - $1 WHERE id = $2",
            [$item['quantity'], $item['fish_id']]
        );
    }

    pg_query_params($db, "DELETE FROM cart WHERE user_id = $1", [$userId]);
    success(['order_id' => $orderId, 'total' => $total], 'Order placed successfully', 201);
}

if ($method === 'PUT') {
    if (!$id) error('Order ID required');
    $body    = getBody();
    $status  = $body['status'] ?? '';
    $allowed = ['pending', 'confirmed', 'delivering', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed)) error('Invalid status');

    pg_query_params($db, "UPDATE orders SET status = $1 WHERE id = $2 AND user_id = $3",
        [$status, $id, $userId]);
    success(null, 'Order updated');
}
