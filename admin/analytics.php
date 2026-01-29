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

// Set default time period
$period = $_GET['period'] ?? 'week';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get analytics data
$analytics = getBusinessAnalytics($business_id, $period, $start_date, $end_date, connectDatabase());

$page_title = "Analytics Dashboard";
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
            <!-- Analytics Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Analytics Dashboard
                            </h4>
                            <p class="text-muted mb-0">Track your business performance and insights</p>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="fas fa-file-export me-2"></i>Export
                            </button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Date Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Period</label>
                            <select class="form-select" name="period" onchange="this.form.submit()">
                                <option value="day" <?php echo $period === 'day' ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                                <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $start_date; ?>"
                                   <?php echo $period !== 'custom' ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $end_date; ?>"
                                   <?php echo $period !== 'custom' ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Apply
                            </button>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <a href="analytics.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Key Metrics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50">Total Revenue</h6>
                                    <h3 class="fw-bold mb-0">$<?php echo number_format($analytics['total_revenue'], 2); ?></h3>
                                </div>
                                <div class="metric-icon">
                                    <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="mt-2">
                                <span class="small">
                                    <?php 
                                    $revenue_change = $analytics['revenue_change'];
                                    $trend_class = $revenue_change >= 0 ? 'text-success' : 'text-danger';
                                    $trend_icon = $revenue_change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                                    ?>
                                    <i class="fas <?php echo $trend_icon; ?> <?php echo $trend_class; ?>"></i>
                                    <?php echo abs($revenue_change); ?>% from previous period
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50">Total Orders</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $analytics['total_orders']; ?></h3>
                                </div>
                                <div class="metric-icon">
                                    <i class="fas fa-receipt fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="mt-2">
                                <span class="small">
                                    <?php 
                                    $orders_change = $analytics['orders_change'];
                                    $trend_class = $orders_change >= 0 ? 'text-white-50' : 'text-warning';
                                    $trend_icon = $orders_change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                                    ?>
                                    <i class="fas <?php echo $trend_icon; ?> <?php echo $trend_class; ?>"></i>
                                    <?php echo abs($orders_change); ?>% from previous period
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50">Average Order Value</h6>
                                    <h3 class="fw-bold mb-0">$<?php echo number_format($analytics['avg_order_value'], 2); ?></h3>
                                </div>
                                <div class="metric-icon">
                                    <i class="fas fa-cart-plus fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="mt-2">
                                <span class="small">
                                    <?php 
                                    $aov_change = $analytics['aov_change'];
                                    $trend_class = $aov_change >= 0 ? 'text-white-50' : 'text-warning';
                                    $trend_icon = $aov_change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                                    ?>
                                    <i class="fas <?php echo $trend_icon; ?> <?php echo $trend_class; ?>"></i>
                                    <?php echo abs($aov_change); ?>% from previous period
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50">Customer Satisfaction</h6>
                                    <h3 class="fw-bold mb-0"><?php echo number_format($analytics['avg_rating'], 1); ?>/5</h3>
                                </div>
                                <div class="metric-icon">
                                    <i class="fas fa-star fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="mt-2">
                                <span class="small">
                                    <i class="fas fa-smile"></i>
                                    <?php echo $analytics['total_reviews']; ?> reviews
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="row mb-4">
                <!-- Revenue Chart -->
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Revenue Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Order Types -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Order Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="orderTypeChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Analytics -->
            <div class="row">
                <!-- Top Selling Items -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Top Selling Items</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Category</th>
                                            <th>Quantity</th>
                                            <th>Revenue</th>
                                            <th>% of Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analytics['top_items'] as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>$<?php echo number_format($item['revenue'], 2); ?></td>
                                            <td>
                                                <div class="progress" style="height: 10px;">
                                                    <div class="progress-bar bg-success" 
                                                         style="width: <?php echo $item['percentage']; ?>%">
                                                    </div>
                                                </div>
                                                <small><?php echo number_format($item['percentage'], 1); ?>%</small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Peak Hours -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Peak Hours Analysis</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="peakHoursChart" height="200"></canvas>
                            <div class="mt-4">
                                <h6>Busiest Hours:</h6>
                                <div class="row">
                                    <?php 
                                    $peak_hours = $analytics['peak_hours'];
                                    arsort($peak_hours);
                                    $top_hours = array_slice($peak_hours, 0, 3, true);
                                    ?>
                                    <?php foreach ($top_hours as $hour => $count): ?>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center py-2">
                                                <h5 class="mb-0"><?php echo $hour; ?>:00</h5>
                                                <p class="text-muted mb-0"><?php echo $count; ?> orders</p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Staff Performance -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Staff Performance</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Staff Member</th>
                                            <th>Role</th>
                                            <th>Orders Served</th>
                                            <th>Revenue Generated</th>
                                            <th>Avg. Service Time</th>
                                            <th>Customer Rating</th>
                                            <th>Efficiency</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analytics['staff_performance'] as $staff): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar me-3">
                                                        <i class="fas fa-user-circle fa-2x text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($staff['name']); ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getRoleColor($staff['role']); ?>">
                                                    <?php echo ucfirst($staff['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $staff['orders_served']; ?></td>
                                            <td>$<?php echo number_format($staff['revenue_generated'], 2); ?></td>
                                            <td><?php echo $staff['avg_service_time']; ?> min</td>
                                            <td>
                                                <div class="star-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $staff['avg_rating'] ? 'text-warning' : 'text-secondary'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-2"><?php echo number_format($staff['avg_rating'], 1); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 10px;">
                                                    <div class="progress-bar bg-<?php echo getEfficiencyColor($staff['efficiency']); ?>" 
                                                         style="width: <?php echo $staff['efficiency']; ?>%">
                                                    </div>
                                                </div>
                                                <small><?php echo number_format($staff['efficiency'], 1); ?>%</small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Advanced Analytics Filter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="advancedFilterForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Time Range</label>
                            <select class="form-select" id="timeRange">
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="last7">Last 7 Days</option>
                                <option value="last30">Last 30 Days</option>
                                <option value="month">This Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="year">This Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">From</label>
                            <input type="date" class="form-control" id="customFrom" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To</label>
                            <input type="date" class="form-control" id="customTo" disabled>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Order Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="typeDineIn" checked>
                                <label class="form-check-label" for="typeDineIn">Dine In</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="typeTakeaway" checked>
                                <label class="form-check-label" for="typeTakeaway">Takeaway</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="typeDelivery" checked>
                                <label class="form-check-label" for="typeDelivery">Delivery</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Order Status</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="statusCompleted" checked>
                                <label class="form-check-label" for="statusCompleted">Completed</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="statusCancelled">
                                <label class="form-check-label" for="statusCancelled">Cancelled</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="statusRefunded">
                                <label class="form-check-label" for="statusRefunded">Refunded</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Staff Member</label>
                            <select class="form-select" id="staffFilter">
                                <option value="all">All Staff</option>
                                <!-- Staff options will be loaded via AJAX -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Table/Area</label>
                            <select class="form-select" id="tableFilter">
                                <option value="all">All Tables/Areas</option>
                                <!-- Table options will be loaded via AJAX -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sort By</label>
                        <select class="form-select" id="sortBy">
                            <option value="revenue">Revenue</option>
                            <option value="orders">Number of Orders</option>
                            <option value="rating">Customer Rating</option>
                            <option value="time">Service Time</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyAdvancedFilters()">
                    <i class="fas fa-filter me-2"></i>Apply Filters
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Analytics Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Export Format</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="exportFormat" id="formatCSV" checked>
                        <label class="form-check-label" for="formatCSV">CSV (Excel)</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="exportFormat" id="formatPDF">
                        <label class="form-check-label" for="formatPDF">PDF Report</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="exportFormat" id="formatJSON">
                        <label class="form-check-label" for="formatJSON">JSON Data</label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Include Data</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="includeRevenue" checked>
                        <label class="form-check-label" for="includeRevenue">Revenue Data</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="includeOrders" checked>
                        <label class="form-check-label" for="includeOrders">Order Details</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="includeStaff" checked>
                        <label class="form-check-label" for="includeStaff">Staff Performance</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="includeMenu" checked>
                        <label class="form-check-label" for="includeMenu">Menu Performance</label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Time Period</label>
                    <select class="form-select" id="exportPeriod">
                        <option value="current">Current Filter</option>
                        <option value="last7">Last 7 Days</option>
                        <option value="last30">Last 30 Days</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="exportAnalyticsData()">
                    <i class="fas fa-download me-2"></i>Export Data
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Initialize charts
    initializeCharts();
    
    // Load filter options
    loadFilterOptions();
    
    // Time range selector
    $('#timeRange').change(function() {
        const value = $(this).val();
        const customFrom = $('#customFrom');
        const customTo = $('#customTo');
        
        if (value === 'custom') {
            customFrom.prop('disabled', false);
            customTo.prop('disabled', false);
        } else {
            customFrom.prop('disabled', true);
            customTo.prop('disabled', true);
            
            // Set default dates based on selection
            const today = new Date();
            let fromDate = new Date();
            
            switch(value) {
                case 'today':
                    fromDate.setDate(today.getDate());
                    break;
                case 'yesterday':
                    fromDate.setDate(today.getDate() - 1);
                    break;
                case 'last7':
                    fromDate.setDate(today.getDate() - 7);
                    break;
                case 'last30':
                    fromDate.setDate(today.getDate() - 30);
                    break;
                case 'month':
                    fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    break;
                case 'last_month':
                    fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    const toDate = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
                case 'year':
                    fromDate = new Date(today.getFullYear(), 0, 1);
                    break;
            }
        }
    });
});

function initializeCharts() {
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($analytics['revenue_data'], 'date')); ?>,
            datasets: [{
                label: 'Revenue ($)',
                data: <?php echo json_encode(array_column($analytics['revenue_data'], 'revenue')); ?>,
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
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '$' + context.parsed.y.toFixed(2);
                        }
                    }
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
    
    // Order Type Chart
    const orderTypeCtx = document.getElementById('orderTypeChart').getContext('2d');
    new Chart(orderTypeCtx, {
        type: 'doughnut',
        data: {
            labels: ['Dine In', 'Takeaway', 'Delivery'],
            datasets: [{
                data: [
                    <?php echo $analytics['order_type']['dine_in']; ?>,
                    <?php echo $analytics['order_type']['takeaway']; ?>,
                    <?php echo $analytics['order_type']['delivery']; ?>
                ],
                backgroundColor: [
                    'rgb(54, 162, 235)',
                    'rgb(255, 205, 86)',
                    'rgb(255, 99, 132)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Peak Hours Chart
    const peakHoursCtx = document.getElementById('peakHoursChart').getContext('2d');
    const hours = <?php echo json_encode(array_keys($analytics['peak_hours'])); ?>;
    const counts = <?php echo json_encode(array_values($analytics['peak_hours'])); ?>;
    
    new Chart(peakHoursCtx, {
        type: 'bar',
        data: {
            labels: hours,
            datasets: [{
                label: 'Number of Orders',
                data: counts,
                backgroundColor: 'rgba(153, 102, 255, 0.7)',
                borderColor: 'rgb(153, 102, 255)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Hour of Day'
                    }
                }
            }
        }
    });
}

function loadFilterOptions() {
    // Load staff options
    $.ajax({
        url: '../api/staff.php',
        method: 'GET',
        data: {
            action: 'get_staff_list',
            business_id: <?php echo $business_id; ?>
        },
        success: function(response) {
            if (response.success) {
                const staffFilter = $('#staffFilter');
                response.data.forEach(staff => {
                    staffFilter.append(new Option(staff.name, staff.id));
                });
            }
        }
    });
    
    // Load table options
    $.ajax({
        url: '../api/tables.php',
        method: 'GET',
        data: {
            action: 'get_table_list',
            business_id: <?php echo $business_id; ?>
        },
        success: function(response) {
            if (response.success) {
                const tableFilter = $('#tableFilter');
                response.data.forEach(table => {
                    const label = table.area ? `${table.table_number} (${table.area})` : table.table_number;
                    tableFilter.append(new Option(label, table.id));
                });
            }
        }
    });
}

function applyAdvancedFilters() {
    const filters = {
        time_range: $('#timeRange').val(),
        custom_from: $('#customFrom').val(),
        custom_to: $('#customTo').val(),
        order_types: {
            dine_in: $('#typeDineIn').is(':checked'),
            takeaway: $('#typeTakeaway').is(':checked'),
            delivery: $('#typeDelivery').is(':checked')
        },
        order_statuses: {
            completed: $('#statusCompleted').is(':checked'),
            cancelled: $('#statusCancelled').is(':checked'),
            refunded: $('#statusRefunded').is(':checked')
        },
        staff_id: $('#staffFilter').val(),
        table_id: $('#tableFilter').val(),
        sort_by: $('#sortBy').val()
    };
    
    // Convert to URL parameters
    const params = new URLSearchParams();
    params.append('advanced', '1');
    params.append('filters', JSON.stringify(filters));
    
    // Reload page with filters
    window.location.search = params.toString();
}

function exportAnalyticsData() {
    const format = $('input[name="exportFormat"]:checked').attr('id').replace('format', '').toLowerCase();
    const period = $('#exportPeriod').val();
    
    const include = {
        revenue: $('#includeRevenue').is(':checked'),
        orders: $('#includeOrders').is(':checked'),
        staff: $('#includeStaff').is(':checked'),
        menu: $('#includeMenu').is(':checked')
    };
    
    // Generate export URL
    let exportUrl = `../api/export.php?type=analytics&format=${format}&period=${period}`;
    exportUrl += `&include=${JSON.stringify(include)}`;
    exportUrl += `&business_id=<?php echo $business_id; ?>`;
    
    // Add current filters if any
    const currentParams = new URLSearchParams(window.location.search);
    if (currentParams.toString()) {
        exportUrl += `&${currentParams.toString()}`;
    }
    
    // Trigger download
    window.open(exportUrl, '_blank');
    
    // Close modal
    $('#exportModal').modal('hide');
    
    showToast('Export started. Your file will download shortly.', 'success');
}

function getRoleColor(role) {
    switch(role) {
        case 'admin': return 'danger';
        case 'manager': return 'info';
        case 'chef': return 'warning';
        case 'waiter': return 'primary';
        case 'rider': return 'success';
        default: return 'secondary';
    }
}

function getEfficiencyColor(efficiency) {
    if (efficiency >= 90) return 'success';
    if (efficiency >= 75) return 'info';
    if (efficiency >= 60) return 'warning';
    return 'danger';
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
.metric-icon {
    opacity: 0.8;
}
.star-rating {
    display: inline-flex;
    align-items: center;
}
.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<?php include '../components/footer.php'; ?>