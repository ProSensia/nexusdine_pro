<?php
session_start();
require_once '../config/database.php';

// If business ID is passed via QR code
if (isset($_GET['business_id']) && isset($_GET['table_id'])) {
    $_SESSION['business_id'] = intval($_GET['business_id']);
    $_SESSION['table_id'] = intval($_GET['table_id']);
    
    // Generate session token for customer
    if (!isset($_SESSION['customer_token'])) {
        $_SESSION['customer_token'] = bin2hex(random_bytes(16));
    }
    
    // Redirect to menu
    header('Location: menu.php');
    exit();
}

$page_title = "Scan QR Code";
?>
<?php include '../components/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="qr-scanner-icon mb-3">
                            <i class="fas fa-qrcode fa-6x text-primary"></i>
                        </div>
                        <h1 class="h3 fw-bold">Scan Table QR Code</h1>
                        <p class="text-muted">Point your camera at the QR code on your table to view the menu and place orders</p>
                    </div>
                    
                    <div id="qr-scanner-container" class="mb-4">
                        <div class="scanner-frame">
                            <video id="qr-video" class="w-100 rounded" playsinline></video>
                            <div class="scanner-overlay">
                                <div class="scan-line"></div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <button id="toggle-camera" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-camera-rotate"></i> Switch Camera
                            </button>
                            <button id="upload-qr" class="btn btn-outline-secondary btn-sm ms-2">
                                <i class="fas fa-upload"></i> Upload QR
                            </button>
                        </div>
                    </div>
                    
                    <div id="manual-input" class="d-none">
                        <div class="mb-3">
                            <label for="business-code" class="form-label">Business Code</label>
                            <input type="text" class="form-control" id="business-code" placeholder="Enter 6-digit code">
                        </div>
                        <div class="mb-3">
                            <label for="table-number" class="form-label">Table Number</label>
                            <input type="text" class="form-control" id="table-number" placeholder="Table/Room number">
                        </div>
                        <button id="manual-submit" class="btn btn-primary w-100">Continue</button>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="#" id="toggle-input" class="text-decoration-none">
                            <i class="fas fa-keyboard"></i> Enter details manually
                        </a>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-info-circle fa-2x"></i>
                            </div>
                            <div>
                                <h6 class="alert-heading">How it works</h6>
                                <p class="mb-0 small">Scan the QR code on your table to access the digital menu, place orders, play games, and request assistance - all from your phone!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="games.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-gamepad"></i> Play Games
                        </a>
                    </div>
                    <div class="col-6">
                        <button id="request-waiter" class="btn btn-outline-warning w-100">
                            <i class="fas fa-bell"></i> Call Waiter
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
<script>
$(document).ready(function() {
    let scanner = null;
    let currentCamera = 0;
    let cameras = [];
    
    // Initialize QR Scanner
    Instascan.Camera.getCameras().then(function(cameraList) {
        cameras = cameraList;
        if (cameras.length > 0) {
            startScanner(currentCamera);
        } else {
            $('#qr-scanner-container').html(
                '<div class="alert alert-warning text-center">No camera found. Please enable camera access or use manual input.</div>'
            );
            $('#manual-input').removeClass('d-none');
        }
    }).catch(function(e) {
        console.error(e);
        $('#manual-input').removeClass('d-none');
    });
    
    function startScanner(cameraIndex) {
        if (scanner) {
            scanner.stop();
        }
        
        scanner = new Instascan.Scanner({
            video: document.getElementById('qr-video'),
            mirror: false
        });
        
        scanner.addListener('scan', function(content) {
            try {
                const data = JSON.parse(content);
                if (data.business_id && data.table_id) {
                    window.location.href = `menu.php?business_id=${data.business_id}&table_id=${data.table_id}`;
                }
            } catch (e) {
                console.error('Invalid QR code:', e);
                showToast('Invalid QR code. Please scan a valid table QR.', 'error');
            }
        });
        
        Instascan.Camera.getCameras().then(function(cameras) {
            if (cameras.length > 0) {
                scanner.start(cameras[cameraIndex]);
            }
        });
    }
    
    // Toggle camera
    $('#toggle-camera').click(function() {
        currentCamera = (currentCamera + 1) % cameras.length;
        startScanner(currentCamera);
    });
    
    // Toggle manual input
    $('#toggle-input').click(function(e) {
        e.preventDefault();
        $('#manual-input').toggleClass('d-none');
        if ($('#manual-input').hasClass('d-none')) {
            $(this).html('<i class="fas fa-keyboard"></i> Enter details manually');
            if (scanner) scanner.start(cameras[currentCamera]);
        } else {
            $(this).html('<i class="fas fa-qrcode"></i> Use QR Scanner');
            if (scanner) scanner.stop();
        }
    });
    
    // Manual submit
    $('#manual-submit').click(function() {
        const businessCode = $('#business-code').val();
        const tableNumber = $('#table-number').val();
        
        if (!businessCode || !tableNumber) {
            showToast('Please enter both business code and table number', 'error');
            return;
        }
        
        // In production, this would be an API call
        window.location.href = `menu.php?business_code=${businessCode}&table=${tableNumber}`;
    });
    
    // Request waiter
    $('#request-waiter').click(function() {
        if (navigator.onLine) {
            $.post('../api/request-help.php', {
                type: 'waiter',
                table_id: <?php echo $_SESSION['table_id'] ?? 'null'; ?>,
                business_id: <?php echo $_SESSION['business_id'] ?? 'null'; ?>
            }, function(response) {
                showToast('Waiter has been notified!', 'success');
            });
        } else {
            // Store offline
            const offlineRequests = JSON.parse(localStorage.getItem('offline_requests') || '[]');
            offlineRequests.push({
                type: 'waiter',
                table_id: <?php echo $_SESSION['table_id'] ?? 'null'; ?>,
                timestamp: new Date().toISOString()
            });
            localStorage.setItem('offline_requests', JSON.stringify(offlineRequests));
            showToast('Request saved offline. Waiter will be notified when online.', 'info');
        }
    });
    
    function showToast(message, type = 'info') {
        // Create toast element
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);
        
        $('.toast-container').append(toast);
        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
        
        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
});
</script>

<style>
.qr-scanner-icon {
    animation: pulse 2s infinite;
}
.scanner-frame {
    position: relative;
    border: 2px solid #007bff;
    border-radius: 10px;
    overflow: hidden;
    background: #000;
}
.scanner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}
.scan-line {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, transparent, #00ff00, transparent);
    animation: scan 2s linear infinite;
}
@keyframes scan {
    0% { top: 0; }
    100% { top: 100%; }
}
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}
</style>

<?php include '../components/footer.php'; ?>