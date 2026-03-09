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

// Check authentication (allow both donors and admins)
if (!is_donor_logged_in() && !is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    $pdo = get_db_connection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handle_get_requests($pdo);
            break;
        case 'POST':
            handle_create_request($pdo);
            break;
        case 'PUT':
            handle_update_request($pdo);
            break;
        case 'DELETE':
            handle_delete_request($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Blood requests API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function handle_get_requests(PDO $pdo): void
{
    $isAdmin = is_admin_logged_in();
    $donorId = $_SESSION['donor_id'] ?? null;
    
    if ($isAdmin) {
        // Admins can see all requests
        $stmt = $pdo->prepare("
            SELECT br.*, d.first_name as donor_first_name, d.surname as donor_surname, d.cell_number as donor_contact
            FROM blood_requests br
            LEFT JOIN donors d ON br.assigned_donor_id = d.id
            ORDER BY br.urgency DESC, br.created_at DESC
        ");
        $stmt->execute();
        $requests = $stmt->fetchAll();
    } else {
        // Donors see requests matching their blood type
        $userBloodType = $_SESSION['donor_blood_type'];
        $stmt = $pdo->prepare("
            SELECT id, patient_name, patient_surname, hospital_name, location, blood_type, 
                   units_needed, urgency, medical_reason, created_at
            FROM blood_requests
            WHERE blood_type = ? AND status IN ('Pending', 'In Progress')
            ORDER BY urgency DESC, created_at DESC
        ");
        $stmt->execute([$userBloodType]);
        $requests = $stmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $requests
    ]);
}

function handle_create_request(PDO $pdo): void
{
    // Only admins can create requests
    if (!is_admin_logged_in()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['patient_name', 'patient_surname', 'hospital_name', 'location', 'blood_type', 'units_needed', 'urgency', 'contact_person', 'contact_number'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate blood type
    $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    if (!in_array($input['blood_type'], $validBloodTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid blood type']);
        return;
    }
    
    // Validate urgency
    $validUrgency = ['Critical', 'High', 'Moderate', 'Low'];
    if (!in_array($input['urgency'], $validUrgency)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid urgency level']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO blood_requests (
                patient_name, patient_surname, hospital_name, location, blood_type,
                units_needed, urgency, contact_person, contact_number, medical_reason,
                requested_by, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        
        $stmt->execute([
            $input['patient_name'],
            $input['patient_surname'],
            $input['hospital_name'],
            $input['location'],
            $input['blood_type'],
            (int) $input['units_needed'],
            $input['urgency'],
            $input['contact_person'],
            $input['contact_number'],
            $input['medical_reason'] ?? null,
            $_SESSION['admin_id']
        ]);
        
        $requestId = $pdo->lastInsertId();
        
        // Log the action
        log_user_action($_SESSION['admin_id'], 'Admin', 'Blood Request Created', 'blood_requests', $requestId);
        
        // Update dashboard stats
        $stmt = $pdo->prepare("UPDATE dashboard_stats SET total_requests = total_requests + 1, pending_requests = pending_requests + 1");
        $stmt->execute();
        
        // Notify matching donors
        notify_matching_donors($pdo, $input['blood_type'], $requestId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Blood request created successfully',
            'request_id' => $requestId
        ]);
        
    } catch (PDOException $e) {
        error_log("Create blood request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create blood request']);
    }
}

function handle_update_request(PDO $pdo): void
{
    // Only admins can update requests
    if (!is_admin_logged_in()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['request_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID is required']);
        return;
    }
    
    try {
        // Get current request status
        $stmt = $pdo->prepare("SELECT status, assigned_donor_id FROM blood_requests WHERE id = ?");
        $stmt->execute([$input['request_id']]);
        $current = $stmt->fetch();
        
        if (!$current) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Blood request not found']);
            return;
        }
        
        // Build update query
        $updateFields = [];
        $updateValues = [];
        
        if (isset($input['status'])) {
            $validStatuses = ['Pending', 'In Progress', 'Fulfilled', 'Cancelled'];
            if (!in_array($input['status'], $validStatuses)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid status']);
                return;
            }
            $updateFields[] = "status = ?";
            $updateValues[] = $input['status'];
            
            // If status is changing to Fulfilled, update fulfilled_at
            if ($input['status'] === 'Fulfilled' && $current['status'] !== 'Fulfilled') {
                $updateFields[] = "fulfilled_at = NOW()";
            }
        }
        
        if (isset($input['assigned_donor_id'])) {
            $updateFields[] = "assigned_donor_id = ?";
            $updateValues[] = $input['assigned_donor_id'];
        }
        
        if (isset($input['notes'])) {
            $updateFields[] = "notes = ?";
            $updateValues[] = $input['notes'];
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No valid fields to update']);
            return;
        }
        
        $updateValues[] = $input['request_id'];
        
        $sql = "UPDATE blood_requests SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Log the action
        log_user_action($_SESSION['admin_id'], 'Admin', 'Blood Request Updated', 'blood_requests', $input['request_id']);
        
        // Update dashboard stats if status changed
        if (isset($input['status'])) {
            update_dashboard_stats($pdo, $current['status'], $input['status']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Blood request updated successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("Update blood request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update blood request']);
    }
}

function handle_delete_request(PDO $pdo): void
{
    // Only admins can delete requests
    if (!is_admin_logged_in()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        return;
    }
    
    $requestId = $_GET['id'] ?? null;
    
    if (!$requestId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID is required']);
        return;
    }
    
    try {
        // Get request info before deletion for logging
        $stmt = $pdo->prepare("SELECT status FROM blood_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        
        if (!$request) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Blood request not found']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM blood_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        
        // Log the action
        log_user_action($_SESSION['admin_id'], 'Admin', 'Blood Request Deleted', 'blood_requests', $requestId);
        
        // Update dashboard stats
        if ($request['status'] !== 'Cancelled') {
            $stmt = $pdo->prepare("UPDATE dashboard_stats SET total_requests = total_requests - 1, pending_requests = pending_requests - 1");
            $stmt->execute();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Blood request deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("Delete blood request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete blood request']);
    }
}

function notify_matching_donors(PDO $pdo, string $bloodType, int $requestId): void
{
    try {
        $stmt = $pdo->prepare("
            SELECT email, first_name FROM donors 
            WHERE blood_type = ? AND status = 'Active' AND email_verified = 1
        ");
        $stmt->execute([$bloodType]);
        $donors = $stmt->fetchAll();
        
        $requestStmt = $pdo->prepare("SELECT * FROM blood_requests WHERE id = ?");
        $requestStmt->execute([$requestId]);
        $request = $requestStmt->fetch();
        
        foreach ($donors as $donor) {
            $message = "
                <h2>Urgent Blood Request - {$bloodType}</h2>
                <p>Dear {$donor['first_name']},</p>
                <p>There is an urgent blood request that matches your blood type:</p>
                <ul>
                    <li><strong>Hospital:</strong> {$request['hospital_name']}</li>
                    <li><strong>Location:</strong> {$request['location']}</li>
                    <li><strong>Urgency:</strong> {$request['urgency']}</li>
                    <li><strong>Units Needed:</strong> {$request['units_needed']}</li>
                </ul>
                <p>Your donation could help save a life. Please consider responding to this urgent request.</p>
                <p><a href='" . APP_URL . "/blood%20request%20list.html'>View Blood Requests</a></p>
                <p>Thank you for being a life saver!</p>
            ";
            
            send_email($donor['email'], "Urgent Blood Request - {$bloodType}", $message);
        }
        
    } catch (PDOException $e) {
        error_log("Error notifying donors: " . $e->getMessage());
    }
}

function update_dashboard_stats(PDO $pdo, string $oldStatus, string $newStatus): void
{
    try {
        if ($oldStatus === 'Pending' && $newStatus === 'Fulfilled') {
            $stmt = $pdo->prepare("UPDATE dashboard_stats SET pending_requests = pending_requests - 1, fulfilled_today = fulfilled_today + 1, total_received = total_received + 1");
            $stmt->execute();
        } elseif ($oldStatus === 'Pending' && $newStatus === 'In Progress') {
            $stmt = $pdo->prepare("UPDATE dashboard_stats SET pending_requests = pending_requests - 1");
            $stmt->execute();
        } elseif ($oldStatus === 'In Progress' && $newStatus === 'Pending') {
            $stmt = $pdo->prepare("UPDATE dashboard_stats SET pending_requests = pending_requests + 1");
            $stmt->execute();
        }
    } catch (PDOException $e) {
        error_log("Error updating dashboard stats: " . $e->getMessage());
    }
}
?>
