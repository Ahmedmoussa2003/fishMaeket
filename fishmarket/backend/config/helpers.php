<?php
function setCORS() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

function success($data, $message = 'Success', $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit();
}

function error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

function getBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches);
        return $matches[1] ?? null;
    }
    return null;
}

function verifyToken($token) {
    // Simple token: base64(user_id:email:secret)
    $secret = 'fish_market_secret_2024';
    $decoded = base64_decode($token);
    $parts = explode(':', $decoded);
    if (count($parts) === 3 && $parts[2] === $secret) {
        return ['id' => $parts[0], 'email' => $parts[1]];
    }
    return null;
}

function generateToken($id, $email) {
    $secret = 'fish_market_secret_2024';
    return base64_encode("$id:$email:$secret");
}
