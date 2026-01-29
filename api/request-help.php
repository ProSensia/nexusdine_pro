<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $conn = connectDatabase();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $business_id = $_POST['business_id'] ?? 0;
        $table_id = $_POST['table_id'] ?? 0;
        $type = $_POST['type'] ?? 'waiter';
        $message = $_POST['message'] ?? '';
        
        // Insert help request
        $stmt = $conn->prepare("
            INSERT INTO help_requests 
            (business_id, table_id, request_type, message, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->bind_param("iiss", $business_id, $table_id, $type, $message);
        
        if ($stmt->execute()) {
            $request_id = $stmt->insert_id;
            
            // Find available staff to assign
            $assign_to = findAvailableStaff($business_id, $type, $conn);
            if ($assign_to) {
                $updateStmt = $conn->prepare("UPDATE help_requests SET assigned_to = ? WHERE id = ?");
                $updateStmt->bind_param("ii", $assign_to, $request_id);
                $updateStmt->execute();
                $updateStmt->close();
            }
            
            // Create notification for staff
            createStaffNotification($business_id, $table_id, $type, $message, $conn);
            
            $response['success'] = true;
            $response['message'] = 'Help request sent successfully';
            $response['data'] = ['request_id' => $request_id];
            
            // Log activity
            if (isset($_SESSION['user_id'])) {
                logActivity($_SESSION['user_id'], 'request_help', "Type: $type, Table: $table_id");
            }
            
        } else {
            throw new Exception('Failed to save help request');
        }
        
        $stmt->close();
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get help requests (for staff dashboard)
        $business_id = $_GET['business_id'] ?? 0;
        $status = $_GET['status'] ?? 'pending';
        
        $stmt = $conn->prepare("
            SELECT hr.*, t.table_number, s.name as staff_name
            FROM help_requests hr
            LEFT JOIN tables t ON hr.table_id = t.id
            LEFT JOIN staff s ON hr.assigned_to = s.id
            WHERE hr.business_id = ? AND hr.status = ?
            ORDER BY hr.created_at DESC
        ");
        
        $stmt->bind_param("is", $business_id, $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $requests = [];
        
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        
        $response['success'] = true;
        $response['data'] = $requests;
        $stmt->close();
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

function findAvailableStaff($business_id, $request_type, $conn) {
    // Logic to find appropriate staff based on request type
    $role = 'waiter'; // Default
    
    switch ($request_type) {
        case 'bill':
        case 'payment':
            $role = 'waiter';
            break;
        case 'emergency':
            $role = 'manager';
            break;
        case 'technical':
            $role = 'admin';
            break;
    }
    
    $stmt = $conn->prepare("
        SELECT id FROM staff 
        WHERE business_id = ? AND role = ? AND is_active = 1
        ORDER BY RAND()
        LIMIT 1
    ");
    
    $stmt->bind_param("is", $business_id, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    return null;
}

function createStaffNotification($business_id, $table_id, $type, $message, $conn) {
    // Get all active staff
    $stmt = $conn->prepare("
        SELECT id FROM staff 
        WHERE business_id = ? AND is_active = 1
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $title = "Help Request - " . ucfirst($type);
        $notification = "Table $table_id: $message";
        
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read)
            VALUES (?, ?, ?, 'warning', 0)
        ");
        $notifStmt->bind_param("iss", $row['id'], $title, $notification);
        $notifStmt->execute();
        $notifStmt->close();
    }
    
    $stmt->close();
}
?>