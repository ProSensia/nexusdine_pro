<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-sticky">
    <div class="list-group list-group-flush">
        <a href="index.php" class="list-group-item list-group-item-action <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
        <a href="menu-manager.php" class="list-group-item list-group-item-action <?php echo $current_page === 'menu-manager.php' ? 'active' : ''; ?>">
            <i class="fas fa-utensils me-2"></i>Menu Manager
        </a>
        <a href="tables.php" class="list-group-item list-group-item-action <?php echo $current_page === 'tables.php' ? 'active' : ''; ?>">
            <i class="fas fa-chair me-2"></i>Tables & QR Codes
        </a>
        <a href="orders.php" class="list-group-item list-group-item-action <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
            <i class="fas fa-receipt me-2"></i>Orders
        </a>
        <a href="staff.php" class="list-group-item list-group-item-action <?php echo $current_page === 'staff.php' ? 'active' : ''; ?>">
            <i class="fas fa-users me-2"></i>Staff Management
        </a>
        <a href="analytics.php" class="list-group-item list-group-item-action <?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line me-2"></i>Analytics
        </a>
        <a href="settings.php" class="list-group-item list-group-item-action <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog me-2"></i>Settings
        </a>
        
        <div class="list-group-item">
            <h6 class="mb-1">Quick Stats</h6>
            <div class="small text-muted">
                <div class="d-flex justify-content-between">
                    <span>Today's Orders:</span>
                    <span class="fw-bold"><?php echo getDashboardStats($_SESSION['business_id'], connectDatabase())['today_orders']; ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Active Tables:</span>
                    <span class="fw-bold"><?php echo getDashboardStats($_SESSION['business_id'], connectDatabase())['active_tables']; ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Revenue:</span>
                    <span class="fw-bold">$<?php echo number_format(getDashboardStats($_SESSION['business_id'], connectDatabase())['today_revenue'], 2); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>