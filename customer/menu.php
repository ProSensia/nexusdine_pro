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

// Get business info
$business = getBusinessInfo($business_id, $conn);
// Get menu categories
$categories = getMenuCategories($business_id, $conn);

$page_title = "Menu - " . ($business['business_name'] ?? 'Restaurant');
?>
<?php include '../components/header.php'; ?>

<div class="container-fluid px-0">
    <!-- Online/Offline Indicator -->
    <div class="sticky-top bg-white shadow-sm py-2">
        <div class="container">
            <div class="row align-items-center">
                <div class="col">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <?php if (!empty($business['logo_url'])): ?>
                            <img src="<?php echo BASE_URL . $business['logo_url']; ?>" alt="Logo" height="40">
                            <?php else: ?>
                            <i class="fas fa-utensils fa-2x text-primary"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($business['business_name'] ?? 'Restaurant'); ?></h5>
                            <small class="text-muted">Table <?php echo getTableNumber($table_id, $conn); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="d-flex align-items-center">
                        <a href="cart.php" class="btn btn-primary position-relative me-3">
                            <i class="fas fa-shopping-cart"></i>
                            <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                0
                            </span>
                        </a>
                        <a href="games.php" class="btn btn-outline-success me-2">
                            <i class="fas fa-gamepad"></i>
                        </a>
                        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#helpModal">
                            <i class="fas fa-bell"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Menu Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
        <div class="container">
            <div class="navbar-nav flex-row overflow-auto" style="flex-wrap: nowrap;">
                <a class="nav-link active" href="#" data-category="all">All Items</a>
                <?php foreach ($categories as $category): ?>
                <a class="nav-link" href="#" data-category="<?php echo $category['id']; ?>">
                    <?php echo htmlspecialchars($category['category_name']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
    
    <!-- Menu Items Grid -->
    <div class="container py-4">
        <!-- Search Box -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="menu-search" class="form-control" placeholder="Search menu items...">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="filter-veg">
                    <label class="form-check-label" for="filter-veg">
                        <i class="fas fa-leaf text-success"></i> Vegetarian Only
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="filter-available">
                    <label class="form-check-label" for="filter-available">
                        <i class="fas fa-check-circle text-primary"></i> Available Only
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Menu Items -->
        <div id="menu-items-container" class="row">
            <!-- Items will be loaded via AJAX -->
        </div>
        
        <!-- Loading Spinner -->
        <div id="loading-spinner" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        
        <!-- No Items Found -->
        <div id="no-items" class="text-center py-5 d-none">
            <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
            <h4>No menu items found</h4>
            <p class="text-muted">Try adjusting your search or filter</p>
        </div>
    </div>
</div>

<!-- Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <img id="itemModalImage" src="" class="img-fluid rounded" alt="">
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h4 id="itemModalName" class="fw-bold"></h4>
                            <div id="itemModalDescription" class="text-muted mb-3"></div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="text-primary mb-0" id="itemModalPrice"></h5>
                                <span id="itemModalStatus"></span>
                            </div>
                        </div>
                        
                        <div id="modifiers-container" class="mb-4">
                            <!-- Modifiers will be loaded here -->
                        </div>
                        
                        <div class="mb-3">
                            <label for="special-request" class="form-label">Special Instructions</label>
                            <textarea id="special-request" class="form-control" rows="2" placeholder="Any special requests?"></textarea>
                        </div>
                        
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="quantity-selector">
                                <button class="btn btn-outline-secondary btn-sm" onclick="adjustQuantity(-1)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span id="itemQuantity" class="mx-3 fw-bold">1</span>
                                <button class="btn btn-outline-secondary btn-sm" onclick="adjustQuantity(1)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <button class="btn btn-primary" onclick="addToCart()">
                                <i class="fas fa-cart-plus"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Assistance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-hands-helping fa-3x text-warning mb-3"></i>
                    <h4>How can we help?</h4>
                </div>
                
                <div class="row g-3">
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 py-3" onclick="requestHelp('waiter')">
                            <i class="fas fa-user-tie fa-2x mb-2"></i><br>
                            Call Waiter
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-success w-100 py-3" onclick="requestHelp('water')">
                            <i class="fas fa-glass-water fa-2x mb-2"></i><br>
                            Water Refill
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-info w-100 py-3" onclick="requestHelp('bill')">
                            <i class="fas fa-file-invoice-dollar fa-2x mb-2"></i><br>
                            Request Bill
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-danger w-100 py-3" onclick="requestHelp('emergency')">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                            Emergency
                        </button>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="help-message" class="form-label">Additional Message (Optional)</label>
                    <textarea id="help-message" class="form-control" rows="2" placeholder="Please specify your request..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitHelpRequest()">Send Request</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script>
let currentItemId = null;
let currentQuantity = 1;
let cart = JSON.parse(localStorage.getItem('cart')) || [];

$(document).ready(function() {
    loadMenuItems();
    updateCartBadge();
    
    // Category navigation
    $('.nav-link[data-category]').click(function(e) {
        e.preventDefault();
        $('.nav-link').removeClass('active');
        $(this).addClass('active');
        loadMenuItems($(this).data('category'));
    });
    
    // Search functionality
    $('#menu-search').on('input', function() {
        loadMenuItems($('.nav-link.active').data('category'));
    });
    
    // Filter functionality
    $('#filter-veg, #filter-available').change(function() {
        loadMenuItems($('.nav-link.active').data('category'));
    });
});

function loadMenuItems(categoryId = 'all') {
    $('#loading-spinner').show();
    $('#no-items').addClass('d-none');
    
    const searchTerm = $('#menu-search').val();
    const vegOnly = $('#filter-veg').is(':checked');
    const availableOnly = $('#filter-available').is(':checked');
    
    $.ajax({
        url: '../api/menu.php',
        method: 'GET',
        data: {
            business_id: <?php echo $business_id; ?>,
            category_id: categoryId === 'all' ? '' : categoryId,
            search: searchTerm,
            veg_only: vegOnly ? 1 : 0,
            available_only: availableOnly ? 1 : 0
        },
        success: function(response) {
            $('#loading-spinner').hide();
            
            if (response.success && response.data.length > 0) {
                renderMenuItems(response.data);
            } else {
                $('#menu-items-container').html('');
                $('#no-items').removeClass('d-none');
            }
        },
        error: function() {
            $('#loading-spinner').hide();
            // Load from cache if offline
            loadFromCache();
        }
    });
}

function renderMenuItems(items) {
    const container = $('#menu-items-container');
    container.html('');
    
    items.forEach(item => {
        const itemCard = `
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card menu-item-card h-100" onclick="openItemModal(${item.id})">
                    <div class="position-relative">
                        <img src="${item.image_url || '../assets/images/food-placeholder.jpg'}" 
                             class="card-img-top" alt="${item.item_name}" style="height: 180px; object-fit: cover;">
                        ${!item.is_available ? 
                            '<div class="position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-flex align-items-center justify-content-center">' +
                            '<span class="badge bg-danger">Sold Out</span></div>' : ''}
                        ${item.is_vegetarian ? 
                            '<span class="position-absolute top-0 end-0 m-2"><i class="fas fa-leaf text-success"></i></span>' : ''}
                    </div>
                    <div class="card-body">
                        <h6 class="card-title fw-bold">${item.item_name}</h6>
                        <p class="card-text text-muted small">${item.description ? item.description.substring(0, 60) + '...' : ''}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-primary fw-bold">$${item.price}</span>
                            <span class="text-muted small"><i class="fas fa-clock"></i> ${item.preparation_time || 15} min</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.append(itemCard);
    });
}

function openItemModal(itemId) {
    currentItemId = itemId;
    currentQuantity = 1;
    
    $.ajax({
        url: '../api/menu.php',
        method: 'GET',
        data: { item_id: itemId },
        success: function(response) {
            if (response.success && response.data) {
                const item = response.data;
                
                $('#itemModalTitle').text(item.item_name);
                $('#itemModalName').text(item.item_name);
                $('#itemModalDescription').text(item.description || '');
                $('#itemModalPrice').text('$' + item.price);
                $('#itemModalImage').attr('src', item.image_url || '../assets/images/food-placeholder.jpg');
                
                if (!item.is_available) {
                    $('#itemModalStatus').html('<span class="badge bg-danger">Currently Unavailable</span>');
                } else {
                    $('#itemModalStatus').html('<span class="badge bg-success">Available</span>');
                }
                
                // Load modifiers
                const modifiersContainer = $('#modifiers-container');
                modifiersContainer.html('');
                
                if (item.modifiers) {
                    try {
                        const modifiers = JSON.parse(item.modifiers);
                        if (modifiers.groups && modifiers.groups.length > 0) {
                            modifiers.groups.forEach((group, index) => {
                                const groupHtml = `
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">${group.name} ${group.required ? '<span class="text-danger">*</span>' : ''}</label>
                                        <div class="modifier-options">
                                            ${group.options.map(option => `
                                                <div class="form-check">
                                                    <input class="form-check-input" type="${group.type === 'single' ? 'radio' : 'checkbox'}" 
                                                           name="modifier-${index}" id="mod-${option.id}" 
                                                           value="${option.id}" ${option.price > 0 ? `data-price="${option.price}"` : ''}
                                                           ${group.type === 'single' && option.default ? 'checked' : ''}>
                                                    <label class="form-check-label" for="mod-${option.id}">
                                                        ${option.name} ${option.price > 0 ? `(+$${option.price})` : ''}
                                                    </label>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                `;
                                modifiersContainer.append(groupHtml);
                            });
                        }
                    } catch (e) {
                        console.error('Error parsing modifiers:', e);
                    }
                }
                
                $('#itemQuantity').text('1');
                $('#special-request').val('');
                
                const modal = new bootstrap.Modal(document.getElementById('itemModal'));
                modal.show();
            }
        }
    });
}

function adjustQuantity(change) {
    currentQuantity = Math.max(1, currentQuantity + change);
    $('#itemQuantity').text(currentQuantity);
}

function addToCart() {
    const itemId = currentItemId;
    const quantity = currentQuantity;
    const specialRequest = $('#special-request').val();
    
    // Get selected modifiers
    const modifiers = {};
    $('.modifier-options').each(function(index) {
        const selected = [];
        $(this).find('input:checked').each(function() {
            selected.push({
                id: $(this).val(),
                name: $(this).next('label').text().trim(),
                price: parseFloat($(this).data('price')) || 0
            });
        });
        if (selected.length > 0) {
            modifiers[`group_${index}`] = selected;
        }
    });
    
    // Get item details
    $.ajax({
        url: '../api/menu.php',
        method: 'GET',
        data: { item_id: itemId },
        success: function(response) {
            if (response.success) {
                const item = response.data;
                
                const cartItem = {
                    id: item.id,
                    name: item.item_name,
                    price: item.price,
                    quantity: quantity,
                    modifiers: modifiers,
                    special_request: specialRequest,
                    image: item.image_url,
                    total: (item.price + Object.values(modifiers).flat().reduce((sum, mod) => sum + mod.price, 0)) * quantity
                };
                
                // Add to cart
                cart.push(cartItem);
                localStorage.setItem('cart', JSON.stringify(cart));
                updateCartBadge();
                
                // Show success message
                showToast(`${quantity} x ${item.item_name} added to cart!`, 'success');
                
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
            }
        }
    });
}

function updateCartBadge() {
    const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
    $('#cart-badge').text(itemCount);
}

function requestHelp(type) {
    $('#helpModal').modal('hide');
    
    const message = $('#help-message').val();
    const helpData = {
        type: type,
        table_id: <?php echo $table_id; ?>,
        business_id: <?php echo $business_id; ?>,
        message: message,
        timestamp: new Date().toISOString()
    };
    
    if (navigator.onLine) {
        $.post('../api/request-help.php', helpData, function(response) {
            if (response.success) {
                showToast('Help request sent successfully!', 'success');
            }
        });
    } else {
        // Store offline
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

// Load from cache when offline
function loadFromCache() {
    const cachedMenu = localStorage.getItem('cached_menu_' + <?php echo $business_id; ?>);
    if (cachedMenu) {
        const items = JSON.parse(cachedMenu);
        renderMenuItems(items);
        showToast('Showing cached menu (Offline Mode)', 'info');
    } else {
        $('#no-items').removeClass('d-none');
        showToast('Cannot load menu. Please check your connection.', 'error');
    }
}

// Cache menu when online
function cacheMenu(items) {
    localStorage.setItem('cached_menu_' + <?php echo $business_id; ?>, JSON.stringify(items));
}
</script>

<style>
.menu-item-card {
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}
.menu-item-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.quantity-selector {
    display: flex;
    align-items: center;
}
.quantity-selector button {
    width: 35px;
    height: 35px;
}
</style>

<?php include '../components/footer.php'; ?>