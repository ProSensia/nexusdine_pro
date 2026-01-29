<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if staff is logged in as chef
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['chef', 'admin', 'manager'])) {
    header('Location: ../auth/login.php');
    exit();
}

$chef_id = $_SESSION['user_id'];
$business_id = $_SESSION['business_id'];

$page_title = "Kitchen Display System";
?>
<?php include '../components/header.php'; ?>
<?php include '../components/navbar.php'; ?>

<div class="container-fluid px-0">
    <!-- Kitchen Header -->
    <div class="kitchen-header bg-dark text-white py-3">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold mb-0">
                        <i class="fas fa-fire me-2"></i>Kitchen Display System
                    </h1>
                    <p class="mb-0">Real-time order management for kitchen staff</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <button class="btn btn-outline-light" onclick="clockInOut()">
                            <i class="fas fa-clock"></i> 
                            <span id="clock-status">Clock In</span>
                        </button>
                        <button class="btn btn-outline-light" onclick="toggleFullscreen()">
                            <i class="fas fa-expand"></i> Fullscreen
                        </button>
                        <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#settingsModal">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kitchen Stats -->
    <div class="kitchen-stats bg-light py-2 border-bottom">
        <div class="container">
            <div class="row text-center">
                <div class="col">
                    <span class="badge bg-primary">
                        <i class="fas fa-clock"></i> Pending: <span id="pending-count">0</span>
                    </span>
                </div>
                <div class="col">
                    <span class="badge bg-warning">
                        <i class="fas fa-utensils"></i> Preparing: <span id="preparing-count">0</span>
                    </span>
                </div>
                <div class="col">
                    <span class="badge bg-success">
                        <i class="fas fa-check"></i> Ready: <span id="ready-count">0</span>
                    </span>
                </div>
                <div class="col">
                    <span class="badge bg-info">
                        <i class="fas fa-history"></i> Today: <span id="today-count">0</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kitchen Tickets -->
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Pending Orders -->
            <div class="col-lg-4">
                <div class="card border-primary h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock"></i> Pending Orders
                            <span class="badge bg-light text-primary float-end" id="pending-badge">0</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="pending-orders" class="kitchen-column">
                            <!-- Orders loaded via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preparing Orders -->
            <div class="col-lg-4">
                <div class="card border-warning h-100">
                    <div class="card-header bg-warning text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-utensils"></i> Preparing
                            <span class="badge bg-light text-warning float-end" id="preparing-badge">0</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="preparing-orders" class="kitchen-column">
                            <!-- Orders loaded via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ready Orders -->
            <div class="col-lg-4">
                <div class="card border-success h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-check"></i> Ready to Serve
                            <span class="badge bg-light text-success float-end" id="ready-badge">0</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="ready-orders" class="kitchen-column">
                            <!-- Orders loaded via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sound Alert -->
    <audio id="new-order-sound" preload="auto">
        <source src="../assets/sounds/new-order.mp3" type="audio/mpeg">
    </audio>
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
                <button type="button" class="btn btn-primary" id="acceptOrderBtn">Accept Order</button>
                <button type="button" class="btn btn-success" id="markReadyBtn">Mark as Ready</button>
            </div>
        </div>
    </div>
</div>

<!-- Kitchen Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kitchen Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Sound Alert</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="sound-alert" checked>
                        <label class="form-check-label" for="sound-alert">Play sound for new orders</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Auto-refresh (seconds)</label>
                    <input type="number" class="form-control" id="refresh-interval" value="10" min="5" max="60">
                </div>
                <div class="mb-3">
                    <label class="form-label">Station Filter</label>
                    <select class="form-select" id="station-filter">
                        <option value="all">All Stations</option>
                        <option value="grill">Grill Station</option>
                        <option value="fry">Fry Station</option>
                        <option value="salad">Salad Station</option>
                        <option value="dessert">Dessert Station</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Save Settings</button>
            </div>
        </div>
    </div>
</div>

<script>
let refreshInterval = 10000; // 10 seconds
let soundEnabled = true;
let currentOrderId = null;
let autoRefresh = true;

$(document).ready(function() {
    loadKitchenOrders();
    
    // Set up auto-refresh
    setInterval(function() {
        if (autoRefresh) {
            loadKitchenOrders();
        }
    }, refreshInterval);
    
    // Set up WebSocket for real-time updates
    setupWebSocket();
    
    // Load settings
    const savedSettings = localStorage.getItem('kitchen_settings');
    if (savedSettings) {
        const settings = JSON.parse(savedSettings);
        soundEnabled = settings.soundEnabled !== false;
        refreshInterval = settings.refreshInterval || 10000;
        $('#sound-alert').prop('checked', soundEnabled);
        $('#refresh-interval').val(refreshInterval / 1000);
    }
});

function setupWebSocket() {
    // In production, use WebSocket server
    // For now, we'll use polling
}

function loadKitchenOrders() {
    $.ajax({
        url: '../api/kitchen.php',
        method: 'GET',
        data: {
            business_id: <?php echo $business_id; ?>,
            chef_id: <?php echo $chef_id; ?>
        },
        success: function(response) {
            if (response.success) {
                updateKitchenDisplay(response.data);
                
                // Play sound if new orders arrived
                if (soundEnabled && response.new_orders > 0) {
                    document.getElementById('new-order-sound').play();
                }
            }
        }
    });
}

function updateKitchenDisplay(data) {
    // Update counts
    $('#pending-count').text(data.counts.pending);
    $('#preparing-count').text(data.counts.preparing);
    $('#ready-count').text(data.counts.ready);
    $('#today-count').text(data.counts.today);
    
    $('#pending-badge').text(data.counts.pending);
    $('#preparing-badge').text(data.counts.preparing);
    $('#ready-badge').text(data.counts.ready);
    
    // Update columns
    renderOrders('#pending-orders', data.pending, 'pending');
    renderOrders('#preparing-orders', data.preparing, 'preparing');
    renderOrders('#ready-orders', data.ready, 'ready');
}

function renderOrders(container, orders, status) {
    $(container).empty();
    
    if (orders.length === 0) {
        $(container).html(`
            <div class="empty-state text-center py-5">
                <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                <p class="text-muted">No orders in this section</p>
            </div>
        `);
        return;
    }
    
    orders.forEach(order => {
        const orderCard = createOrderCard(order, status);
        $(container).append(orderCard);
    });
}

function createOrderCard(order, status) {
    const timeAgo = getTimeAgo(order.created_at);
    const elapsedTime = getElapsedTime(order.created_at);
    const priorityClass = order.priority === 'high' ? 'priority-high' : order.priority === 'urgent' ? 'priority-urgent' : '';
    
    return `
        <div class="kitchen-ticket ${priorityClass}" onclick="viewOrderDetails(${order.id})">
            <div class="ticket-header">
                <div class="ticket-id">#${order.id}</div>
                <div class="ticket-time">${timeAgo}</div>
            </div>
            <div class="ticket-body">
                <div class="ticket-table">
                    <i class="fas fa-chair"></i> Table ${order.table_number}
                    ${order.order_type === 'takeaway' ? '<span class="badge bg-info">Takeaway</span>' : ''}
                    ${order.order_type === 'delivery' ? '<span class="badge bg-warning">Delivery</span>' : ''}
                </div>
                <div class="ticket-items">
                    ${order.items.map(item => `
                        <div class="ticket-item">
                            <span class="item-quantity">${item.quantity}x</span>
                            <span class="item-name">${item.name}</span>
                            ${item.modifiers ? '<i class="fas fa-edit text-muted"></i>' : ''}
                            ${item.special_request ? '<i class="fas fa-sticky-note text-warning"></i>' : ''}
                        </div>
                    `).join('')}
                </div>
                <div class="ticket-footer">
                    <div class="elapsed-time">
                        <i class="fas fa-clock"></i> ${elapsedTime}
                    </div>
                    <div class="ticket-actions">
                        ${status === 'pending' ? 
                            `<button class="btn btn-sm btn-success" onclick="acceptOrder(${order.id}, event)">Accept</button>` : 
                        status === 'preparing' ? 
                            `<button class="btn btn-sm btn-success" onclick="markOrderReady(${order.id}, event)">Ready</button>` : 
                            `<button class="btn btn-sm btn-secondary" onclick="markOrderServed(${order.id}, event)">Served</button>`}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function viewOrderDetails(orderId) {
    currentOrderId = orderId;
    
    $.ajax({
        url: '../api/kitchen.php',
        method: 'GET',
        data: {
            action: 'order_details',
            order_id: orderId
        },
        success: function(response) {
            if (response.success) {
                $('#order-details-id').text(orderId);
                $('#order-details-content').html(response.data.html);
                
                // Show/hide buttons based on order status
                const order = response.data.order;
                $('#acceptOrderBtn').toggle(order.status === 'pending');
                $('#markReadyBtn').toggle(order.status === 'preparing');
                
                // Set button click handlers
                $('#acceptOrderBtn').off('click').on('click', function() {
                    acceptOrder(orderId);
                    $('#orderDetailsModal').modal('hide');
                });
                
                $('#markReadyBtn').off('click').on('click', function() {
                    markOrderReady(orderId);
                    $('#orderDetailsModal').modal('hide');
                });
                
                $('#orderDetailsModal').modal('show');
            }
        }
    });
}

function acceptOrder(orderId, event = null) {
    if (event) event.stopPropagation();
    
    $.post('../api/kitchen.php', {
        action: 'accept_order',
        order_id: orderId,
        chef_id: <?php echo $chef_id; ?>
    }, function(response) {
        if (response.success) {
            showToast('Order accepted!', 'success');
            loadKitchenOrders();
        }
    });
}

function markOrderReady(orderId, event = null) {
    if (event) event.stopPropagation();
    
    $.post('../api/kitchen.php', {
        action: 'mark_ready',
        order_id: orderId,
        chef_id: <?php echo $chef_id; ?>
    }, function(response) {
        if (response.success) {
            showToast('Order marked as ready!', 'success');
            loadKitchenOrders();
        }
    });
}

function markOrderServed(orderId, event = null) {
    if (event) event.stopPropagation();
    
    $.post('../api/kitchen.php', {
        action: 'mark_served',
        order_id: orderId,
        chef_id: <?php echo $chef_id; ?>
    }, function(response) {
        if (response.success) {
            showToast('Order marked as served!', 'success');
            loadKitchenOrders();
        }
    });
}

function clockInOut() {
    $.post('../api/staff.php', {
        action: 'clock_in_out',
        staff_id: <?php echo $chef_id; ?>
    }, function(response) {
        if (response.success) {
            const btn = $('#clock-status');
            if (response.data.status === 'in') {
                btn.html('<i class="fas fa-clock"></i> Clock Out');
                showToast('Clocked in successfully!', 'success');
            } else {
                btn.html('<i class="fas fa-clock"></i> Clock In');
                showToast('Clocked out successfully!', 'info');
            }
        }
    });
}

function toggleFullscreen() {
    const elem = document.documentElement;
    if (!document.fullscreenElement) {
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const past = new Date(timestamp);
    const diff = Math.floor((now - past) / 1000); // in seconds
    
    if (diff < 60) return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    return `${Math.floor(diff / 3600)}h ago`;
}

function getElapsedTime(timestamp) {
    const now = new Date();
    const past = new Date(timestamp);
    const diff = Math.floor((now - past) / 1000 / 60); // in minutes
    
    if (diff < 60) return `${diff}m`;
    const hours = Math.floor(diff / 60);
    const minutes = diff % 60;
    return `${hours}h ${minutes}m`;
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

// Save settings
$('#settingsModal').on('hide.bs.modal', function() {
    soundEnabled = $('#sound-alert').is(':checked');
    refreshInterval = $('#refresh-interval').val() * 1000;
    
    const settings = {
        soundEnabled: soundEnabled,
        refreshInterval: refreshInterval,
        stationFilter: $('#station-filter').val()
    };
    
    localStorage.setItem('kitchen_settings', JSON.stringify(settings));
});
</script>

<style>
.kitchen-header {
    background: linear-gradient(135deg, #343a40 0%, #212529 100%);
}
.kitchen-column {
    height: calc(100vh - 300px);
    overflow-y: auto;
    padding: 15px;
}
.kitchen-ticket {
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    margin-bottom: 15px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s;
}
.kitchen-ticket:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: #007bff;
}
.kitchen-ticket.priority-high {
    border-color: #ffc107;
    background: #fff8e1;
}
.kitchen-ticket.priority-urgent {
    border-color: #dc3545;
    background: #f8d7da;
    animation: pulse 2s infinite;
}
.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}
.ticket-id {
    font-weight: bold;
    font-size: 1.2rem;
    color: #007bff;
}
.ticket-time {
    font-size: 0.9rem;
    color: #6c757d;
}
.ticket-table {
    font-weight: bold;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ticket-items {
    margin-bottom: 15px;
}
.ticket-item {
    display: flex;
    align-items: center;
    padding: 5px 0;
    border-bottom: 1px dashed #dee2e6;
}
.ticket-item:last-child {
    border-bottom: none;
}
.item-quantity {
    font-weight: bold;
    color: #007bff;
    min-width: 30px;
}
.item-name {
    flex: 1;
}
.ticket-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #dee2e6;
}
.elapsed-time {
    font-size: 0.9rem;
    color: #6c757d;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}
</style>

<?php include '../components/footer.php'; ?>