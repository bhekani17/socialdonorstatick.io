<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check authentication
require_donor_login();

try {
    $pdo = get_db_connection();
    $donorId = $_SESSION['donor_id'];
    
    // Get dashboard statistics
    $stats = [];
    
    // Total requests count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM blood_requests WHERE status != 'Cancelled'");
    $stmt->execute();
    $stats['total_requests'] = $stmt->fetch()['total'];
    
    // Total received donations
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM donations WHERE status = 'Completed'");
    $stmt->execute();
    $stats['total_received'] = $stmt->fetch()['total'];
    
    // Blood inventory
    $stmt = $pdo->prepare("SELECT SUM(units_available) as total FROM blood_inventory");
    $stmt->execute();
    $stats['in_stock'] = $stmt->fetch()['total'] ?? 0;
    
    // User's donation count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM donations WHERE donor_id = ? AND status = 'Completed'");
    $stmt->execute([$donorId]);
    $stats['user_donations'] = $stmt->fetch()['count'];
    
    // User's last donation date
    $stmt = $pdo->prepare("SELECT MAX(donation_date) as last_date FROM donations WHERE donor_id = ? AND status = 'Completed'");
    $stmt->execute([$donorId]);
    $stats['last_donation'] = $stmt->fetch()['last_date'];
    
    // Get recent donations for display
    $stmt = $pdo->prepare("
        SELECT d.first_name, d.surname, d.blood_type, dn.donation_date, dn.location
        FROM donations dn
        JOIN donors d ON dn.donor_id = d.id
        WHERE dn.status = 'Completed'
        ORDER BY dn.donation_date DESC
        LIMIT 6
    ");
    $stmt->execute();
    $recent_donations = $stmt->fetchAll();
    
    // Get blood type statistics for pie chart
    $stmt = $pdo->prepare("
        SELECT blood_type, COUNT(*) as count
        FROM donors
        WHERE status = 'Active'
        GROUP BY blood_type
        ORDER BY count DESC
    ");
    $stmt->execute();
    $blood_type_stats = $stmt->fetchAll();
    
    // Get urgent blood requests matching donor's blood type
    $userBloodType = $_SESSION['donor_blood_type'];
    $stmt = $pdo->prepare("
        SELECT id, patient_name, patient_surname, hospital_name, location, urgency, units_needed, created_at
        FROM blood_requests
        WHERE blood_type = ? AND status IN ('Pending', 'In Progress')
        ORDER BY urgency DESC, created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userBloodType]);
    $urgent_requests = $stmt->fetchAll();
    
    // Response data
    $response = [
        'success' => true,
        'data' => [
            'stats' => $stats,
            'recent_donations' => $recent_donations,
            'blood_type_stats' => $blood_type_stats,
            'urgent_requests' => $urgent_requests,
            'user_info' => [
                'name' => $_SESSION['donor_name'],
                'blood_type' => $_SESSION['donor_blood_type'],
                'email' => $_SESSION['donor_email']
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load dashboard data'
    ]);
}
?>
