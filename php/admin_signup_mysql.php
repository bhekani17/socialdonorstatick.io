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
    header('Location: ../super admin.html');
    exit;
}

// Get and sanitize form data
$fullName = sanitize_input($_POST['full_name'] ?? '');
$surname = sanitize_input($_POST['surname'] ?? '');
$dob = sanitize_input($_POST['dob'] ?? '');
$id = sanitize_input($_POST['id'] ?? '');
$cellNumber = sanitize_input($_POST['cell_number'] ?? '');
$email = strtolower(sanitize_input($_POST['email'] ?? ''));
$bloodType = strtoupper(sanitize_input($_POST['blood'] ?? ''));
$address = sanitize_input($_POST['address'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$username = strtolower(strtok($email, '@') ?: '');

// Validation
$errors = [];

if (empty($fullName)) $errors[] = 'Full name is required';
if (empty($surname)) $errors[] = 'Surname is required';
if (empty($dob)) $errors[] = 'Date of birth is required';
if (empty($id)) $errors[] = 'ID number is required';
if (empty($cellNumber)) $errors[] = 'Cell number is required';
if (empty($email)) $errors[] = 'Email is required';
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
if (!validate_id_number($id)) {
    $errors[] = 'Invalid South African ID number format';
}

// Password validation
if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match';
}
if (strlen($password) < 10) {
    $errors[] = 'Password must be at least 10 characters long';
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
if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    $errors[] = 'Password must contain at least one special character';
}

// Date of birth validation (must be at least 18 years old)
$minAge = date('Y-m-d', strtotime('-18 years'));
if ($dob > $minAge) {
    $errors[] = 'Admin must be at least 18 years old';
}

// Blood type validation (optional)
if (!empty($bloodType)) {
    $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    if (!in_array($bloodType, $validBloodTypes)) {
        $errors[] = 'Invalid blood type';
    }
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(', ', $errors);
    $_SESSION['form_data'] = $_POST;
    header('Location: ../super admin.html');
    exit;
}

try {
    $pdo = get_db_connection();
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Email address already registered';
        header('Location: ../super admin.html');
        exit;
    }
    
    // Handle verification document upload if present
    $verificationDoc = null;
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        try {
            $verificationDoc = handle_file_upload($_FILES['document'], 'documents/');
        } catch (Exception $e) {
            $_SESSION['error'] = 'Document upload failed: ' . $e->getMessage();
            header('Location: ../super admin.html');
            exit;
        }
    }
    
    // Insert new admin
    $stmt = $pdo->prepare("
        INSERT INTO admins (
            username, full_name, surname, email, cell_number, role, 
            permissions, verification_document, password_hash
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $username,
        $fullName,
        $surname,
        $email,
        $cellNumber,
        'Admin',
        'Can manage donor records, view requests, and issue alerts.',
        $verificationDoc,
        password_hash($password, PASSWORD_DEFAULT)
    ]);
    
    $adminId = $pdo->lastInsertId();
    
    // Log the action
    log_user_action($adminId, 'Admin', 'Admin Account Created', 'admins', $adminId);
    
    // Send notification email to system administrator
    $notificationMessage = "
        <h2>New Admin Registration</h2>
        <p>A new administrator has registered on the Social Donor platform:</p>
        <ul>
            <li><strong>Name:</strong> {$fullName} {$surname}</li>
            <li><strong>Email:</strong> {$email}</li>
            <li><strong>Cell:</strong> {$cellNumber}</li>
            <li><strong>Registration Date:</strong> " . date('Y-m-d H:i:s') . "</li>
        </ul>
        <p>Please review and approve this admin account if necessary.</p>
        <p><a href='" . APP_URL . "/login%20admin.html'>Admin Login</a></p>
    ";
    
    send_email(FROM_EMAIL, 'New Admin Registration - Social Donor', $notificationMessage);
    
    // Send confirmation email to new admin
    $welcomeMessage = "
        <h2>Welcome to Social Donor Admin Panel</h2>
        <p>Dear {$fullName},</p>
        <p>Your administrator account has been successfully created on the Social Donor platform.</p>
        <p><strong>Your Account Details:</strong></p>
        <ul>
            <li>Email: {$email}</li>
            <li>Role: Administrator</li>
        </ul>
        <p>You can now access the admin dashboard to manage donor records and blood requests.</p>
        <p><a href='" . APP_URL . "/login%20admin.html'>Access Admin Dashboard</a></p>
        <p>Thank you for joining our team!</p>
        <p><em>The Social Donor Team</em></p>
    ";
    
    send_email($email, 'Admin Account Created - Social Donor', $welcomeMessage);
    
    $_SESSION['success'] = 'Admin account created successfully! Please check your email for confirmation.';
    header('Location: ../login admin.html?signup=success');
    exit;
    
} catch (PDOException $e) {
    error_log("Admin signup error: " . $e->getMessage());
    $_SESSION['error'] = 'Registration failed. Please try again later.';
    header('Location: ../super admin.html');
    exit;
}
?>
