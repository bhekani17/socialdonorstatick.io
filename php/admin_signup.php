<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$fullName = post_value('full_name');
$surname = post_value('surname');
$email = strtolower(post_value('email'));
$cell = post_value('cell_number');
$password = post_value('password');
$confirm = post_value('confirm_password');
$username = strtolower(strtok($email, '@') ?: '');

if ($fullName === '' || $surname === '' || $email === '' || $password === '' || $confirm === '') {
    redirect_to('../super%20admin.html?error=missing_fields');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_to('../super%20admin.html?error=invalid_email');
}

if (strlen($password) < 10) {
    redirect_to('../super%20admin.html?error=weak_password');
}

if (!hash_equals($password, $confirm)) {
    redirect_to('../super%20admin.html?error=password_mismatch');
}

$pdo = app_pdo();
$stmt = $pdo->prepare(
    'INSERT INTO admins (username, full_name, surname, email, cell_number, role, permissions, password_hash)
     VALUES (:username, :full_name, :surname, :email, :cell_number, :role, :permissions, :password_hash)'
);

try {
    $stmt->execute([
        ':username' => $username === '' ? null : $username,
        ':full_name' => $fullName,
        ':surname' => $surname,
        ':email' => $email,
        ':cell_number' => $cell,
        ':role' => 'Admin',
        ':permissions' => 'Can approve donor records, issue urgent alerts, and view request analytics.',
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        redirect_to('../super%20admin.html?error=email_exists');
    }

    throw $e;
}

redirect_to('../login%20admin.html?signup=success');
