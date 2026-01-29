<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['user_role'] ?? 'guest';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>">
            <i class="fas fa-utensils me-2"></i>NexusDine Pro
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto">
                <?php if ($user_role === 'admin' || $user_role === 'manager'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>admin/">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'menu-manager.php' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>admin/menu-manager.php">
                        <i class="fas fa-utensils"></i> Menu
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'tables.php' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>admin/tables.php">
                        <i class="fas fa-chair"></i> Tables
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>admin/orders.php">
                        <i class="fas fa-receipt"></i> Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'staff.php' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>admin/staff.php">
                        <i class="fas fa-users"></i> Staff
                    </a>
                </li>
                <?php elseif ($user_role === 'waiter'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>staff/">
                        <i class="fas fa-tasks"></i> Tasks
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>staff/kitchen.php">
                        <i class="fas fa-utensils"></i> Kitchen View
                    </a>
                </li>
                <?php elseif ($user_role === 'chef'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'kitchen.php' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>staff/kitchen.php">
                        <i class="fas fa-fire"></i> Kitchen Display
                    </a>
                </li>
                <?php elseif ($user_role === 'customer'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'menu.php' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>customer/menu.php">
                        <i class="fas fa-book"></i> Menu
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'cart.php' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>customer/cart.php">
                        <i class="fas fa-shopping-cart"></i> Cart
                        <span id="cart-count" class="badge bg-danger">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'games.php' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>customer/games.php">
                        <i class="fas fa-gamepad"></i> Games
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> 
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                        <?php if ($user_role === 'admin'): ?>
                        <span class="badge bg-warning">Admin</span>
                        <?php elseif ($user_role === 'manager'): ?>
                        <span class="badge bg-info">Manager</span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>auth/login.php">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>