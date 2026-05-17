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
        $stmt = $db->prepare("
            SELECT o.*, 
                   oi.fish_id, oi.quantity, oi.price as item_price,
                   f.name as fish_name, f.image_url
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            JOIN fish f ON f.id = oi.fish_id
            WHERE o.id = ? AND o.user_id = ?
        ");
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!$rows) error('Order not found', 404);

        $order = [
            'id'               => $rows[0]['id'],
            'total'            => $rows[0]['total'],
            'status'           => $rows[0]['status'],
            'delivery_address' => $rows[0]['delivery_address'],
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
        $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        success($orders);
    }
}

if ($method === 'POST') {
    $body    = getBody();
    $address = $body['delivery_address'] ?? '';
    $notes   = $body['notes'] ?? '';

    if (!$address) error('Delivery address required');

    // Get cart items
    $stmt = $db->prepare("SELECT c.fish_id, c.quantity, f.price, f.stock, f.name FROM cart c JOIN fish f ON f.id = c.fish_id WHERE c.user_id = ? AND f.is_available = 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($cartItems)) error('Cart is empty');

    $total = 0;
    foreach ($cartItems as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    $stmt = $db->prepare("INSERT INTO orders (user_id, total, delivery_address, notes) VALUES (?,?,?,?)");
    $stmt->bind_param('idss', $userId, $total, $address, $notes);
    $stmt->execute();
    $orderId = $db->insert_id;

    foreach ($cartItems as $item) {
        $stmt = $db->prepare("INSERT INTO order_items (order_id, fish_id, quantity, price) VALUES (?,?,?,?)");
        $stmt->bind_param('iidd', $orderId, $item['fish_id'], $item['quantity'], $item['price']);
        $stmt->execute();

        $stmt = $db->prepare("UPDATE fish SET stock = stock - ? WHERE id = ?");
        $stmt->bind_param('di', $item['quantity'], $item['fish_id']);
        $stmt->execute();
    }

    // Clear cart
    $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    success(['order_id' => $orderId, 'total' => $total], 'Order placed successfully', 201);
}

if ($method === 'PUT') {
    if (!$id) error('Order ID required');
    $body   = getBody();
    $status = $body['status'] ?? '';
    $allowed = ['pending', 'confirmed', 'delivering', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed)) error('Invalid status');

    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param('sii', $status, $id, $userId);
    $stmt->execute();
    success(null, 'Order updated');
}
