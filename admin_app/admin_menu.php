<?php
require_once 'db.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: adminsignin.php');
    exit();
}

// Get admin information from session
$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];
$admin_role = $_SESSION['admin_role'];
$admin_full_name = $_SESSION['admin_full_name'];

// Initialize categories array with default values
$categories = [
    ['category_id' => 1, 'category_name' => 'Dish', 'icon' => '🍽️'],
    ['category_id' => 2, 'category_name' => 'Rice', 'icon' => '🍚'],
    ['category_id' => 3, 'category_name' => 'Merienda', 'icon' => '🍩'],
    ['category_id' => 4, 'category_name' => 'Beverages', 'icon' => '🥤'],
    ['category_id' => 5, 'category_name' => 'Pasta & Noodles', 'icon' => '🍝'],
    ['category_id' => 6, 'category_name' => 'Desserts', 'icon' => '🍰']
];

// Try to fetch categories from database, fallback to defaults if table doesn't exist
try {
    $categories_stmt = $pdo->prepare("SELECT * FROM categories ORDER BY category_name");
    $categories_stmt->execute();
    $db_categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($db_categories)) {
        $categories = $db_categories;
    } else {
        // Insert default categories if table is empty
        $default_categories = [
            ['Dish', '🍽️'],
            ['Rice', '🍚'],
            ['Merienda', '🍩'],
            ['Beverages', '🥤'],
            ['Pasta & Noodles', '🍝'],
            ['Desserts', '🍰']
        ];

        foreach ($default_categories as $category) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO categories (category_name, icon) VALUES (?, ?)");
            $stmt->execute($category);
        }

        // Refetch categories after insertion
        $categories_stmt->execute();
        $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    // Continue with default categories if table doesn't exist
}

// Fetch menu items from database
try {
    $stmt = $pdo->prepare("SELECT * FROM menu_items ORDER BY category_name, item_name");
    $stmt->execute();
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching menu items: " . $e->getMessage());
    $menu_items = [];
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        // Add new menu item
        $item_name = $_POST['item_name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $category_name = $_POST['category_name'];
        $available = isset($_POST['available']) ? 1 : 0;
        $image_url = $_POST['image_url'] ?: 'https://via.placeholder.com/300x200?text=Food+Item';

        try {
            $stmt = $pdo->prepare("INSERT INTO menu_items (item_name, description, price, category_name, available, image_url) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$item_name, $description, $price, $category_name, $available, $image_url]);
            $_SESSION['success'] = "Menu item added successfully!";
            header('Location: admin_menu.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error adding menu item: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_item'])) {
        // Update menu item
        $item_id = $_POST['item_id'];
        $item_name = $_POST['item_name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $category_name = $_POST['category_name'];
        $available = isset($_POST['available']) ? 1 : 0;
        $image_url = $_POST['image_url'] ?: 'https://via.placeholder.com/300x200?text=Food+Item';

        try {
            $stmt = $pdo->prepare("UPDATE menu_items SET item_name = ?, description = ?, price = ?, category_name = ?, available = ?, image_url = ? WHERE item_id = ?");
            $stmt->execute([$item_name, $description, $price, $category_name, $available, $image_url, $item_id]);
            $_SESSION['success'] = "Menu item updated successfully!";
            header('Location: admin_menu.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error updating menu item: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_item'])) {
        // Delete menu item
        $item_id = $_POST['item_id'];

        try {
            $stmt = $pdo->prepare("DELETE FROM menu_items WHERE item_id = ?");
            $stmt->execute([$item_id]);
            $_SESSION['success'] = "Menu item deleted successfully!";
            header('Location: admin_menu.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error deleting menu item: " . $e->getMessage();
        }
    } elseif (isset($_POST['add_category'])) {
        // Add new category
        $category_name = $_POST['new_category_name'];
        $icon = $_POST['new_category_icon'] ?: '📦';

        try {
            $stmt = $pdo->prepare("INSERT INTO categories (category_name, icon) VALUES (?, ?)");
            $stmt->execute([$category_name, $icon]);
            $_SESSION['success'] = "Category added successfully!";
            header('Location: admin_menu.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error adding category: " . $e->getMessage();
        }
    }
}

// Process logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: adminsignin.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - University Canteen Kiosk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #eb6c1eff;
            --primary-light: #ff8c3a;
            --primary-dark: #53545cff;
            --secondary: #6c757d;
            --dark: #1d2a3a;
            --light: #f8f9fa;
            --light-gray: #e9ecef;
            --border: #dee2e6;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f8fafc;
            color: var(--dark);
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: var(--dark);
            color: white;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            margin-right: 10px;
        }

        .logo-text {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .logo-text .canteen {
            color: var(--primary);
        }

        .logo-text .kiosk {
            color: white;
        }

        .admin-info {
            display: flex;
            align-items: center;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 10px;
        }

        .admin-details {
            flex: 1;
        }

        .admin-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .admin-role {
            font-size: 0.8rem;
            color: var(--secondary);
        }

        .sidebar-menu {
            flex: 1;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
        }

        .menu-item:hover, .menu-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .menu-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
        }

        .logout-btn:hover {
            color: white;
        }

        .logout-btn i {
            margin-right: 10px;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .welcome-text {
            color: var(--secondary);
            margin-top: 5px;
        }

        /* Alert Styles */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(76, 201, 240, 0.1);
            color: #0a58ca;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: rgba(247, 37, 133, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert i {
            font-size: 20px;
        }

        /* Form Styles */
        .form-container {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }

        .form-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(235, 108, 30, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            background: white;
            transition: var(--transition);
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(235, 108, 30, 0.1);
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-checkbox input {
            width: 18px;
            height: 18px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: var(--light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 1px solid var(--border);
        }

        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover {
            background: var(--light);
        }

        .status-available {
            color: var(--success);
            font-weight: 500;
        }

        .status-unavailable {
            color: var(--danger);
            font-weight: 500;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            text-decoration: none;
        }

        .edit-btn {
            background: var(--info);
            color: white;
        }

        .edit-btn:hover {
            background: #138496;
        }

        .delete-btn {
            background: var(--danger);
            color: white;
        }

        .delete-btn:hover {
            background: #c82333;
        }

        /* Category Styles */
        .category-icon {
            font-size: 1.2rem;
            margin-right: 8px;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            background: var(--light);
            border-radius: 4px;
            font-size: 0.85rem;
        }

        /* Tabs */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .tab {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: var(--secondary);
            transition: var(--transition);
            border-bottom: 2px solid transparent;
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--light-gray);
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 10px;
            }
            
            .sidebar-menu {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-bottom: 10px;
            }
            
            .menu-item {
                padding: 10px;
                border-radius: var(--radius);
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .table {
                min-width: 800px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .tabs {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="logo-text">
                        <span class="canteen">Canteen</span><span class="kiosk">Kiosk</span>
                    </div>
                </div>
                
                <div class="admin-info">
                    <div class="admin-avatar">
                        <?php echo strtoupper(substr($admin_full_name, 0, 1)); ?>
                    </div>
                    <div class="admin-details">
                        <div class="admin-name"><?php echo htmlspecialchars($admin_full_name); ?></div>
                        <div class="admin-role"><?php echo htmlspecialchars(ucfirst($admin_role)); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <a href="admindashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="admin_orders.php" class="menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
                <a href="admin_menu.php" class="menu-item active">
                    <i class="fas fa-utensils"></i>
                    <span>Menu</span>
                </a>
                <a href="admin_users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="admin_reports.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="admin_settings.php" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            
            <div class="sidebar-footer">
                <a href="?action=logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Menu Management</h1>
                    <p class="welcome-text">Manage categories and menu items</p>
                </div>
                <div style="color: var(--secondary); font-size: 0.9rem;">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
                </div>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" data-tab="menu-items">Menu Items</button>
                <button class="tab" data-tab="categories">Categories</button>
            </div>

            <!-- Menu Items Tab -->
            <div id="menu-items" class="tab-content active">
                <!-- Add/Edit Menu Item Form -->
                <div class="form-container">
                    <h2 class="form-title">
                        <i class="fas fa-plus-circle"></i>
                        <?php echo isset($_GET['edit']) ? 'Edit Menu Item' : 'Add New Menu Item'; ?>
                    </h2>
                    <form method="POST">
                        <?php if (isset($_GET['edit'])): ?>
                            <?php
                            $edit_id = $_GET['edit'];
                            $edit_stmt = $pdo->prepare("SELECT * FROM menu_items WHERE item_id = ?");
                            $edit_stmt->execute([$edit_id]);
                            $edit_item = $edit_stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            <input type="hidden" name="item_id" value="<?php echo $edit_item['item_id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="item_name" class="form-label">Item Name</label>
                                <input type="text" id="item_name" name="item_name" class="form-input" 
                                       value="<?php echo isset($edit_item) ? $edit_item['item_name'] : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="category_name" class="form-label">Category</label>
                                <select id="category_name" name="category_name" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_name']; ?>" 
                                            <?php echo (isset($edit_item) && $edit_item['category_name'] == $category['category_name']) ? 'selected' : ''; ?>>
                                            <?php echo $category['icon'] . ' ' . htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="price" class="form-label">Price (₱)</label>
                                <input type="number" id="price" name="price" class="form-input" step="0.01" min="0"
                                       value="<?php echo isset($edit_item) ? $edit_item['price'] : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="image_url" class="form-label">Image URL</label>
                                <input type="url" id="image_url" name="image_url" class="form-input"
                                       value="<?php echo isset($edit_item) ? $edit_item['image_url'] : ''; ?>" 
                                       placeholder="https://example.com/image.jpg">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-input" rows="3" required><?php echo isset($edit_item) ? $edit_item['description'] : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-checkbox">
                                <input type="checkbox" name="available" value="1" 
                                    <?php echo (isset($edit_item) && $edit_item['available'] == 1) ? 'checked' : 'checked'; ?>>
                                <span>Available for ordering</span>
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <?php if (isset($_GET['edit'])): ?>
                                <button type="submit" name="update_item" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Update Item
                                </button>
                                <a href="admin_menu.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </a>
                            <?php else: ?>
                                <button type="submit" name="add_item" class="btn btn-primary">
                                    <i class="fas fa-plus"></i>
                                    Add Item
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Menu Items Table -->
                <div class="table-container">
                    <h2 class="form-title">
                        <i class="fas fa-list"></i>
                        Current Menu Items
                    </h2>
                    
                    <?php if (empty($menu_items)): ?>
                        <div class="empty-state">
                            <i class="fas fa-utensils"></i>
                            <h3>No Menu Items</h3>
                            <p>Add your first menu item using the form above.</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menu_items as $item): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo $item['image_url']; ?>" alt="<?php echo $item['item_name']; ?>" 
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                        </td>
                                        <td><?php echo $item['item_name']; ?></td>
                                        <td>
                                            <span class="category-badge">
                                                <?php
                                                $category_icon = '📦';
                                                foreach ($categories as $cat) {
                                                    if ($cat['category_name'] == $item['category_name']) {
                                                        $category_icon = $cat['icon'];
                                                        break;
                                                    }
                                                }
                                                echo $category_icon . ' ' . $item['category_name'];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo $item['description']; ?></td>
                                        <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                        <td>
                                            <?php if ($item['available'] == 1): ?>
                                                <span class="status-available">Available</span>
                                            <?php else: ?>
                                                <span class="status-unavailable">Unavailable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="admin_menu.php?edit=<?php echo $item['item_id']; ?>" class="btn edit-btn action-btn">
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </a>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                    <button type="submit" name="delete_item" class="btn delete-btn action-btn" 
                                                            onclick="return confirm('Are you sure you want to delete this menu item?')">
                                                        <i class="fas fa-trash"></i>
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Categories Tab -->
            <div id="categories" class="tab-content">
                <!-- Add Category Form -->
                <div class="form-container">
                    <h2 class="form-title">
                        <i class="fas fa-folder-plus"></i>
                        Add New Category
                    </h2>
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="new_category_name" class="form-label">Category Name</label>
                                <input type="text" id="new_category_name" name="new_category_name" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_category_icon" class="form-label">Icon (Emoji)</label>
                                <input type="text" id="new_category_icon" name="new_category_icon" class="form-input" 
                                       placeholder="🍕" maxlength="2">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_category" class="btn btn-success">
                                <i class="fas fa-plus"></i>
                                Add Category
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Categories Table -->
                <div class="table-container">
                    <h2 class="form-title">
                        <i class="fas fa-folder"></i>
                        Current Categories
                    </h2>
                    
                    <?php if (empty($categories)): ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <h3>No Categories</h3>
                            <p>Add your first category using the form above.</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Icon</th>
                                    <th>Category Name</th>
                                    <th>Menu Items Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <?php
                                    // Count menu items in this category
                                    $count_stmt = $pdo->prepare("SELECT COUNT(*) as item_count FROM menu_items WHERE category_name = ?");
                                    $count_stmt->execute([$category['category_name']]);
                                    $item_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['item_count'];
                                    ?>
                                    <tr>
                                        <td style="font-size: 1.5rem;"><?php echo $category['icon'] ?? '📦'; ?></td>
                                        <td><?php echo $category['category_name']; ?></td>
                                        <td>
                                            <span class="category-badge">
                                                <?php echo $item_count; ?> item(s)
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const priceInput = form.querySelector('input[name="price"]');
                    if (priceInput && parseFloat(priceInput.value) <= 0) {
                        e.preventDefault();
                        alert('Price must be greater than 0.');
                        priceInput.focus();
                    }
                });
            });
            
            // Tab functionality
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show target content
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === targetTab) {
                            content.classList.add('active');
                        }
                    });
                });
            });
            
            // If there's an edit parameter, scroll to the form
            if (window.location.search.includes('edit=')) {
                document.querySelector('.form-container').scrollIntoView({ 
                    behavior: 'smooth' 
                });
            }
            
            // Auto-hide success alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.classList.contains('alert-success')) {
                        alert.style.transition = 'opacity 0.5s ease';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 500);
                    }
                });
            }, 5000);
        });
    </script>
</body>
</html>