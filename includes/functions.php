<?php
// Database connection function
function connectDatabase() {
    global $conn;
    if (!$conn) {
        require_once __DIR__ . '/../config/database.php';
    }
    return $conn;
}

// Redirect user based on role
function redirectBasedOnRole() {
    if (isset($_SESSION['user_role'])) {
        switch ($_SESSION['user_role']) {
            case 'admin':
            case 'manager':
                header('Location: /nexusdine_pro/admin/');
                break;
            case 'waiter':
                header('Location: /nexusdine_pro/staff/');
                break;
            case 'chef':
                header('Location: /nexusdine_pro/staff/kitchen.php');
                break;
            case 'rider':
                header('Location: /nexusdine_pro/staff/rider.php');
                break;
            case 'customer':
                header('Location: /nexusdine_pro/customer/menu.php');
                break;
            default:
                header('Location: /nexusdine_pro/index.php');
        }
        exit();
    }
}

// Get business information
function getBusinessInfo($business_id, $conn) {
    $stmt = $conn->prepare("
        SELECT * FROM businesses 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $business = $result->fetch_assoc();
    $stmt->close();
    
    return $business ?: [];
}

// Get menu categories
function getMenuCategories($business_id, $conn) {
    $stmt = $conn->prepare("
        SELECT * FROM menu_categories 
        WHERE business_id = ? AND is_active = 1 
        ORDER BY display_order
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    $stmt->close();
    return $categories;
}

// Get table number
function getTableNumber($table_id, $conn) {
    if (!$table_id) return 'Takeaway';
    
    $stmt = $conn->prepare("SELECT table_number FROM tables WHERE id = ?");
    $stmt->bind_param("i", $table_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $table = $result->fetch_assoc();
    $stmt->close();
    
    return $table ? $table['table_number'] : 'Unknown';
}

// Dashboard statistics
function getDashboardStats($business_id, $conn) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $stats = [
        'today_orders' => 0,
        'active_tables' => 0,
        'total_tables' => 0,
        'today_revenue' => 0,
        'yesterday_revenue' => 0,
        'revenue_change' => 0,
        'pending_orders' => 0
    ];
    
    // Today's orders count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM orders 
        WHERE business_id = ? AND DATE(created_at) = ?
    ");
    $stmt->bind_param("is", $business_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['today_orders'] = $row['count'];
    $stmt->close();
    
    // Active and total tables
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as active
        FROM tables 
        WHERE business_id = ?
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['total_tables'] = $row['total'];
    $stats['active_tables'] = $row['active'];
    $stats['occupancy_rate'] = $stats['total_tables'] > 0 ? 
        round(($stats['active_tables'] / $stats['total_tables']) * 100) : 0;
    $stmt->close();
    
    // Today's revenue
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(final_amount), 0) as revenue 
        FROM orders 
        WHERE business_id = ? AND DATE(created_at) = ? AND payment_status = 'paid'
    ");
    $stmt->bind_param("is", $business_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['today_revenue'] = $row['revenue'];
    $stmt->close();
    
    // Yesterday's revenue
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(final_amount), 0) as revenue 
        FROM orders 
        WHERE business_id = ? AND DATE(created_at) = ? AND payment_status = 'paid'
    ");
    $stmt->bind_param("is", $business_id, $yesterday);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $yesterday_revenue = $row['revenue'];
    $stmt->close();
    
    // Revenue change
    $stats['revenue_change'] = $yesterday_revenue > 0 ? 
        $stats['today_revenue'] - $yesterday_revenue : 0;
    
    // Pending orders
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM orders 
        WHERE business_id = ? AND order_status IN ('pending', 'preparing')
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['pending_orders'] = $row['count'];
    $stmt->close();
    
    return $stats;
}

// Get recent orders
function getRecentOrders($business_id, $limit = 5, $conn) {
    $stmt = $conn->prepare("
        SELECT o.*, t.table_number 
        FROM orders o
        LEFT JOIN tables t ON o.table_id = t.id
        WHERE o.business_id = ?
        ORDER BY o.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $business_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    $stmt->close();
    return $orders;
}

// Time ago function
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

// Get status color
function getStatusColor($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'preparing': return 'info';
        case 'ready': return 'success';
        case 'served': return 'primary';
        case 'completed': return 'secondary';
        case 'cancelled': return 'danger';
        default: return 'light';
    }
}

// Get floor areas
function getFloorAreas($business_id, $conn) {
    $stmt = $conn->prepare("
        SELECT DISTINCT area 
        FROM tables 
        WHERE business_id = ? AND area IS NOT NULL AND area != ''
        ORDER BY area
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $areas = [];
    
    while ($row = $result->fetch_assoc()) {
        $areas[] = $row['area'];
    }
    
    $stmt->close();
    return $areas;
}

// Get staff tasks
function getStaffTasks($staff_id, $business_id, $conn) {
    $today = date('Y-m-d');
    
    $stmt = $conn->prepare("
        SELECT t.*, tab.table_number 
        FROM (
            SELECT 
                o.id,
                CONCAT('Order #', o.id) as title,
                'Serve order' as description,
                'order_serve' as task_type,
                CASE 
                    WHEN TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) > 30 THEN 'urgent'
                    WHEN TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) > 15 THEN 'high'
                    ELSE 'normal'
                END as priority,
                o.table_id,
                o.created_at,
                'pending' as status
            FROM orders o
            WHERE o.waiter_id = ? 
                AND o.order_status IN ('ready', 'preparing')
                AND DATE(o.created_at) = ?
            
            UNION ALL
            
            SELECT 
                hr.id,
                CONCAT('Help: ', hr.type) as title,
                hr.message as description,
                'help_request' as task_type,
                'high' as priority,
                hr.table_id,
                hr.created_at,
                hr.status
            FROM help_requests hr
            WHERE hr.business_id = ? 
                AND hr.assigned_to = ? 
                AND hr.status = 'pending'
        ) as t
        LEFT JOIN tables tab ON t.table_id = tab.id
        ORDER BY 
            CASE priority 
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                ELSE 3 
            END,
            t.created_at DESC
    ");
    
    $stmt->bind_param("isis", $staff_id, $today, $business_id, $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tasks = [];
    
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    
    $stmt->close();
    return $tasks;
}

// Get task priority color
function getTaskPriorityColor($priority) {
    switch ($priority) {
        case 'urgent': return 'danger';
        case 'high': return 'warning';
        case 'normal': return 'primary';
        default: return 'secondary';
    }
}

// Get task icon
function getTaskIcon($task_type) {
    switch ($task_type) {
        case 'order_serve': return 'utensils';
        case 'help_request': return 'hands-helping';
        case 'table_clear': return 'broom';
        case 'payment': return 'credit-card';
        default: return 'tasks';
    }
}

// Sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Generate QR code data
function generateQRCodeData($business_id, $table_id, $table_number, $area = '') {
    return json_encode([
        'business_id' => $business_id,
        'table_id' => $table_id,
        'table_number' => $table_number,
        'area' => $area,
        'timestamp' => time()
    ]);
}

// Check if user has permission
function hasPermission($permission) {
    if (!isset($_SESSION['staff_permissions'])) {
        return false;
    }
    
    return $_SESSION['staff_permissions'][$permission] ?? false;
}

// Log activity
function logActivity($user_id, $action, $details = '') {
    $conn = connectDatabase();
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt->bind_param("issss", $user_id, $action, $details, $ip, $agent);
    $stmt->execute();
    $stmt->close();
}

// Get notification count
function getNotificationCount($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] ?? 0;
}

// Format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Get popular menu items
function getPopularMenuItems($business_id, $limit = 5, $conn) {
    $stmt = $conn->prepare("
        SELECT mi.item_name, COUNT(oi.id) as order_count, SUM(oi.quantity) as total_quantity
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.business_id = ? 
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY mi.id
        ORDER BY total_quantity DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $business_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    $stmt->close();
    return $items;
}

// Check table availability
function isTableAvailable($table_id, $conn) {
    $stmt = $conn->prepare("
        SELECT status, last_occupied 
        FROM tables 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $table_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $table = $result->fetch_assoc();
    $stmt->close();
    
    if (!$table) return false;
    
    // Check if table has been occupied for more than 2 hours
    if ($table['status'] === 'occupied' && $table['last_occupied']) {
        $last_occupied = strtotime($table['last_occupied']);
        $two_hours_ago = time() - (2 * 3600);
        
        if ($last_occupied < $two_hours_ago) {
            // Auto-mark as available
            $updateStmt = $conn->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
            $updateStmt->bind_param("i", $table_id);
            $updateStmt->execute();
            $updateStmt->close();
            return true;
        }
        return false;
    }
    
    return $table['status'] === 'available';
}

// Get order timeline
function getOrderTimeline($order_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            created_at as received,
            (SELECT updated_at FROM orders WHERE id = ? AND order_status = 'preparing') as preparing,
            (SELECT updated_at FROM orders WHERE id = ? AND order_status = 'ready') as ready,
            (SELECT updated_at FROM orders WHERE id = ? AND order_status = 'served') as served
        FROM orders 
        WHERE id = ?
    ");
    $stmt->bind_param("iiii", $order_id, $order_id, $order_id, $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $timeline = $result->fetch_assoc();
    $stmt->close();
    
    return $timeline ?: [];
}

// Send notification
function sendNotification($user_id, $title, $message, $type = 'info', $link = '') {
    $conn = connectDatabase();
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, is_read)
        VALUES (?, ?, ?, ?, ?, 0)
    ");
    $stmt->bind_param("issss", $user_id, $title, $message, $type, $link);
    $stmt->execute();
    $stmt->close();
    
    // In production, you would also send push notifications here
}

// Validate session
function validateSession() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /nexusdine_pro/auth/login.php');
        exit();
    }
    
    // Check session timeout (1 hour)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        session_unset();
        session_destroy();
        header('Location: /nexusdine_pro/auth/login.php?timeout=1');
        exit();
    }
    
    $_SESSION['last_activity'] = time();
}

// Check if user is online
function isOnline() {
    return isset($_SESSION['online_status']) ? $_SESSION['online_status'] : true;
}

// Generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Get current subscription info
function getSubscriptionInfo($business_id, $conn) {
    $stmt = $conn->prepare("
        SELECT subscription_plan, subscription_expiry 
        FROM businesses 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscription = $result->fetch_assoc();
    $stmt->close();
    
    return $subscription ?: [];
}

// Check subscription validity
function isSubscriptionValid($business_id, $conn) {
    $subscription = getSubscriptionInfo($business_id, $conn);
    
    if (!$subscription) return false;
    
    $expiry = strtotime($subscription['subscription_expiry']);
    $today = time();
    
    return $expiry >= $today;
}

// Get business analytics
function getBusinessAnalytics($business_id, $period = 'week', $conn) {
    $data = [
        'revenue' => [],
        'orders' => [],
        'customers' => []
    ];
    
    switch ($period) {
        case 'day':
            $interval = '1 DAY';
            $format = '%H:00';
            break;
        case 'week':
            $interval = '7 DAY';
            $format = '%Y-%m-%d';
            break;
        case 'month':
            $interval = '30 DAY';
            $format = '%Y-%m-%d';
            break;
        default:
            $interval = '7 DAY';
            $format = '%Y-%m-%d';
    }
    
    // Revenue data
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(created_at, ?) as period,
            SUM(final_amount) as revenue
        FROM orders 
        WHERE business_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL $interval)
            AND payment_status = 'paid'
        GROUP BY period
        ORDER BY period
    ");
    $stmt->bind_param("si", $format, $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data['revenue'][] = $row;
    }
    $stmt->close();
    
    return $data;
}

// Calculate estimated wait time
function calculateWaitTime($business_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_wait_time,
            COUNT(*) as pending_orders
        FROM orders 
        WHERE business_id = ? 
            AND order_status IN ('pending', 'preparing')
            AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    $avg_wait = $data['avg_wait_time'] ?? 20;
    $pending = $data['pending_orders'] ?? 0;
    
    // Simple formula: average wait time * sqrt(pending orders)
    return min(120, round($avg_wait * sqrt($pending + 1)));
}

// Get staff performance
function getStaffPerformance($business_id, $period = 'week', $conn) {
    $stmt = $conn->prepare("
        SELECT 
            s.name,
            s.role,
            COUNT(o.id) as orders_served,
            SUM(o.final_amount) as revenue_generated,
            AVG(TIMESTAMPDIFF(MINUTE, o.created_at, o.updated_at)) as avg_service_time
        FROM staff s
        LEFT JOIN orders o ON s.id = o.waiter_id 
            AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND o.order_status = 'completed'
        WHERE s.business_id = ? 
            AND s.role IN ('waiter', 'chef')
        GROUP BY s.id
        ORDER BY revenue_generated DESC
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = [];
    
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    
    $stmt->close();
    return $staff;
}

// Encrypt sensitive data
function encryptData($data, $key = 'nexusdine_secret_key') {
    $method = 'AES-256-CBC';
    $iv_length = openssl_cipher_iv_length($method);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

// Decrypt data
function decryptData($data, $key = 'nexusdine_secret_key') {
    $method = 'AES-256-CBC';
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
    return openssl_decrypt($encrypted_data, $method, $key, 0, $iv);
}

// Get current weather for delivery
function getCurrentWeather() {
    // This is a placeholder - in production, use a weather API
    $weather_conditions = ['Sunny', 'Cloudy', 'Rainy', 'Stormy', 'Snowy'];
    return $weather_conditions[rand(0, 4)];
}

// Calculate delivery estimate
function calculateDeliveryEstimate($distance_km, $weather = 'Sunny') {
    $base_time = 20; // 20 minutes base
    $time_per_km = 3; // 3 minutes per km
    
    $estimate = $base_time + ($distance_km * $time_per_km);
    
    // Adjust for weather
    switch ($weather) {
        case 'Rainy':
            $estimate += 10;
            break;
        case 'Stormy':
            $estimate += 20;
            break;
        case 'Snowy':
            $estimate += 30;
            break;
    }
    
    return $estimate;
}

// Generate invoice number
function generateInvoiceNumber($business_id, $order_id) {
    $date = date('Ymd');
    return "INV-{$business_id}-{$date}-{$order_id}";
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone number
function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

// Get user IP address
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Create backup of important data
function createDataBackup($business_id, $conn) {
    $backup_data = [];
    $tables = ['menu_items', 'orders', 'customers', 'staff', 'tables'];
    
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SELECT * FROM $table WHERE business_id = ?");
        $stmt->bind_param("i", $business_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $backup_data[$table][] = $row;
        }
        
        $stmt->close();
    }
    
    return json_encode($backup_data);
}

// Format time for display
function formatTime($time, $format = 'h:i A') {
    return date($format, strtotime($time));
}

// Get kitchen efficiency
function getKitchenEfficiency($business_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            AVG(TIMESTAMPDIFF(MINUTE, kt.accepted_at, kt.completed_at)) as avg_cooking_time,
            COUNT(*) as items_completed,
            SUM(CASE WHEN kt.completed_at IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*) * 100 as completion_rate
        FROM kitchen_tickets kt
        JOIN orders o ON kt.order_id = o.id
        WHERE o.business_id = ? 
            AND kt.accepted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $efficiency = $result->fetch_assoc();
    $stmt->close();
    
    return $efficiency ?: ['avg_cooking_time' => 0, 'items_completed' => 0, 'completion_rate' => 0];
}
?>