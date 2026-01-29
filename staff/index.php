<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if staff is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['waiter', 'admin', 'manager'])) {
    header('Location: ../auth/login.php');
    exit();
}

$staff_id = $_SESSION['user_id'];
$business_id = $_SESSION['business_id'];

// Get assigned tasks
$tasks = getStaffTasks($staff_id, $business_id, $conn);

$page_title = "Waiter Dashboard";
?>
<?php include '../components/header.php'; ?>
<?php include '../components/navbar.php'; ?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-user-tie fa-4x text-primary"></i>
                    </div>
                    <h5 class="card-title"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h5>
                    <p class="text-muted">Waiter</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="clockInOut()">
                            <i class="fas fa-clock"></i> 
                            <span id="clock-status">Clock In</span>
                        </button>
                        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#helpRequestsModal">
                            <i class="fas fa-bell"></i> Help Requests
                            <span id="help-count" class="badge bg-danger">0</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Assigned Tables -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Assigned Tables</h6>
                </div>
                <div class="card-body">
                    <div id="assigned-tables">
                        <!-- Tables loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Task Dashboard -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Today's Tasks</h5>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary active" data-filter="all">All</button>
                            <button class="btn btn-sm btn-outline-primary" data-filter="pending">Pending</button>
                            <button class="btn btn-sm btn-outline-primary" data-filter="completed">Completed</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row" id="tasks-container">
                        <?php foreach ($tasks as $task): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card task-card border-<?php echo getTaskPriorityColor($task['priority']); ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="card-title mb-1">
                                                <i class="fas fa-<?php echo getTaskIcon($task['task_type']); ?> me-2"></i>
                                                <?php echo htmlspecialchars($task['title']); ?>
                                            </h6>
                                            <p class="text-muted small mb-0">Table <?php echo $task['table_number']; ?></p>
                                        </div>
                                        <span class="badge bg-<?php echo getTaskPriorityColor($task['priority']); ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </div>
                                    
                                    <p class="card-text small"><?php echo htmlspecialchars($task['description']); ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo time_ago($task['created_at']); ?>
                                        </small>
                                        <div class="btn-group">
                                            <?php if ($task['status'] === 'pending'): ?>
                                            <button class="btn btn-sm btn-success" onclick="markTaskComplete(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-check"></i> Complete
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="viewTaskDetails(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php else: ?>
                                            <span class="badge bg-success">Completed</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($tasks)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <h5>No tasks assigned</h5>
                        <p class="text-muted">You're all caught up!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Queue -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Ready to Serve</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Table</th>
                                    <th>Items</th>
                                    <th>Ready Time</th>
                                    <th>Wait Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="ready-orders">
                                <!-- Orders loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Task Details Modal -->
<div class="modal fade" id="taskDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalTitle">Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="taskModalContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="completeTaskBtn">Mark Complete</button>
            </div>
        </div>
    </div>
</div>

<!-- Help Requests Modal -->
<div class="modal fade" id="helpRequestsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Customer Help Requests</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="help-requests-container">
                    <!-- Help requests loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    loadAssignedTables();
    loadReadyOrders();
    loadHelpRequests();
    
    // Refresh data every 15 seconds
    setInterval(function() {
        loadReadyOrders();
        loadHelpRequests();
    }, 15000);
    
    // Task filtering
    $('[data-filter]').click(function() {
        $('[data-filter]').removeClass('active');
        $(this).addClass('active');
        
        const filter = $(this).data('filter');
        $('.task-card').show();
        
        if (filter !== 'all') {
            $('.task-card').each(function() {
                const status = $(this).find('.badge').text().toLowerCase();
                if (filter === 'pending' && status === 'completed') {
                    $(this).hide();
                } else if (filter === 'completed' && status !== 'completed') {
                    $(this).hide();
                }
            });
        }
    });
});

function loadAssignedTables() {
    $.ajax({
        url: '../api/staff.php',
        method: 'GET',
        data: {
            action: 'assigned_tables',
            staff_id: <?php echo $staff_id; ?>
        },
        success: function(response) {
            if (response.success) {
                $('#assigned-tables').html(response.html);
            }
        }
    });
}

function loadReadyOrders() {
    $.ajax({
        url: '../api/staff.php',
        method: 'GET',
        data: {
            action: 'ready_orders',
            business_id: <?php echo $business_id; ?>
        },
        success: function(response) {
            if (response.success) {
                $('#ready-orders').html(response.html);
            }
        }
    });
}

function loadHelpRequests() {
    $.ajax({
        url: '../api/staff.php',
        method: 'GET',
        data: {
            action: 'help_requests',
            business_id: <?php echo $business_id; ?>
        },
        success: function(response) {
            if (response.success) {
                $('#help-requests-container').html(response.html);
                $('#help-count').text(response.count || 0);
            }
        }
    });
}

function markTaskComplete(taskId) {
    $.post('../api/staff.php', {
        action: 'complete_task',
        task_id: taskId,
        staff_id: <?php echo $staff_id; ?>
    }, function(response) {
        if (response.success) {
            showToast('Task marked as complete!', 'success');
            // Reload tasks
            location.reload();
        }
    });
}

function viewTaskDetails(taskId) {
    $.ajax({
        url: '../api/staff.php',
        method: 'GET',
        data: {
            action: 'task_details',
            task_id: taskId
        },
        success: function(response) {
            if (response.success) {
                $('#taskModalTitle').text(response.data.title);
                $('#taskModalContent').html(response.data.html);
                $('#completeTaskBtn').off('click').on('click', function() {
                    markTaskComplete(taskId);
                    $('#taskDetailsModal').modal('hide');
                });
                $('#taskDetailsModal').modal('show');
            }
        }
    });
}

function serveOrder(orderId) {
    $.post('../api/staff.php', {
        action: 'serve_order',
        order_id: orderId,
        staff_id: <?php echo $staff_id; ?>
    }, function(response) {
        if (response.success) {
            showToast('Order marked as served!', 'success');
            loadReadyOrders();
        }
    });
}

function acknowledgeHelp(requestId) {
    $.post('../api/staff.php', {
        action: 'acknowledge_help',
        request_id: requestId,
        staff_id: <?php echo $staff_id; ?>
    }, function(response) {
        if (response.success) {
            showToast('Request acknowledged!', 'success');
            loadHelpRequests();
        }
    });
}

function clockInOut() {
    $.post('../api/staff.php', {
        action: 'clock_in_out',
        staff_id: <?php echo $staff_id; ?>
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
</script>

<style>
.task-card {
    transition: transform 0.2s;
}
.task-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}
.table-item-small {
    width: 60px;
    height: 60px;
    margin: 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-weight: bold;
}
</style>

<?php include '../components/footer.php'; ?>