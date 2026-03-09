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
    header('Location: ../donors login.html');
    exit;
}

// Get and sanitize form data
$email = strtolower(sanitize_input($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

// Validation
if (empty($email) || empty($password)) {
    $_SESSION['error'] = 'Email and password are required';
    header('Location: ../donors login.html');
    exit;
}

if (!validate_email($email)) {
    $_SESSION['error'] = 'Invalid email format';
    header('Location: ../donors login.html');
    exit;
}

// Check login attempts
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = 0;
}

if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
    if (time() - $_SESSION['last_attempt'] < LOGIN_LOCKOUT_TIME) {
        $_SESSION['error'] = 'Too many login attempts. Please try again in ' . 
                          ceil((LOGIN_LOCKOUT_TIME - (time() - $_SESSION['last_attempt'])) / 60) . ' minutes.';
        header('Location: ../donors login.html');
        exit;
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

try {
    $pdo = get_db_connection();
    
    // Get donor by email
    $stmt = $pdo->prepare("
        SELECT id, username, first_name, surname, email, blood_type, password_hash, status 
        FROM donors 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $donor = $stmt->fetch();
    
    if (!$donor) {
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt'] = time();
        $_SESSION['error'] = 'Invalid email or password';
        header('Location: ../donors login.html');
        exit;
    }
    
    // Check account status
    if ($donor['status'] !== 'Active') {
        $_SESSION['error'] = 'Your account is not active. Please contact support.';
        header('Location: ../donors login.html');
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $donor['password_hash'])) {
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt'] = time();
        $_SESSION['error'] = 'Invalid email or password';
        header('Location: ../donors login.html');
        exit;
    }
    
    // Password is correct, reset login attempts
    $_SESSION['login_attempts'] = 0;
    
    // Create secure session
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['donor_id'] = $donor['id'];
    $_SESSION['donor_username'] = $donor['username'];
    $_SESSION['donor_name'] = $donor['first_name'] . ' ' . $donor['surname'];
    $_SESSION['donor_email'] = $donor['email'];
    $_SESSION['donor_blood_type'] = $donor['blood_type'];
    $_SESSION['user_type'] = 'donor';
    $_SESSION['login_time'] = time();
    
    // Store session in database for enhanced security
    $sessionId = session_id();
    $stmt = $pdo->prepare("
        INSERT INTO user_sessions (session_id, user_id, user_type, ip_address, user_agent, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $sessionId,
        $donor['id'],
        'Donor',
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        date('Y-m-d H:i:s', time() + SESSION_LIFETIME)
    ]);
    
    // Log the login
    log_user_action($donor['id'], 'Donor', 'Login Successful', 'donors', $donor['id']);
    
    // Redirect to dashboard
    $_SESSION['success'] = 'Welcome back, ' . $donor['first_name'] . '!';
    header('Location: ../public dashboard.html');
    exit;
    
} catch (PDOException $e) {
    error_log("Donor login error: " . $e->getMessage());
    $_SESSION['error'] = 'Login failed. Please try again later.';
    header('Location: ../donors login.html');
    exit;
}
?>
