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
$id = $_GET['id'] ?? null;

if ($method === 'GET') {
    $db = getDB();
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM fish WHERE id = ? AND is_available = 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if (!$result) error('Fish not found', 404);
        success($result);
    } else {
        $search = $_GET['search'] ?? '';
        if ($search) {
            $like = "%$search%";
            $stmt = $db->prepare("SELECT * FROM fish WHERE is_available = 1 AND (name LIKE ? OR location LIKE ? OR tag LIKE ?) ORDER BY id DESC");
            $stmt->bind_param('sss', $like, $like, $like);
        } else {
            $stmt = $db->prepare("SELECT * FROM fish WHERE is_available = 1 ORDER BY id DESC");
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        success($rows);
    }
}

if ($method === 'POST') {
    $body = getBody();
    $db = getDB();
    $name     = $body['name'] ?? '';
    $location = $body['location'] ?? '';
    $tag      = $body['tag'] ?? '';
    $price    = $body['price'] ?? 0;
    $stock    = $body['stock'] ?? 0;
    $image    = $body['image_url'] ?? '';

    if (!$name || !$price) error('Name and price are required');

    $stmt = $db->prepare("INSERT INTO fish (name, location, tag, price, stock, image_url) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('sssdds', $name, $location, $tag, $price, $stock, $image);
    $stmt->execute();
    success(['id' => $db->insert_id], 'Fish added successfully', 201);
}

if ($method === 'PUT') {
    if (!$id) error('ID required');
    $body = getBody();
    $db = getDB();
    $name      = $body['name'] ?? '';
    $location  = $body['location'] ?? '';
    $tag       = $body['tag'] ?? '';
    $price     = $body['price'] ?? 0;
    $stock     = $body['stock'] ?? 0;
    $image     = $body['image_url'] ?? '';
    $available = $body['is_available'] ?? 1;

    $stmt = $db->prepare("UPDATE fish SET name=?, location=?, tag=?, price=?, stock=?, image_url=?, is_available=? WHERE id=?");
    $stmt->bind_param('sssddsii', $name, $location, $tag, $price, $stock, $image, $available, $id);
    $stmt->execute();
    success(null, 'Fish updated successfully');
}

if ($method === 'DELETE') {
    if (!$id) error('ID required');
    $db = getDB();
    $stmt = $db->prepare("UPDATE fish SET is_available = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    success(null, 'Fish deleted');
}
