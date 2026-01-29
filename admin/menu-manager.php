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

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';
$error = '';

// Get categories for dropdown
$categories = getMenuCategories($business_id, connectDatabase());

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = connectDatabase();
    
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        $description = trim($_POST['description']);
        $display_order = intval($_POST['display_order']);
        
        if (!empty($category_name)) {
            $stmt = $conn->prepare("
                INSERT INTO menu_categories (business_id, category_name, description, display_order)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("issi", $business_id, $category_name, $description, $display_order);
            
            if ($stmt->execute()) {
                $message = 'Category added successfully';
                $categories = getMenuCategories($business_id, $conn); // Refresh list
            } else {
                $error = 'Failed to add category: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    elseif (isset($_POST['add_item'])) {
        $item_name = trim($_POST['item_name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $category_id = intval($_POST['category_id']);
        $is_vegetarian = isset($_POST['is_vegetarian']) ? 1 : 0;
        $allergens = trim($_POST['allergens']);
        $preparation_time = intval($_POST['preparation_time']);
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        // Handle image upload
        $image_url = '';
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) {
            $upload_dir = '../assets/uploads/menu/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['item_image']['name']);
            $target_file = $upload_dir . $file_name;
            
            // Check file type
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($imageFileType, $allowed_types)) {
                if (move_uploaded_file($_FILES['item_image']['tmp_name'], $target_file)) {
                    $image_url = 'assets/uploads/menu/' . $file_name;
                }
            }
        }
        
        // Handle modifiers
        $modifiers = [];
        if (!empty($_POST['modifier_name'])) {
            $modifier_groups = [];
            $group_count = count($_POST['modifier_name']);
            
            for ($i = 0; $i < $group_count; $i++) {
                if (!empty($_POST['modifier_name'][$i])) {
                    $group = [
                        'name' => $_POST['modifier_name'][$i],
                        'type' => $_POST['modifier_type'][$i],
                        'required' => isset($_POST['modifier_required'][$i]) ? 1 : 0,
                        'options' => []
                    ];
                    
                    if (!empty($_POST['option_name'][$i])) {
                        $option_count = count($_POST['option_name'][$i]);
                        for ($j = 0; $j < $option_count; $j++) {
                            if (!empty($_POST['option_name'][$i][$j])) {
                                $group['options'][] = [
                                    'id' => $j + 1,
                                    'name' => $_POST['option_name'][$i][$j],
                                    'price' => floatval($_POST['option_price'][$i][$j] ?? 0),
                                    'default' => isset($_POST['option_default'][$i][$j]) ? 1 : 0
                                ];
                            }
                        }
                    }
                    
                    if (!empty($group['options'])) {
                        $modifier_groups[] = $group;
                    }
                }
            }
            
            if (!empty($modifier_groups)) {
                $modifiers = ['groups' => $modifier_groups];
            }
        }
        
        $modifiers_json = json_encode($modifiers);
        
        $stmt = $conn->prepare("
            INSERT INTO menu_items 
            (business_id, category_id, item_name, description, price, 
             image_url, is_vegetarian, allergens, preparation_time, 
             is_available, modifiers)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("iissdsisisis",
            $business_id,
            $category_id,
            $item_name,
            $description,
            $price,
            $image_url,
            $is_vegetarian,
            $allergens,
            $preparation_time,
            $is_available,
            $modifiers_json
        );
        
        if ($stmt->execute()) {
            $message = 'Menu item added successfully';
            logActivity($user_id, 'add_menu_item', "Added: $item_name");
        } else {
            $error = 'Failed to add item: ' . $stmt->error;
        }
        $stmt->close();
    }
    
    elseif (isset($_POST['update_item'])) {
        $item_id = intval($_POST['item_id']);
        $item_name = trim($_POST['item_name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $category_id = intval($_POST['category_id']);
        $is_vegetarian = isset($_POST['is_vegetarian']) ? 1 : 0;
        $allergens = trim($_POST['allergens']);
        $preparation_time = intval($_POST['preparation_time']);
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        // Handle image update
        $image_url = $_POST['current_image'] ?? '';
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) {
            $upload_dir = '../assets/uploads/menu/';
            $file_name = time() . '_' . basename($_FILES['item_image']['name']);
            $target_file = $upload_dir . $file_name;
            
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($imageFileType, $allowed_types)) {
                if (move_uploaded_file($_FILES['item_image']['tmp_name'], $target_file)) {
                    // Delete old image if exists
                    if (!empty($image_url) && file_exists('../' . $image_url)) {
                        unlink('../' . $image_url);
                    }
                    $image_url = 'assets/uploads/menu/' . $file_name;
                }
            }
        }
        
        // Handle modifiers update (simplified for brevity)
        $modifiers_json = $_POST['current_modifiers'] ?? '[]';
        
        $stmt = $conn->prepare("
            UPDATE menu_items 
            SET category_id = ?, item_name = ?, description = ?, price = ?, 
                image_url = ?, is_vegetarian = ?, allergens = ?, 
                preparation_time = ?, is_available = ?, modifiers = ?
            WHERE id = ? AND business_id = ?
        ");
        
        $stmt->bind_param("issdsisisisi",
            $category_id,
            $item_name,
            $description,
            $price,
            $image_url,
            $is_vegetarian,
            $allergens,
            $preparation_time,
            $is_available,
            $modifiers_json,
            $item_id,
            $business_id
        );
        
        if ($stmt->execute()) {
            $message = 'Menu item updated successfully';
            logActivity($user_id, 'update_menu_item', "Updated: $item_name");
        } else {
            $error = 'Failed to update item: ' . $stmt->error;
        }
        $stmt->close();
    }
    
    $conn->close();
}

// Handle delete action
if ($action === 'delete' && isset($_GET['id'])) {
    $conn = connectDatabase();
    $item_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("UPDATE menu_items SET is_active = 0 WHERE id = ? AND business_id = ?");
    $stmt->bind_param("ii", $item_id, $business_id);
    
    if ($stmt->execute()) {
        $message = 'Item deleted successfully';
        logActivity($user_id, 'delete_menu_item', "Deleted ID: $item_id");
    } else {
        $error = 'Failed to delete item';
    }
    
    $stmt->close();
    $conn->close();
    
    // Redirect to remove query string
    header('Location: menu-manager.php?message=' . urlencode($message));
    exit();
}

// Get menu items for listing
$conn = connectDatabase();
$category_filter = $_GET['category'] ?? 'all';
$search_filter = $_GET['search'] ?? '';

$where = "mi.business_id = ? AND mi.is_active = 1";
$params = [$business_id];
$types = "i";

if ($category_filter !== 'all' && is_numeric($category_filter)) {
    $where .= " AND mi.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

if (!empty($search_filter)) {
    $where .= " AND (mi.item_name LIKE ? OR mi.description LIKE ?)";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
    $types .= "ss";
}

$stmt = $conn->prepare("
    SELECT mi.*, mc.category_name,
           (SELECT COUNT(*) FROM order_items WHERE menu_item_id = mi.id) as order_count
    FROM menu_items mi
    LEFT JOIN menu_categories mc ON mi.category_id = mc.id
    WHERE $where
    ORDER BY mc.display_order, mi.display_order, mi.item_name
");

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$menu_items = [];

while ($row = $result->fetch_assoc()) {
    $menu_items[] = $row;
}

$stmt->close();
$conn->close();

$page_title = "Menu Manager";
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
            <?php if ($message || isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($message ?: $_GET['message']); ?>
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
            
            <!-- Menu Management Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0">
                                <i class="fas fa-utensils me-2"></i>
                                Menu Management
                            </h4>
                            <p class="text-muted mb-0">Manage your menu items and categories</p>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                <i class="fas fa-plus me-2"></i>Add Item
                            </button>
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="fas fa-folder-plus me-2"></i>Add Category
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Category Filter</label>
                            <select class="form-select" name="category" onchange="this.form.submit()">
                                <option value="all">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($category_filter == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Search Items</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search_filter); ?>" 
                                       placeholder="Search by name or description...">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Actions</label>
                            <div class="btn-group w-100">
                                <a href="menu-manager.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                                <button type="button" class="btn btn-outline-success" onclick="exportMenu()">
                                    <i class="fas fa-file-export"></i> Export
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Menu Items Grid -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Image</th>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Orders</th>
                                    <th>Prep Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($menu_items)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                                        <h5>No menu items found</h5>
                                        <p class="text-muted">Add your first menu item to get started</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($menu_items as $item): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?php echo BASE_URL . $item['image_url']; ?>" 
                                             class="rounded" width="50" height="50" style="object-fit: cover;">
                                        <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                             style="width: 50px; height: 50px;">
                                            <i class="fas fa-utensils text-muted"></i>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                        <div class="text-muted small">
                                            <?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 50)); ?>...
                                        </div>
                                        <?php if ($item['is_vegetarian']): ?>
                                        <span class="badge bg-success small">
                                            <i class="fas fa-leaf"></i> Veg
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($item['allergens']): ?>
                                        <span class="badge bg-warning small">
                                            <i class="fas fa-exclamation-triangle"></i> Allergens
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td>
                                        <strong class="text-primary">$<?php echo number_format($item['price'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $item['is_available'] ? 'success' : 'danger'; ?>">
                                            <?php echo $item['is_available'] ? 'Available' : 'Sold Out'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $item['order_count']; ?></span>
                                    </td>
                                    <td><?php echo $item['preparation_time'] ?? 15; ?> min</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editItem(<?php echo $item['id']; ?>)"
                                                    data-bs-toggle="tooltip" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-success"
                                                    onclick="toggleAvailability(<?php echo $item['id']; ?>, <?php echo $item['is_available']; ?>)"
                                                    data-bs-toggle="tooltip" title="Toggle Availability">
                                                <i class="fas fa-toggle-<?php echo $item['is_available'] ? 'on' : 'off'; ?>"></i>
                                            </button>
                                            <a href="?action=delete&id=<?php echo $item['id']; ?>" 
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('Delete this menu item?')"
                                               data-bs-toggle="tooltip" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center py-3">
                                    <h5 class="mb-0"><?php echo count($menu_items); ?></h5>
                                    <small>Total Items</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center py-3">
                                    <h5 class="mb-0">
                                        <?php echo count(array_filter($menu_items, fn($item) => $item['is_available'])); ?>
                                    </h5>
                                    <small>Available</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center py-3">
                                    <h5 class="mb-0">
                                        <?php echo count(array_filter($menu_items, fn($item) => $item['is_vegetarian'])); ?>
                                    </h5>
                                    <small>Vegetarian</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center py-3">
                                    <h5 class="mb-0">
                                        <?php echo count($categories); ?>
                                    </h5>
                                    <small>Categories</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-folder-plus me-2"></i>
                    Add New Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" class="form-control" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" class="form-control" name="display_order" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Menu Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="itemForm">
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="item_id">
                    <input type="hidden" name="current_image" id="current_image">
                    <input type="hidden" name="current_modifiers" id="current_modifiers">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Item Name *</label>
                                <input type="text" class="form-control" name="item_name" id="item_name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" name="category_id" id="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Price ($) *</label>
                                <input type="number" class="form-control" name="price" id="price" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Preparation Time (min)</label>
                                <input type="number" class="form-control" name="preparation_time" 
                                       id="preparation_time" min="1" value="15">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Allergens</label>
                                <input type="text" class="form-control" name="allergens" id="allergens" 
                                       placeholder="e.g., nuts, dairy">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" 
                                           name="is_available" id="is_available" value="1" checked>
                                    <label class="form-check-label" for="is_available">Available</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           name="is_vegetarian" id="is_vegetarian" value="1">
                                    <label class="form-check-label" for="is_vegetarian">Vegetarian</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Item Image</label>
                        <input type="file" class="form-control" name="item_image" id="item_image" 
                               accept="image/*">
                        <div class="form-text">Recommended size: 500x500px</div>
                        <div id="image-preview" class="mt-2"></div>
                    </div>
                    
                    <!-- Modifiers Section -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold">Custom Modifiers</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="addModifierGroup()">
                                <i class="fas fa-plus"></i> Add Modifier
                            </button>
                        </div>
                        <div id="modifiers-container">
                            <!-- Modifier groups will be added here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_item" id="submitBtn" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script>
let modifierCounter = 0;

$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Image preview
    $('#item_image').change(function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#image-preview').html(`
                    <img src="${e.target.result}" class="img-thumbnail" style="max-height: 150px;">
                `);
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Initialize add item modal
    $('#addItemModal').click(function() {
        resetItemForm();
        $('#modalTitle').text('Add Menu Item');
        $('#submitBtn').attr('name', 'add_item').text('Save Item');
        $('#itemModal').modal('show');
    });
});

function resetItemForm() {
    $('#itemForm')[0].reset();
    $('#item_id').val('');
    $('#current_image').val('');
    $('#current_modifiers').val('[]');
    $('#image-preview').empty();
    $('#modifiers-container').empty();
    modifierCounter = 0;
}

function addModifierGroup() {
    const groupId = modifierCounter++;
    const groupHtml = `
        <div class="card mb-3 modifier-group" data-group="${groupId}">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-5">
                        <input type="text" class="form-control" 
                               name="modifier_name[]" 
                               placeholder="Modifier name (e.g., Spice Level)">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="modifier_type[]">
                            <option value="single">Single Select</option>
                            <option value="multiple">Multiple Select</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   name="modifier_required[]" value="1" id="required_${groupId}">
                            <label class="form-check-label" for="required_${groupId}">
                                Required
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-sm btn-danger" 
                                onclick="removeModifierGroup(${groupId})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="options-container">
                    <div class="option-item mb-2">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <input type="text" class="form-control" 
                                       name="option_name[${groupId}][]" 
                                       placeholder="Option name">
                            </div>
                            <div class="col-md-3">
                                <input type="number" class="form-control" 
                                       name="option_price[${groupId}][]" 
                                       placeholder="Price" step="0.01" min="0">
                            </div>
                            <div class="col-md-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="option_default[${groupId}][]" 
                                           value="1">
                                    <label class="form-check-label">
                                        Default
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                        onclick="addOption(${groupId})">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#modifiers-container').append(groupHtml);
}

function addOption(groupId) {
    const container = $(`.modifier-group[data-group="${groupId}"] .options-container`);
    const optionHtml = `
        <div class="option-item mb-2">
            <div class="row g-2">
                <div class="col-md-5">
                    <input type="text" class="form-control" 
                           name="option_name[${groupId}][]" 
                           placeholder="Option name">
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control" 
                           name="option_price[${groupId}][]" 
                           placeholder="Price" step="0.01" min="0">
                </div>
                <div class="col-md-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" 
                               name="option_default[${groupId}][]" 
                               value="1">
                        <label class="form-check-label">
                            Default
                        </label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" 
                            onclick="$(this).closest('.option-item').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.append(optionHtml);
}

function removeModifierGroup(groupId) {
    $(`.modifier-group[data-group="${groupId}"]`).remove();
}

function editItem(itemId) {
    $.ajax({
        url: '../api/menu.php',
        method: 'GET',
        data: { item_id: itemId },
        success: function(response) {
            if (response.success && response.data) {
                const item = response.data;
                
                $('#item_id').val(item.id);
                $('#item_name').val(item.item_name);
                $('#description').val(item.description || '');
                $('#price').val(item.price);
                $('#category_id').val(item.category_id);
                $('#preparation_time').val(item.preparation_time || 15);
                $('#allergens').val(item.allergens || '');
                $('#is_available').prop('checked', item.is_available == 1);
                $('#is_vegetarian').prop('checked', item.is_vegetarian == 1);
                $('#current_image').val(item.image_url || '');
                $('#current_modifiers').val(item.modifiers || '[]');
                
                // Show image preview
                if (item.image_url) {
                    $('#image-preview').html(`
                        <img src="../${item.image_url}" class="img-thumbnail" style="max-height: 150px;">
                    `);
                }
                
                // Load modifiers (simplified - would need more complex logic)
                $('#modifiers-container').empty();
                if (item.modifiers) {
                    try {
                        const modifiers = JSON.parse(item.modifiers);
                        // Add modifier groups based on data
                    } catch (e) {
                        console.error('Error parsing modifiers:', e);
                    }
                }
                
                $('#modalTitle').text('Edit Menu Item');
                $('#submitBtn').attr('name', 'update_item').text('Update Item');
                $('#itemModal').modal('show');
            }
        }
    });
}

function toggleAvailability(itemId, currentStatus) {
    const newStatus = currentStatus ? 0 : 1;
    
    $.post('../api/menu.php', {
        action: 'toggle_availability',
        item_id: itemId,
        status: newStatus
    }, function(response) {
        if (response.success) {
            showToast('Availability updated!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Update failed!', 'error');
        }
    });
}

function exportMenu() {
    window.location.href = '../api/export.php?type=menu&business_id=<?php echo $business_id; ?>';
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
.modifier-group {
    border-left: 4px solid #007bff;
}
.option-item {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}
.table img {
    transition: transform 0.2s;
}
.table img:hover {
    transform: scale(1.5);
}
</style>

<?php include '../components/footer.php'; ?>