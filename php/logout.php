<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

// Get current session info for logging
$userId = $_SESSION['donor_id'] ?? $_SESSION['admin_id'] ?? null;
$userType = $_SESSION['user_type'] ?? 'Unknown';

// Log the logout action
if ($userId) {
    log_user_action($userId, $userType, 'Logout', null, $userId);
}

// Remove session from database
if (isset($_SESSION['donor_id']) || isset($_SESSION['admin_id'])) {
    try {
        $pdo = get_db_connection();
        $sessionId = session_id();
        
        $stmt = $pdo->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        
    } catch (PDOException $e) {
        error_log("Logout session cleanup error: " . $e->getMessage());
    }
}

// Destroy all session data
$_SESSION = [];
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to appropriate login page
if ($userType === 'Admin') {
    header('Location: ../login admin.html?logout=success');
} else {
    header('Location: ../donors login.html?logout=success');
}
exit;
