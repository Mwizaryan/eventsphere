<?php
/**
 * check_auth.php - Returns the current authentication status.
 * Returns JSON indicating whether a user is logged in.
 * Re-verifies is_admin flag from the database on every request for security.
 * Used by the frontend to guard routes and show/hide UI elements.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/db.php';
    
    // Re-verify user and fetch fresh is_admin status from database
    try {
        $stmt = $pdo->prepare('SELECT id, name, email, is_admin FROM users WHERE id = :id');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Update session with fresh is_admin value
            $_SESSION['is_admin'] = (int) $user['is_admin'];
            
            echo json_encode([
                'logged_in' => true,
                'user' => [
                    'id'       => (int) $user['id'],
                    'name'     => $user['name'],
                    'email'    => $user['email'],
                    'is_admin' => (bool) $_SESSION['is_admin'],
                ],
            ]);
        } else {
            // User no longer exists, destroy session
            session_unset();
            session_destroy();
            http_response_code(401);
            echo json_encode([
                'logged_in' => false,
                'user'      => null,
            ]);
        }
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'logged_in' => false,
            'user'      => null,
            'message'   => 'Database error.',
        ]);
    }
} else {
    http_response_code(401);
    echo json_encode([
        'logged_in' => false,
        'user'      => null,
    ]);
}
?>
