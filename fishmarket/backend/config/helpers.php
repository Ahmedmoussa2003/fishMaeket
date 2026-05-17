<?php
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
    $auth = null;

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $auth = $value;
                break;
            }
        }
    }

    if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!$auth && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if ($auth && preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
        return $matches[1];
    }

    return null;
}

function verifyToken($token) {
    $secret  = 'fish_market_secret_2024';
    $decoded = base64_decode($token);
    $parts   = explode(':', $decoded);
    if (count($parts) === 3 && $parts[2] === $secret) {
        return ['id' => $parts[0], 'email' => $parts[1]];
    }
    return null;
}

function generateToken($id, $email) {
    $secret = 'fish_market_secret_2024';
    return base64_encode("$id:$email:$secret");
}

function pgFetchAll($result) {
    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}
