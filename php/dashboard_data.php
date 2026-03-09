<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

$pdo = app_pdo();

$donorCount = (int) $pdo->query('SELECT COUNT(*) FROM donors')->fetchColumn();
$adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
$alertCount = (int) $pdo->query('SELECT COUNT(*) FROM alerts')->fetchColumn();
$requestCount = (int) $pdo->query('SELECT COUNT(*) FROM urgent_places')->fetchColumn();

$recentStmt = $pdo->query(
    'SELECT first_name, surname, blood_type, COALESCE(address, "") AS address, created_at
     FROM donors
     ORDER BY id DESC
     LIMIT 5'
);

$recentDonors = [];
foreach ($recentStmt->fetchAll() as $row) {
    $recentDonors[] = [
        'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? '')),
        'blood_type' => (string) ($row['blood_type'] ?? ''),
        'location' => (string) ($row['address'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'requests' => $requestCount,
    'received' => $alertCount,
    'stock' => max($donorCount * 3, 0),
    'visitors' => max($donorCount * 5, 100),
    'new_donors' => $donorCount,
    'admins' => $adminCount,
    'new_requests' => $requestCount,
    'recent_donors' => $recentDonors,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
