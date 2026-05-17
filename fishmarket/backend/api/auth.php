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
$action = $_GET['action'] ?? '';

if ($method !== 'POST') error('Method not allowed', 405);

$body = getBody();
$db   = getDB();

// ── REGISTER ─────────────────────────────────
if ($action === 'register') {
    $name     = trim($body['name'] ?? '');
    $email    = trim($body['email'] ?? '');
    $phone    = trim($body['phone'] ?? '');
    $password = $body['password'] ?? '';
    $city     = $body['city'] ?? 'Nouakchott';

    if (!$name || !$email || !$password) error('Name, email and password are required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) error('Invalid email');
    if (strlen($password) < 6) error('Password must be at least 6 characters');

    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param('s', $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) error('Email already registered');

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, city) VALUES (?,?,?,?,?)");
    $stmt->bind_param('sssss', $name, $email, $phone, $hashed, $city);
    $stmt->execute();

    $userId = $db->insert_id;
    $token  = generateToken($userId, $email);

    success([
        'token' => $token,
        'user'  => ['id' => $userId, 'name' => $name, 'email' => $email, 'city' => $city]
    ], 'Registered successfully', 201);
}

// ── LOGIN ─────────────────────────────────────
if ($action === 'login') {
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if (!$email || !$password) error('Email and password are required');

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
        error('Invalid email or password', 401);
    }

    $token = generateToken($user['id'], $user['email']);

    success([
        'token' => $token,
        'user'  => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'city'  => $user['city'],
        ]
    ], 'Login successful');
}

error('Invalid action');
