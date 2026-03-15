<?php
/**
 * check_auth.php - Returns the current authentication status.
 * Returns JSON indicating whether a user is logged in.
 * Used by the frontend to guard routes and show/hide UI elements.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'logged_in' => true,
        'user' => [
            'id'   => (int) $_SESSION['user_id'],
            'name' => $_SESSION['user_name'] ?? null,
        ],
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'logged_in' => false,
        'user'      => null,
    ]);
}
?>
