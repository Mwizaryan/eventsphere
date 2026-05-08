<?php
/**
 * edit_service.php - Updates an existing service for admin users.
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
$title = isset($input['title']) ? trim((string) $input['title']) : '';
$category = isset($input['category']) ? trim((string) $input['category']) : '';
$description = isset($input['description']) ? trim((string) $input['description']) : '';
$price = $input['price'] ?? null;
$imageUrl = isset($input['image_url']) ? trim((string) $input['image_url']) : null;

if ($serviceId <= 0) {
    adminCrudRespond(400, ['success' => false, 'message' => 'A valid service_id is required.']);
    return;
}

if ($title === '') {
    adminCrudRespond(400, ['success' => false, 'message' => 'Title is required.']);
    return;
}

$allowedCategories = ['venue', 'entertainer', 'catering'];
if ($category === '' || !in_array($category, $allowedCategories, true)) {
    adminCrudRespond(400, ['success' => false, 'message' => 'Invalid category.']);
    return;
}

if (!is_numeric($price) || (float) $price < 0) {
    adminCrudRespond(400, ['success' => false, 'message' => 'Price must be a valid non-negative number.']);
    return;
}

if ($imageUrl !== null && $imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
    adminCrudRespond(400, ['success' => false, 'message' => 'Image URL must be valid or blank.']);
    return;
}

$imageUrl = ($imageUrl === '') ? null : $imageUrl;

try {
    $stmt = $pdo->prepare(
        'UPDATE services
         SET title = :title,
             category = :category,
             description = :description,
             price = :price,
             image_url = :image_url
         WHERE id = :id'
    );
    $stmt->execute([
        ':title' => $title,
        ':category' => $category,
        ':description' => $description !== '' ? $description : null,
        ':price' => (float) $price,
        ':image_url' => $imageUrl,
        ':id' => $serviceId,
    ]);

    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT id FROM services WHERE id = :id');
        $exists->execute([':id' => $serviceId]);
        if (!$exists->fetch()) {
            adminCrudRespond(404, ['success' => false, 'message' => 'Service not found.']);
            return;
        }
    }

    adminCrudRespond(200, [
        'success' => true,
        'message' => 'Service updated successfully.',
        'service_id' => $serviceId,
    ]);
} catch (\PDOException $e) {
    adminCrudRespond(500, ['success' => false, 'message' => 'Failed to update service.']);
}
