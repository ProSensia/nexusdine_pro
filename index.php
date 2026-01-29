<?php
session_start();
require_once 'config/database.php';
require_once 'config/constants.php';
$page_title = "Unified Dining Platform";
?>
<?php include 'components/header.php'; ?>

<!-- Hero Section -->
<section class="hero-section py-5 bg-gradient">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Revolutionize Your Dining Experience</h1>
                <p class="lead mb-4">NexusDine Pro bridges offline and online dining with a unified digital platform for restaurants and hotels.</p>
                <div class="d-grid gap-3 d-md-flex">
                    <a href="auth/register.php" class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-rocket me-2"></i>Get Started Free
                    </a>
                    <a href="#features" class="btn btn-outline-primary btn-lg px-4">
                        <i class="fas fa-play-circle me-2"></i>View Demo
                    </a>
                </div>
                <div class="mt-4">
                    <span class="text-muted"><i class="fas fa-check-circle text-success me-1"></i> No app store needed</span>
                    <span class="text-muted ms-3"><i class="fas fa-check-circle text-success me-1"></i> Works offline</span>
                    <span class="text-muted ms-3"><i class="fas fa-check-circle text-success me-1"></i> Real-time sync</span>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="position-relative">
                    <div class="device-mockup">
                        <div class="screen">
                            <img src="assets/images/dashboard-preview.png" class="img-fluid rounded shadow" alt="Dashboard Preview">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Powerful Features</h2>
            <p class="lead text-muted">Everything you need to manage your restaurant or hotel digitally</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-qrcode fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold">QR Code Ordering</h4>
                        <p class="text-muted">Unique QR codes for each table for instant menu access and ordering.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-wifi-slash fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold">Offline-First</h4>
                        <p class="text-muted">Full functionality without internet with automatic sync when online.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-gamepad fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold">Interactive Games</h4>
                        <p class="text-muted">Engage guests with multiplayer games while they wait for orders.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-kitchen-set fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold">Kitchen Display</h4>
                        <p class="text-muted">Real-time order tracking and management for kitchen staff.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-chart-line fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold">Advanced Analytics</h4>
                        <p class="text-muted">Comprehensive reports and insights for business optimization.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-motorcycle fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold">Delivery Management</h4>
                        <p class="text-muted">Integrated rider management with live GPS tracking.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pricing Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Simple, Transparent Pricing</h2>
            <p class="lead text-muted">Choose the perfect plan for your business</p>
        </div>
        
        <div class="row justify-content-center">
            <?php foreach ($subscription_plans as $plan_name => $plan): ?>
            <div class="col-md-4 mb-4">
                <div class="card pricing-card h-100 <?php echo $plan_name === 'pro' ? 'popular' : ''; ?>">
                    <?php if ($plan_name === 'pro'): ?>
                    <div class="card-header bg-primary text-white text-center">
                        <span class="badge bg-warning">Most Popular</span>
                    </div>
                    <?php endif; ?>
                    <div class="card-body text-center p-4">
                        <h3 class="fw-bold text-uppercase"><?php echo ucfirst($plan_name); ?></h3>
                        <div class="price mt-3">
                            <span class="h1 fw-bold">$<?php echo $plan['price']; ?></span>
                            <span class="text-muted">/month</span>
                        </div>
                        <ul class="list-unstyled mt-4 mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <?php echo $plan['tables']; ?> Tables</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <?php echo $plan['menu_items']; ?> Menu Items</li>
                            <li class="mb-2"><i class="fas <?php echo $plan['analytics'] ? 'fa-check text-success' : 'fa-times text-secondary'; ?> me-2"></i> Advanced Analytics</li>
                            <li class="mb-2"><i class="fas <?php echo $plan['branding'] ? 'fa-check text-success' : 'fa-times text-secondary'; ?> me-2"></i> White Labeling</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> 24/7 Support</li>
                        </ul>
                        <a href="auth/register.php?plan=<?php echo $plan_name; ?>" class="btn <?php echo $plan_name === 'pro' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                            Get Started
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2 class="fw-bold mb-4">Ready to Transform Your Business?</h2>
        <p class="lead mb-4">Join thousands of restaurants and hotels using NexusDine Pro</p>
        <a href="auth/register.php" class="btn btn-light btn-lg px-5">
            <i class="fas fa-calendar-check me-2"></i>Start Free Trial
        </a>
        <p class="mt-3">No credit card required • 14-day free trial</p>
    </div>
</section>

<?php include 'components/footer.php'; ?>