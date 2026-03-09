<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function require_donor_session_json(): int
{
    $donorId = (int) ($_SESSION['donor_id'] ?? 0);
    if ($donorId <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }

    return $donorId;
}

function require_admin_session_json(): int
{
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }

    return $adminId;
}

function require_donor_session_redirect(): int
{
    $donorId = (int) ($_SESSION['donor_id'] ?? 0);
    if ($donorId <= 0) {
        redirect_to('../donors%20login.html?error=login_required');
    }

    return $donorId;
}

function require_admin_session_redirect(): int
{
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0) {
        redirect_to('../login%20admin.html?error=login_required');
    }

    return $adminId;
}
