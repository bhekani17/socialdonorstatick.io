<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

$adminId = require_admin_session_json();
$pdo = app_pdo();
$stmt = $pdo->prepare(
    'SELECT id, username, full_name, surname, email, role, cell_number, permissions
     FROM admins
     WHERE id = :id
     LIMIT 1'
);
$stmt->execute([':id' => $adminId]);
$admin = $stmt->fetch();

header('Content-Type: application/json; charset=utf-8');
if (!$admin) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

echo json_encode($admin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
