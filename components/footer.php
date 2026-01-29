    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">&copy; <?php echo date('Y'); ?> NexusDine Pro. All rights reserved.</span>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-muted">v<?php echo APP_VERSION; ?></span>
                </div>
            </div>
        </div>
    </footer>

    <!-- PWA Installation Prompt -->
    <div id="installPrompt" class="install-prompt">
        <div class="install-prompt-content">
            <p>Install NexusDine Pro for better experience</p>
            <button id="installBtn" class="btn btn-primary btn-sm">Install</button>
            <button id="cancelInstall" class="btn btn-secondary btn-sm">Later</button>
        </div>
    </div>

    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/offline-manager.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/sync-manager.js"></script>
    
    <?php if (file_exists('assets/js/' . basename($_SERVER['PHP_SELF'], '.php') . '.js')): ?>
    <script src="<?php echo BASE_URL; ?>assets/js/<?php echo basename($_SERVER['PHP_SELF'], '.php'); ?>.js"></script>
    <?php endif; ?>
    
    <!-- Initialize PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?php echo BASE_URL; ?>service-worker.js')
                    .then(registration => {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(err => {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
        
        // Monitor connection status
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        
        function updateOnlineStatus() {
            const status = document.getElementById('connection-status');
            if (navigator.onLine) {
                status.className = 'connection-status online';
                status.innerHTML = '<i class="fas fa-wifi"></i> Online';
                // Trigger sync
                if (window.syncOfflineData) {
                    syncOfflineData();
                }
            } else {
                status.className = 'connection-status offline';
                status.innerHTML = '<i class="fas fa-wifi-slash"></i> Offline Mode';
            }
        }
    </script>
</body>
</html>