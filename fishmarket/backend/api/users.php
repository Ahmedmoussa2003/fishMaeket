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
$token  = getBearerToken();
if (!$token) error('Unauthorized', 401);
$auth = verifyToken($token);
if (!$auth) error('Invalid token', 401);
$userId = $auth['id'];
$role   = $auth['role'] ?? 'user';
$db     = getDB();
// ── GET
if ($method === 'GET') {
    // تحقق من الـ role مباشرة من قاعدة البيانات لضمان الدقة
    $checkAdmin = pg_query_params($db,
        "SELECT role FROM users WHERE id = $1", [$userId]);
    $adminRow   = pg_fetch_assoc($checkAdmin);
    $actualRole = $adminRow['role'] ?? $role;

    // Admin: أرجع قائمة كل المستخدمين
    if ($actualRole === 'admin') {
        $result = pg_query($db,
            "SELECT id, name, email, phone, city, role, created_at
             FROM users
             ORDER BY created_at DESC"
        );
        if (!$result) error('Database error', 500);
        $users = [];
        while ($row = pg_fetch_assoc($result)) {
            $users[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $users]);
        exit();
    }
    // User عادي: أرجع بياناته فقط
    $result = pg_query_params($db,
        "SELECT id, name, email, phone, city, role, created_at
         FROM users
         WHERE id = $1",
        [$userId]
    );
    $user = pg_fetch_assoc($result);
    if (!$user) error('User not found', 404);
    success($user);
}
// ── PUT (تحديث البروفايل — للمستخدم العادي فقط)
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
        "SELECT id, name, email, phone, city, role
         FROM users
         WHERE id = $1",
        [$userId]
    );
    success(pg_fetch_assoc($result), 'Profile updated successfully');
}
// ── DELETE (Admin فقط — حذف مستخدم)
if ($method === 'DELETE') {
    if ($role !== 'admin') error('Forbidden', 403);
    $targetId = $_GET['id'] ?? null;
    if (!$targetId) error('User ID required');
    // لا يمكن حذف نفسك
    if ((string)$targetId === (string)$userId) error('Cannot delete your own account');
    $check = pg_query_params($db,
        "SELECT id FROM users WHERE id = $1", [$targetId]);
    if (!pg_fetch_assoc($check)) error('User not found', 404);
    pg_query_params($db, "DELETE FROM users WHERE id = $1", [$targetId]);
    success(null, 'User deleted successfully');
}
