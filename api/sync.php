<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $conn = connectDatabase();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        $business_id = $_POST['business_id'] ?? $_GET['business_id'] ?? 0;
        $table_id = $_POST['table_id'] ?? $_GET['table_id'] ?? 0;
        $session_token = $_POST['session_token'] ?? $_GET['session_token'] ?? '';
        $last_sync = $_POST['last_sync'] ?? $_GET['last_sync'] ?? '1970-01-01 00:00:00';
        
        switch ($action) {
            case 'check_updates':
                // Check for updates since last sync
                $updates = [];
                
                // Check menu updates
                $stmt = $conn->prepare("
                    SELECT mi.*, mc.category_name 
                    FROM menu_items mi
                    LEFT JOIN menu_categories mc ON mi.category_id = mc.id
                    WHERE mi.business_id = ? 
                    AND mi.updated_at > ? 
                    AND mi.is_active = 1
                    ORDER BY mi.updated_at DESC
                ");
                $stmt->bind_param("is", $business_id, $last_sync);
                $stmt->execute();
                $result = $stmt->get_result();
                $menu_updates = [];
                while ($row = $result->fetch_assoc()) {
                    $menu_updates[] = $row;
                }
                $stmt->close();
                
                if (!empty($menu_updates)) {
                    $updates['menu'] = $menu_updates;
                }
                
                // Check order status updates for this table
                if ($table_id > 0) {
                    $stmt = $conn->prepare("
                        SELECT o.*, t.table_number 
                        FROM orders o
                        LEFT JOIN tables t ON o.table_id = t.id
                        WHERE o.table_id = ? 
                        AND o.updated_at > ?
                        ORDER BY o.updated_at DESC
                    ");
                    $stmt->bind_param("is", $table_id, $last_sync);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $order_updates = [];
                    while ($row = $result->fetch_assoc()) {
                        $order_updates[] = $row;
                    }
                    $stmt->close();
                    
                    if (!empty($order_updates)) {
                        $updates['orders'] = $order_updates;
                    }
                }
                
                // Check notifications
                $stmt = $conn->prepare("
                    SELECT n.* 
                    FROM notifications n
                    WHERE n.business_id = ? 
                    AND n.created_at > ?
                    ORDER BY n.created_at DESC
                ");
                $stmt->bind_param("is", $business_id, $last_sync);
                $stmt->execute();
                $result = $stmt->get_result();
                $notification_updates = [];
                while ($row = $result->fetch_assoc()) {
                    $notification_updates[] = $row;
                }
                $stmt->close();
                
                if (!empty($notification_updates)) {
                    $updates['notifications'] = $notification_updates;
                }
                
                $response['success'] = true;
                $response['data'] = $updates;
                $response['current_time'] = date('Y-m-d H:i:s');
                break;
                
            case 'sync_offline_data':
                // Receive offline data from client and sync with server
                $offline_data = json_decode($_POST['data'] ?? '[]', true);
                $synced_ids = [];
                
                foreach ($offline_data as $data) {
                    switch ($data['type']) {
                        case 'order':
                            // Process offline order
                            $order_result = processOfflineOrder($data, $conn);
                            if ($order_result['success']) {
                                $synced_ids[] = [
                                    'client_id' => $data['client_id'],
                                    'server_id' => $order_result['order_id']
                                ];
                            }
                            break;
                            
                        case 'help_request':
                            // Process offline help request
                            $help_result = processOfflineHelp($data, $conn);
                            if ($help_result['success']) {
                                $synced_ids[] = [
                                    'client_id' => $data['client_id'],
                                    'server_id' => $help_result['request_id']
                                ];
                            }
                            break;
                            
                        case 'game_score':
                            // Process offline game score
                            $game_result = processOfflineGame($data, $conn);
                            if ($game_result['success']) {
                                $synced_ids[] = [
                                    'client_id' => $data['client_id'],
                                    'server_id' => $game_result['game_id']
                                ];
                            }
                            break;
                    }
                }
                
                $response['success'] = true;
                $response['data'] = ['synced_ids' => $synced_ids];
                break;
                
            case 'get_initial_data':
                // Get initial data for offline use
                $initial_data = [];
                
                // Get menu
                $stmt = $conn->prepare("
                    SELECT mi.*, mc.category_name 
                    FROM menu_items mi
                    LEFT JOIN menu_categories mc ON mi.category_id = mc.id
                    WHERE mi.business_id = ? AND mi.is_active = 1 AND mi.is_available = 1
                    ORDER BY mc.display_order, mi.display_order
                ");
                $stmt->bind_param("i", $business_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $menu_data = [];
                while ($row = $result->fetch_assoc()) {
                    $menu_data[] = $row;
                }
                $stmt->close();
                
                $initial_data['menu'] = $menu_data;
                
                // Get categories
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
                
                $initial_data['categories'] = $categories;
                
                // Get business info
                $stmt = $conn->prepare("
                    SELECT business_name, theme_color, logo_url 
                    FROM businesses WHERE id = ?
                ");
                $stmt->bind_param("i", $business_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $business_info = $result->fetch_assoc();
                $stmt->close();
                
                $initial_data['business'] = $business_info;
                
                $response['success'] = true;
                $response['data'] = $initial_data;
                $response['sync_time'] = date('Y-m-d H:i:s');
                break;
                
            case 'ping':
                // Simple ping to check connection
                $response['success'] = true;
                $response['data'] = ['status' => 'online', 'timestamp' => time()];
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Handle GET requests for polling
        $action = $_GET['action'] ?? 'check_updates';
        
        switch ($action) {
            case 'poll_updates':
                // Long polling for real-time updates
                session_write_close(); // Allow other requests
                
                $business_id = $_GET['business_id'] ?? 0;
                $table_id = $_GET['table_id'] ?? 0;
                $timeout = 30; // 30 seconds timeout
                $start_time = time();
                
                while (time() - $start_time < $timeout) {
                    // Check for new orders for this table
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as new_orders 
                        FROM orders 
                        WHERE table_id = ? 
                        AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
                    ");
                    $stmt->bind_param("i", $table_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($row['new_orders'] > 0) {
                        $response['success'] = true;
                        $response['data'] = ['has_updates' => true, 'type' => 'new_order'];
                        break;
                    }
                    
                    // Check for order status updates
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as status_updates 
                        FROM orders 
                        WHERE table_id = ? 
                        AND updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
                        AND created_at < DATE_SUB(NOW(), INTERVAL 2 SECOND)
                    ");
                    $stmt->bind_param("i", $table_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($row['status_updates'] > 0) {
                        $response['success'] = true;
                        $response['data'] = ['has_updates' => true, 'type' => 'status_update'];
                        break;
                    }
                    
                    // Check for help request responses
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as help_updates 
                        FROM help_requests 
                        WHERE table_id = ? 
                        AND updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
                    ");
                    $stmt->bind_param("i", $table_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($row['help_updates'] > 0) {
                        $response['success'] = true;
                        $response['data'] = ['has_updates' => true, 'type' => 'help_response'];
                        break;
                    }
                    
                    // Sleep for 1 second before checking again
                    sleep(1);
                }
                
                if (!$response['success']) {
                    $response['success'] = true;
                    $response['data'] = ['has_updates' => false];
                }
                break;
                
            default:
                $response['message'] = 'Invalid action for GET request';
        }
    }
    
} catch (Exception $e) {
    $response['message'] = 'Sync error: ' . $e->getMessage();
    error_log('Sync API Error: ' . $e->getMessage());
}

echo json_encode($response);

// Helper functions
function processOfflineOrder($order_data, $conn) {
    try {
        $conn->begin_transaction();
        
        // Insert order
        $stmt = $conn->prepare("
            INSERT INTO orders 
            (business_id, table_id, user_session_token, order_type, 
             total_amount, final_amount, special_instructions, order_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->bind_param("iissdds",
            $order_data['business_id'],
            $order_data['table_id'],
            $order_data['session_token'],
            $order_data['order_type'],
            $order_data['total_amount'],
            $order_data['total_amount'],
            $order_data['special_instructions']
        );
        
        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();
        
        // Insert order items
        foreach ($order_data['items'] as $item) {
            $stmt = $conn->prepare("
                INSERT INTO order_items 
                (order_id, menu_item_id, quantity, unit_price, modifiers, special_request)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $modifiers_json = json_encode($item['modifiers'] ?? []);
            $special_request = $item['special_request'] ?? '';
            
            $stmt->bind_param("iiidss",
                $order_id,
                $item['id'],
                $item['quantity'],
                $item['price'],
                $modifiers_json,
                $special_request
            );
            
            $stmt->execute();
            $order_item_id = $stmt->insert_id;
            $stmt->close();
            
            // Create kitchen ticket
            $stmt = $conn->prepare("
                INSERT INTO kitchen_tickets 
                (order_id, order_item_id, ticket_status)
                VALUES (?, ?, 'pending')
            ");
            
            $stmt->bind_param("ii", $order_id, $order_item_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Assign waiter
        $waiter_id = assignWaiterToTable($order_data['table_id'], $order_data['business_id'], $conn);
        if ($waiter_id) {
            $stmt = $conn->prepare("UPDATE orders SET waiter_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $waiter_id, $order_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->commit();
        
        return ['success' => true, 'order_id' => $order_id];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function processOfflineHelp($help_data, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO help_requests 
        (business_id, table_id, request_type, message, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    
    $stmt->bind_param("iiss",
        $help_data['business_id'],
        $help_data['table_id'],
        $help_data['type'],
        $help_data['message']
    );
    
    if ($stmt->execute()) {
        $request_id = $stmt->insert_id;
        $stmt->close();
        
        // Send notification to staff
        sendStaffNotification($help_data['business_id'], $help_data['table_id'], $help_data['type']);
        
        return ['success' => true, 'request_id' => $request_id];
    }
    
    $stmt->close();
    return ['success' => false];
}

function processOfflineGame($game_data, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO game_sessions 
        (table_id, game_type, player_count, player_names, scores, duration)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $player_names_json = json_encode($game_data['player_names'] ?? []);
    $scores_json = json_encode($game_data['scores'] ?? []);
    
    $stmt->bind_param("isiisi",
        $game_data['table_id'],
        $game_data['game_type'],
        $game_data['player_count'],
        $player_names_json,
        $scores_json,
        $game_data['duration']
    );
    
    if ($stmt->execute()) {
        $game_id = $stmt->insert_id;
        $stmt->close();
        return ['success' => true, 'game_id' => $game_id];
    }
    
    $stmt->close();
    return ['success' => false];
}

function assignWaiterToTable($table_id, $business_id, $conn) {
    $stmt = $conn->prepare("
        SELECT s.id, COUNT(o.id) as order_count
        FROM staff s
        LEFT JOIN orders o ON s.id = o.waiter_id AND o.order_status IN ('pending', 'preparing')
        WHERE s.business_id = ? AND s.role = 'waiter' AND s.is_active = 1
        GROUP BY s.id
        ORDER BY order_count ASC
        LIMIT 1
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    return null;
}

function sendStaffNotification($business_id, $table_id, $type) {
    // In production, implement WebSocket or push notification
    // For now, we'll just log it
    error_log("Staff Notification: $type request from table $table_id in business $business_id");
}
?>