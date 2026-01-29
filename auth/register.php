<?php
session_start();
require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_name = trim($_POST['business_name'] ?? '');
    $business_type = $_POST['business_type'] ?? 'restaurant';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $plan = $_POST['plan'] ?? 'basic';
    
    // Validation
    if (empty($business_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        $conn = connectDatabase();
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM businesses WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = 'Email already registered';
            $stmt->close();
        } else {
            $stmt->close();
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert business
                $stmt = $conn->prepare("
                    INSERT INTO businesses 
                    (business_name, business_type, email, phone, subscription_plan, subscription_expiry)
                    VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 14 DAY))
                ");
                
                $stmt->bind_param("sssss", $business_name, $business_type, $email, $phone, $plan);
                $stmt->execute();
                $business_id = $stmt->insert_id;
                $stmt->close();
                
                // Create admin staff member
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $admin_permissions = json_encode([
                    'manage_menu' => true,
                    'manage_staff' => true,
                    'view_reports' => true,
                    'manage_tables' => true,
                    'manage_orders' => true,
                    'manage_settings' => true
                ]);
                
                $stmt = $conn->prepare("
                    INSERT INTO staff 
                    (business_id, name, email, password, role, permissions)
                    VALUES (?, ?, ?, ?, 'admin', ?)
                ");
                
                $admin_name = "Admin - $business_name";
                $stmt->bind_param("issss", $business_id, $admin_name, $email, $hashed_password, $admin_permissions);
                $stmt->execute();
                $staff_id = $stmt->insert_id;
                $stmt->close();
                
                // Create default tables
                for ($i = 1; $i <= 5; $i++) {
                    $stmt = $conn->prepare("
                        INSERT INTO tables (business_id, table_number, table_type, capacity)
                        VALUES (?, ?, 'dining', 4)
                    ");
                    $table_number = "T$i";
                    $stmt->bind_param("is", $business_id, $table_number);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Create default menu categories
                $default_categories = ['Appetizers', 'Main Course', 'Desserts', 'Beverages', 'Specials'];
                foreach ($default_categories as $category) {
                    $stmt = $conn->prepare("
                        INSERT INTO menu_categories (business_id, category_name)
                        VALUES (?, ?)
                    ");
                    $stmt->bind_param("is", $business_id, $category);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Commit transaction
                $conn->commit();
                
                // Auto login
                $_SESSION['user_id'] = $staff_id;
                $_SESSION['user_name'] = $admin_name;
                $_SESSION['user_role'] = 'admin';
                $_SESSION['business_id'] = $business_id;
                $_SESSION['business_name'] = $business_name;
                
                $success = 'Registration successful! Redirecting...';
                
                // Redirect after 2 seconds
                header("refresh:2;url=../admin/");
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
        $conn->close();
    }
}

$page_title = "Register Business";
?>
<?php include '../components/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <h1 class="h2 fw-bold">Start Your Free Trial</h1>
                        <p class="text-muted">Get 14 days free on all plans. No credit card required.</p>
                    </div>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="registrationForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="business_name" class="form-label">Business Name *</label>
                                    <input type="text" class="form-control" id="business_name" name="business_name" 
                                           value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>" 
                                           required placeholder="Your Restaurant/Hotel Name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="business_type" class="form-label">Business Type *</label>
                                    <select class="form-select" id="business_type" name="business_type" required>
                                        <option value="restaurant" <?php echo ($_POST['business_type'] ?? '') === 'restaurant' ? 'selected' : ''; ?>>Restaurant</option>
                                        <option value="hotel" <?php echo ($_POST['business_type'] ?? '') === 'hotel' ? 'selected' : ''; ?>>Hotel</option>
                                        <option value="both" <?php echo ($_POST['business_type'] ?? '') === 'both' ? 'selected' : ''; ?>>Both Restaurant & Hotel</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           required placeholder="admin@yourbusiness.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                           placeholder="+1 (555) 123-4567">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required minlength="8">
                                    <div class="form-text">Minimum 8 characters</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <div class="form-text" id="password-match"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Select Plan *</label>
                            <div class="row">
                                <?php
                                $plans = [
                                    'basic' => ['price' => '$49.99', 'tables' => 10, 'items' => 50],
                                    'pro' => ['price' => '$99.99', 'tables' => 50, 'items' => 200],
                                    'enterprise' => ['price' => '$199.99', 'tables' => 'Unlimited', 'items' => 'Unlimited']
                                ];
                                
                                foreach ($plans as $plan_key => $plan_details):
                                ?>
                                <div class="col-md-4 mb-2">
                                    <div class="card plan-card <?php echo $plan_key === 'pro' ? 'border-primary' : ''; ?>">
                                        <div class="card-body text-center">
                                            <h5 class="card-title"><?php echo ucfirst($plan_key); ?></h5>
                                            <h3 class="text-primary"><?php echo $plan_details['price']; ?></h3>
                                            <p class="small text-muted">per month</p>
                                            <ul class="list-unstyled small">
                                                <li><?php echo $plan_details['tables']; ?> Tables</li>
                                                <li><?php echo $plan_details['items']; ?> Menu Items</li>
                                                <li>24/7 Support</li>
                                            </ul>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="plan" 
                                                       value="<?php echo $plan_key; ?>" 
                                                       id="plan_<?php echo $plan_key; ?>" 
                                                       <?php echo ($_POST['plan'] ?? 'basic') === $plan_key ? 'checked' : ''; ?>
                                                       <?php echo $plan_key === 'pro' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="plan_<?php echo $plan_key; ?>">
                                                    Select <?php echo $plan_key === 'pro' ? 'Recommended' : ''; ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" class="text-decoration-none">Terms of Service</a> and <a href="#" class="text-decoration-none">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-rocket me-2"></i>Start Free Trial
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <p class="text-muted">
                                Already have an account? 
                                <a href="login.php" class="text-decoration-none">Sign In</a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="mt-4">
                <div class="alert alert-info">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-shield-alt fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="alert-heading">Secure & Reliable</h6>
                            <p class="mb-0 small">Your data is encrypted and secured. We never store your credit card information.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Password confirmation check
    $('#confirm_password').on('keyup', function() {
        const password = $('#password').val();
        const confirm = $(this).val();
        
        if (password === confirm && password.length >= 8) {
            $('#password-match').html('<i class="fas fa-check text-success"></i> Passwords match');
        } else if (password.length < 8) {
            $('#password-match').html('<i class="fas fa-times text-danger"></i> Password too short');
        } else {
            $('#password-match').html('<i class="fas fa-times text-danger"></i> Passwords do not match');
        }
    });
    
    // Plan selection styling
    $('.plan-card').click(function() {
        $('.plan-card').removeClass('border-primary');
        $(this).addClass('border-primary');
        $(this).find('input[type="radio"]').prop('checked', true);
    });
    
    // Form validation
    $('#registrationForm').submit(function(e) {
        const password = $('#password').val();
        const confirm = $('#confirm_password').val();
        
        if (password !== confirm) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        
        if (password.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters!');
            return false;
        }
        
        return true;
    });
});
</script>

<style>
.plan-card {
    cursor: pointer;
    transition: all 0.3s;
    height: 100%;
}
.plan-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.plan-card.border-primary {
    border-width: 2px;
}
</style>

<?php include '../components/footer.php'; ?>