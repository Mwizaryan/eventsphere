<?php
/**
 * admin_guard.php — Reusable admin authentication guard.
 * 
 * Usage: Include at the top of any admin-only endpoint.
 * Example: require_once __DIR__ . '/admin_guard.php';
 * 
 * This guard:
 * - Starts the session
 * - Checks if user is logged in (user_id exists in session)
 * - Re-verifies is_admin flag from the database for security
 * - Returns 403 JSON error if not admin
 * - Returns early if admin (no further action needed)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please log in to access this resource.',
    ]);
    exit;
}

require_once __DIR__ . '/db.php';

// Re-verify user and fetch fresh is_admin status from database
try {
    $stmt = $pdo->prepare('SELECT id, name, email, is_admin FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User no longer exists, destroy session
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'User not found. Please log in again.',
        ]);
        exit;
    }
    
    // Update session with fresh is_admin value
    $_SESSION['is_admin'] = (int) $user['is_admin'];
    
    // Check if user is admin
    if ($_SESSION['is_admin'] !== 1) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden. Admin access required.',
        ]);
        exit;
    }
    
    // User is authenticated and is admin - guard passes
    // The including script can continue execution
    
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please try again later.',
    ]);
    exit;
}
?>
