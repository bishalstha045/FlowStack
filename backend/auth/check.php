<?php
/**
 * FlowStack — Auth: Session Check
 * GET /backend/auth/check.php
 */
require_once __DIR__ . '/../helpers/response.php';
setApiHeaders();

if (!isset($_SESSION['user_id']) || !is_int($_SESSION['user_id'])) {
    http_response_code(200);
    echo json_encode(['authenticated' => false]);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'user' => [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['name']  ?? '',
        'email' => $_SESSION['email'] ?? '',
    ]
]);
