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
    redirect_to('../donors%20login.html?error=missing_credentials');
}

$pdo = app_pdo();
$stmt = $pdo->prepare('SELECT id, first_name, surname, email, password_hash FROM donors WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$donor = $stmt->fetch();

if (!$donor || !password_verify($password, (string) $donor['password_hash'])) {
    redirect_to('../donors%20login.html?error=invalid_credentials');
}

$_SESSION['donor_id'] = (int) $donor['id'];
$_SESSION['donor_email'] = (string) $donor['email'];
$_SESSION['donor_name'] = trim(($donor['first_name'] ?? '') . ' ' . ($donor['surname'] ?? ''));

redirect_to('../public%20dashboard.html');
