<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if customer session exists
if (!isset($_SESSION['customer_token'])) {
    header('Location: index.php');
    exit();
}

$business_id = $_SESSION['business_id'] ?? 0;
$table_id = $_SESSION['table_id'] ?? 0;
$session_token = $_SESSION['customer_token'] ?? '';

// Get cart from localStorage via JavaScript
// We'll handle cart operations via JavaScript and sync with server on checkout

$page_title = "Your Cart";
?>
<?php include '../components/header.php'; ?>

<div class="container py-4">
    <div class="row">
        <!-- Cart Items -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-white border-bottom-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-shopping-cart text-primary me-2"></i>
                            Your Order
                        </h4>
                        <span class="badge bg-primary" id="cart-item-count">0 items</span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="cart-items-container">
                        <!-- Cart items loaded via JavaScript -->
                        <div class="text-center py-5" id="empty-cart">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <h4>Your cart is empty</h4>
                            <p class="text-muted">Add items from the menu to get started</p>
                            <a href="menu.php" class="btn btn-primary mt-3">
                                <i class="fas fa-utensils me-2"></i>Browse Menu
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Special Instructions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-sticky-note me-2"></i>
                        Special Instructions
                    </h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" id="special-instructions" rows="3" 
                              placeholder="Any special requests or instructions for the kitchen?"></textarea>
                    <div class="form-text mt-2">
                        <i class="fas fa-info-circle"></i> 
                        Please mention any allergies or dietary restrictions
                    </div>
                </div>
            </div>
            
            <!-- Multiplayer Games -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-gamepad me-2"></i>
                        Play While You Wait
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Enjoy these games while waiting for your order:</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="games.php?game=trivia" class="btn btn-outline-primary w-100">
                                <i class="fas fa-question-circle fa-2x mb-2"></i><br>
                                Trivia
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="games.php?game=puzzle" class="btn btn-outline-success w-100">
                                <i class="fas fa-puzzle-piece fa-2x mb-2"></i><br>
                                Puzzle
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="games.php?game=cards" class="btn btn-outline-warning w-100">
                                <i class="fas fa-heart fa-2x mb-2"></i><br>
                                Card Game
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Summary & Checkout -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-receipt me-2"></i>
                        Order Summary
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Order Details -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Table</span>
                            <span class="fw-bold"><?php echo getTableNumber($table_id, connectDatabase()); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Order Type</span>
                            <select class="form-select form-select-sm w-auto" id="order-type">
                                <option value="dine_in" selected>Dine In</option>
                                <option value="takeaway">Takeaway</option>
                                <option value="delivery">Delivery</option>
                            </select>
                        </div>
                        
                        <!-- Delivery Address (hidden by default) -->
                        <div id="delivery-address" class="mt-3 d-none">
                            <label class="form-label">Delivery Address</label>
                            <textarea class="form-control form-control-sm" id="delivery-address-text" 
                                      rows="2" placeholder="Enter your delivery address"></textarea>
                        </div>
                    </div>
                    
                    <!-- Price Breakdown -->
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal</span>
                            <span id="subtotal-amount">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Tax (10%)</span>
                            <span id="tax-amount">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Service Charge</span>
                            <span id="service-charge">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Discount</span>
                            <span class="text-success" id="discount-amount">-$0.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-bold">Total</span>
                            <span class="fw-bold fs-5 text-primary" id="total-amount">$0.00</span>
                        </div>
                        
                        <!-- Split Bill Options -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="split-bill">
                                <label class="form-check-label" for="split-bill">
                                    <i class="fas fa-users me-1"></i> Split Bill
                                </label>
                            </div>
                            <div id="split-options" class="mt-2 d-none">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Split by</span>
                                    <select class="form-select" id="split-method">
                                        <option value="equally">Equally</option>
                                        <option value="items">By Items</option>
                                    </select>
                                    <input type="number" class="form-control" id="split-people" 
                                           value="2" min="2" max="10" placeholder="People">
                                </div>
                                <div class="mt-2 text-center" id="split-result"></div>
                            </div>
                        </div>
                        
                        <!-- Checkout Button -->
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-lg" id="checkout-btn" disabled>
                                <i class="fas fa-paper-plane me-2"></i>
                                Place Order
                            </button>
                            <button class="btn btn-outline-secondary" id="save-for-later">
                                <i class="fas fa-save me-2"></i>
                                Save for Later
                            </button>
                        </div>
                        
                        <!-- Payment Options -->
                        <div class="mt-4">
                            <p class="text-muted small mb-2">
                                <i class="fas fa-lock me-1"></i>
                                Secure payment options
                            </p>
                            <div class="d-flex justify-content-around">
                                <i class="fab fa-cc-visa fa-2x text-primary"></i>
                                <i class="fab fa-cc-mastercard fa-2x text-danger"></i>
                                <i class="fab fa-cc-amex fa-2x text-info"></i>
                                <i class="fab fa-cc-paypal fa-2x text-warning"></i>
                                <i class="fas fa-university fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Help & Support -->
            <div class="card mt-4">
                <div class="card-body text-center">
                    <h6 class="card-title">
                        <i class="fas fa-question-circle me-2"></i>
                        Need Help?
                    </h6>
                    <p class="text-muted small mb-3">Our staff is ready to assist you</p>
                    <button class="btn btn-outline-warning w-100 mb-2" onclick="requestHelp('waiter')">
                        <i class="fas fa-user-tie me-2"></i>Call Waiter
                    </button>
                    <button class="btn btn-outline-info w-100" data-bs-toggle="modal" data-bs-target="#faqModal">
                        <i class="fas fa-info-circle me-2"></i>FAQs
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Complete Your Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Payment Tabs -->
                <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="card-tab" data-bs-toggle="tab" data-bs-target="#card">
                            <i class="fas fa-credit-card me-2"></i>Card
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="upi-tab" data-bs-toggle="tab" data-bs-target="#upi">
                            <i class="fas fa-mobile-alt me-2"></i>UPI
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="wallet-tab" data-bs-toggle="tab" data-bs-target="#wallet">
                            <i class="fas fa-wallet me-2"></i>Wallet
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="cash-tab" data-bs-toggle="tab" data-bs-target="#cash">
                            <i class="fas fa-money-bill-wave me-2"></i>Pay at Counter
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content p-3" id="paymentTabsContent">
                    <!-- Card Payment -->
                    <div class="tab-pane fade show active" id="card" role="tabpanel">
                        <form id="card-form">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Card Number</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="1234 5678 9012 3456" 
                                               maxlength="19" id="card-number">
                                        <span class="input-group-text">
                                            <i class="fas fa-credit-card"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Card Holder Name</label>
                                    <input type="text" class="form-control" placeholder="John Doe">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="text" class="form-control" placeholder="MM/YY">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">CVV</label>
                                    <input type="text" class="form-control" placeholder="123" maxlength="3">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Amount</label>
                                    <input type="text" class="form-control" id="card-amount" readonly>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="save-card">
                                <label class="form-check-label" for="save-card">
                                    Save card for future payments
                                </label>
                            </div>
                        </form>
                    </div>
                    
                    <!-- UPI Payment -->
                    <div class="tab-pane fade" id="upi" role="tabpanel">
                        <div class="text-center py-4">
                            <i class="fas fa-qrcode fa-4x text-primary mb-3"></i>
                            <h5>Scan to Pay</h5>
                            <p class="text-muted">Scan this QR code with any UPI app</p>
                            <div class="qr-code-placeholder bg-light p-4 d-inline-block">
                                <!-- QR code would be generated here -->
                                <div class="text-center">
                                    <div class="mb-2">UPI QR Code</div>
                                    <div class="text-muted small">Amount: <span id="upi-amount">$0.00</span></div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="text-muted">Or enter UPI ID:</p>
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" placeholder="yourname@upi">
                                    <button class="btn btn-primary">Pay Now</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Wallet Payment -->
                    <div class="tab-pane fade" id="wallet" role="tabpanel">
                        <div class="text-center py-4">
                            <h5>Select Wallet</h5>
                            <div class="row g-3 mt-3">
                                <div class="col-4">
                                    <button class="btn btn-outline-primary w-100 py-3">
                                        <i class="fab fa-google-pay fa-2x"></i>
                                    </button>
                                </div>
                                <div class="col-4">
                                    <button class="btn btn-outline-success w-100 py-3">
                                        <i class="fab fa-apple-pay fa-2x"></i>
                                    </button>
                                </div>
                                <div class="col-4">
                                    <button class="btn btn-outline-warning w-100 py-3">
                                        <i class="fab fa-amazon-pay fa-2x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cash Payment -->
                    <div class="tab-pane fade" id="cash" role="tabpanel">
                        <div class="text-center py-5">
                            <i class="fas fa-hand-holding-usd fa-4x text-success mb-3"></i>
                            <h5>Pay at Counter</h5>
                            <p class="text-muted">Your order will be prepared. Please pay at the counter when ready.</p>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Table Number: <strong><?php echo getTableNumber($table_id, connectDatabase()); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-payment">
                    <i class="fas fa-check me-2"></i>Confirm Payment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- FAQ Modal -->
<div class="modal fade" id="faqModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-question-circle me-2"></i>
                    Frequently Asked Questions
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                How do I modify my order?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                You can modify your order before placing it. Once placed, please call a waiter for any changes.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Can I split the bill?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes! Use the "Split Bill" option in the Order Summary section.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                How long will my order take?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Average preparation time is 15-25 minutes. You can track your order status in real-time.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script>
let cart = JSON.parse(localStorage.getItem('cart')) || [];
let isOnline = navigator.onLine;

$(document).ready(function() {
    loadCartItems();
    updateCartSummary();
    
    // Order type change
    $('#order-type').change(function() {
        const type = $(this).val();
        if (type === 'delivery') {
            $('#delivery-address').removeClass('d-none');
        } else {
            $('#delivery-address').addClass('d-none');
        }
        updateCartSummary();
    });
    
    // Split bill toggle
    $('#split-bill').change(function() {
        if ($(this).is(':checked')) {
            $('#split-options').removeClass('d-none');
            calculateSplit();
        } else {
            $('#split-options').addClass('d-none');
        }
    });
    
    // Split calculation
    $('#split-method, #split-people').change(calculateSplit);
    
    // Checkout button
    $('#checkout-btn').click(function() {
        if (cart.length === 0) {
            showToast('Your cart is empty!', 'error');
            return;
        }
        
        const orderType = $('#order-type').val();
        if (orderType === 'delivery' && !$('#delivery-address-text').val().trim()) {
            showToast('Please enter delivery address', 'error');
            return;
        }
        
        $('#paymentModal').modal('show');
        updatePaymentAmounts();
    });
    
    // Confirm payment
    $('#confirm-payment').click(placeOrder);
    
    // Save for later
    $('#save-for-later').click(function() {
        localStorage.setItem('saved_cart', JSON.stringify(cart));
        showToast('Cart saved for later!', 'success');
    });
    
    // Monitor online status
    window.addEventListener('online', function() {
        isOnline = true;
        showToast('Back online! Syncing data...', 'success');
        syncOfflineOrders();
    });
    
    window.addEventListener('offline', function() {
        isOnline = false;
        showToast('You are offline. Orders will be synced when online.', 'warning');
    });
});

function loadCartItems() {
    const container = $('#cart-items-container');
    const emptyCart = $('#empty-cart');
    
    if (cart.length === 0) {
        container.html(emptyCart.show());
        $('#cart-item-count').text('0 items');
        $('#checkout-btn').prop('disabled', true);
        return;
    }
    
    emptyCart.hide();
    container.empty();
    
    cart.forEach((item, index) => {
        const itemHtml = createCartItemHTML(item, index);
        container.append(itemHtml);
    });
    
    $('#cart-item-count').text(`${cart.length} item${cart.length > 1 ? 's' : ''}`);
    $('#checkout-btn').prop('disabled', false);
}

function createCartItemHTML(item, index) {
    const modifiersText = item.modifiers ? 
        Object.values(item.modifiers).flat().map(m => m.name).join(', ') : '';
    
    return `
        <div class="cart-item mb-3 p-3 border rounded" data-index="${index}">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="${item.image || '../assets/images/food-placeholder.jpg'}" 
                         class="rounded" width="80" height="80" style="object-fit: cover;">
                </div>
                <div class="col">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1 fw-bold">${item.name}</h6>
                            <p class="text-muted small mb-1">${item.special_request || 'No special requests'}</p>
                            ${modifiersText ? `<p class="text-muted small mb-1"><i class="fas fa-edit"></i> ${modifiersText}</p>` : ''}
                        </div>
                        <div class="text-end">
                            <h6 class="text-primary mb-1">$${(item.total).toFixed(2)}</h6>
                            <div class="quantity-selector d-inline-flex align-items-center">
                                <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${index}, -1)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="mx-2 fw-bold">${item.quantity}</span>
                                <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${index}, 1)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 text-end">
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function updateQuantity(index, change) {
    if (cart[index]) {
        cart[index].quantity = Math.max(1, cart[index].quantity + change);
        cart[index].total = cart[index].quantity * (cart[index].price + 
            (cart[index].modifiers_total || 0));
        
        localStorage.setItem('cart', JSON.stringify(cart));
        loadCartItems();
        updateCartSummary();
    }
}

function removeFromCart(index) {
    if (confirm('Remove this item from cart?')) {
        cart.splice(index, 1);
        localStorage.setItem('cart', JSON.stringify(cart));
        loadCartItems();
        updateCartSummary();
    }
}

function updateCartSummary() {
    if (cart.length === 0) {
        $('#subtotal-amount').text('$0.00');
        $('#tax-amount').text('$0.00');
        $('#service-charge').text('$0.00');
        $('#total-amount').text('$0.00');
        return;
    }
    
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const tax = subtotal * 0.10; // 10% tax
    const serviceCharge = subtotal * 0.05; // 5% service charge
    const discount = 0; // Could be calculated based on promotions
    const total = subtotal + tax + serviceCharge - discount;
    
    $('#subtotal-amount').text('$' + subtotal.toFixed(2));
    $('#tax-amount').text('$' + tax.toFixed(2));
    $('#service-charge').text('$' + serviceCharge.toFixed(2));
    $('#discount-amount').text('-$' + discount.toFixed(2));
    $('#total-amount').text('$' + total.toFixed(2));
    
    // Update payment modals
    $('#card-amount').val('$' + total.toFixed(2));
    $('#upi-amount').text('$' + total.toFixed(2));
    
    // Recalculate split if active
    if ($('#split-bill').is(':checked')) {
        calculateSplit();
    }
}

function calculateSplit() {
    const total = parseFloat($('#total-amount').text().replace('$', ''));
    const method = $('#split-method').val();
    const people = parseInt($('#split-people').val()) || 2;
    
    if (method === 'equally') {
        const perPerson = total / people;
        $('#split-result').html(`
            <div class="alert alert-info py-2">
                <strong>$${perPerson.toFixed(2)}</strong> per person
            </div>
        `);
    } else {
        // Split by items would be more complex
        $('#split-result').html(`
            <div class="alert alert-info py-2">
                Please inform waiter for item-wise split
            </div>
        `);
    }
}

function updatePaymentAmounts() {
    const total = $('#total-amount').text();
    $('#card-amount').val(total);
    $('#upi-amount').text(total);
}

function placeOrder() {
    const orderType = $('#order-type').val();
    const specialInstructions = $('#special-instructions').val();
    const deliveryAddress = orderType === 'delivery' ? $('#delivery-address-text').val() : '';
    
    const orderData = {
        business_id: <?php echo $business_id; ?>,
        table_id: <?php echo $table_id; ?>,
        session_token: '<?php echo $session_token; ?>',
        order_type: orderType,
        items: cart,
        special_instructions: specialInstructions,
        delivery_address: deliveryAddress,
        total_amount: parseFloat($('#total-amount').text().replace('$', '')),
        payment_method: $('#paymentTabs .nav-link.active').text().trim()
    };
    
    if (isOnline) {
        // Submit order online
        $.ajax({
            url: '../api/orders.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(orderData),
            success: function(response) {
                if (response.success) {
                    // Clear cart
                    cart = [];
                    localStorage.removeItem('cart');
                    
                    // Show success
                    $('#paymentModal').modal('hide');
                    showToast('Order placed successfully!', 'success');
                    
                    // Redirect to order status page
                    setTimeout(() => {
                        window.location.href = 'order-status.php?order_id=' + response.data.order_id;
                    }, 2000);
                } else {
                    showToast('Order failed: ' + response.message, 'error');
                }
            },
            error: function() {
                // Save offline
                saveOrderOffline(orderData);
            }
        });
    } else {
        // Save offline
        saveOrderOffline(orderData);
    }
}

function saveOrderOffline(orderData) {
    const offlineOrders = JSON.parse(localStorage.getItem('offline_orders') || '[]');
    orderData.offline_id = 'offline_' + Date.now();
    orderData.status = 'pending_sync';
    orderData.created_at = new Date().toISOString();
    
    offlineOrders.push(orderData);
    localStorage.setItem('offline_orders', JSON.stringify(offlineOrders));
    
    // Clear cart
    cart = [];
    localStorage.removeItem('cart');
    
    $('#paymentModal').modal('hide');
    showToast('Order saved offline! Will sync when online.', 'info');
    
    // Still redirect to show order was saved
    setTimeout(() => {
        window.location.href = 'order-status.php?offline=1';
    }, 2000);
}

function syncOfflineOrders() {
    const offlineOrders = JSON.parse(localStorage.getItem('offline_orders') || '[]');
    
    if (offlineOrders.length > 0) {
        offlineOrders.forEach((order, index) => {
            if (order.status === 'pending_sync') {
                $.ajax({
                    url: '../api/orders.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(order),
                    success: function(response) {
                        if (response.success) {
                            // Mark as synced
                            offlineOrders[index].status = 'synced';
                            offlineOrders[index].server_id = response.data.order_id;
                            localStorage.setItem('offline_orders', JSON.stringify(offlineOrders));
                            
                            showToast('Offline order synced successfully!', 'success');
                        }
                    }
                });
            }
        });
        
        // Clean up synced orders
        const pendingOrders = offlineOrders.filter(o => o.status === 'pending_sync');
        localStorage.setItem('offline_orders', JSON.stringify(pendingOrders));
    }
}

function requestHelp(type) {
    const helpData = {
        type: type,
        table_id: <?php echo $table_id; ?>,
        business_id: <?php echo $business_id; ?>,
        message: 'Cart page assistance',
        timestamp: new Date().toISOString()
    };
    
    if (isOnline) {
        $.post('../api/request-help.php', helpData, function(response) {
            if (response.success) {
                showToast('Help request sent!', 'success');
            }
        });
    } else {
        const offlineRequests = JSON.parse(localStorage.getItem('offline_requests') || '[]');
        offlineRequests.push(helpData);
        localStorage.setItem('offline_requests', JSON.stringify(offlineRequests));
        showToast('Request saved offline. Staff will be notified when online.', 'info');
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

// Auto-save special instructions
$('#special-instructions').on('blur', function() {
    localStorage.setItem('special_instructions', $(this).val());
});

// Load saved special instructions
const savedInstructions = localStorage.getItem('special_instructions');
if (savedInstructions) {
    $('#special-instructions').val(savedInstructions);
}
</script>

<style>
.cart-item {
    transition: all 0.3s;
}
.cart-item:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}
.quantity-selector button {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sticky-top {
    z-index: 1020;
}
</style>

<?php include '../components/footer.php'; ?>