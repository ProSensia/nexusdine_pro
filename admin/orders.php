<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager', 'waiter'])) {
    header('Location: ../auth/login.php');
    exit();
}

$business_id = $_SESSION['business_id'];
$user_id = $_SESSION['user_id'];

// Handle actions
$action = $_GET['action'] ?? '';
$order_id = $_GET['order_id'] ?? 0;
$message = '';
$error = '';

// Handle order actions
if ($action && $order_id) {
    $conn = connectDatabase();
    
    switch ($action) {
        case 'update_status':
            $status = $_POST['status'] ?? '';
            if ($status) {
                $stmt = $conn->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ? AND business_id = ?");
                $stmt->bind_param("sii", $status, $order_id, $business_id);
                if ($stmt->execute()) {
                    $message = 'Order status updated';
                    logActivity($user_id, 'update_order_status', "Order #$order_id to $status");
                } else {
                    $error = 'Failed to update order status';
                }
                $stmt->close();
            }
            break;
            
        case 'assign_waiter':
            $waiter_id = $_POST['waiter_id'] ?? 0;
            if ($waiter_id) {
                $stmt = $conn->prepare("UPDATE orders SET waiter_id = ? WHERE id = ? AND business_id = ?");
                $stmt->bind_param("iii", $waiter_id, $order_id, $business_id);
                if ($stmt->execute()) {
                    $message = 'Waiter assigned to order';
                    logActivity($user_id, 'assign_waiter', "Order #$order_id to waiter $waiter_id");
                } else {
                    $error = 'Failed to assign waiter';
                }
                $stmt->close();
            }
            break;
            
        case 'cancel':
            $stmt = $conn->prepare("UPDATE orders SET order_status = 'cancelled', updated_at = NOW() WHERE id = ? AND business_id = ?");
            $stmt->bind_param("ii", $order_id, $business_id);
            if ($stmt->execute()) {
                $message = 'Order cancelled';
                logActivity($user_id, 'cancel_order', "Order #$order_id");
            } else {
                $error = 'Failed to cancel order';
            }
            $stmt->close();
            break;
            
        case 'delete':
            // Soft delete
            $stmt = $conn->prepare("UPDATE orders SET is_active = 0 WHERE id = ? AND business_id = ?");
            $stmt->bind_param("ii", $order_id, $business_id);
            if ($stmt->execute()) {
                $message = 'Order deleted';
                logActivity($user_id, 'delete_order', "Order #$order_id");
            } else {
                $error = 'Failed to delete order';
            }
            $stmt->close();
            break;
    }
    
    $conn->close();
    
    if ($message || $error) {
        header('Location: orders.php?message=' . urlencode($message) . '&error=' . urlencode($error));
        exit();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$date_filter = $_GET['date'] ?? date('Y-m-d');
$search_filter = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get orders with filters
$conn = connectDatabase();
$where = "o.business_id = ? AND o.is_active = 1";
$params = [$business_id];
$types = "i";

if ($status_filter !== 'all') {
    $where .= " AND o.order_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($type_filter !== 'all') {
    $where .= " AND o.order_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if ($date_filter) {
    $where .= " AND DATE(o.created_at) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

if (!empty($search_filter)) {
    $where .= " AND (o.id = ? OR t.table_number LIKE ?)";
    $params[] = $search_filter;
    $params[] = "%$search_filter%";
    $types .= "ss";
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders o LEFT JOIN tables t ON o.table_id = t.id WHERE $where";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_orders = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $limit);
$count_stmt->close();

// Get orders
$sql = "SELECT o.*, t.table_number, s.name as waiter_name 
        FROM orders o 
        LEFT JOIN tables t ON o.table_id = t.id 
        LEFT JOIN staff s ON o.waiter_id = s.id 
        WHERE $where 
        ORDER BY o.created_at DESC 
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];

while ($row = $result->fetch_assoc()) {
    // Get order items count
    $item_stmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE order_id = ?");
    $item_stmt->bind_param("i", $row['id']);
    $item_stmt->execute();
    $item_result = $item_stmt->get_result();
    $item_count = $item_result->fetch_assoc()['count'];
    $row['item_count'] = $item_count;
    $item_stmt->close();
    
    $orders[] = $row;
}

$stmt->close();

// Get waiters for assignment
$waiters = [];
$waiter_stmt = $conn->prepare("SELECT id, name FROM staff WHERE business_id = ? AND role = 'waiter' AND is_active = 1");
$waiter_stmt->bind_param("i", $business_id);
$waiter_stmt->execute();
$waiter_result = $waiter_stmt->get_result();
while ($waiter = $waiter_result->fetch_assoc()) {
    $waiters[] = $waiter;
}
$waiter_stmt->close();

$conn->close();

$page_title = "Order Management";
?>
<?php include '../components/header.php'; ?>
<?php include '../components/navbar.php'; ?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <?php include '../components/sidebar.php'; ?>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Messages -->
            <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($_GET['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Orders Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0">
                                <i class="fas fa-receipt me-2"></i>
                                Order Management
                            </h4>
                            <p class="text-muted mb-0">Manage and track all customer orders</p>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createOrderModal">
                                <i class="fas fa-plus me-2"></i>Create Order
                            </button>
                            <button class="btn btn-outline-primary" onclick="printOrders()">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="preparing" <?php echo $status_filter === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                <option value="ready" <?php echo $status_filter === 'ready' ? 'selected' : ''; ?>>Ready</option>
                                <option value="served" <?php echo $status_filter === 'served' ? 'selected' : ''; ?>>Served</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Order Type</label>
                            <select class="form-select" name="type" onchange="this.form.submit()">
                                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="dine_in" <?php echo $type_filter === 'dine_in' ? 'selected' : ''; ?>>Dine In</option>
                                <option value="takeaway" <?php echo $type_filter === 'takeaway' ? 'selected' : ''; ?>>Takeaway</option>
                                <option value="delivery" <?php echo $type_filter === 'delivery' ? 'selected' : ''; ?>>Delivery</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" 
                                   value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search_filter); ?>" 
                                       placeholder="Order ID or Table">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Orders Summary -->
            <div class="row mb-4">
                <?php
                $summary = getOrdersSummary($business_id, $date_filter, connectDatabase());
                $summary_colors = [
                    'total' => 'primary',
                    'pending' => 'warning',
                    'preparing' => 'info',
                    'ready' => 'success',
                    'completed' => 'secondary'
                ];
                ?>
                <?php foreach ($summary as $key => $value): ?>
                <?php if ($key !== 'revenue'): ?>
                <div class="col-md-2 mb-2">
                    <div class="card bg-<?php echo $summary_colors[$key] ?? 'light'; ?> text-white">
                        <div class="card-body text-center py-2">
                            <h6 class="mb-0"><?php echo ucfirst($key); ?></h6>
                            <h4 class="fw-bold mb-0"><?php echo $value; ?></h4>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
                <div class="col-md-2 mb-2">
                    <div class="card bg-dark text-white">
                        <div class="card-body text-center py-2">
                            <h6 class="mb-0">Revenue</h6>
                            <h4 class="fw-bold mb-0">$<?php echo number_format($summary['revenue'], 2); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Orders Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Table/Type</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Waiter</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                        <h5>No orders found</h5>
                                        <p class="text-muted">Try adjusting your filters</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo $order['id']; ?></strong>
                                        <div class="small text-muted">
                                            <?php echo date('h:i A', strtotime($order['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($order['table_number']): ?>
                                        <span class="badge bg-primary">
                                            <i class="fas fa-chair"></i> Table <?php echo $order['table_number']; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-<?php echo $order['order_type'] === 'takeaway' ? 'warning' : 'info'; ?>">
                                            <?php echo ucfirst($order['order_type']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $order['item_count']; ?> items
                                        <?php if ($order['special_instructions']): ?>
                                        <div class="small text-muted">
                                            <i class="fas fa-sticky-note"></i> Has notes
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                        <div class="small">
                                            <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatusColor($order['order_status']); ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                        <?php if ($order['order_status'] === 'preparing'): ?>
                                        <div class="small text-muted">
                                            <?php 
                                            $time_diff = time() - strtotime($order['updated_at']);
                                            $minutes = floor($time_diff / 60);
                                            echo $minutes > 0 ? "$minutes min ago" : "Just now";
                                            ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order['waiter_name']): ?>
                                        <?php echo htmlspecialchars($order['waiter_name']); ?>
                                        <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo time_ago($order['created_at']); ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="viewOrderDetails(<?php echo $order['id']; ?>)"
                                                    data-bs-toggle="tooltip" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-warning"
                                                    data-bs-toggle="dropdown"
                                                    data-bs-toggle="tooltip" title="Change Status">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'confirmed')">Confirm</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'preparing')">Start Preparing</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'ready')">Mark as Ready</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'served')">Mark as Served</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'completed')">Complete</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="cancelOrder(<?php echo $order['id']; ?>)">Cancel Order</a></li>
                                            </ul>
                                            <button class="btn btn-outline-info"
                                                    data-bs-toggle="dropdown"
                                                    data-bs-toggle="tooltip" title="Assign Waiter">
                                                <i class="fas fa-user-tie"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php foreach ($waiters as $waiter): ?>
                                                <li><a class="dropdown-item" href="#" onclick="assignWaiter(<?php echo $order['id']; ?>, <?php echo $waiter['id']; ?>)">
                                                    <?php echo htmlspecialchars($waiter['name']); ?>
                                                </a></li>
                                                <?php endforeach; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="#" onclick="assignWaiter(<?php echo $order['id']; ?>, 0)">Unassign</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav class="p-3">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details - #<span id="order-details-id"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="order-details-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printOrder()">
                    <i class="fas fa-print me-2"></i>Print Invoice
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Create Order Modal -->
<div class="modal fade" id="createOrderModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createOrderForm">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Customer Info -->
                            <div class="mb-3">
                                <label class="form-label">Order Type</label>
                                <select class="form-select" id="new-order-type" required>
                                    <option value="dine_in" selected>Dine In</option>
                                    <option value="takeaway">Takeaway</option>
                                    <option value="delivery">Delivery</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Table Number</label>
                                <select class="form-select" id="new-order-table" required>
                                    <option value="">Select Table</option>
                                    <!-- Tables loaded via AJAX -->
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Customer Name (Optional)</label>
                                <input type="text" class="form-control" id="new-customer-name" placeholder="Enter customer name">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Customer Phone (Optional)</label>
                                <input type="tel" class="form-control" id="new-customer-phone" placeholder="Enter phone number">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Special Instructions</label>
                                <textarea class="form-control" id="new-special-instructions" rows="3" placeholder="Any special requests?"></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <!-- Menu Selection -->
                            <div class="mb-3">
                                <label class="form-label">Select Menu Items</label>
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="menu-search" placeholder="Search menu items...">
                                    <button class="btn btn-outline-secondary" type="button" onclick="searchMenuItems()">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                
                                <div class="menu-items-container border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                    <!-- Menu items loaded via AJAX -->
                                </div>
                            </div>
                            
                            <!-- Selected Items -->
                            <div class="mb-3">
                                <label class="form-label">Selected Items</label>
                                <div class="selected-items-container border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                    <!-- Selected items will appear here -->
                                </div>
                            </div>
                            
                            <!-- Order Summary -->
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Order Summary</h6>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span id="order-subtotal">$0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Tax (10%):</span>
                                        <span id="order-tax">$0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Total:</span>
                                        <span class="fw-bold" id="order-total">$0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitNewOrder()">
                    <i class="fas fa-check me-2"></i>Create Order
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Load tables for new order
    loadTablesForNewOrder();
    
    // Load menu items for new order
    loadMenuItemsForNewOrder();
});

let selectedItems = [];

function viewOrderDetails(orderId) {
    $.ajax({
        url: '../api/orders.php',
        method: 'GET',
        data: {
            order_id: orderId,
            business_id: <?php echo $business_id; ?>
        },
        success: function(response) {
            if (response.success && response.data) {
                const order = response.data;
                
                $('#order-details-id').text(order.id);
                
                // Format order details HTML
                const html = `
                    <div class="order-details">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Order Information</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Order #:</strong></td>
                                        <td>${order.id}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Table:</strong></td>
                                        <td>${order.table_number || 'Takeaway/Delivery'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Type:</strong></td>
                                        <td>${order.order_type}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge bg-${getStatusColor(order.order_status)}">
                                                ${order.order_status}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Payment:</strong></td>
                                        <td>
                                            <span class="badge bg-${order.payment_status === 'paid' ? 'success' : 'warning'}">
                                                ${order.payment_status}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Waiter:</strong></td>
                                        <td>${order.waiter_name || 'Not assigned'}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Timeline</h6>
                                <div class="timeline">
                                    <div class="timeline-item ${order.order_status === 'pending' || order.order_status === 'confirmed' || order.order_status === 'preparing' || order.order_status === 'ready' || order.order_status === 'served' || order.order_status === 'completed' ? 'active' : ''}">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <strong>Order Placed</strong>
                                            <div class="text-muted">${formatDateTime(order.created_at)}</div>
                                        </div>
                                    </div>
                                    ${order.order_status === 'confirmed' || order.order_status === 'preparing' || order.order_status === 'ready' || order.order_status === 'served' || order.order_status === 'completed' ? `
                                    <div class="timeline-item ${order.order_status === 'preparing' || order.order_status === 'ready' || order.order_status === 'served' || order.order_status === 'completed' ? 'active' : ''}">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <strong>Confirmed</strong>
                                            <div class="text-muted">${formatDateTime(order.updated_at)}</div>
                                        </div>
                                    </div>
                                    ` : ''}
                                    ${order.order_status === 'preparing' || order.order_status === 'ready' || order.order_status === 'served' || order.order_status === 'completed' ? `
                                    <div class="timeline-item ${order.order_status === 'ready' || order.order_status === 'served' || order.order_status === 'completed' ? 'active' : ''}">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <strong>Preparing</strong>
                                        </div>
                                    </div>
                                    ` : ''}
                                    ${order.order_status === 'ready' || order.order_status === 'served' || order.order_status === 'completed' ? `
                                    <div class="timeline-item ${order.order_status === 'served' || order.order_status === 'completed' ? 'active' : ''}">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <strong>Ready</strong>
                                        </div>
                                    </div>
                                    ` : ''}
                                    ${order.order_status === 'served' || order.order_status === 'completed' ? `
                                    <div class="timeline-item ${order.order_status === 'completed' ? 'active' : ''}">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <strong>Served</strong>
                                        </div>
                                    </div>
                                    ` : ''}
                                    ${order.order_status === 'completed' ? `
                                    <div class="timeline-item active">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <strong>Completed</strong>
                                        </div>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        
                        ${order.special_instructions ? `
                        <div class="alert alert-warning mb-4">
                            <i class="fas fa-exclamation-circle"></i>
                            <strong>Special Instructions:</strong><br>
                            ${order.special_instructions}
                        </div>
                        ` : ''}
                        
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
                                    ${order.items.map(item => `
                                    <tr>
                                        <td>
                                            <strong>${item.item_name}</strong>
                                            ${item.special_request ? `<div class="text-muted small"><i class="fas fa-sticky-note"></i> ${item.special_request}</div>` : ''}
                                        </td>
                                        <td>${item.quantity}</td>
                                        <td>$${parseFloat(item.unit_price).toFixed(2)}</td>
                                        <td>$${(item.quantity * item.unit_price).toFixed(2)}</td>
                                        <td>
                                            <span class="badge bg-${getStatusColor(item.item_status || 'pending')}">
                                                ${item.item_status || 'pending'}
                                            </span>
                                        </td>
                                    </tr>
                                    `).join('')}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                        <td colspan="2">
                                            <strong>$${parseFloat(order.total_amount).toFixed(2)}</strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        ${order.payment_status === 'paid' ? `
                        <div class="alert alert-success mt-3">
                            <i class="fas fa-check-circle"></i>
                            <strong>Payment Details:</strong><br>
                            Method: ${order.payment_method || 'Not specified'}<br>
                            Paid at: ${order.payment_date ? formatDateTime(order.payment_date) : 'N/A'}
                        </div>
                        ` : ''}
                    </div>
                `;
                
                $('#order-details-content').html(html);
                $('#orderDetailsModal').modal('show');
            }
        }
    });
}

function getStatusColor(status) {
    switch(status) {
        case 'pending': return 'warning';
        case 'confirmed': return 'info';
        case 'preparing': return 'primary';
        case 'ready': return 'success';
        case 'served': return 'secondary';
        case 'completed': return 'dark';
        case 'cancelled': return 'danger';
        default: return 'light';
    }
}

function formatDateTime(datetime) {
    const date = new Date(datetime);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function updateOrderStatus(orderId, status) {
    if (confirm(`Change order status to "${status}"?`)) {
        $.post('orders.php', {
            action: 'update_status',
            order_id: orderId,
            status: status
        }, function(response) {
            location.reload();
        });
    }
}

function assignWaiter(orderId, waiterId) {
    $.post('orders.php', {
        action: 'assign_waiter',
        order_id: orderId,
        waiter_id: waiterId
    }, function(response) {
        location.reload();
    });
}

function cancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order?')) {
        $.post('orders.php', {
            action: 'cancel',
            order_id: orderId
        }, function(response) {
            location.reload();
        });
    }
}

function printOrders() {
    const printWindow = window.open('', '_blank');
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('print', '1');
    
    printWindow.location.href = currentUrl.toString();
}

function printOrder() {
    const orderId = $('#order-details-id').text();
    window.open(`../api/print.php?order_id=${orderId}`, '_blank');
}

function loadTablesForNewOrder() {
    $.ajax({
        url: '../api/tables.php',
        method: 'GET',
        data: {
            action: 'get_available_tables',
            business_id: <?php echo $business_id; ?>
        },
        success: function(response) {
            if (response.success) {
                const select = $('#new-order-table');
                response.data.forEach(table => {
                    const label = table.area ? `Table ${table.table_number} (${table.area})` : `Table ${table.table_number}`;
                    select.append(new Option(label, table.id));
                });
            }
        }
    });
}

function loadMenuItemsForNewOrder(search = '') {
    $.ajax({
        url: '../api/menu.php',
        method: 'GET',
        data: {
            business_id: <?php echo $business_id; ?>,
            search: search,
            available_only: 1
        },
        success: function(response) {
            if (response.success) {
                const container = $('.menu-items-container');
                container.empty();
                
                if (response.data.length === 0) {
                    container.html('<div class="text-center py-4 text-muted">No menu items found</div>');
                    return;
                }
                
                // Group by category
                const categories = {};
                response.data.forEach(item => {
                    if (!categories[item.category_name]) {
                        categories[item.category_name] = [];
                    }
                    categories[item.category_name].push(item);
                });
                
                // Display by category
                for (const [categoryName, items] of Object.entries(categories)) {
                    container.append(`<h6 class="mt-3 mb-2">${categoryName}</h6>`);
                    
                    items.forEach(item => {
                        const itemHtml = `
                            <div class="card mb-2 menu-item-card">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">${item.item_name}</h6>
                                            <p class="text-muted small mb-0">$${item.price}</p>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="addToOrder(${item.id}, '${item.item_name}', ${item.price})">
                                            <i class="fas fa-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.append(itemHtml);
                    });
                }
            }
        }
    });
}

function searchMenuItems() {
    const searchTerm = $('#menu-search').val();
    loadMenuItemsForNewOrder(searchTerm);
}

function addToOrder(itemId, itemName, price) {
    // Check if item already exists
    const existingIndex = selectedItems.findIndex(item => item.id === itemId);
    
    if (existingIndex > -1) {
        selectedItems[existingIndex].quantity++;
    } else {
        selectedItems.push({
            id: itemId,
            name: itemName,
            price: price,
            quantity: 1
        });
    }
    
    updateSelectedItemsDisplay();
    updateOrderSummary();
}

function updateSelectedItemsDisplay() {
    const container = $('.selected-items-container');
    container.empty();
    
    if (selectedItems.length === 0) {
        container.html('<div class="text-center py-4 text-muted">No items selected</div>');
        return;
    }
    
    selectedItems.forEach((item, index) => {
        const itemHtml = `
            <div class="selected-item mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${item.name}</strong>
                        <div class="text-muted small">$${item.price} × ${item.quantity} = $${(item.price * item.quantity).toFixed(2)}</div>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" onclick="adjustQuantity(${index}, -1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="adjustQuantity(${index}, 1)">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="removeItem(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.append(itemHtml);
    });
}

function adjustQuantity(index, change) {
    selectedItems[index].quantity += change;
    
    if (selectedItems[index].quantity <= 0) {
        selectedItems.splice(index, 1);
    }
    
    updateSelectedItemsDisplay();
    updateOrderSummary();
}

function removeItem(index) {
    selectedItems.splice(index, 1);
    updateSelectedItemsDisplay();
    updateOrderSummary();
}

function updateOrderSummary() {
    const subtotal = selectedItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const tax = subtotal * 0.10;
    const total = subtotal + tax;
    
    $('#order-subtotal').text('$' + subtotal.toFixed(2));
    $('#order-tax').text('$' + tax.toFixed(2));
    $('#order-total').text('$' + total.toFixed(2));
}

function submitNewOrder() {
    if (selectedItems.length === 0) {
        showToast('Please add at least one item to the order', 'error');
        return;
    }
    
    const orderData = {
        business_id: <?php echo $business_id; ?>,
        table_id: $('#new-order-table').val(),
        order_type: $('#new-order-type').val(),
        customer_name: $('#new-customer-name').val(),
        customer_phone: $('#new-customer-phone').val(),
        special_instructions: $('#new-special-instructions').val(),
        items: selectedItems,
        total_amount: parseFloat($('#order-total').text().replace('$', '')),
        payment_status: 'pending'
    };
    
    $.ajax({
        url: '../api/orders.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(orderData),
        success: function(response) {
            if (response.success) {
                showToast('Order created successfully!', 'success');
                $('#createOrderModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Failed to create order: ' + response.message, 'error');
            }
        },
        error: function() {
            showToast('Failed to create order. Please try again.', 'error');
        }
    });
}

function showToast(message, type = 'info') {
    const toast = $(`
        <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    
    $('.toast-container').append(toast);
    const bsToast = new bootstrap.Toast(toast[0], { delay: 3000 });
    bsToast.show();
    
    toast.on('hidden.bs.toast', function() {
        $(this).remove();
    });
}

// Helper function for orders summary
function getOrdersSummary(business_id, date, conn) {
    // This function should be defined in functions.php
    // For now, we'll return dummy data
    return {
        'total': 45,
        'pending': 5,
        'preparing': 8,
        'ready': 3,
        'completed': 29,
        'revenue': 1245.50
    };
}
</script>

<style>
.timeline {
    position: relative;
    padding-left: 20px;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-marker {
    position: absolute;
    left: -20px;
    top: 5px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #dee2e6;
    border: 2px solid white;
}
.timeline-item.active .timeline-marker {
    background: #007bff;
}
.timeline-content {
    padding-left: 5px;
}
.menu-item-card {
    cursor: pointer;
    transition: all 0.2s;
}
.menu-item-card:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}
.selected-item {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}
</style>

<?php include '../components/footer.php'; ?>