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
$db     = getDB();

if ($method === 'GET') {
    if ($id) {
        $result = pg_query_params($db, "SELECT * FROM fish WHERE id = $1 AND is_available = true", [$id]);
        $row    = pg_fetch_assoc($result);
        if (!$row) error('Fish not found', 404);
        success($row);
    } else {
        $search = $_GET['search'] ?? '';
        if ($search) {
            $like   = '%' . $search . '%';
            $result = pg_query_params($db,
                "SELECT * FROM fish WHERE is_available = true AND (name ILIKE $1 OR location ILIKE $2 OR tag ILIKE $3) ORDER BY id DESC",
                [$like, $like, $like]
            );
        } else {
            $result = pg_query($db, "SELECT * FROM fish WHERE is_available = true ORDER BY id DESC");
        }
        success(pgFetchAll($result));
    }
}

if ($method === 'POST') {
    $body     = getBody();
    $name     = $body['name']      ?? '';
    $location = $body['location']  ?? '';
    $tag      = $body['tag']       ?? '';
    $price    = $body['price']     ?? 0;
    $stock    = $body['stock']     ?? 0;
    $image    = $body['image_url'] ?? '';

    if (!$name || !$price) error('Name and price are required');

    $result = pg_query_params($db,
        "INSERT INTO fish (name, location, tag, price, stock, image_url) VALUES ($1,$2,$3,$4,$5,$6) RETURNING id",
        [$name, $location, $tag, $price, $stock, $image]
    );
    $row = pg_fetch_assoc($result);
    success(['id' => $row['id']], 'Fish added successfully', 201);
}

if ($method === 'PUT') {
    if (!$id) error('ID required');
    $body      = getBody();
    $name      = $body['name']         ?? '';
    $location  = $body['location']     ?? '';
    $tag       = $body['tag']          ?? '';
    $price     = $body['price']        ?? 0;
    $stock     = $body['stock']        ?? 0;
    $image     = $body['image_url']    ?? '';
    $available = $body['is_available'] ?? true;

    pg_query_params($db,
        "UPDATE fish SET name=$1, location=$2, tag=$3, price=$4, stock=$5, image_url=$6, is_available=$7 WHERE id=$8",
        [$name, $location, $tag, $price, $stock, $image, $available ? 'true' : 'false', $id]
    );
    success(null, 'Fish updated successfully');
}

if ($method === 'DELETE') {
    if (!$id) error('ID required');
    pg_query_params($db, "UPDATE fish SET is_available = false WHERE id = $1", [$id]);
    success(null, 'Fish deleted');
}
