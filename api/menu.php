<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $conn = connectDatabase();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $business_id = isset($_GET['business_id']) ? intval($_GET['business_id']) : 0;
        $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
        $item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
        
        if ($item_id > 0) {
            // Get single menu item
            $stmt = $conn->prepare("
                SELECT mi.*, mc.category_name 
                FROM menu_items mi
                LEFT JOIN menu_categories mc ON mi.category_id = mc.id
                WHERE mi.id = ? AND mi.business_id = ? AND mi.is_active = 1
            ");
            $stmt->bind_param("ii", $item_id, $business_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $response['success'] = true;
                $response['data'] = $row;
            } else {
                $response['message'] = 'Menu item not found';
            }
            $stmt->close();
            
        } else {
            // Get multiple menu items
            $where = "mi.business_id = ? AND mi.is_active = 1";
            $params = [$business_id];
            $types = "i";
            
            if ($category_id > 0) {
                $where .= " AND mi.category_id = ?";
                $params[] = $category_id;
                $types .= "i";
            }
            
            if (!empty($search)) {
                $where .= " AND (mi.item_name LIKE ? OR mi.description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $types .= "ss";
            }
            
            if (isset($_GET['veg_only']) && $_GET['veg_only'] == 1) {
                $where .= " AND mi.is_vegetarian = 1";
            }
            
            if (isset($_GET['available_only']) && $_GET['available_only'] == 1) {
                $where .= " AND mi.is_available = 1";
            }
            
            $stmt = $conn->prepare("
                SELECT mi.*, mc.category_name 
                FROM menu_items mi
                LEFT JOIN menu_categories mc ON mi.category_id = mc.id
                WHERE $where
                ORDER BY mc.display_order, mi.display_order
            ");
            
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $items = [];
            
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $items;
            $stmt->close();
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Admin: Add/update menu item
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            throw new Exception('Unauthorized');
        }
        
        $business_id = $_SESSION['business_id'];
        
        if (isset($data['id']) && $data['id'] > 0) {
            // Update existing item
            $stmt = $conn->prepare("
                UPDATE menu_items 
                SET item_name = ?, description = ?, price = ?, 
                    category_id = ?, is_vegetarian = ?, allergens = ?, 
                    preparation_time = ?, is_available = ?, modifiers = ?
                WHERE id = ? AND business_id = ?
            ");
            
            $modifiers_json = json_encode($data['modifiers'] ?? []);
            
            $stmt->bind_param("ssdiisiiisi",
                $data['item_name'],
                $data['description'],
                $data['price'],
                $data['category_id'],
                $data['is_vegetarian'],
                $data['allergens'],
                $data['preparation_time'],
                $data['is_available'],
                $modifiers_json,
                $data['id'],
                $business_id
            );
            
        } else {
            // Insert new item
            $stmt = $conn->prepare("
                INSERT INTO menu_items 
                (business_id, category_id, item_name, description, price, 
                 is_vegetarian, allergens, preparation_time, is_available, modifiers)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $modifiers_json = json_encode($data['modifiers'] ?? []);
            
            $stmt->bind_param("iissdisiis",
                $business_id,
                $data['category_id'],
                $data['item_name'],
                $data['description'],
                $data['price'],
                $data['is_vegetarian'],
                $data['allergens'],
                $data['preparation_time'],
                $data['is_available'],
                $modifiers_json
            );
        }
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = isset($data['id']) ? 'Menu item updated' : 'Menu item added';
            $response['data'] = ['id' => isset($data['id']) ? $data['id'] : $stmt->insert_id];
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
        $stmt->close();
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Admin: Delete menu item
        parse_str(file_get_contents('php://input'), $data);
        $item_id = isset($data['id']) ? intval($data['id']) : 0;
        
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            throw new Exception('Unauthorized');
        }
        
        $business_id = $_SESSION['business_id'];
        
        $stmt = $conn->prepare("UPDATE menu_items SET is_active = 0 WHERE id = ? AND business_id = ?");
        $stmt->bind_param("ii", $item_id, $business_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Menu item deleted';
        }
        $stmt->close();
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>