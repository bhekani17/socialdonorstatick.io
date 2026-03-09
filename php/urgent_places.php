<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = app_pdo();
$stmt = $pdo->query('SELECT id, name, area, blood, urgency, lat, lng, updated_at FROM urgent_places ORDER BY CASE urgency WHEN "Critical" THEN 1 WHEN "High" THEN 2 ELSE 3 END, name');
$rows = $stmt->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
