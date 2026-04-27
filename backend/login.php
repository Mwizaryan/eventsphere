<?php
/**
 * login.php - Handles user login.
 * Accepts POST requests with: email, password.
 * On success, starts a PHP session and sets $_SESSION['user_id'].
 * Supports both JSON body and application/x-www-form-urlencoded.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Start the session before any output
session_start();

require_once __DIR__ . '/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

// Attempt to parse JSON body first, then fall back to form data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$email    = isset($input['email'])    ? trim($input['email'])  : '';
$password = isset($input['password']) ? $input['password']     : '';

// --- Validation ---
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

// --- Fetch user by email (including is_admin flag) ---
try {
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, is_admin FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit;
}

// --- Verify password (use same timing whether user exists or not) ---
// Use a dummy hash to prevent timing attacks when user is not found
$dummyHash = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
$hashToCheck = $user ? $user['password_hash'] : $dummyHash;

if (!$user || !password_verify($password, $hashToCheck)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

// --- Login success: regenerate session ID for security ---
session_regenerate_id(true);

$_SESSION['user_id']   = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['is_admin']  = (int) $user['is_admin'];

echo json_encode([
    'success' => true,
    'message' => 'Login successful.',
    'user'    => [
        'id'       => (int) $user['id'],
        'name'     => $user['name'],
        'email'    => $user['email'],
        'is_admin' => (bool) $user['is_admin'],
    ],
]);
?>
