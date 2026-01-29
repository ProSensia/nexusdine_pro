<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$business_id = $_SESSION['business_id'];
$user_id = $_SESSION['user_id'];

// Get dashboard statistics
$stats = getDashboardStats($business_id, $conn);

$page_title = "Dashboard";
?>
<?php include '../components/header.php'; ?>
<?php include '../components/navbar.php'; ?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-2">
            <?php include '../components/sidebar.php'; ?>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-10">
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col">
                    <div class="card border-0 bg-primary text-white">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="card-title fw-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h4>
                                    <p class="card-text">Here's what's happening with your business today.</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <i class="fas fa-chart-line fa-4x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Today's Orders</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $stats['today_orders']; ?></h3>
                                </div>
                                <div class="stat-icon bg-primary">
                                    <i class="fas fa-receipt text-white"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="text-success">
                                    <i class="fas fa-arrow-up"></i> 12% from yesterday
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Active Tables</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $stats['active_tables']; ?>/<?php echo $stats['total_tables']; ?></h3>
                                </div>
                                <div class="stat-icon bg-success">
                                    <i class="fas fa-chair text-white"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="text-muted"><?php echo $stats['occupancy_rate']; ?>% occupancy</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Today's Revenue</h6>
                                    <h3 class="fw-bold mb-0">$<?php echo number_format($stats['today_revenue'], 2); ?></h3>
                                </div>
                                <div class="stat-icon bg-warning">
                                    <i class="fas fa-dollar-sign text-white"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="text-success">
                                    <i class="fas fa-arrow-up"></i> $<?php echo number_format($stats['revenue_change'], 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Pending Orders</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $stats['pending_orders']; ?></h3>
                                </div>
                                <div class="stat-icon bg-danger">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="orders.php?filter=pending" class="text-decoration-none">View all</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts and Recent Activity -->
            <div class="row">
                <!-- Revenue Chart -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Revenue Overview (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Orders -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Orders</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php 
                                $recent_orders = getRecentOrders($business_id, 5, $conn);
                                foreach ($recent_orders as $order): 
                                ?>
                                <a href="orders.php?view=<?php echo $order['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Order #<?php echo $order['id']; ?></h6>
                                        <small><?php echo time_ago($order['created_at']); ?></small>
                                    </div>
                                    <p class="mb-1">Table <?php echo $order['table_number']; ?> • $<?php echo $order['total_amount']; ?></p>
                                    <small>
                                        <span class="badge bg-<?php echo getStatusColor($order['order_status']); ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Live Floor Plan -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Live Floor Plan</h5>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary active" data-area="all">All Areas</button>
                                    <?php 
                                    $areas = getFloorAreas($business_id, $conn);
                                    foreach ($areas as $area): 
                                    ?>
                                    <button class="btn btn-sm btn-outline-primary" data-area="<?php echo $area; ?>">
                                        <?php echo $area; ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="floor-plan" class="floor-plan-container">
                                <!-- Tables will be loaded via AJAX -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Modal -->
<div class="modal fade" id="quickActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="menu-manager.php" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-utensils fa-2x mb-2"></i><br>
                            Manage Menu
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="tables.php" class="btn btn-outline-success w-100 py-3">
                            <i class="fas fa-qrcode fa-2x mb-2"></i><br>
                            Generate QR
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="staff.php" class="btn btn-outline-info w-100 py-3">
                            <i class="fas fa-users fa-2x mb-2"></i><br>
                            Staff Management
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="analytics.php" class="btn btn-outline-warning w-100 py-3">
                            <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    loadFloorPlan();
    loadRevenueChart();
    
    // Refresh floor plan every 10 seconds
    setInterval(loadFloorPlan, 10000);
    
    // Area filter
    $('[data-area]').click(function() {
        $('[data-area]').removeClass('active');
        $(this).addClass('active');
        loadFloorPlan($(this).data('area'));
    });
});

function loadFloorPlan(area = 'all') {
    $.ajax({
        url: '../api/floor-plan.php',
        method: 'GET',
        data: {
            business_id: <?php echo $business_id; ?>,
            area: area
        },
        success: function(response) {
            if (response.success) {
                $('#floor-plan').html(response.html);
            }
        }
    });
}

function loadRevenueChart() {
    $.ajax({
        url: '../api/analytics.php',
        method: 'GET',
        data: {
            action: 'revenue_chart',
            business_id: <?php echo $business_id; ?>,
            days: 7
        },
        success: function(response) {
            if (response.success) {
                const ctx = document.getElementById('revenueChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: response.data.labels,
                        datasets: [{
                            label: 'Revenue ($)',
                            data: response.data.values,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    });
}

// Quick table actions
function markTableStatus(tableId, status) {
    $.post('../api/tables.php', {
        action: 'update_status',
        table_id: tableId,
        status: status
    }, function(response) {
        if (response.success) {
            showToast('Table status updated!', 'success');
            loadFloorPlan();
        }
    });
}

function assignWaiter(tableId) {
    const waiterId = prompt('Enter waiter ID to assign:');
    if (waiterId) {
        $.post('../api/tables.php', {
            action: 'assign_waiter',
            table_id: tableId,
            waiter_id: waiterId
        }, function(response) {
            if (response.success) {
                showToast('Waiter assigned!', 'success');
                loadFloorPlan();
            }
        });
    }
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
.stat-card {
    transition: transform 0.2s;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.stat-card:hover {
    transform: translateY(-5px);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.floor-plan-container {
    min-height: 300px;
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
}
.table-item {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    margin: 10px;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}
.table-item:hover {
    transform: scale(1.05);
}
.table-available { background: #d4edda; border: 2px solid #28a745; }
.table-occupied { background: #f8d7da; border: 2px solid #dc3545; }
.table-reserved { background: #fff3cd; border: 2px solid #ffc107; }
.table-cleaning { background: #d1ecf1; border: 2px solid #17a2b8; }
</style>

<?php include '../components/footer.php'; ?>