<?php
/**
 * book_service.php — Creates a new booking in the bookings table.
 * Accepts POST: service_id, event_date
 * Requires an active session ($_SESSION['user_id']).
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

session_start();

// ── AUTH GUARD ────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in to make a booking.']);
    exit;
}

// ── METHOD CHECK ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/db.php';

// ── PARSE INPUT ───────────────────────────────────────────────
$input      = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$userId     = (int) $_SESSION['user_id'];
$serviceId  = isset($input['service_id'])  ? (int) trim($input['service_id'])  : 0;
$eventDate  = isset($input['event_date'])  ? trim($input['event_date'])         : '';

// ── VALIDATION ───────────────────────────────────────────────
if ($serviceId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid service_id is required.']);
    exit;
}

if (empty($eventDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'An event_date is required.']);
    exit;
}

// Validate date format (YYYY-MM-DD) and that it's in the future
$dateObj = DateTime::createFromFormat('Y-m-d', $eventDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $eventDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD.']);
    exit;
}

$today = new DateTime('today');
if ($dateObj <= $today) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event date must be in the future.']);
    exit;
}

// ── VERIFY SERVICE EXISTS ─────────────────────────────────────
try {
    $stmt = $pdo->prepare('SELECT id, title FROM services WHERE id = :id');
    $stmt->execute([':id' => $serviceId]);
    $service = $stmt->fetch();

    if (!$service) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Service not found.']);
        exit;
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error verifying service.']);
    exit;
}

// ── PREVENT DUPLICATE BOOKING (same user + service + date) ───
try {
    $stmt = $pdo->prepare(
        'SELECT id FROM bookings
         WHERE user_id = :user_id AND service_id = :service_id AND event_date = :event_date
         AND status != :cancelled'
    );
    $stmt->execute([
        ':user_id'    => $userId,
        ':service_id' => $serviceId,
        ':event_date' => $eventDate,
        ':cancelled'  => 'cancelled',
    ]);

    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'You already have an active booking for this service on that date.']);
        exit;
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error checking duplicates.']);
    exit;
}

// ── INSERT BOOKING ────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'INSERT INTO bookings (user_id, service_id, event_date, status)
         VALUES (:user_id, :service_id, :event_date, :status)'
    );
    $stmt->execute([
        ':user_id'    => $userId,
        ':service_id' => $serviceId,
        ':event_date' => $eventDate,
        ':status'     => 'pending',
    ]);

    $bookingId = (int) $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'success'    => true,
        'message'    => 'Booking confirmed!',
        'booking_id' => $bookingId,
        'service'    => $service['title'],
        'event_date' => $eventDate,
        'status'     => 'pending',
    ]);

    // ── EMAIL NOTIFICATION (Post-Response) ──────────────────────────
    try {
        require_once __DIR__ . '/mailer.php';

        // Get user details and service price for the email
        $stmtDetails = $pdo->prepare('
            SELECT u.name, u.email, s.title, s.price 
            FROM users u, services s 
            WHERE u.id = :user_id AND s.id = :service_id
        ');
        $stmtDetails->execute([':user_id' => $userId, ':service_id' => $serviceId]);
        $details = $stmtDetails->fetch();

        if ($details) {
            $formattedPrice = number_format($details['price'], 2);
            $heading = "Booking Confirmation — #{$bookingId}";
            $content = "
                <p>Hello <strong>{$details['name']}</strong>,</p>
                <p>Your booking for <strong>{$details['title']}</strong> has been received and is currently <strong>PENDING</strong>.</p>
                <p><strong>Booking Details:</strong></p>
                <ul>
                    <li><strong>Booking ID:</strong> #{$bookingId}</li>
                    <li><strong>Service:</strong> {$details['title']}</li>
                    <li><strong>Event Date:</strong> {$eventDate}</li>
                    <li><strong>Total Price:</strong> \${$formattedPrice}</li>
                </ul>
                <p>We will notify you once an administrator has reviewed and confirmed your booking.</p>
                <a href='#' class='btn'>View Dashboard</a>
            ";

            $htmlBody = emailTemplate($heading, $content);
            sendBookingEmail($details['name'], $details['email'], "EventSphere Booking Received: #{$bookingId}", $htmlBody);
        }
    } catch (\Exception $e) {
        error_log("Email Notification Failed for Booking #{$bookingId}: " . $e->getMessage());
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create booking. Please try again.']);
}
?>
