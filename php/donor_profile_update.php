<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$donorId = require_donor_session_redirect();

$username = post_value('username');
$firstName = post_value('first_name');
$surname = post_value('surname');
$email = strtolower(post_value('email'));
$contact = post_value('emergency_contact');
$contactNumber = post_value('emergency_number');

if ($firstName === '' || $surname === '' || $email === '') {
    redirect_to('../donors%20profile.html?error=missing_fields');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_to('../donors%20profile.html?error=invalid_email');
}

$pdo = app_pdo();
$stmt = $pdo->prepare(
    'UPDATE donors
     SET username = :username,
         first_name = :first_name,
         surname = :surname,
         email = :email,
         emergency_contact = :emergency_contact,
         emergency_number = :emergency_number
     WHERE id = :id'
);

try {
    $stmt->execute([
        ':username' => $username === '' ? null : $username,
        ':first_name' => $firstName,
        ':surname' => $surname,
        ':email' => $email,
        ':emergency_contact' => $contact === '' ? null : $contact,
        ':emergency_number' => $contactNumber === '' ? null : $contactNumber,
        ':id' => $donorId,
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        redirect_to('../donors%20profile.html?error=email_exists');
    }

    throw $e;
}

$_SESSION['donor_email'] = $email;
$_SESSION['donor_name'] = trim($firstName . ' ' . $surname);

redirect_to('../donors%20profile.html?updated=1');
