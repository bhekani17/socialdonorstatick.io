<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$sender = post_value('sender');
$subject = post_value('subject');
$message = post_value('message');

if ($sender === '' || $subject === '' || $message === '') {
    redirect_to('../alert%20page.html?error=missing_fields');
}

$pdo = app_pdo();
$stmt = $pdo->prepare('INSERT INTO alerts (sender, subject, message) VALUES (:sender, :subject, :message)');
$stmt->execute([
    ':sender' => $sender,
    ':subject' => $subject,
    ':message' => $message,
]);

redirect_to('../alert%20page.html?sent=1');
