<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check authentication
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    // Only admins can create/update/delete alerts
    require_admin_login();
    $userId = $_SESSION['admin_id'];
} else {
    // Both donors and admins can view alerts
    if (!is_donor_logged_in() && !is_admin_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }
    $userId = $_SESSION['donor_id'] ?? $_SESSION['admin_id'];
}

try {
    $pdo = get_db_connection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handle_get_alerts($pdo);
            break;
        case 'POST':
            handle_create_alert($pdo);
            break;
        case 'PUT':
            handle_update_alert($pdo);
            break;
        case 'DELETE':
            handle_delete_alert($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Alerts API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function handle_get_alerts(PDO $pdo): void
{
    $isAdmin = is_admin_logged_in();
    
    if ($isAdmin) {
        // Admins see all alerts
        $stmt = $pdo->prepare("
            SELECT a.*, ad.full_name as sender_name
            FROM alerts a
            LEFT JOIN admins ad ON a.sender_id = ad.id
            ORDER BY a.created_at DESC
        ");
        $stmt->execute();
        $alerts = $stmt->fetchAll();
    } else {
        // Donors see targeted alerts based on their profile
        $donorId = $_SESSION['donor_id'];
        $bloodType = $_SESSION['donor_blood_type'];
        
        $stmt = $pdo->prepare("
            SELECT a.*
            FROM alerts a
            WHERE a.is_active = 1
            AND (
                a.target_group = 'All Donors'
                OR (a.target_group = 'Specific Blood Type' AND a.target_criteria = ?)
                OR (a.target_group = 'Location Based' AND a.id IN (
                    SELECT alert_id FROM alert_location_targets 
                    WHERE location IN (SELECT address FROM donors WHERE id = ?)
                ))
            )
            ORDER BY a.is_urgent DESC, a.created_at DESC
        ");
        $stmt->execute([$bloodType, $donorId]);
        $alerts = $stmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $alerts
    ]);
}

function handle_create_alert(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['subject', 'message', 'target_group'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate target group
    $validTargetGroups = ['All Donors', 'Specific Blood Type', 'Location Based', 'All Admins'];
    if (!in_array($input['target_group'], $validTargetGroups)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid target group']);
        return;
    }
    
    // Validate target criteria for specific groups
    if ($input['target_group'] === 'Specific Blood Type') {
        $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        if (empty($input['target_criteria']) || !in_array($input['target_criteria'], $validBloodTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid blood type required for this target group']);
            return;
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO alerts (
                sender_id, subject, message, target_group, target_criteria, is_urgent, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['admin_id'],
            $input['subject'],
            $input['message'],
            $input['target_group'],
            $input['target_criteria'] ?? null,
            $input['is_urgent'] ?? false,
            $input['is_active'] ?? true
        ]);
        
        $alertId = $pdo->lastInsertId();
        
        // Log the action
        log_user_action($_SESSION['admin_id'], 'Admin', 'Alert Created', 'alerts', $alertId);
        
        // Send notifications to targeted users
        send_alert_notifications($pdo, $alertId, $input['target_group'], $input['target_criteria'] ?? null);
        
        echo json_encode([
            'success' => true,
            'message' => 'Alert created successfully',
            'alert_id' => $alertId
        ]);
        
    } catch (PDOException $e) {
        error_log("Create alert error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create alert']);
    }
}

function handle_update_alert(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['alert_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Alert ID is required']);
        return;
    }
    
    try {
        // Check if alert exists
        $stmt = $pdo->prepare("SELECT id FROM alerts WHERE id = ?");
        $stmt->execute([$input['alert_id']]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Alert not found']);
            return;
        }
        
        // Build update query
        $updateFields = [];
        $updateValues = [];
        
        if (isset($input['subject'])) {
            $updateFields[] = "subject = ?";
            $updateValues[] = sanitize_input($input['subject']);
        }
        
        if (isset($input['message'])) {
            $updateFields[] = "message = ?";
            $updateValues[] = sanitize_input($input['message']);
        }
        
        if (isset($input['is_urgent'])) {
            $updateFields[] = "is_urgent = ?";
            $updateValues[] = (bool) $input['is_urgent'];
        }
        
        if (isset($input['is_active'])) {
            $updateFields[] = "is_active = ?";
            $updateValues[] = (bool) $input['is_active'];
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No valid fields to update']);
            return;
        }
        
        $updateValues[] = $input['alert_id'];
        
        $sql = "UPDATE alerts SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Log the action
        log_user_action($_SESSION['admin_id'], 'Admin', 'Alert Updated', 'alerts', $input['alert_id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Alert updated successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("Update alert error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update alert']);
    }
}

function handle_delete_alert(PDO $pdo): void
{
    $alertId = $_GET['id'] ?? null;
    
    if (!$alertId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Alert ID is required']);
        return;
    }
    
    try {
        // Check if alert exists
        $stmt = $pdo->prepare("SELECT id FROM alerts WHERE id = ?");
        $stmt->execute([$alertId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Alert not found']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM alerts WHERE id = ?");
        $stmt->execute([$alertId]);
        
        // Log the action
        log_user_action($_SESSION['admin_id'], 'Admin', 'Alert Deleted', 'alerts', $alertId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Alert deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("Delete alert error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete alert']);
    }
}

function send_alert_notifications(PDO $pdo, int $alertId, string $targetGroup, ?string $targetCriteria): void
{
    try {
        // Get alert details
        $stmt = $pdo->prepare("SELECT * FROM alerts WHERE id = ?");
        $stmt->execute([$alertId]);
        $alert = $stmt->fetch();
        
        if (!$alert) return;
        
        $recipients = [];
        
        switch ($targetGroup) {
            case 'All Donors':
                $stmt = $pdo->prepare("SELECT email, first_name FROM donors WHERE status = 'Active' AND email_verified = 1");
                $stmt->execute();
                $recipients = $stmt->fetchAll();
                break;
                
            case 'Specific Blood Type':
                $stmt = $pdo->prepare("SELECT email, first_name FROM donors WHERE blood_type = ? AND status = 'Active' AND email_verified = 1");
                $stmt->execute([$targetCriteria]);
                $recipients = $stmt->fetchAll();
                break;
                
            case 'Location Based':
                // This would require more complex location matching logic
                // For now, we'll skip location-based notifications
                break;
                
            case 'All Admins':
                $stmt = $pdo->prepare("SELECT email, full_name FROM admins WHERE is_active = 1");
                $stmt->execute();
                $recipients = $stmt->fetchAll();
                break;
        }
        
        foreach ($recipients as $recipient) {
            $name = $recipient['first_name'] ?? $recipient['full_name'];
            $message = "
                <h2>" . ($alert['is_urgent'] ? '🚨 URGENT:' : '📢') . " {$alert['subject']}</h2>
                <p>Dear {$name},</p>
                <div>{$alert['message']}</div>
                <hr>
                <p><small>This is an automated message from the Social Donor platform.</small></p>
            ";
            
            send_email($recipient['email'], $alert['subject'], $message);
        }
        
        // Update sent_at timestamp
        $stmt = $pdo->prepare("UPDATE alerts SET sent_at = NOW() WHERE id = ?");
        $stmt->execute([$alertId]);
        
    } catch (PDOException $e) {
        error_log("Error sending alert notifications: " . $e->getMessage());
    }
}
?>
