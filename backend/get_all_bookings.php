<?php
/**
 * get_all_bookings.php — Returns all bookings in the system for admin management.
 * Joins bookings with users and services to provide complete information.
 * Requires admin access.
 */

// ── ADMIN GUARD ───────────────────────────────────────────────
// Checks session, verifies user exists, and confirms is_admin = 1
// Returns 401/403 JSON error and exits if not authorized.
require_once __DIR__ . '/admin_guard.php';

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
