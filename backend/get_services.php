<?php
/**
 * get_services.php — Returns all services as JSON.
 * Optionally filter by ?category=venue|entertainer|catering
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

// Auth guard — only logged-in users may fetch services
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_once __DIR__ . '/db.php';

$allowedCategories = ['venue', 'entertainer', 'catering'];
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

try {
    if ($category !== '' && in_array($category, $allowedCategories, true)) {
        $stmt = $pdo->prepare(
            'SELECT id, category, title, description, price, image_url
             FROM services
             WHERE category = :category
             ORDER BY title ASC'
        );
        $stmt->execute([':category' => $category]);
    } else {
        $stmt = $pdo->query(
            'SELECT id, category, title, description, price, image_url
             FROM services
             ORDER BY category ASC, title ASC'
        );
    }

    $services = $stmt->fetchAll();

    echo json_encode([
        'success'  => true,
        'count'    => count($services),
        'services' => $services,
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve services.']);
}
?>
