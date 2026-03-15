<?php
/**
 * get_all_bookings.php — Returns all bookings in the system for admin management.
 * Joins bookings with users and services to provide complete information.
 */

header('Content-Type: application/json');

session_start();

// ── AUTH GUARD ────────────────────────────────────────────────
// In a real app, we'd check for an is_admin flag here.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->prepare(
        'SELECT 
            b.id, 
            b.event_date, 
            b.status, 
            u.name as customer_name, 
            u.email as customer_email,
            s.title as service_title, 
            s.category as service_category, 
            s.price as service_price
         FROM bookings b 
         JOIN users u ON b.user_id = u.id
         JOIN services s ON b.service_id = s.id 
         ORDER BY b.event_date ASC'
    );
    $stmt->execute();
    $bookings = $stmt->fetchAll();

    echo json_encode([
        'success'  => true,
        'count'    => count($bookings),
        'bookings' => $bookings,
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve all bookings.']);
}
?>
