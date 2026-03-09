<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    $_SESSION['error'] = 'Security token expired. Please try again.';
    header('Location: ../donor signup.html');
    exit;
}

// Get and sanitize form data
$firstName = sanitize_input($_POST['first_name'] ?? '');
$surname = sanitize_input($_POST['surname'] ?? '');
$idNumber = sanitize_input($_POST['id_number'] ?? '');
$cellNumber = sanitize_input($_POST['cell_number'] ?? '');
$email = strtolower(sanitize_input($_POST['email'] ?? ''));
$bloodType = strtoupper(sanitize_input($_POST['blood_type'] ?? ''));
$address = sanitize_input($_POST['address'] ?? '');
$race = sanitize_input($_POST['race'] ?? '');
$gender = sanitize_input($_POST['gender'] ?? '');
$password = $_POST['password'] ?? '';
$username = strtolower(strtok($email, '@') ?: '');

// Validation
$errors = [];

if (empty($firstName)) $errors[] = 'First name is required';
if (empty($surname)) $errors[] = 'Surname is required';
if (empty($idNumber)) $errors[] = 'ID number is required';
if (empty($cellNumber)) $errors[] = 'Cell number is required';
if (empty($email)) $errors[] = 'Email is required';
if (empty($bloodType)) $errors[] = 'Blood type is required';
if (empty($address)) $errors[] = 'Address is required';
if (empty($race)) $errors[] = 'Race is required';
if (empty($gender)) $errors[] = 'Gender is required';
if (empty($password)) $errors[] = 'Password is required';

// Email validation
if (!validate_email($email)) {
    $errors[] = 'Invalid email format';
}

// Phone validation
if (!validate_phone($cellNumber)) {
    $errors[] = 'Invalid South African phone number format';
}

// ID number validation
if (!validate_id_number($idNumber)) {
    $errors[] = 'Invalid South African ID number format';
}

// Password strength validation
if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long';
}
if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = 'Password must contain at least one uppercase letter';
}
if (!preg_match('/[a-z]/', $password)) {
    $errors[] = 'Password must contain at least one lowercase letter';
}
if (!preg_match('/[0-9]/', $password)) {
    $errors[] = 'Password must contain at least one number';
}

// Blood type validation
$validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
if (!in_array($bloodType, $validBloodTypes)) {
    $errors[] = 'Invalid blood type';
}

// Race validation
$validRaces = ['Black African', 'White', 'Coloured', 'Indian/Asian'];
if (!in_array($race, $validRaces)) {
    $errors[] = 'Invalid race selection';
}

// Gender validation
$validGenders = ['Male', 'Female', 'Other'];
if (!in_array($gender, $validGenders)) {
    $errors[] = 'Invalid gender selection';
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(', ', $errors);
    $_SESSION['form_data'] = $_POST;
    header('Location: ../donor signup.html');
    exit;
}

try {
    $pdo = get_db_connection();
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM donors WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Email address already registered';
        header('Location: ../donor signup.html');
        exit;
    }
    
    // Check if ID number already exists
    $stmt = $pdo->prepare("SELECT id FROM donors WHERE id_number = ?");
    $stmt->execute([$idNumber]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'ID number already registered';
        header('Location: ../donor signup.html');
        exit;
    }
    
    // Handle profile photo upload if present
    $profilePhoto = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        try {
            $profilePhoto = handle_file_upload($_FILES['profile_photo'], 'profiles/');
        } catch (Exception $e) {
            $_SESSION['error'] = 'Profile photo upload failed: ' . $e->getMessage();
            header('Location: ../donor signup.html');
            exit;
        }
    }
    
    // Insert new donor
    $stmt = $pdo->prepare("
        INSERT INTO donors (
            username, first_name, surname, id_number, cell_number, email, 
            blood_type, address, race, gender, profile_photo, password_hash
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $username,
        $firstName,
        $surname,
        $idNumber,
        $cellNumber,
        $email,
        $bloodType,
        $address,
        $race,
        $gender,
        $profilePhoto,
        password_hash($password, PASSWORD_DEFAULT)
    ]);
    
    $donorId = $pdo->lastInsertId();
    
    // Log the action
    log_user_action($donorId, 'Donor', 'Account Created', 'donors', $donorId);
    
    // Send welcome email
    $welcomeMessage = "
        <h2>Welcome to Social Donor, {$firstName}!</h2>
        <p>Thank you for registering as a blood donor. Your registration has been successfully processed.</p>
        <p><strong>Your Details:</strong></p>
        <ul>
            <li>Blood Type: {$bloodType}</li>
            <li>Contact: {$cellNumber}</li>
            <li>Email: {$email}</li>
        </ul>
        <p>You can now login to your donor dashboard to view donation opportunities and manage your profile.</p>
        <p><a href='" . APP_URL . "/donors%20login.html'>Login to Your Account</a></p>
        <p>Thank you for being a life saver!</p>
        <p><em>The Social Donor Team</em></p>
    ";
    
    send_email($email, 'Welcome to Social Donor', $welcomeMessage);
    
    // Update dashboard stats
    $stmt = $pdo->prepare("UPDATE dashboard_stats SET active_donors = active_donors + 1");
    $stmt->execute();
    
    $_SESSION['success'] = 'Registration successful! Please check your email for confirmation.';
    header('Location: ../donors login.html?signup=success');
    exit;
    
} catch (PDOException $e) {
    error_log("Donor signup error: " . $e->getMessage());
    $_SESSION['error'] = 'Registration failed. Please try again later.';
    header('Location: ../donor signup.html');
    exit;
}
?>
