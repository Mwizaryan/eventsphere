<?php
/**
 * add_service.php — Inserts a new service into the services table.
 * Accepts POST: category, title, description, price, image_url (optional).
 * Supports JSON body and application/x-www-form-urlencoded.
 * Requires admin access.
 */

// ── ADMIN GUARD ───────────────────────────────────────────────
// Checks session, verifies user exists, and confirms is_admin = 1
// Returns 401/403 JSON error and exits if not authorized.
require_once __DIR__ . '/admin_guard.php';

// ── METHOD CHECK ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/db.php';

// ── PARSE INPUT (JSON or form-data) ──────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$allowedCategories = ['venue', 'entertainer', 'catering'];

$category  = isset($input['category'])    ? trim($input['category'])    : '';
$title     = isset($input['title'])       ? trim($input['title'])       : '';
$desc      = isset($input['description']) ? trim($input['description']) : '';
$price     = isset($input['price'])       ? $input['price']             : '';
$imageUrl  = isset($input['image_url'])   ? trim($input['image_url'])   : null;

// ── VALIDATION ───────────────────────────────────────────────
$errors = [];

if (!in_array($category, $allowedCategories, true)) {
    $errors[] = 'Category must be one of: venue, entertainer, catering.';
}
if (empty($title)) {
    $errors[] = 'Title is required.';
} elseif (strlen($title) > 255) {
    $errors[] = 'Title must be 255 characters or fewer.';
}
if (!is_numeric($price) || (float)$price < 0) {
    $errors[] = 'Price must be a valid non-negative number.';
}
if ($imageUrl !== null && $imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
    $errors[] = 'Image URL must be a valid URL or left blank.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Normalise: empty image_url → NULL in DB
$imageUrl = ($imageUrl === '') ? null : $imageUrl;

// ── INSERT ───────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'INSERT INTO services (category, title, description, price, image_url)
         VALUES (:category, :title, :description, :price, :image_url)'
    );
    $stmt->execute([
        ':category'    => $category,
        ':title'       => $title,
        ':description' => $desc ?: null,
        ':price'       => (float) $price,
        ':image_url'   => $imageUrl,
    ]);

    http_response_code(201);
    echo json_encode([
        'success'    => true,
        'message'    => 'Service added successfully.',
        'service_id' => (int) $pdo->lastInsertId(),
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Could not add service.']);
}
?>
