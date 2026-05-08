<?php
/**
 * cancel_booking.php - Allows a user to cancel their own pending booking.
 */

if (!headers_sent()) {
    header('Content-Type: application/json');
}

if (
    session_status() !== PHP_SESSION_ACTIVE
    && !(PHP_SAPI === 'cli' && isset($_SESSION) && is_array($_SESSION))
) {
    session_start();
}

if (!function_exists('cancelBookingRespond')) {
    /**
     * Emit a JSON response without terminating the whole PHPUnit process.
     */
    function cancelBookingRespond(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        echo json_encode($payload);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cancelBookingRespond(405, ['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    return;
}

if (!isset($_SESSION['user_id'])) {
    cancelBookingRespond(401, ['success' => false, 'message' => 'Unauthorized. Please log in.']);
    return;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/db.php';
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || $input === []) {
    $input = $_POST;
}

$bookingId = isset($input['booking_id']) ? (int) $input['booking_id'] : 0;
$userId = (int) $_SESSION['user_id'];

if ($bookingId <= 0) {
    cancelBookingRespond(400, ['success' => false, 'message' => 'A valid booking_id is required.']);
    return;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, user_id, status
         FROM bookings
         WHERE id = :id'
    );
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking || (int) $booking['user_id'] !== $userId) {
        cancelBookingRespond(403, ['success' => false, 'message' => 'Forbidden. You cannot cancel this booking.']);
        return;
    }

    if ($booking['status'] !== 'pending') {
        cancelBookingRespond(400, ['success' => false, 'message' => 'Only pending bookings can be cancelled.']);
        return;
    }

    $update = $pdo->prepare(
        'UPDATE bookings
         SET status = :status
         WHERE id = :id'
    );
    $update->execute([
        ':status' => 'cancelled',
        ':id'     => $bookingId,
    ]);

    cancelBookingRespond(200, [
        'success'    => true,
        'message'    => 'Booking cancelled successfully.',
        'booking_id' => $bookingId,
        'status'     => 'cancelled',
    ]);
} catch (\PDOException $e) {
    cancelBookingRespond(500, ['success' => false, 'message' => 'Failed to cancel booking.']);
}
