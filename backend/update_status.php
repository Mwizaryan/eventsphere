<?php
/**
 * update_status.php — Updates the status of a booking.
 * Accepts POST: booking_id, new_status
 * Requires admin access.
 */

// ── ADMIN GUARD ───────────────────────────────────────────────
// Checks session, verifies user exists, and confirms is_admin = 1
// Returns 401/403 JSON error and exits if not authorized.
require_once __DIR__ . '/admin_guard.php';

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

        // ── STATUS UPDATE EMAIL ──────────────────────────────────────
        try {
            require_once __DIR__ . '/mailer.php';

            // Get customer and service details via JOIN
            $stmtDetails = $pdo->prepare('
                SELECT u.name, u.email, s.title, b.event_date
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN services s ON b.service_id = s.id
                WHERE b.id = :id
            ');
            $stmtDetails->execute([':id' => $bookingId]);
            $details = $stmtDetails->fetch();

            if ($details) {
                $statusUpper = strtoupper($newStatus);
                $heading = "Booking Update: #{$bookingId}";
                $content = "
                    <p>Hello <strong>{$details['name']}</strong>,</p>
                    <p>The status of your booking for <strong>{$details['title']}</strong> on <strong>{$details['event_date']}</strong> has been updated to:</p>
                    <div style='background:rgba(255,255,255,0.05); padding:20px; border-radius:8px; text-align:center; margin:20px 0;'>
                        <span style='font-size:24px; font-weight:800; color:" . ($newStatus === 'confirmed' ? '#10b981' : '#ef4444') . ";'>
                            {$statusUpper}
                        </span>
                    </div>
                ";

                if ($newStatus === 'confirmed') {
                    $content .= "<p>Great news! Your booking is now confirmed. We look forward to seeing you!</p>";
                    $subject = "EventSphere Booking CONFIRMED: #{$bookingId}";
                } else {
                    $content .= "<p>We regret to inform you that your booking has been cancelled. If you have any questions, please contact our support team.</p>";
                    $subject = "EventSphere Booking CANCELLED: #{$bookingId}";
                }

                $htmlBody = emailTemplate($heading, $content);
                sendBookingEmail($details['name'], $details['email'], $subject, $htmlBody);
            }
        } catch (\Exception $e) {
            error_log("Status Update Email Failed for Booking #{$bookingId}: " . $e->getMessage());
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Booking not found or status already set.']);
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update booking status.']);
}
?>
