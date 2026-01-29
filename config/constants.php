<?php
// Application Constants
define('APP_NAME', 'NexusDine Pro');
define('APP_VERSION', '1.0.0');
define('MAX_OFFLINE_ORDERS', 50);
define('SESSION_TIMEOUT', 3600); // 1 hour
define('ORDER_TIMEOUT', 1800); // 30 minutes

// Subscription Plans
$subscription_plans = [
    'basic' => [
        'tables' => 10,
        'menu_items' => 50,
        'analytics' => false,
        'branding' => false,
        'price' => 49.99
    ],
    'pro' => [
        'tables' => 50,
        'menu_items' => 200,
        'analytics' => true,
        'branding' => true,
        'price' => 99.99
    ],
    'enterprise' => [
        'tables' => 999,
        'menu_items' => 999,
        'analytics' => true,
        'branding' => true,
        'price' => 199.99
    ]
];
?>