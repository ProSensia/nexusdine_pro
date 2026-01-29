<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $conn = connectDatabase();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            $business_id = $data['business_id'] ?? 0;
            $table_id = $data['table_id'] ?? 0;
            $session_token = $data['session_token'] ?? '';
            $order_type = $data['order_type'] ?? 'dine_in';
            $items = $data['items'] ?? [];
            $special_instructions = $data['special_instructions'] ?? '';
            
            if (empty($items)) {
                throw new Exception('No items in order');
            }
            
            // Calculate total amount
            $total_amount = 0;
            foreach ($items as $item) {
                $total_amount += ($item['price'] + ($item['modifiers_total'] ?? 0)) * $item['quantity'];
            }
            
            // Create order
            $stmt = $conn->prepare("
                INSERT INTO orders 
                (business_id, table_id, user_session_token, order_type, total_amount, 
                 final_amount, special_instructions, order_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->bind_param("iissdds",
                $business_id,
                $table_id,
                $session_token,
                $order_type,
                $total_amount,
                $total_amount,
                $special_instructions
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create order: ' . $stmt->error);
            }
            
            $order_id = $stmt->insert_id;
            $stmt->close();
            
            // Add order items
            foreach ($items as $item) {
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
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to add order item: ' . $stmt->error);
                }
                
                $order_item_id = $stmt->insert_id;
                $stmt->close();
                
                // Create kitchen ticket
                $stmt = $conn->prepare("
                    INSERT INTO kitchen_tickets 
                    (order_id, order_item_id, ticket_status)
                    VALUES (?, ?, 'pending')
                ");
                
                $stmt->bind_param("ii", $order_id, $order_item_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to create kitchen ticket: ' . $stmt->error);
                }
                $stmt->close();
            }
            
            // Assign to least busy waiter
            $waiter_id = assignWaiterToTable($table_id, $business_id, $conn);
            if ($waiter_id) {
                $stmt = $conn->prepare("UPDATE orders SET waiter_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $waiter_id, $order_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            $response['success'] = true;
            $response['message'] = 'Order placed successfully';
            $response['data'] = ['order_id' => $order_id];
            
            // Send notification to kitchen
            sendKitchenNotification($order_id, $business_id);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $business_id = isset($_GET['business_id']) ? intval($_GET['business_id']) : 0;
        
        if ($order_id > 0) {
            // Get specific order
            $stmt = $conn->prepare("
                SELECT o.*, t.table_number, 
                       s.name as waiter_name,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                FROM orders o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN staff s ON o.waiter_id = s.id
                WHERE o.id = ? AND o.business_id = ?
            ");
            $stmt->bind_param("ii", $order_id, $business_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Get order items
                $stmt2 = $conn->prepare("
                    SELECT oi.*, mi.item_name, mi.image_url
                    FROM order_items oi
                    LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
                    WHERE oi.order_id = ?
                ");
                $stmt2->bind_param("i", $order_id);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                $items = [];
                
                while ($item = $result2->fetch_assoc()) {
                    $items[] = $item;
                }
                
                $row['items'] = $items;
                $response['success'] = true;
                $response['data'] = $row;
                $stmt2->close();
            }
            $stmt->close();
            
        } else {
            // Get orders list with filters
            $business_id = $_SESSION['business_id'] ?? $business_id;
            $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
            $date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : date('Y-m-d');
            
            $where = "o.business_id = ?";
            $params = [$business_id];
            $types = "i";
            
            if (!empty($status)) {
                $where .= " AND o.order_status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if (!empty($date)) {
                $where .= " AND DATE(o.created_at) = ?";
                $params[] = $date;
                $types .= "s";
            }
            
            $stmt = $conn->prepare("
                SELECT o.*, t.table_number, s.name as waiter_name
                FROM orders o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN staff s ON o.waiter_id = s.id
                WHERE $where
                ORDER BY o.created_at DESC
                LIMIT 100
            ");
            
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $orders = [];
            
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $orders;
            $stmt->close();
        }
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

// Helper functions
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

function sendKitchenNotification($order_id, $business_id) {
    // In production, use WebSocket or Push Notification
    // For now, we'll just log it
    error_log("Kitchen Notification: New order #$order_id for business #$business_id");
}
?>