<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ../auth/login.php');
    exit();
}

$business_id = $_SESSION['business_id'];
$user_id = $_SESSION['user_id'];

// Handle actions
$action = $_GET['action'] ?? '';
$staff_id = $_GET['id'] ?? 0;
$message = '';
$error = '';

if ($action && $staff_id) {
    $conn = connectDatabase();
    
    switch ($action) {
        case 'activate':
            $stmt = $conn->prepare("UPDATE staff SET is_active = 1 WHERE id = ? AND business_id = ?");
            $stmt->bind_param("ii", $staff_id, $business_id);
            if ($stmt->execute()) {
                $message = 'Staff member activated';
                logActivity($user_id, 'activate_staff', "Staff ID: $staff_id");
            } else {
                $error = 'Failed to activate staff member';
            }
            $stmt->close();
            break;
            
        case 'deactivate':
            $stmt = $conn->prepare("UPDATE staff SET is_active = 0 WHERE id = ? AND business_id = ?");
            $stmt->bind_param("ii", $staff_id, $business_id);
            if ($stmt->execute()) {
                $message = 'Staff member deactivated';
                logActivity($user_id, 'deactivate_staff', "Staff ID: $staff_id");
            } else {
                $error = 'Failed to deactivate staff member';
            }
            $stmt->close();
            break;
            
        case 'delete':
            // Soft delete
            $stmt = $conn->prepare("UPDATE staff SET is_active = 0 WHERE id = ? AND business_id = ?");
            $stmt->bind_param("ii", $staff_id, $business_id);
            if ($stmt->execute()) {
                $message = 'Staff member deleted';
                logActivity($user_id, 'delete_staff', "Staff ID: $staff_id");
            } else {
                $error = 'Failed to delete staff member';
            }
            $stmt->close();
            break;
    }
    
    $conn->close();
    
    if ($message || $error) {
        header('Location: staff.php?message=' . urlencode($message) . '&error=' . urlencode($error));
        exit();
    }
}

// // Handle form submissions
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $conn = connectDatabase();
    
//     if (isset($_POST['add_staff'])) {
//         $name = trim($_POST['name'] ?? '');
//         $email = trim($_POST['email'] ?? '');
//         $role = $_POST['role'] ?? 'waiter';
//         $password = $_POST['password'] ?? '';
//         $phone = trim($_POST['phone'] ?? '');
//         $section_assigned = trim($_POST['section_assigned'] ?? '');
//         $shift_start = $_POST['shift_start'] ?? '09:00';
//         $shift_end = $_POST['shift_end'] ?? '17:00';
        
//         // Set permissions based on role
//         $permissions = getDefaultPermissions($role);
        
//         // Hash password
//         $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
//         $stmt = $conn->prepare("
//             INSERT INTO staff 
//             (business_id, name, email, password, role, permissions, 
//              phone, section_assigned, shift_start, shift_end)
//             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
//         ");
        
//         $permissions_json = json_encode($permissions);
        
//         $stmt->bind_param("isssssssss",
//             $business_id,
//             $name,
//             $email,
//             $hashed_password,
//             $role,
//             $permissions_json,
//             $phone,
//             $section_assigned,
//             $shift_start,
//             $shift_end
//         );
        
//         if ($stmt->execute()) {
//             $message = 'Staff member added successfully';
//             logActivity($user_id, 'add_staff', "Added: $name ($role)");
//         } else {
//             $error = 'Failed to add staff member: ' . $stmt->error;
//         }
//         $stmt->close();
        
//     }