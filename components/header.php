<?php
// Check if user is online/offline
$isOnline = isset($_SESSION['online_status']) ? $_SESSION['online_status'] : true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="NexusDine Pro - Unified Hotel & Restaurant Digital Platform">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#007bff">
    <link rel="manifest" href="manifest.json">
    
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>NexusDine Pro</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    
    <!-- PWA CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/pwa.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Online Status Indicator -->
    <div id="connection-status" class="connection-status <?php echo $isOnline ? 'online' : 'offline'; ?>">
        <i class="fas fa-<?php echo $isOnline ? 'wifi' : 'wifi-slash'; ?>"></i>
        <?php echo $isOnline ? 'Online' : 'Offline Mode'; ?>
    </div>
</head>
<body>
    <?php if (isset($_SESSION['business_id'])): ?>
    <!-- Business Custom Theme -->
    <style>
        :root {
            --primary-color: <?php echo $_SESSION['theme_color'] ?? '#007bff'; ?>;
        }
    </style>
    <?php endif; ?>