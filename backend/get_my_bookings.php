<?php
/**
 * get_my_bookings.php — Returns all bookings for the logged-in user.
 */

header('Content-Type: application/json');

session_start();

// ── AUTH GUARD ────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

require_once __DIR__ . '/db.php';

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT 
            b.id, 
            b.event_date, 
            b.status, 
            s.title, 
            s.category, 
            s.price, 
            s.image_url 
         FROM bookings b 
         JOIN services s ON b.service_id = s.id 
         WHERE b.user_id = :user_id 
         ORDER BY b.event_date DESC'
    );
    $stmt->execute([':user_id' => $userId]);
    $bookings = $stmt->fetchAll();

    echo json_encode([
        'success'  => true,
        'count'    => count($bookings),
        'bookings' => $bookings,
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve bookings.']);
}
?>
