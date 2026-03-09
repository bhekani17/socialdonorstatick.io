<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'donor_logged_in' => isset($_SESSION['donor_id']),
    'admin_logged_in' => isset($_SESSION['admin_id']),
    'donor_name' => $_SESSION['donor_name'] ?? null,
    'admin_name' => $_SESSION['admin_name'] ?? null,
    'admin_role' => $_SESSION['admin_role'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
