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

// Get business info
$conn = connectDatabase();
$business = getBusinessInfo($business_id, $conn);

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_business'])) {
        $business_name = trim($_POST['business_name'] ?? '');
        $business_type = $_POST['business_type'] ?? 'restaurant';
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $theme_color = $_POST['theme_color'] ?? '#007bff';
        
        // Handle logo upload
        $logo_url = $business['logo_url'] ?? '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $upload_dir = '../assets/uploads/logos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['logo']['name']);
            $target_file = $upload_dir . $file_name;
            
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($imageFileType, $allowed_types)) {
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                    // Delete old logo if exists
                    if (!empty($logo_url) && file_exists('../' . $logo_url)) {
                        unlink('../' . $logo_url);
                    }
                    $logo_url = 'assets/uploads/logos/' . $file_name;
                }
            }
        }
        
        $stmt = $conn->prepare("
            UPDATE businesses 
            SET business_name = ?, business_type = ?, email = ?, phone = ?, 
                address = ?, logo_url = ?, theme_color = ?
            WHERE id = ?
        ");
        
        $stmt->bind_param("sssssssi", 
            $business_name, $business_type, $email, $phone, 
            $address, $logo_url, $theme_color, $business_id
        );
        
        if ($stmt->execute()) {
            // Update session variables
            $_SESSION['business_name'] = $business_name;
            $_SESSION['theme_color'] = $theme_color;
            
            $message = 'Business settings updated successfully';
            logActivity($user_id, 'update_business_settings', "Updated business profile");
        } else {
            $error = 'Failed to update business settings: ' . $stmt->error;
        }
        $stmt->close();
        
    } elseif (isset($_POST['update_subscription'])) {
        $plan = $_POST['subscription_plan'] ?? 'basic';
        
        $stmt = $conn->prepare("
            UPDATE businesses 
            SET subscription_plan = ?, 
                subscription_expiry = DATE_ADD(NOW(), INTERVAL 1 MONTH)
            WHERE id = ?
        ");
        
        $stmt->bind_param("si", $plan, $business_id);
        
        if ($stmt->execute()) {
            $message = 'Subscription plan updated successfully';
            logActivity($user_id, 'update_subscription', "Changed to $plan plan");
        } else {
            $error = 'Failed to update subscription plan';
        }
        $stmt->close();
        
    } elseif (isset($_POST['update_notifications'])) {
        $notifications = json_encode([
            'new_order' => isset($_POST['notify_new_order']) ? 1 : 0,
            'order_ready' => isset($_POST['notify_order_ready']) ? 1 : 0,
            'low_stock' => isset($_POST['notify_low_stock']) ? 1 : 0,
            'negative_review' => isset($_POST['notify_negative_review']) ? 1 : 0,
            'daily_report' => isset($_POST['notify_daily_report']) ? 1 : 0
        ]);
        
        $stmt = $conn->prepare("UPDATE businesses SET notifications = ? WHERE id = ?");
        $stmt->bind_param("si", $notifications, $business_id);
        
        if ($stmt->execute()) {
            $message = 'Notification settings updated';
            logActivity($user_id, 'update_notifications', "Updated notification preferences");
        } else {
            $error = 'Failed to update notification settings';
        }
        $stmt->close();
        
    } elseif (isset($_POST['update_hours'])) {
        $hours = json_encode([
            'monday' => [
                'open' => $_POST['mon_open'] ?? '09:00',
                'close' => $_POST['mon_close'] ?? '22:00',
                'closed' => isset($_POST['mon_closed']) ? 1 : 0
            ],
            'tuesday' => [
                'open' => $_POST['tue_open'] ?? '09:00',
                'close' => $_POST['tue_close'] ?? '22:00',
                'closed' => isset($_POST['tue_closed']) ? 1 : 0
            ],
            'wednesday' => [
                'open' => $_POST['wed_open'] ?? '09:00',
                'close' => $_POST['wed_close'] ?? '22:00',
                'closed' => isset($_POST['wed_closed']) ? 1 : 0
            ],
            'thursday' => [
                'open' => $_POST['thu_open'] ?? '09:00',
                'close' => $_POST['thu_close'] ?? '22:00',
                'closed' => isset($_POST['thu_closed']) ? 1 : 0
            ],
            'friday' => [
                'open' => $_POST['fri_open'] ?? '09:00',
                'close' => $_POST['fri_close'] ?? '22:00',
                'closed' => isset($_POST['fri_closed']) ? 1 : 0
            ],
            'saturday' => [
                'open' => $_POST['sat_open'] ?? '10:00',
                'close' => $_POST['sat_close'] ?? '23:00',
                'closed' => isset($_POST['sat_closed']) ? 1 : 0
            ],
            'sunday' => [
                'open' => $_POST['sun_open'] ?? '10:00',
                'close' => $_POST['sun_close'] ?? '22:00',
                'closed' => isset($_POST['sun_closed']) ? 1 : 0
            ]
        ]);
        
        $stmt = $conn->prepare("UPDATE businesses SET operating_hours = ? WHERE id = ?");
        $stmt->bind_param("si", $hours, $business_id);
        
        if ($stmt->execute()) {
            $message = 'Operating hours updated';
            logActivity($user_id, 'update_hours', "Updated business hours");
        } else {
            $error = 'Failed to update operating hours';
        }
        $stmt->close();
    }
}

// Get subscription info
$subscription = getSubscriptionInfo($business_id, $conn);

// Parse notification settings
$notifications = json_decode($business['notifications'] ?? '{}', true);

// Parse operating hours
$operating_hours = json_decode($business['operating_hours'] ?? '{}', true);
$default_hours = [
    'monday' => ['open' => '09:00', 'close' => '22:00', 'closed' => 0],
    'tuesday' => ['open' => '09:00', 'close' => '22:00', 'closed' => 0],
    'wednesday' => ['open' => '09:00', 'close' => '22:00', 'closed' => 0],
    'thursday' => ['open' => '09:00', 'close' => '22:00', 'closed' => 0],
    'friday' => ['open' => '09:00', 'close' => '22:00', 'closed' => 0],
    'saturday' => ['open' => '10:00', 'close' => '23:00', 'closed' => 0],
    'sunday' => ['open' => '10:00', 'close' => '22:00', 'closed' => 0]
];
$operating_hours = array_merge($default_hours, $operating_hours);

$conn->close();

$page_title = "Settings";
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
            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Settings Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0">
                                <i class="fas fa-cog me-2"></i>
                                Settings
                            </h4>
                            <p class="text-muted mb-0">Configure your business preferences</p>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary" onclick="backupData()">
                                <i class="fas fa-download me-2"></i>Backup
                            </button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#helpModal">
                                <i class="fas fa-question-circle me-2"></i>Help
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Settings Tabs -->
            <div class="card">
                <div class="card-body">
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="business-tab" data-bs-toggle="tab" data-bs-target="#business">
                                <i class="fas fa-building me-2"></i>Business
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="subscription-tab" data-bs-toggle="tab" data-bs-target="#subscription">
                                <i class="fas fa-credit-card me-2"></i>Subscription
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications">
                                <i class="fas fa-bell me-2"></i>Notifications
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="hours-tab" data-bs-toggle="tab" data-bs-target="#hours">
                                <i class="fas fa-clock me-2"></i>Hours
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="integration-tab" data-bs-toggle="tab" data-bs-target="#integration">
                                <i class="fas fa-plug me-2"></i>Integrations
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security">
                                <i class="fas fa-shield-alt me-2"></i>Security
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content p-3" id="settingsTabsContent">
                        <!-- Business Settings -->
                        <div class="tab-pane fade show active" id="business" role="tabpanel">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Business Name *</label>
                                            <input type="text" class="form-control" name="business_name" 
                                                   value="<?php echo htmlspecialchars($business['business_name'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Business Type</label>
                                            <select class="form-select" name="business_type">
                                                <option value="restaurant" <?php echo ($business['business_type'] ?? '') === 'restaurant' ? 'selected' : ''; ?>>Restaurant</option>
                                                <option value="hotel" <?php echo ($business['business_type'] ?? '') === 'hotel' ? 'selected' : ''; ?>>Hotel</option>
                                                <option value="both" <?php echo ($business['business_type'] ?? '') === 'both' ? 'selected' : ''; ?>>Both</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo htmlspecialchars($business['email'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" name="phone" 
                                                   value="<?php echo htmlspecialchars($business['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Business Address</label>
                                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($business['address'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Theme Color</label>
                                            <div class="input-group">
                                                <input type="color" class="form-control form-control-color" name="theme_color" 
                                                       value="<?php echo htmlspecialchars($business['theme_color'] ?? '#007bff'); ?>">
                                                <span class="input-group-text">
                                                    <?php echo htmlspecialchars($business['theme_color'] ?? '#007bff'); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Business Logo</label>
                                            <input type="file" class="form-control" name="logo" accept="image/*">
                                            <?php if (!empty($business['logo_url'])): ?>
                                            <div class="mt-2">
                                                <img src="<?php echo BASE_URL . $business['logo_url']; ?>" 
                                                     class="img-thumbnail" style="max-height: 100px;">
                                                <div class="form-check mt-2">
                                                    <input class="form-check-input" type="checkbox" name="remove_logo" value="1">
                                                    <label class="form-check-label">Remove current logo</label>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Timezone</label>
                                            <select class="form-select" name="timezone">
                                                <option value="America/New_York" <?php echo ($business['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time (ET)</option>
                                                <option value="America/Chicago" <?php echo ($business['timezone'] ?? '') === 'America/Chicago' ? 'selected' : ''; ?>>Central Time (CT)</option>
                                                <option value="America/Denver" <?php echo ($business['timezone'] ?? '') === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time (MT)</option>
                                                <option value="America/Los_Angeles" <?php echo ($business['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time (PT)</option>
                                                <option value="UTC" <?php echo ($business['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="update_business" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Business Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Subscription Settings -->
                        <div class="tab-pane fade" id="subscription" role="tabpanel">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Current Plan</h5>
                                            
                                            <div class="row mb-4">
                                                <div class="col-md-6">
                                                    <div class="plan-card bg-primary text-white">
                                                        <div class="card-body">
                                                            <h3 class="fw-bold"><?php echo ucfirst($subscription['subscription_plan'] ?? 'basic'); ?></h3>
                                                            <p class="mb-0">Current Plan</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="plan-card bg-success text-white">
                                                        <div class="card-body">
                                                            <h3 class="fw-bold">
                                                                <?php 
                                                                $expiry = strtotime($subscription['subscription_expiry'] ?? '');
                                                                $days_left = ceil(($expiry - time()) / (60 * 60 * 24));
                                                                echo max(0, $days_left);
                                                                ?>
                                                            </h3>
                                                            <p class="mb-0">Days Remaining</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <form method="POST" action="">
                                                <div class="mb-3">
                                                    <label class="form-label">Change Subscription Plan</label>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="radio" name="subscription_plan" 
                                                               value="basic" id="planBasic" 
                                                               <?php echo ($subscription['subscription_plan'] ?? '') === 'basic' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="planBasic">
                                                            <strong>Basic</strong> - $49.99/month
                                                            <p class="text-muted small mb-0">10 tables, 50 menu items, basic analytics</p>
                                                        </label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="radio" name="subscription_plan" 
                                                               value="pro" id="planPro" 
                                                               <?php echo ($subscription['subscription_plan'] ?? '') === 'pro' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="planPro">
                                                            <strong>Pro</strong> - $99.99/month
                                                            <p class="text-muted small mb-0">50 tables, 200 menu items, advanced analytics, white-labeling</p>
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="subscription_plan" 
                                                               value="enterprise" id="planEnterprise" 
                                                               <?php echo ($subscription['subscription_plan'] ?? '') === 'enterprise' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="planEnterprise">
                                                            <strong>Enterprise</strong> - $199.99/month
                                                            <p class="text-muted small mb-0">Unlimited tables and menu items, all features, priority support</p>
                                                        </label>
                                                    </div>
                                                </div>
                                                
                                                <div class="text-end">
                                                    <button type="submit" name="update_subscription" class="btn btn-primary">
                                                        <i class="fas fa-credit-card me-2"></i>Update Plan
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Billing Information</h5>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Payment Method</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">
                                                        <i class="fab fa-cc-visa"></i>
                                                    </span>
                                                    <input type="text" class="form-control" value="**** **** **** 1234" disabled>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Next Billing Date</label>
                                                <input type="text" class="form-control" 
                                                       value="<?php echo date('F j, Y', strtotime($subscription['subscription_expiry'] ?? '')); ?>" disabled>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Total Spent</label>
                                                <h3 class="text-primary">$<?php 
                                                    $months = 1; // Default
                                                    if ($subscription['subscription_plan'] === 'basic') {
                                                        echo number_format($months * 49.99, 2);
                                                    } elseif ($subscription['subscription_plan'] === 'pro') {
                                                        echo number_format($months * 99.99, 2);
                                                    } else {
                                                        echo number_format($months * 199.99, 2);
                                                    }
                                                ?></h3>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#billingModal">
                                                    <i class="fas fa-file-invoice-dollar me-2"></i>View Invoices
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notification Settings -->
                        <div class="tab-pane fade" id="notifications" role="tabpanel">
                            <form method="POST" action="">
                                <h5 class="mb-3">Email Notifications</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6 class="card-title">Order Notifications</h6>
                                                
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox" name="notify_new_order" 
                                                           id="notifyNewOrder" <?php echo ($notifications['new_order'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notifyNewOrder">
                                                        New Order Received
                                                    </label>
                                                </div>
                                                
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox" name="notify_order_ready" 
                                                           id="notifyOrderReady" <?php echo ($notifications['order_ready'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notifyOrderReady">
                                                        Order Ready for Pickup
                                                    </label>
                                                </div>
                                                
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="notify_order_cancelled" 
                                                           id="notifyOrderCancelled" <?php echo ($notifications['order_cancelled'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notifyOrderCancelled">
                                                        Order Cancelled
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6 class="card-title">System Notifications</h6>
                                                
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox" name="notify_low_stock" 
                                                           id="notifyLowStock" <?php echo ($notifications['low_stock'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notifyLowStock">
                                                        Low Stock Alerts
                                                    </label>
                                                </div>
                                                
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox" name="notify_negative_review" 
                                                           id="notifyNegativeReview" <?php echo ($notifications['negative_review'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notifyNegativeReview">
                                                        Negative Reviews
                                                    </label>
                                                </div>
                                                
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="notify_daily_report" 
                                                           id="notifyDailyReport" <?php echo ($notifications['daily_report'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notifyDailyReport">
                                                        Daily Sales Report
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Notification Preferences</h6>
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Email Address</label>
                                                    <input type="email" class="form-control" 
                                                           value="<?php echo htmlspecialchars($business['email'] ?? ''); ?>" disabled>
                                                    <small class="form-text text-muted">Primary notification email</small>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">SMS Notifications</label>
                                                    <input type="tel" class="form-control" 
                                                           value="<?php echo htmlspecialchars($business['phone'] ?? ''); ?>" 
                                                           placeholder="Enter phone number for SMS">
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Notification Frequency</label>
                                                    <select class="form-select" name="notification_frequency">
                                                        <option value="immediate" <?php echo ($business['notification_frequency'] ?? '') === 'immediate' ? 'selected' : ''; ?>>Immediate</option>
                                                        <option value="hourly" <?php echo ($business['notification_frequency'] ?? '') === 'hourly' ? 'selected' : ''; ?>>Hourly Digest</option>
                                                        <option value="daily" <?php echo ($business['notification_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily Digest</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end mt-3">
                                    <button type="submit" name="update_notifications" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Notification Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Operating Hours -->
                        <div class="tab-pane fade" id="hours" role="tabpanel">
                            <form method="POST" action="">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Set Operating Hours</h5>
                                        <p class="text-muted">Define when your business is open for orders</p>
                                        
                                        <div class="operating-hours">
                                            <?php 
                                            $days = [
                                                'monday' => 'Monday',
                                                'tuesday' => 'Tuesday',
                                                'wednesday' => 'Wednesday',
                                                'thursday' => 'Thursday',
                                                'friday' => 'Friday',
                                                'saturday' => 'Saturday',
                                                'sunday' => 'Sunday'
                                            ];
                                            
                                            foreach ($days as $key => $day):
                                                $hours = $operating_hours[$key] ?? ['open' => '09:00', 'close' => '22:00', 'closed' => 0];
                                            ?>
                                            <div class="row mb-3 align-items-center">
                                                <div class="col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="<?php echo $key; ?>_closed" 
                                                               id="<?php echo $key; ?>Closed"
                                                               <?php echo $hours['closed'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="<?php echo $key; ?>Closed">
                                                            <strong><?php echo $day; ?></strong>
                                                        </label>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <label class="form-label">Opening Time</label>
                                                    <input type="time" class="form-control" 
                                                           name="<?php echo $key; ?>_open" 
                                                           value="<?php echo $hours['open']; ?>"
                                                           <?php echo $hours['closed'] ? 'disabled' : ''; ?>>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <label class="form-label">Closing Time</label>
                                                    <input type="time" class="form-control" 
                                                           name="<?php echo $key; ?>_close" 
                                                           value="<?php echo $hours['close']; ?>"
                                                           <?php echo $hours['closed'] ? 'disabled' : ''; ?>>
                                                </div>
                                                
                                                <div class="col-md-1">
                                                    <div class="form-text text-center mt-4">
                                                        <?php if (!$hours['closed']): ?>
                                                        <?php echo $hours['open']; ?> - <?php echo $hours['close']; ?>
                                                        <?php else: ?>
                                                        <span class="text-danger">Closed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <h6>Special Hours</h6>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                You can set special hours for holidays or events. Contact support to configure special hours.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end mt-3">
                                    <button type="submit" name="update_hours" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Operating Hours
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Integrations -->
                        <div class="tab-pane fade" id="integration" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fab fa-paypal me-2"></i>
                                                Payment Gateways
                                            </h5>
                                            <p class="text-muted">Configure payment integrations</p>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="paypalEnabled" checked>
                                                <label class="form-check-label" for="paypalEnabled">
                                                    PayPal Integration
                                                </label>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="stripeEnabled" checked>
                                                <label class="form-check-label" for="stripeEnabled">
                                                    Stripe Integration
                                                </label>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="razorpayEnabled">
                                                <label class="form-check-label" for="razorpayEnabled">
                                                    Razorpay Integration
                                                </label>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#paymentSettingsModal">
                                                    <i class="fas fa-cog me-2"></i>Configure Payment Settings
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-truck me-2"></i>
                                                Delivery Services
                                            </h5>
                                            <p class="text-muted">Connect with delivery partners</p>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="uberEatsEnabled">
                                                <label class="form-check-label" for="uberEatsEnabled">
                                                    Uber Eats Integration
                                                </label>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="doordashEnabled">
                                                <label class="form-check-label" for="doordashEnabled">
                                                    DoorDash Integration
                                                </label>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="grubhubEnabled">
                                                <label class="form-check-label" for="grubhubEnabled">
                                                    Grubhub Integration
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-chart-bar me-2"></i>
                                                Analytics & Marketing
                                            </h5>
                                            <p class="text-muted">Connect with analytics tools</p>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="googleAnalyticsEnabled" checked>
                                                <label class="form-check-label" for="googleAnalyticsEnabled">
                                                    Google Analytics
                                                </label>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Google Analytics ID</label>
                                                <input type="text" class="form-control" placeholder="UA-XXXXXXXXX-X">
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="facebookPixelEnabled">
                                                <label class="form-check-label" for="facebookPixelEnabled">
                                                    Facebook Pixel
                                                </label>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Facebook Pixel ID</label>
                                                <input type="text" class="form-control" placeholder="XXXXXXXXXXXXXXX">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-sync-alt me-2"></i>
                                                POS Integration
                                            </h5>
                                            <p class="text-muted">Connect with your existing POS system</p>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">POS System</label>
                                                <select class="form-select" id="posSystem">
                                                    <option value="">Select POS System</option>
                                                    <option value="square">Square</option>
                                                    <option value="clover">Clover</option>
                                                    <option value="lightspeed">Lightspeed</option>
                                                    <option value="shopify">Shopify POS</option>
                                                    <option value="custom">Custom API</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">API Key</label>
                                                <input type="password" class="form-control" placeholder="Enter API Key">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">API Secret</label>
                                                <input type="password" class="form-control" placeholder="Enter API Secret">
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button class="btn btn-outline-primary">
                                                    <i class="fas fa-link me-2"></i>Test Connection
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end mt-3">
                                <button class="btn btn-primary" onclick="saveIntegrations()">
                                    <i class="fas fa-save me-2"></i>Save Integration Settings
                                </button>
                            </div>
                        </div>
                        
                        <!-- Security Settings -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-user-shield me-2"></i>
                                                Account Security
                                            </h5>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Two-Factor Authentication</label>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="twoFactorEnabled">
                                                    <label class="form-check-label" for="twoFactorEnabled">
                                                        Enable 2FA for all admin accounts
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Session Timeout</label>
                                                <select class="form-select" id="sessionTimeout">
                                                    <option value="15">15 minutes</option>
                                                    <option value="30" selected>30 minutes</option>
                                                    <option value="60">1 hour</option>
                                                    <option value="120">2 hours</option>
                                                    <option value="480">8 hours</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Failed Login Attempts</label>
                                                <input type="number" class="form-control" value="5" min="1" max="10">
                                                <small class="form-text text-muted">Number of attempts before account lockout</small>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button class="btn btn-outline-primary" onclick="updateSecuritySettings()">
                                                    <i class="fas fa-shield-alt me-2"></i>Update Security Settings
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-history me-2"></i>
                                                Activity Log
                                            </h5>
                                            <p class="text-muted">Recent security events</p>
                                            
                                            <div class="activity-log">
                                                <?php
                                                $conn = connectDatabase();
                                                $stmt = $conn->prepare("
                                                    SELECT * FROM activity_logs 
                                                    WHERE user_id = ? 
                                                    ORDER BY created_at DESC 
                                                    LIMIT 5
                                                ");
                                                $stmt->bind_param("i", $user_id);
                                                $stmt->execute();
                                                $result = $stmt->get_result();
                                                
                                                while ($log = $result->fetch_assoc()):
                                                ?>
                                                <div class="activity-item mb-2 pb-2 border-bottom">
                                                    <div class="d-flex justify-content-between">
                                                        <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                                                        <span class="text-muted small"><?php echo time_ago($log['created_at']); ?></span>
                                                    </div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($log['details']); ?></div>
                                                    <div class="small">
                                                        <i class="fas fa-globe me-1"></i>
                                                        <?php echo htmlspecialchars($log['ip_address']); ?>
                                                    </div>
                                                </div>
                                                <?php endwhile; ?>
                                                $stmt->close();
                                                $conn->close();
                                                ?>
                                            </div>
                                            
                                            <div class="text-center mt-3">
                                                <a href="activity-log.php" class="btn btn-sm btn-outline-secondary">
                                                    View Full Activity Log
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-key me-2"></i>
                                                Change Password
                                            </h5>
                                            
                                            <form id="changePasswordForm">
                                                <div class="mb-3">
                                                    <label class="form-label">Current Password</label>
                                                    <input type="password" class="form-control" id="currentPassword" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">New Password</label>
                                                    <input type="password" class="form-control" id="newPassword" required minlength="8">
                                                    <div class="password-strength mt-1">
                                                        <div class="progress" style="height: 5px;">
                                                            <div class="progress-bar" id="passwordStrengthBar" style="width: 0%;"></div>
                                                        </div>
                                                        <small id="passwordStrengthText" class="form-text"></small>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Confirm New Password</label>
                                                    <input type="password" class="form-control" id="confirmPassword" required>
                                                    <div class="form-text" id="passwordMatch"></div>
                                                </div>
                                                
                                                <div class="d-grid">
                                                    <button type="button" class="btn btn-primary" onclick="changePassword()">
                                                        <i class="fas fa-key me-2"></i>Change Password
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-users me-2"></i>
                                                Access Control
                                            </h5>
                                            <p class="text-muted">Manage user permissions</p>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Admin Access</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="allowMultipleAdmins" checked>
                                                    <label class="form-check-label" for="allowMultipleAdmins">
                                                        Allow multiple admin accounts
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">IP Whitelist</label>
                                                <textarea class="form-control" rows="3" placeholder="Enter allowed IP addresses (one per line)"></textarea>
                                                <small class="form-text text-muted">Leave empty to allow all IPs</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">API Access</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="enableApiAccess">
                                                    <label class="form-check-label" for="enableApiAccess">
                                                        Enable API access for third-party apps
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button class="btn btn-outline-primary">
                                                    <i class="fas fa-save me-2"></i>Save Access Settings
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Billing Modal -->
<div class="modal fade" id="billingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Billing History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice #</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo date('M j, Y'); ?></td>
                                <td>INV-<?php echo $business_id; ?>-001</td>
                                <td><?php echo ucfirst($subscription['subscription_plan'] ?? 'basic'); ?></td>
                                <td>
                                    <?php
                                    if (($subscription['subscription_plan'] ?? '') === 'basic') {
                                        echo '$49.99';
                                    } elseif (($subscription['subscription_plan'] ?? '') === 'pro') {
                                        echo '$99.99';
                                    } else {
                                        echo '$199.99';
                                    }
                                    ?>
                                </td>
                                <td><span class="badge bg-success">Paid</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i> Download
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Settings Modal -->
<div class="modal fade" id="paymentSettingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Gateway Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">PayPal Client ID</label>
                    <input type="text" class="form-control" placeholder="Enter PayPal Client ID">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">PayPal Secret</label>
                    <input type="password" class="form-control" placeholder="Enter PayPal Secret">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Stripe Publishable Key</label>
                    <input type="text" class="form-control" placeholder="pk_live_...">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Stripe Secret Key</label>
                    <input type="password" class="form-control" placeholder="sk_live_...">
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="testMode">
                    <label class="form-check-label" for="testMode">
                        Enable Test Mode
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Save Settings</button>
            </div>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-question-circle me-2"></i>
                    Settings Help
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="accordion" id="helpAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#help1">
                                Business Settings
                            </button>
                        </h2>
                        <div id="help1" class="accordion-collapse collapse show" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                Configure your business name, contact information, and appearance settings.
                                The theme color will be used throughout the customer and staff interfaces.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help2">
                                Subscription Management
                            </button>
                        </h2>
                        <div id="help2" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                View your current plan, billing information, and upgrade or downgrade your subscription.
                                All changes take effect immediately.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help3">
                                Security Settings
                            </button>
                        </h2>
                        <div id="help3" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                Enhance your account security with two-factor authentication, password policies,
                                and access control settings.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="mailto:support@nexusdine.com" class="btn btn-primary">
                    <i class="fas fa-envelope me-2"></i>Contact Support
                </a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Toggle time inputs based on closed checkbox
    $('input[type="checkbox"][name$="_closed"]').change(function() {
        const day = $(this).attr('name').replace('_closed', '');
        const openInput = $(`input[name="${day}_open"]`);
        const closeInput = $(`input[name="${day}_close"]`);
        
        if ($(this).is(':checked')) {
            openInput.prop('disabled', true);
            closeInput.prop('disabled', true);
        } else {
            openInput.prop('disabled', false);
            closeInput.prop('disabled', false);
        }
    });
    
    // Theme color preview
    $('input[name="theme_color"]').change(function() {
        const color = $(this).val();
        $(this).next('.input-group-text').text(color);
    });
    
    // Password strength checker
    $('#newPassword').on('input', function() {
        checkPasswordStrength($(this).val());
    });
    
    // Password confirmation
    $('#confirmPassword').on('input', function() {
        const newPassword = $('#newPassword').val();
        const confirmPassword = $(this).val();
        
        if (confirmPassword === '') {
            $('#passwordMatch').html('');
        } else if (newPassword === confirmPassword) {
            $('#passwordMatch').html('<i class="fas fa-check text-success"></i> Passwords match');
        } else {
            $('#passwordMatch').html('<i class="fas fa-times text-danger"></i> Passwords do not match');
        }
    });
});

function checkPasswordStrength(password) {
    let strength = 0;
    const bar = $('#passwordStrengthBar');
    const text = $('#passwordStrengthText');
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    let width = strength * 20;
    let color = 'danger';
    let message = 'Very Weak';
    
    if (strength === 2) {
        color = 'warning';
        message = 'Weak';
    } else if (strength === 3) {
        color = 'info';
        message = 'Good';
    } else if (strength === 4) {
        color = 'primary';
        message = 'Strong';
    } else if (strength >= 5) {
        color = 'success';
        message = 'Very Strong';
    }
    
    bar.css('width', width + '%').removeClass('bg-danger bg-warning bg-info bg-primary bg-success').addClass('bg-' + color);
    text.text(message).removeClass('text-danger text-warning text-info text-primary text-success').addClass('text-' + color);
}

function changePassword() {
    const currentPassword = $('#currentPassword').val();
    const newPassword = $('#newPassword').val();
    const confirmPassword = $('#confirmPassword').val();
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        showToast('Please fill in all password fields', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }
    
    if (newPassword.length < 8) {
        showToast('Password must be at least 8 characters', 'error');
        return;
    }
    
    $.ajax({
        url: '../api/settings.php',
        method: 'POST',
        data: {
            action: 'change_password',
            current_password: currentPassword,
            new_password: newPassword
        },
        success: function(response) {
            if (response.success) {
                showToast('Password changed successfully', 'success');
                $('#changePasswordForm')[0].reset();
                $('#passwordStrengthBar').css('width', '0%');
                $('#passwordStrengthText').text('');
                $('#passwordMatch').html('');
            } else {
                showToast('Failed to change password: ' + response.message, 'error');
            }
        },
        error: function() {
            showToast('Failed to change password. Please try again.', 'error');
        }
    });
}

function updateSecuritySettings() {
    const twoFactorEnabled = $('#twoFactorEnabled').is(':checked');
    const sessionTimeout = $('#sessionTimeout').val();
    const allowMultipleAdmins = $('#allowMultipleAdmins').is(':checked');
    
    $.ajax({
        url: '../api/settings.php',
        method: 'POST',
        data: {
            action: 'update_security',
            two_factor: twoFactorEnabled ? 1 : 0,
            session_timeout: sessionTimeout,
            multiple_admins: allowMultipleAdmins ? 1 : 0
        },
        success: function(response) {
            if (response.success) {
                showToast('Security settings updated', 'success');
            } else {
                showToast('Failed to update settings: ' + response.message, 'error');
            }
        }
    });
}

function saveIntegrations() {
    // Collect integration settings
    const integrations = {
        paypal: $('#paypalEnabled').is(':checked'),
        stripe: $('#stripeEnabled').is(':checked'),
        razorpay: $('#razorpayEnabled').is(':checked'),
        google_analytics: $('#googleAnalyticsEnabled').is(':checked'),
        facebook_pixel: $('#facebookPixelEnabled').is(':checked')
    };
    
    $.ajax({
        url: '../api/settings.php',
        method: 'POST',
        data: {
            action: 'update_integrations',
            integrations: JSON.stringify(integrations)
        },
        success: function(response) {
            if (response.success) {
                showToast('Integration settings saved', 'success');
            } else {
                showToast('Failed to save settings: ' + response.message, 'error');
            }
        }
    });
}

function backupData() {
    if (confirm('Create a backup of all your business data? This may take a moment.')) {
        $.ajax({
            url: '../api/backup.php',
            method: 'POST',
            data: {
                business_id: <?php echo $business_id; ?>
            },
            success: function(response) {
                if (response.success) {
                    // Trigger download
                    const link = document.createElement('a');
                    link.href = response.data.url;
                    link.download = response.data.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showToast('Backup created and downloaded', 'success');
                } else {
                    showToast('Failed to create backup: ' + response.message, 'error');
                }
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
.plan-card {
    border-radius: 10px;
    text-align: center;
    padding: 20px;
    transition: transform 0.2s;
}
.plan-card:hover {
    transform: translateY(-5px);
}
.operating-hours .form-check {
    margin-bottom: 0;
}
.password-strength .progress {
    background-color: #e9ecef;
}
.activity-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0;
    padding-bottom: 0;
}
</style>

<?php include '../components/footer.php'; ?>