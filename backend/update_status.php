<?php
/**
 * update_status.php — Updates the status of a booking.
 * Accepts POST: booking_id, new_status
 */

header('Content-Type: application/json');

session_start();

// ── AUTH GUARD ────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/db.php';

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$bookingId = isset($input['booking_id']) ? (int) $input['booking_id'] : 0;
$newStatus = isset($input['new_status']) ? trim($input['new_status']) : '';

// Validation
if ($bookingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID.']);
    exit;
}

$allowedStatuses = ['confirmed', 'cancelled'];
if (!in_array($newStatus, $allowedStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status. Choose "confirmed" or "cancelled".']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE bookings SET status = :status WHERE id = :id');
    $stmt->execute([
        ':status' => $newStatus,
        ':id'     => $bookingId
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Booking status updated successfully!',
            'booking_id' => $bookingId,
            'new_status' => $newStatus
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Booking not found or status already set.']);
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update booking status.']);
}
?>
