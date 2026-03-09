<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$adminId = require_admin_session_redirect();

$username = post_value('username');
$name = post_value('name');
$surname = post_value('surname');
$email = strtolower(post_value('email'));
$role = post_value('role');
$phone = post_value('phone');
$permissions = post_value('permissions');

if ($name === '' || $surname === '' || $email === '' || $role === '') {
    redirect_to('../administration%20profile.html?error=missing_fields');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_to('../administration%20profile.html?error=invalid_email');
}

$pdo = app_pdo();
$stmt = $pdo->prepare(
    'UPDATE admins
     SET username = :username,
         full_name = :full_name,
         surname = :surname,
         email = :email,
         role = :role,
         cell_number = :cell_number,
         permissions = :permissions
     WHERE id = :id'
);

try {
    $stmt->execute([
        ':username' => $username === '' ? null : $username,
        ':full_name' => $name,
        ':surname' => $surname,
        ':email' => $email,
        ':role' => $role,
        ':cell_number' => $phone === '' ? null : $phone,
        ':permissions' => $permissions === '' ? null : $permissions,
        ':id' => $adminId,
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        redirect_to('../administration%20profile.html?error=email_exists');
    }

    throw $e;
}

$_SESSION['admin_email'] = $email;
$_SESSION['admin_role'] = $role;
$_SESSION['admin_name'] = trim($name . ' ' . $surname);

redirect_to('../administration%20profile.html?updated=1');
