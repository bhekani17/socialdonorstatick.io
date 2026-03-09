<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check authentication
if ($_GET['type'] === 'admin') {
    require_admin_login();
    $userId = $_SESSION['admin_id'];
    $userType = 'Admin';
} else {
    require_donor_login();
    $userId = $_SESSION['donor_id'];
    $userType = 'Donor';
}

try {
    $pdo = get_db_connection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handle_get_profile($pdo, $userId, $userType);
            break;
        case 'POST':
        case 'PUT':
            handle_update_profile($pdo, $userId, $userType);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Profile management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function handle_get_profile(PDO $pdo, int $userId, string $userType): void
{
    if ($userType === 'Admin') {
        $stmt = $pdo->prepare("
            SELECT id, username, full_name, surname, email, cell_number, role, 
                   permissions, verification_document, created_at, last_login
            FROM admins 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        
        if ($profile) {
            // Remove sensitive data
            unset($profile['password_hash']);
        }
    } else {
        $stmt = $pdo->prepare("
            SELECT id, username, first_name, surname, id_number, cell_number, email, 
                   blood_type, address, race, gender, emergency_contact, emergency_number,
                   profile_photo, email_verified, phone_verified, last_donation, 
                   donation_count, status, created_at
            FROM donors 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        
        if ($profile) {
            // Remove sensitive data
            unset($profile['password_hash']);
            
            // Get donation history
            $donationStmt = $pdo->prepare("
                SELECT donation_date, location, units_donated, status, notes
                FROM donations
                WHERE donor_id = ?
                ORDER BY donation_date DESC
                LIMIT 10
            ");
            $donationStmt->execute([$userId]);
            $profile['donation_history'] = $donationStmt->fetchAll();
        }
    }
    
    if ($profile) {
        echo json_encode([
            'success' => true,
            'data' => $profile
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Profile not found']);
    }
}

function handle_update_profile(PDO $pdo, int $userId, string $userType): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($userType === 'Admin') {
        update_admin_profile($pdo, $userId, $input);
    } else {
        update_donor_profile($pdo, $userId, $input);
    }
}

function update_admin_profile(PDO $pdo, int $userId, array $input): void
{
    $allowedFields = ['full_name', 'surname', 'cell_number', 'permissions'];
    $updateFields = [];
    $updateValues = [];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = ?";
            $updateValues[] = sanitize_input($input[$field]);
        }
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No valid fields to update']);
        return;
    }
    
    // Handle profile photo upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        try {
            $profilePhoto = handle_file_upload($_FILES['profile_photo'], 'profiles/');
            $updateFields[] = "profile_photo = ?";
            $updateValues[] = $profilePhoto;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Photo upload failed: ' . $e->getMessage()]);
            return;
        }
    }
    
    // Handle password change
    if (!empty($input['current_password']) && !empty($input['new_password'])) {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ?");
        $stmt->execute([$userId]);
        $admin = $stmt->fetch();
        
        if (!$admin || !password_verify($input['current_password'], $admin['password_hash'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            return;
        }
        
        // Validate new password
        if (strlen($input['new_password']) < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password must be at least 10 characters long']);
            return;
        }
        
        $updateFields[] = "password_hash = ?";
        $updateValues[] = password_hash($input['new_password'], PASSWORD_DEFAULT);
    }
    
    $updateValues[] = $userId;
    
    try {
        $sql = "UPDATE admins SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Log the action
        log_user_action($userId, 'Admin', 'Profile Updated', 'admins', $userId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("Update admin profile error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update profile']);
    }
}

function update_donor_profile(PDO $pdo, int $userId, array $input): void
{
    $allowedFields = ['first_name', 'surname', 'cell_number', 'address', 'emergency_contact', 'emergency_number'];
    $updateFields = [];
    $updateValues = [];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = ?";
            $updateValues[] = sanitize_input($input[$field]);
        }
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No valid fields to update']);
        return;
    }
    
    // Handle profile photo upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        try {
            $profilePhoto = handle_file_upload($_FILES['profile_photo'], 'profiles/');
            $updateFields[] = "profile_photo = ?";
            $updateValues[] = $profilePhoto;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Photo upload failed: ' . $e->getMessage()]);
            return;
        }
    }
    
    // Handle password change
    if (!empty($input['current_password']) && !empty($input['new_password'])) {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM donors WHERE id = ?");
        $stmt->execute([$userId]);
        $donor = $stmt->fetch();
        
        if (!$donor || !password_verify($input['current_password'], $donor['password_hash'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            return;
        }
        
        // Validate new password
        if (strlen($input['new_password']) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters long']);
            return;
        }
        
        $updateFields[] = "password_hash = ?";
        $updateValues[] = password_hash($input['new_password'], PASSWORD_DEFAULT);
    }
    
    $updateValues[] = $userId;
    
    try {
        $sql = "UPDATE donors SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Log the action
        log_user_action($userId, 'Donor', 'Profile Updated', 'donors', $userId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("Update donor profile error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update profile']);
    }
}
?>
