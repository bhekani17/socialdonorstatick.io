<?php
declare(strict_types=1);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'socialdonor');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Security Settings
define('HASH_COST', 12);
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Application Settings
define('APP_NAME', 'Social Donor');
define('APP_URL', 'http://localhost/socialdonorstatick.io');
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Email Settings (configure these)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('FROM_EMAIL', 'noreply@socialdonor.org');
define('FROM_NAME', 'Social Donor Platform');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

// Timezone
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Africa/Johannesburg');
}

// Initialize session only if not already started
function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Secure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        session_start();
        
        // Regenerate session ID to prevent session fixation
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
        
        // Check session expiration
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            session_destroy();
            header('Location: ../index.html');
            exit;
        }
        
        $_SESSION['last_activity'] = time();
    }
}

// Create database connection
function get_db_connection(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Set charset after connection
            $pdo->exec("SET NAMES utf8mb4");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check configuration.");
        }
    }
    
    return $pdo;
}

// Security functions
function sanitize_input(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_phone(string $phone): bool
{
    // South African phone number validation
    return preg_match('/^(\+27|0)[6-8][0-9]{8}$/', $phone);
}

function validate_id_number(string $id): bool
{
    // South African ID number validation (basic)
    return preg_match('/^[0-9]{13}$/', $id);
}

function generate_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        secure_session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function log_user_action(int $userId, string $userType, string $action, ?string $tableName = null, ?int $recordId = null): void
{
    try {
        $pdo = get_db_connection();
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, user_type, action, table_name, record_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $userType,
            $action,
            $tableName,
            $recordId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log user action: " . $e->getMessage());
    }
}

// Check if user is logged in
function is_donor_logged_in(): bool
{
    return isset($_SESSION['donor_id']) && !empty($_SESSION['donor_id']);
}

function is_admin_logged_in(): bool
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Redirect if not logged in
function require_donor_login(): void
{
    if (!is_donor_logged_in()) {
        $_SESSION['error'] = 'Please login to access this page.';
        header('Location: ../donors login.html');
        exit;
    }
}

function require_admin_login(): void
{
    if (!is_admin_logged_in()) {
        $_SESSION['error'] = 'Please login to access admin panel.';
        header('Location: ../login admin.html');
        exit;
    }
}

// File upload helper
function handle_file_upload(array $file, string $destinationFolder): ?string
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size exceeds maximum allowed size.');
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
    }
    
    // Create destination folder if it doesn't exist
    $fullPath = UPLOAD_PATH . $destinationFolder;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('upload_', true) . '.' . $extension;
    $destination = $fullPath . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $destinationFolder . $filename;
    }
    
    return null;
}

// Email helper function
function send_email(string $to, string $subject, string $message): bool
{
    $headers = [
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

// Initialize session
secure_session_start();
?>
