<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

$donorId = require_donor_session_json();
$pdo = app_pdo();
$stmt = $pdo->prepare(
    'SELECT id, username, first_name, surname, email, emergency_contact, emergency_number, blood_type
     FROM donors
     WHERE id = :id
     LIMIT 1'
);
$stmt->execute([':id' => $donorId]);
$donor = $stmt->fetch();

header('Content-Type: application/json; charset=utf-8');
if (!$donor) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

echo json_encode($donor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
