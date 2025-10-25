
<?php
require_once 'db.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: adminsignin.php');
    exit();
}

// Get admin information from session
$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];
$admin_role = $_SESSION['admin_role'];
$admin_full_name = $_SESSION['admin_full_name'];

// Check if we need to migrate menu items from menu_order.php
if (isset($_GET['migrate']) && $_GET['migrate'] == '1') {
    try {
        // Clear existing menu items
        $pdo->exec("DELETE FROM menu_items");

        // Define menu items from menu_order.php - updated with category_name instead of category_id
        $menu_items = [
            // Dishes (formerly Rice Meals)
            ['item_name' => 'Chicken Teriyaki', 'price' => 45.00, 'category_name' => 'Dish', 'description' => 'Grilled chicken teriyaki', 'available' => 1, 'image_url' => 'https://i.pinimg.com/1200x/a9/e0/48/a9e048cdfdf298cfb9f6ff4758bfec39.jpg'],
            ['item_name' => 'Fried Chicken', 'price' => 25.00, 'category_name' => 'Dish', 'description' => 'Crispy fried chicken', 'available' => 1, 'image_url' => 'https://i.pinimg.com/736x/71/b4/f0/71b4f0fe34997044f43d7f683572a1a1.jpg'],
            // ... (rest of the menu items remain the same)
        ];

        // Insert new menu items - updated to use category_name
        $stmt = $pdo->prepare("INSERT INTO menu_items (item_name, description, price, category_name, available, image_url, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($menu_items as $item) {
            // Set a default stock quantity
            $stock_quantity = 50;

            $stmt->execute([
                $item['item_name'],
                $item['description'],
                $item['price'],
                $item['category_name'],
                $item['available'],
                $item['image_url'],
                $stock_quantity
            ]);
        }

        $migration_success = "Migration completed successfully! " . count($menu_items) . " menu items imported.";

    } catch (Exception $e) {
        $migration_error = "Error during migration: " . $e->getMessage();
    }
}

// Fetch menu items
$category_filter = isset($_GET['category_name']) ? $_GET['category_name'] : '';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters - updated to use category_name
$query = "SELECT * FROM menu_items WHERE 1=1";
$params = [];

if (!empty($category_filter) && $category_filter != 'all') {
    $query .= " AND category_name = ?";
    $params[] = $category_filter;
}

if (!empty($search_term)) {
    $query .= " AND (item_name LIKE ? OR description LIKE ?)";
    $search_like = "%$search_term%";
    $params[] = $search_like;
    $params[] = $search_like;
}

$query .= " ORDER BY category_name, item_name";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories for filter - updated to use category_name
    $category_stmt = $pdo->prepare("SELECT DISTINCT category_name FROM menu_items ORDER BY category_name");
    $category_stmt->execute();
    $categories = $category_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    error_log("Error fetching menu items: " . $e->getMessage());
    $menu_items = [];
    $categories = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        // Add new menu item
        $item_name = $_POST['item_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $price = $_POST['price'] ?? 0;
        $category_name = $_POST['category_name'] ?? ''; // Updated to category_name
        $available = isset($_POST['available']) ? 1 : 0;

        // Handle image upload
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                $image_url = $file_path;
            }
        }

        try {
            // Updated to use category_name
            $stmt = $pdo->prepare("INSERT INTO menu_items (item_name, description, price, category_name, available, image_url, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$item_name, $description, $price, $category_name, $available, $image_url, 50]);

            // Redirect to avoid form resubmission
            header("Location: admin_menu.php?added=1");
            exit();
        } catch (Exception $e) {
            error_log("Error adding menu item: " . $e->getMessage());
            $error = "Failed to add menu item.";
        }
    } elseif (isset($_POST['update_item'])) {
        // Update menu item
        $item_id = $_POST['item_id'] ?? 0;
        $item_name = $_POST['item_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $price = $_POST['price'] ?? 0;
        $category_name = $_POST['category_name'] ?? ''; // Updated to category_name
        $available = isset($_POST['available']) ? 1 : 0;

        // Handle image upload
        $image_url = $_POST['current_image'] ?? '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Delete old image if exists
            if (!empty($image_url) && file_exists($image_url)) {
                unlink($image_url);
            }

            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                $image_url = $file_path;
            }
        }

        try {
            // Updated to use category_name
            $stmt = $pdo->prepare("UPDATE menu_items SET item_name = ?, description = ?, price = ?, category_name = ?, available = ?, image_url = ? WHERE item_id = ?");
            $stmt->execute([$item_name, $description, $price, $category_name, $available, $image_url, $item_id]);

            // Redirect to avoid form resubmission
            header("Location: admin_menu.php?updated=" . $item_id);
            exit();
        } catch (Exception $e) {
            error_log("Error updating menu item: " . $e->getMessage());
            $error = "Failed to update menu item.";
        }
    } elseif (isset($_POST['delete_item'])) {
        // Delete menu item
        $item_id = $_POST['item_id'] ?? 0;

        // Get image path to delete file
        $stmt = $pdo->prepare("SELECT image_url FROM menu_items WHERE item_id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        // Delete image file if exists
        if (!empty($item['image_url']) && file_exists($item['image_url'])) {
            unlink($item['image_url']);
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM menu_items WHERE item_id = ?");
            $stmt->execute([$item_id]);

            // Redirect to avoid form resubmission
            header("Location: admin_menu.php?deleted=" . $item_id);
            exit();
        } catch (Exception $e) {
            error_log("Error deleting menu item: " . $e->getMessage());
            $error = "Failed to delete menu item.";
        }
    } elseif (isset($_POST['toggle_availability'])) {
        // Toggle item availability
        $item_id = $_POST['item_id'] ?? 0;
        $available = $_POST['available'] ?? 0;

        try {
            $stmt = $pdo->prepare("UPDATE menu_items SET available = ? WHERE item_id = ?");
            $stmt->execute([$available, $item_id]);

            // Redirect to avoid form resubmission
            header("Location: admin_menu.php?toggled=" . $item_id);
            exit();
        } catch (Exception $e) {
            error_log("Error toggling availability: " . $e->getMessage());
            $error = "Failed to update availability.";
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

        /* Filters and search */
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--secondary);
        }

        .filter-select, .filter-input {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.9rem;
        }

        .filter-button {
            padding: 8px 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            align-self: flex-end;
        }

        /* Menu Items Grid */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .menu-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .menu-image {
            height: 180px;
            overflow: hidden;
            position: relative;
        }

        .menu-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .menu-card:hover .menu-image img {
            transform: scale(1.05);
        }

        .menu-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--primary);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .menu-content {
            padding: 16px;
        }

        .menu-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .menu-title {
            font-weight: 600;
            font-size: 18px;
            color: var(--dark);
        }

        .menu-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 18px;
        }

        .menu-description {
            color: var(--secondary);
            margin-bottom: 15px;
            font-size: 14px;
            line-height: 1.5;
        }

        .menu-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border);
            padding-top: 15px;
        }

        .availability-toggle {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .availability-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--success);
        }

        input:checked + .slider:before {
            transform: translateX(20px);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 6px 10px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 13px;
            transition: var(--transition);
        }

        .btn-edit {
            background: rgba(72, 149, 239, 0.1);
            color: var(--info);
        }

        .btn-edit:hover {
            background: var(--info);
            color: white;
        }

        .btn-danger {
            background: rgba(247, 37, 133, 0.1);
            color: var(--danger);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: white;
        }

        .add-item-btn {
            background: var(--success);
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: var(--transition);
        }

        .add-item-btn:hover {
            background: #3aafd9;
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: var(--radius);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-weight: 600;
            font-size: 18px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--secondary);
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-family: 'Inter', sans-serif;
        }

        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-checkbox input {
            width: 16px;
            height: 16px;
        }

        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--dark);
            border: none;
            padding: 10px 16px;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
        }

        .btn-secondary:hover {
            background: #dde1e7;
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

        .alert-danger {
            background: rgba(247, 37, 133, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert i {
            font-size: 20px;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 10px;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
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
                <a href="menu.php" class="menu-item active">
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
                    <p class="welcome-text">Manage your menu items and categories</p>
                </div>
                <button class="add-item-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i>
                    Add New Item
                </button>
            </div>

            <?php if (isset($migration_success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $migration_success; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($migration_error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $migration_error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Menu item added successfully!
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Menu item updated successfully!
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Menu item deleted successfully!
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['toggled'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Availability updated successfully!
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <form method="GET" action="menu.php">
                <div class="filters">
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category_name" class="filter-select">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($category_filter == $category) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search menu items..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="filter-button">Apply Filters</button>
                    </div>
                    <?php if (empty($menu_items)): ?>
                        <div class="filter-group">
                            <a href="?migrate=1" class="filter-button" style="background-color: var(--warning);">Import Sample Menu</a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Menu Items Grid -->
            <?php if (!empty($menu_items)): ?>
                <div class="menu-grid">
                    <?php foreach ($menu_items as $item): ?>
                        <div class="menu-card">
                            <div class="menu-image">
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                <div class="menu-badge"><?php echo htmlspecialchars($item['category_name']); ?></div>
                            </div>
                            <div class="menu-content">
                                <div class="menu-header">
                                    <h3 class="menu-title"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                    <div class="menu-price">₱<?php echo number_format($item['price'], 2); ?></div>
                                </div>
                                <p class="menu-description"><?php echo htmlspecialchars($item['description']); ?></p>
                                <div class="menu-footer">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                        <input type="hidden" name="available" value="<?php echo $item['available'] ? '0' : '1'; ?>">
                                        <label class="availability-toggle">
                                            <input type="checkbox" <?php echo $item['available'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                            <span class="slider"></span>
                                        </label>
                                        <input type="hidden" name="toggle_availability" value="1">
                                    </form>
                                    <div class="action-buttons">
                                        <button class="btn btn-edit" onclick="openEditModal(
                                            <?php echo $item['item_id']; ?>,
                                            '<?php echo addslashes($item['item_name']); ?>',
                                            '<?php echo addslashes($item['description']); ?>',
                                            <?php echo $item['price']; ?>,
                                            '<?php echo addslashes($item['category_name']); ?>',
                                            <?php echo $item['available']; ?>,
                                            '<?php echo addslashes($item['image_url']); ?>'
                                        )">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <button type="submit" name="delete_item" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this menu item?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-utensils"></i>
                    <h3>No menu items found</h3>
                    <p>Add your first menu item or import sample data to get started.</p>
                    <button class="add-item-btn" onclick="openAddModal()" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i>
                        Add New Item
                    </button>
                    <?php if (empty($categories)): ?>
                        <a href="?migrate=1" class="filter-button" style="background-color: var(--warning); margin-top: 15px; display: inline-block;">
                            Import Sample Menu
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Menu Item</h2>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="item_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" name="price" step="0.01" min="0" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_name" class="form-select" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                            <option value="new">+ Add New Category</option>
                        </select>
                    </div>
                    <div class="form-group" id="newCategoryGroup" style="display: none;">
                        <label class="form-label">New Category Name</label>
                        <input type="text" name="new_category" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Image</label>
                        <input type="file" name="image" class="form-input" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="available" checked>
                            <span>Available for ordering</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_item" class="btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Menu Item</h2>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="item_id" id="edit_item_id">
                <input type="hidden" name="current_image" id="edit_current_image">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="item_name" id="edit_item_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-textarea" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" name="price" id="edit_price" step="0.01" min="0" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_name" id="edit_category_name" class="form-select" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                            <option value="new">+ Add New Category</option>
                        </select>
                    </div>
                    <div class="form-group" id="editNewCategoryGroup" style="display: none;">
                        <label class="form-label">New Category Name</label>
                        <input type="text" name="new_category" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current Image</label>
                        <img id="edit_image_preview" src="" alt="Current image" style="max-width: 100%; height: 150px; object-fit: cover; border-radius: var(--radius); margin-bottom: 10px; display: block;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Change Image</label>
                        <input type="file" name="image" class="form-input" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="available" id="edit_available">
                            <span>Available for ordering</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_item" class="btn-primary">Update Item</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(itemId, itemName, description, price, category, available, imageUrl) {
            document.getElementById('edit_item_id').value = itemId;
            document.getElementById('edit_item_name').value = itemName;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_category_name').value = category;
            document.getElementById('edit_available').checked = available == 1;
            document.getElementById('edit_current_image').value = imageUrl;
            document.getElementById('edit_image_preview').src = imageUrl;
            
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('addModal')) {
                closeAddModal();
            }
            if (event.target == document.getElementById('editModal')) {
                closeEditModal();
            }
        }

        // Show/hide new category field based on selection
        document.querySelector('select[name="category_name"]').addEventListener('change', function() {
            document.getElementById('newCategoryGroup').style.display = this.value === 'new' ? 'block' : 'none';
        });

        document.getElementById('edit_category_name').addEventListener('change', function() {
            document.getElementById('editNewCategoryGroup').style.display = this.value === 'new' ? 'block' : 'none';
        });
    </script>
</body>
</html>