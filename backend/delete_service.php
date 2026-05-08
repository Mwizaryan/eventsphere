<?php
/**
 * delete_service.php - Deletes an existing service for admin users.
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

if (!function_exists('adminCrudRespond')) {
    function adminCrudRespond(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        echo json_encode($payload);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminCrudRespond(405, ['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    return;
}

if (!isset($_SESSION['user_id'])) {
    adminCrudRespond(403, ['success' => false, 'message' => 'Forbidden. Admin access required.']);
    return;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/db.php';
}

try {
    $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = :id');
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    $isAdmin = $user && (int) $user['is_admin'] === 1;
    $_SESSION['is_admin'] = $isAdmin ? 1 : 0;

    if (!$isAdmin) {
        adminCrudRespond(403, ['success' => false, 'message' => 'Forbidden. Admin access required.']);
        return;
    }
} catch (\PDOException $e) {
    adminCrudRespond(500, ['success' => false, 'message' => 'Failed to verify admin access.']);
    return;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || $input === []) {
    $input = $_POST;
}

$serviceId = isset($input['service_id']) ? (int) $input['service_id'] : 0;

if ($serviceId <= 0) {
    adminCrudRespond(400, ['success' => false, 'message' => 'A valid service_id is required.']);
    return;
}

try {
    $stmt = $pdo->prepare('DELETE FROM services WHERE id = :id');
    $stmt->execute([':id' => $serviceId]);

    if ($stmt->rowCount() === 0) {
        adminCrudRespond(404, ['success' => false, 'message' => 'Service not found.']);
        return;
    }

    adminCrudRespond(200, [
        'success' => true,
        'message' => 'Service deleted successfully.',
        'service_id' => $serviceId,
    ]);
} catch (\PDOException $e) {
    adminCrudRespond(500, ['success' => false, 'message' => 'Failed to delete service.']);
}
