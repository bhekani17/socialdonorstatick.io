<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$email = strtolower(post_value('email'));
$password = post_value('password');

if ($email === '' || $password === '') {
    redirect_to('../login%20admin.html?error=missing_credentials');
}

$pdo = app_pdo();
$stmt = $pdo->prepare('SELECT id, full_name, surname, email, role, password_hash FROM admins WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
    redirect_to('../login%20admin.html?error=invalid_credentials');
}

$_SESSION['admin_id'] = (int) $admin['id'];
$_SESSION['admin_email'] = (string) $admin['email'];
$_SESSION['admin_role'] = (string) $admin['role'];
$_SESSION['admin_name'] = trim(($admin['full_name'] ?? '') . ' ' . ($admin['surname'] ?? ''));

redirect_to('../super%20admini%20dashboard.html');
