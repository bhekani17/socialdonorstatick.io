<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$firstName = post_value('first_name');
$surname = post_value('surname');
$idNumber = post_value('id_number');
$cellNumber = post_value('cell_number');
$email = strtolower(post_value('email'));
$bloodType = strtoupper(post_value('blood_type'));
$address = post_value('address');
$race = post_value('race');
$gender = post_value('gender');
$password = post_value('password');
$username = strtolower(strtok($email, '@') ?: '');

if (
    $firstName === '' ||
    $surname === '' ||
    $idNumber === '' ||
    $cellNumber === '' ||
    $email === '' ||
    $bloodType === '' ||
    $address === '' ||
    $race === '' ||
    $gender === '' ||
    $password === ''
) {
    redirect_to('../donor%20signup.html?error=missing_fields');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_to('../donor%20signup.html?error=invalid_email');
}

if (strlen($password) < 8) {
    redirect_to('../donor%20signup.html?error=weak_password');
}

$pdo = app_pdo();
$stmt = $pdo->prepare(
    'INSERT INTO donors (username, first_name, surname, id_number, cell_number, email, blood_type, address, race, gender, password_hash)
     VALUES (:username, :first_name, :surname, :id_number, :cell_number, :email, :blood_type, :address, :race, :gender, :password_hash)'
);

try {
    $stmt->execute([
        ':username' => $username === '' ? null : $username,
        ':first_name' => $firstName,
        ':surname' => $surname,
        ':id_number' => $idNumber,
        ':cell_number' => $cellNumber,
        ':email' => $email,
        ':blood_type' => $bloodType,
        ':address' => $address,
        ':race' => $race,
        ':gender' => $gender,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        redirect_to('../donor%20signup.html?error=email_exists');
    }

    throw $e;
}

redirect_to('../donors%20login.html?signup=success');
