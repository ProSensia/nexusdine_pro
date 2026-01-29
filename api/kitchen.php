<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $conn = connectDatabase();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'list';
        $business_id = $_GET['business_id'] ?? 0;
        $chef_id = $_GET['chef_id'] ?? 0;
        
        switch ($action) {
            case 'list':
                // Get kitchen orders by status
                $pending = getKitchenOrdersByStatus($business_id, 'pending', $conn);
                $preparing = getKitchenOrdersByStatus($business_id, 'preparing', $conn);
                $ready = getKitchenOrdersByStatus($business_id, 'ready', $conn);
                
                $response['success'] = true;
                $response['data'] = [
                    'pending' => $pending,
                    'preparing' => $preparing,
                    'ready' => $ready,
                    'counts' => [
                        'pending' => count($pending),
                        'preparing' => count($preparing),
                        'ready' => count($ready),
                        'today' => getTodayOrderCount($business_id, $conn)
                    ]
                ];
                break;
                
            case 'order_details':
                $order_id = $_GET['order_id'] ?? 0;
                $order_details = getOrderDetails($order_id, $conn);
                
                if ($order_details) {
                    $response['success'] = true;
                    $response['data'] = [
                        'order' => $order_details,
                        'html' => generateOrderDetailsHTML($order_details)
                    ];
                } else {
                    $response['message'] = 'Order not found';
                }
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'accept_order':
                $order_id = $_POST['order_id'] ?? 0;
                $chef_id = $_POST['chef_id'] ?? 0;
                
                // Update kitchen tickets
                $stmt = $conn->prepare("
                    UPDATE kitchen_tickets kt
                    JOIN order_items oi ON kt.order_item_id = oi.id
                    SET kt.ticket_status = 'accepted', 
                        kt.chef_id = ?,
                        kt.accepted_at = NOW(),
                        kt.started_at = NOW()
                    WHERE kt.order_id = ? AND kt.ticket_status = 'pending'
                ");
                
                $stmt->bind_param("ii", $chef_id, $order_id);
                
                if ($stmt->execute()) {
                    // Update order status
                    $updateStmt = $conn->prepare("
                        UPDATE orders 
                        SET order_status = 'preparing', 
                            chef_id = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->bind_param("ii", $chef_id, $order_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    $response['success'] = true;
                    $response['message'] = 'Order accepted';
                    
                    logActivity($chef_id, 'accept_order', "Order #$order_id");
                } else {
                    throw new Exception('Failed to accept order');
                }
                $stmt->close();
                break;
                
            case 'mark_ready':
                $order_id = $_POST['order_id'] ?? 0;
                $chef_id = $_POST['chef_id'] ?? 0;
                
                // Update kitchen tickets
                $stmt = $conn->prepare("
                    UPDATE kitchen_tickets 
                    SET ticket_status = 'ready', 
                        completed_at = NOW()
                    WHERE order_id = ? AND chef_id = ?
                ");
                
                $stmt->bind_param("ii", $order_id, $chef_id);
                
                if ($stmt->execute()) {
                    // Check if all items are ready
                    $checkStmt = $conn->prepare("
                        SELECT COUNT(*) as pending_count 
                        FROM kitchen_tickets 
                        WHERE order_id = ? AND ticket_status != 'ready'
                    ");
                    $checkStmt->bind_param("i", $order_id);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result();
                    $row = $result->fetch_assoc();
                    $checkStmt->close();
                    
                    if ($row['pending_count'] == 0) {
                        // All items ready, update order status
                        $updateStmt = $conn->prepare("
                            UPDATE orders 
                            SET order_status = 'ready', 
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->bind_param("i", $order_id);
                        $updateStmt->execute();
                        $updateStmt->close();
                        
                        // Notify waiters
                        notifyWaiters($order_id, $conn);
                    }
                    
                    $response['success'] = true;
                    $response['message'] = 'Order marked as ready';
                    
                    logActivity($chef_id, 'mark_order_ready', "Order #$order_id");
                } else {
                    throw new Exception('Failed to mark order as ready');
                }
                $stmt->close();
                break;
                
            case 'mark_served':
                $order_id = $_POST['order_id'] ?? 0;
                $chef_id = $_POST['chef_id'] ?? 0;
                
                // Update order status
                $stmt = $conn->prepare("
                    UPDATE orders 
                    SET order_status = 'served', 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->bind_param("i", $order_id);
                
                if ($stmt->execute()) {
                    // Update kitchen tickets
                    $updateStmt = $conn->prepare("
                        UPDATE kitchen_tickets 
                        SET ticket_status = 'served' 
                        WHERE order_id = ?
                    ");
                    $updateStmt->bind_param("i", $order_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    $response['success'] = true;
                    $response['message'] = 'Order marked as served';
                    
                    logActivity($chef_id, 'mark_order_served', "Order #$order_id");
                } else {
                    throw new Exception('Failed to mark order as served');
                }
                $stmt->close();
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

// Helper functions
function getKitchenOrdersByStatus($business_id, $status, $conn) {
    $stmt = $conn->prepare("
        SELECT DISTINCT o.id, o.table_id, o.order_type, o.created_at,
               t.table_number,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
               (SELECT GROUP_CONCAT(CONCAT(quantity, 'x ', mi.item_name) SEPARATOR ', ') 
                FROM order_items oi 
                JOIN menu_items mi ON oi.menu_item_id = mi.id 
                WHERE oi.order_id = o.id) as item_summary
        FROM orders o
        JOIN tables t ON o.table_id = t.id
        WHERE o.business_id = ? AND o.order_status = ?
        ORDER BY 
            CASE 
                WHEN TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) > 30 THEN 1
                WHEN TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) > 15 THEN 2
                ELSE 3
            END,
            o.created_at
    ");
    
    $stmt->bind_param("is", $business_id, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    
    while ($row = $result->fetch_assoc()) {
        // Calculate priority
        $time_diff = time() - strtotime($row['created_at']);
        if ($time_diff > 1800) { // 30 minutes
            $row['priority'] = 'urgent';
        } elseif ($time_diff > 900) { // 15 minutes
            $row['priority'] = 'high';
        } else {
            $row['priority'] = 'normal';
        }
        
        // Get individual items for ticket display
        $itemStmt = $conn->prepare("
            SELECT oi.quantity, mi.item_name, oi.special_request, oi.modifiers
            FROM order_items oi
            JOIN menu_items mi ON oi.menu_item_id = mi.id
            WHERE oi.order_id = ?
        ");
        $itemStmt->bind_param("i", $row['id']);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();
        $items = [];
        
        while ($item = $itemResult->fetch_assoc()) {
            $items[] = $item;
        }
        
        $row['items'] = $items;
        $orders[] = $row;
        $itemStmt->close();
    }
    
    $stmt->close();
    return $orders;
}

function getOrderDetails($order_id, $conn) {
    $stmt = $conn->prepare("
        SELECT o.*, t.table_number, s.name as waiter_name
        FROM orders o
        LEFT JOIN tables t ON o.table_id = t.id
        LEFT JOIN staff s ON o.waiter_id = s.id
        WHERE o.id = ?
    ");
    
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if ($order) {
        // Get order items
        $stmt = $conn->prepare("
            SELECT oi.*, mi.item_name, mi.image_url
            FROM order_items oi
            JOIN menu_items mi ON oi.menu_item_id = mi.id
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        
        while ($item = $result->fetch_assoc()) {
            $items[] = $item;
        }
        
        $order['items'] = $items;
        $stmt->close();
        
        // Get kitchen tickets status
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN ticket_status = 'ready' THEN 1 ELSE 0 END) as ready,
                   SUM(CASE WHEN ticket_status = 'preparing' THEN 1 ELSE 0 END) as preparing
            FROM kitchen_tickets
            WHERE order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ticket_status = $result->fetch_assoc();
        $stmt->close();
        
        $order['ticket_status'] = $ticket_status;
    }
    
    return $order;
}

function generateOrderDetailsHTML($order) {
    ob_start();
    ?>
    <div class="order-details">
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Order Information</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Order #:</strong></td>
                        <td><?php echo $order['id']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Table:</strong></td>
                        <td><?php echo $order['table_number']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Type:</strong></td>
                        <td><?php echo ucfirst($order['order_type']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="badge bg-<?php echo getStatusColor($order['order_status']); ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Waiter:</strong></td>
                        <td><?php echo $order['waiter_name'] ?? 'Not assigned'; ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Progress</h6>
                <?php if ($order['ticket_status']): ?>
                <div class="progress mb-2" style="height: 20px;">
                    <?php 
                    $ready_percent = ($order['ticket_status']['ready'] / $order['ticket_status']['total']) * 100;
                    $preparing_percent = ($order['ticket_status']['preparing'] / $order['ticket_status']['total']) * 100;
                    ?>
                    <div class="progress-bar bg-success" style="width: <?php echo $ready_percent; ?>%">
                        Ready: <?php echo $order['ticket_status']['ready']; ?>
                    </div>
                    <div class="progress-bar bg-warning" style="width: <?php echo $preparing_percent; ?>%">
                        Preparing: <?php echo $order['ticket_status']['preparing']; ?>
                    </div>
                </div>
                <p class="small text-muted mb-0">
                    <?php echo $order['ticket_status']['ready']; ?> of <?php echo $order['ticket_status']['total']; ?> items ready
                </p>
                <?php endif; ?>
                
                <?php if ($order['special_instructions']): ?>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Special Instructions:</strong><br>
                    <?php echo htmlspecialchars($order['special_instructions']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <h6>Order Items</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                            <?php if ($item['special_request']): ?>
                            <div class="text-muted small">
                                <i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars($item['special_request']); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td>$<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                        <td>
                            <?php
                            // Get kitchen ticket status for this item
                            $status = 'pending'; // Default
                            // You would query the kitchen_tickets table here
                            ?>
                            <span class="badge bg-<?php echo getStatusColor($status); ?>">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                        <td colspan="2">
                            <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function getTodayOrderCount($business_id, $conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE business_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] ?? 0;
}

function notifyWaiters($order_id, $conn) {
    // Get order details
    $stmt = $conn->prepare("
        SELECT o.table_id, t.table_number, o.waiter_id
        FROM orders o
        JOIN tables t ON o.table_id = t.id
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if ($order) {
        // Create notification for waiter
        $title = "Order Ready";
        $message = "Order #$order_id for Table {$order['table_number']} is ready to serve";
        
        if ($order['waiter_id']) {
            // Notify specific waiter
            $notifStmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read)
                VALUES (?, ?, ?, 'success', 0)
            ");
            $notifStmt->bind_param("iss", $order['waiter_id'], $title, $message);
            $notifStmt->execute();
            $notifStmt->close();
        } else {
            // Notify all waiters
            $waiterStmt = $conn->prepare("
                SELECT id FROM staff 
                WHERE role = 'waiter' AND is_active = 1
            ");
            $waiterStmt->execute();
            $waiterResult = $waiterStmt->get_result();
            
            while ($waiter = $waiterResult->fetch_assoc()) {
                $notifStmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read)
                    VALUES (?, ?, ?, 'success', 0)
                ");
                $notifStmt->bind_param("iss", $waiter['id'], $title, $message);
                $notifStmt->execute();
                $notifStmt->close();
            }
            $waiterStmt->close();
        }
    }
}
?>