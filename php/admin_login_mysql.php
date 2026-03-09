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
    header('Location: ../login admin.html');
    exit;
}

// Get and sanitize form data
$email = strtolower(sanitize_input($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

// Validation
if (empty($email) || empty($password)) {
    $_SESSION['error'] = 'Email and password are required';
    header('Location: ../login admin.html');
    exit;
}

if (!validate_email($email)) {
    $_SESSION['error'] = 'Invalid email format';
    header('Location: ../login admin.html');
    exit;
}

// Check login attempts
if (!isset($_SESSION['admin_login_attempts'])) {
    $_SESSION['admin_login_attempts'] = 0;
    $_SESSION['admin_last_attempt'] = 0;
}

if ($_SESSION['admin_login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
    if (time() - $_SESSION['admin_last_attempt'] < LOGIN_LOCKOUT_TIME) {
        $_SESSION['error'] = 'Too many login attempts. Please try again in ' . 
                          ceil((LOGIN_LOCKOUT_TIME - (time() - $_SESSION['admin_last_attempt'])) / 60) . ' minutes.';
        header('Location: ../login admin.html');
        exit;
    } else {
        $_SESSION['admin_login_attempts'] = 0;
    }
}

try {
    $pdo = get_db_connection();
    
    // Get admin by email
    $stmt = $pdo->prepare("
        SELECT id, username, full_name, surname, email, role, permissions, is_active, password_hash 
        FROM admins 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        $_SESSION['admin_login_attempts']++;
        $_SESSION['admin_last_attempt'] = time();
        $_SESSION['error'] = 'Invalid email or password';
        header('Location: ../login admin.html');
        exit;
    }
    
    // Check if admin is active
    if (!$admin['is_active']) {
        $_SESSION['error'] = 'Your admin account is not active. Please contact system administrator.';
        header('Location: ../login admin.html');
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_login_attempts']++;
        $_SESSION['admin_last_attempt'] = time();
        $_SESSION['error'] = 'Invalid email or password';
        header('Location: ../login admin.html');
        exit;
    }
    
    // Password is correct, reset login attempts
    $_SESSION['admin_login_attempts'] = 0;
    
    // Create secure session
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_name'] = $admin['full_name'] . ' ' . $admin['surname'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['admin_permissions'] = $admin['permissions'];
    $_SESSION['user_type'] = 'admin';
    $_SESSION['login_time'] = time();
    
    // Store session in database for enhanced security
    $sessionId = session_id();
    $stmt = $pdo->prepare("
        INSERT INTO user_sessions (session_id, user_id, user_type, ip_address, user_agent, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $sessionId,
        $admin['id'],
        'Admin',
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        date('Y-m-d H:i:s', time() + SESSION_LIFETIME)
    ]);
    
    // Update last login time
    $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$admin['id']]);
    
    // Log the login
    log_user_action($admin['id'], 'Admin', 'Admin Login Successful', 'admins', $admin['id']);
    
    // Redirect based on role
    if ($admin['role'] === 'Super Admin') {
        $_SESSION['success'] = 'Welcome back, Super Admin ' . $admin['full_name'] . '!';
        header('Location: ../super admini dashboard.html');
    } else {
        $_SESSION['success'] = 'Welcome back, ' . $admin['full_name'] . '!';
        header('Location: ../administration profile.html');
    }
    exit;
    
} catch (PDOException $e) {
    error_log("Admin login error: " . $e->getMessage());
    $_SESSION['error'] = 'Login failed. Please try again later.';
    header('Location: ../login admin.html');
    exit;
}
?>
