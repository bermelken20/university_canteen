
<?php
require_once 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

// Fetch user data from database
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: signin.php');
    exit();
}

// Get user's full name
$user_name = $user['first_name'] . ' ' . $user['last_name'];

// Fetch menu items from database
try {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE available = 1 ORDER BY category_name, item_name");
    $stmt->execute();
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $menu_items = $menu_items ?: [];
} catch (Exception $e) {
    error_log("Error fetching menu items: " . $e->getMessage());
    $menu_items = [];
}

// Get unique categories for filter buttons
$categories = [];
foreach ($menu_items as $item) {
    if (!in_array($item['category_name'], $categories)) {
        $categories[] = $item['category_name'];
    }
}

// Group items by category
$menu_by_category = [];
foreach ($menu_items as $item) {
    if ($item['available'] == 1) {
        $menu_by_category[$item['category_name']][] = $item;
    }
}

// Process order if form is submitted
$error = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $order_items = [];
    $total_amount = 0;

    // Collect items from the order form
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'item_') === 0 && $value > 0) {
            $item_id = substr($key, 5);
            $quantity = (int) $value;

            // Find the item details
            foreach ($menu_items as $item) {
                if ($item['item_id'] == $item_id) {
                    $order_items[] = [
                        'item_id' => $item_id,
                        'name' => $item['item_name'],
                        'price' => $item['price'],
                        'quantity' => $quantity
                    ];
                    $total_amount += $item['price'] * $quantity;
                    break;
                }
            }
        }
    }

    // Add service fee to total amount
    $service_fee = 15.00;
    $total_amount += $service_fee;

    // Get special instructions
    $special_instructions = isset($_POST['special_instructions']) ? trim($_POST['special_instructions']) : '';

    if (!empty($order_items)) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Create order record - FIXED: Removed potential UNIQUE constraint issue
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, status, items, special_instructions, created_at) VALUES (?, ?, 'pending', ?, ?, NOW())");

            // Create items summary
            $items_summary = [];
            foreach ($order_items as $item) {
                $items_summary[] = $item['quantity'] . 'x ' . $item['name'];
            }
            $items_text = implode(', ', $items_summary);

            $stmt->execute([$user_id, $total_amount, $items_text, $special_instructions]);
            $order_id = $pdo->lastInsertId();

            // Add order items
            foreach ($order_items as $item) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, item_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $item['item_id'], $item['quantity'], $item['price']]);
            }

            // Add initial status log
            $stmt = $pdo->prepare("INSERT INTO order_status_log (order_id, status, changed_by, notes) VALUES (?, 'pending', ?, 'Order placed by customer')");
            $stmt->execute([$order_id, $user_id]);

            // Commit transaction
            $pdo->commit();

            // Clear cart and show success message
            $success_message = "Order placed successfully! Your order ID is: $order_id";

            // Redirect to avoid form resubmission
            header("Location: menu_order.php?success=1&order_id=$order_id");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Order Error: " . $e->getMessage());

            // More specific error handling
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'user_id') !== false) {
                $error = "You already have a pending order. Please wait for your current order to be processed or contact staff.";
            } else {
                $error = "Failed to place order. Please try again. Error: " . $e->getMessage();
            }
        }
    } else {
        $error = "Please select at least one item to order.";
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['order_id'])) {
    $success_message = "Order placed successfully! Your order ID is: " . htmlspecialchars($_GET['order_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu & Order - University Canteen Kiosk</title>
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
            display: flex;
            flex-direction: column;
        }

        .dashboard-container {
            display: flex;
            flex: 1;
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
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .logo-text {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .logo-text .canteen {
            color: var(--primary-light);
        }

        .logo-text .kiosk {
            color: white;
        }

        .sidebar-nav {
            flex: 1;
        }

        .nav-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: var(--transition);
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-item i {
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding: 0 10px;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .user-details {
            flex: 1;
            overflow: hidden;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: white;
            margin-bottom: 3px;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.7;
            color: white;
        }

        .logout-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: rgba(255,255,255,0.1);
            color: white;
            text-align: center;
            border-radius: var(--radius);
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: var(--primary);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-text {
            color: var(--secondary);
            margin-top: 5px;
        }

        .date-display {
            background: white;
            padding: 10px 15px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Category Filter Styles */
        .category-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
            padding: 15px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-btn:hover {
            background: var(--light-gray);
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Menu Styles */
        .menu-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 25px;
        }

        @media (max-width: 1024px) {
            .menu-container {
                grid-template-columns: 1fr;
            }
        }

        .menu-categories {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .category-section {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .category-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .category-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .category-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark);
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .menu-card {
            background: white;
            border: 1px solid var(--light-gray);
            border-radius: var(--radius);
            overflow: hidden;
            transition: var(--transition);
        }

        .menu-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        .menu-card-image {
            height: 180px;
            background-color: #f5f5f5;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .item-price {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .menu-card-content {
            padding: 15px;
        }

        .menu-card-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
            font-size: 1.1rem;
        }

        .menu-card-description {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.4;
            height: 40px;
            overflow: hidden;
        }

        .menu-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .menu-card-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .menu-card-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .menu-card-btn {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .menu-card-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .menu-card-quantity {
            width: 40px;
            text-align: center;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 5px;
            font-weight: 500;
        }

        /* Order Summary */
        .order-summary {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }

        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .summary-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .clear-cart {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .clear-cart:hover {
            text-decoration: underline;
        }

        .cart-items {
            margin-bottom: 20px;
            min-height: 100px;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .item-info {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 500;
            margin-bottom: 3px;
        }

        .cart-item-quantity {
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .item-total {
            font-weight: 600;
            color: var(--dark);
        }

        .cart-empty {
            text-align: center;
            padding: 30px 0;
            color: var(--secondary);
        }

        .cart-empty i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--light-gray);
        }

        .cart-totals {
            border-top: 2px solid var(--light-gray);
            padding-top: 15px;
            margin-bottom: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            align-items: center;
        }

        .total-label {
            color: var(--secondary);
        }

        .total-amount {
            font-weight: 600;
        }

        .grand-total {
            font-size: 1.2rem;
            color: var(--primary);
            font-weight: 700;
            border-top: 2px solid var(--light-gray);
            padding-top: 10px;
        }

        /* Special Instructions */
        .special-instructions {
            margin-bottom: 20px;
        }

        .instructions-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .instructions-textarea {
            width: 100%;
            min-height: 80px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            resize: vertical;
            font-family: inherit;
            transition: var(--transition);
        }

        .instructions-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(235, 108, 30, 0.1);
        }

        .instructions-note {
            font-size: 0.8rem;
            color: var(--secondary);
            margin-top: 5px;
        }

        .checkout-btn {
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .checkout-btn:hover {
            background: var(--primary-dark);
        }

        .checkout-btn:disabled {
            background: var(--secondary);
            cursor: not-allowed;
        }

        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
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
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                padding: 10px;
            }

            .sidebar-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-bottom: 10px;
            }

            .nav-item {
                padding: 10px;
                border-radius: var(--radius);
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .date-display {
                align-self: stretch;
            }
            
            .category-filter {
                justify-content: center;
            }
            
            .filter-btn {
                font-size: 0.9rem;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="logo-text">
                        <span class="canteen">Canteen</span> <span class="kiosk">Kiosk</span>
                    </div>
                </div>
            </div>
            
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="menu_order.php" class="nav-item active">
                    <i class="fas fa-utensils"></i>
                    <span>Menu & Order</span>
                </a>
                <a href="order_history.php" class="nav-item">
                    <i class="fas fa-history"></i>
                    <span>Order History</span>
                </a>
            </div>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=eb6c1e&color=fff" alt="User Profile">
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                        <div class="user-role">Student</div>
                    </div>
                </div>
                <form method="POST" action="logout.php">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-utensils"></i>
                        Menu & Order
                    </h1>
                    <p class="welcome-text">Select food items and place your order</p>
                </div>
                <div class="date-display">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo date('l, F j, Y'); ?>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($menu_items)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    No menu items available at the moment. Please check back later or contact the canteen staff.
                </div>
            <?php endif; ?>

            <form method="POST" id="order-form">
                <div class="menu-container">
                    <div>
                        <!-- Category Filter -->
                        <div class="category-filter">
                            <button type="button" class="filter-btn active" data-category="all">
                                <i class="fas fa-th-large"></i>
                                All Categories
                            </button>
                            <?php foreach ($categories as $category): ?>
                                <button type="button" class="filter-btn" data-category="<?php echo htmlspecialchars($category); ?>">
                                    <?php
                                    switch ($category) {
                                        case 'Dish':
                                            echo '<i class="fas fa-utensils"></i>';
                                            break;
                                        case 'Rice':
                                            echo '<i class="fas fa-bowl-rice"></i>';
                                            break;
                                        case 'Merienda':
                                            echo '<i class="fas fa-cookie-bite"></i>';
                                            break;
                                        case 'Beverages':
                                            echo '<i class="fas fa-glass-whiskey"></i>';
                                            break;
                                        case 'Pasta & Noodles':
                                            echo '<i class="fas fa-bacon"></i>';
                                            break;
                                        case 'Desserts':
                                            echo '<i class="fas fa-ice-cream"></i>';
                                            break;
                                        default:
                                            echo '<i class="fas fa-utensils"></i>';
                                    }
                                    ?>
                                    <?php echo htmlspecialchars($category); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Menu Categories -->
                        <div class="menu-categories">
                            <?php if (!empty($menu_by_category)): ?>
                                <?php foreach ($menu_by_category as $category => $items): ?>
                                    <div class="category-section" data-category="<?php echo htmlspecialchars($category); ?>">
                                        <div class="category-header">
                                            <div class="category-icon">
                                                <?php
                                                switch ($category) {
                                                    case 'Dish':
                                                        echo '<i class="fas fa-utensils"></i>';
                                                        break;
                                                    case 'Rice':
                                                        echo '<i class="fas fa-bowl-rice"></i>';
                                                        break;
                                                    case 'Merienda':
                                                        echo '<i class="fas fa-cookie-bite"></i>';
                                                        break;
                                                    case 'Beverages':
                                                        echo '<i class="fas fa-glass-whiskey"></i>';
                                                        break;
                                                    case 'Pasta & Noodles':
                                                        echo '<i class="fas fa-bacon"></i>';
                                                        break;
                                                    case 'Desserts':
                                                        echo '<i class="fas fa-ice-cream"></i>';
                                                        break;
                                                    default:
                                                        echo '<i class="fas fa-utensils"></i>';
                                                }
                                                ?>
                                            </div>
                                            <h2 class="category-title"><?php echo htmlspecialchars($category); ?></h2>
                                        </div>
                                        <div class="menu-grid">
                                            <?php foreach ($items as $item): ?>
                                                <div class="menu-card">
                                                    <div class="menu-card-image" style="background-image: url('<?php echo !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'https://via.placeholder.com/300x180?text=No+Image'; ?>');">
                                                        <div class="item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                                                    </div>
                                                    <div class="menu-card-content">
                                                        <h3 class="menu-card-title"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                                        <p class="menu-card-description"><?php echo htmlspecialchars($item['description']); ?></p>
                                                        <div class="menu-card-footer">
                                                            <div class="menu-card-price">₱<?php echo number_format($item['price'], 2); ?></div>
                                                            <div class="menu-card-controls">
                                                                <button type="button" class="menu-card-btn minus" data-item-id="<?php echo $item['item_id']; ?>">-</button>
                                                                <input type="number" name="item_<?php echo $item['item_id']; ?>" id="item_<?php echo $item['item_id']; ?>" value="0" min="0" max="10" class="menu-card-quantity" readonly>
                                                                <button type="button" class="menu-card-btn plus" data-item-id="<?php echo $item['item_id']; ?>">+</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-utensils"></i>
                                    <h3>No Menu Items Available</h3>
                                    <p>Please check back later or contact the canteen staff.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="order-summary">
                        <div class="summary-header">
                            <h2 class="summary-title">
                                <i class="fas fa-shopping-cart"></i>
                                Order Summary
                            </h2>
                            <button type="button" class="clear-cart" id="clear-cart">
                                <i class="fas fa-trash"></i>
                                Clear
                            </button>
                        </div>

                        <div class="cart-items" id="cart-items">
                            <div class="cart-empty" id="empty-cart">
                                <i class="fas fa-shopping-cart"></i>
                                <p>Your cart is empty</p>
                                <p>Select items to add to your order</p>
                            </div>
                        </div>

                        <div class="cart-totals">
                            <div class="total-row">
                                <span class="total-label">Subtotal</span>
                                <span class="total-amount" id="subtotal">₱0.00</span>
                            </div>
                            <div class="total-row">
                                <span class="total-label">Service Fee</span>
                                <span class="total-amount">₱15.00</span>
                            </div>
                            <div class="total-row grand-total">
                                <span class="total-label">Total</span>
                                <span class="total-amount" id="total">₱15.00</span>
                            </div>
                        </div>

                        <div class="special-instructions">
                            <label for="special_instructions" class="instructions-label">
                                <i class="fas fa-sticky-note"></i>
                                Special Instructions
                            </label>
                            <textarea name="special_instructions" id="special_instructions" class="instructions-textarea" placeholder="Any special requests or instructions for your order..."><?php echo isset($_POST['special_instructions']) ? htmlspecialchars($_POST['special_instructions']) : ''; ?></textarea>
                            <p class="instructions-note">Note: We'll try our best to accommodate your requests</p>
                        </div>

                        <button type="submit" name="place_order" class="checkout-btn" id="checkout-btn" disabled>
                            <i class="fas fa-check-circle"></i>
                            Place Order
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Category filter functionality
            const filterButtons = document.querySelectorAll('.filter-btn');
            const categorySections = document.querySelectorAll('.category-section');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const category = this.getAttribute('data-category');
                    
                    // Update active button
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show/hide categories
                    if (category === 'all') {
                        categorySections.forEach(section => {
                            section.style.display = 'block';
                        });
                    } else {
                        categorySections.forEach(section => {
                            if (section.getAttribute('data-category') === category) {
                                section.style.display = 'block';
                            } else {
                                section.style.display = 'none';
                            }
                        });
                    }
                });
            });
            
            // Quantity control functionality
            const plusButtons = document.querySelectorAll('.plus');
            const minusButtons = document.querySelectorAll('.minus');
            const quantityInputs = document.querySelectorAll('.menu-card-quantity');
            
            plusButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-item-id');
                    const input = document.getElementById(`item_${itemId}`);
                    let value = parseInt(input.value) || 0;
                    
                    if (value < 10) {
                        input.value = value + 1;
                        updateCart();
                    }
                });
            });
            
            minusButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-item-id');
                    const input = document.getElementById(`item_${itemId}`);
                    let value = parseInt(input.value) || 0;
                    
                    if (value > 0) {
                        input.value = value - 1;
                        updateCart();
                    }
                });
            });
            
            // Clear cart functionality
            document.getElementById('clear-cart').addEventListener('click', function() {
                quantityInputs.forEach(input => {
                    input.value = 0;
                });
                updateCart();
            });
            
            // Update cart function
            function updateCart() {
                const cartItems = document.getElementById('cart-items');
                const emptyCart = document.getElementById('empty-cart');
                const checkoutBtn = document.getElementById('checkout-btn');
                
                let subtotal = 0;
                let hasItems = false;
                
                // Clear current cart display
                cartItems.innerHTML = '';
                
                // Build cart items
                quantityInputs.forEach(input => {
                    const quantity = parseInt(input.value) || 0;
                    
                    if (quantity > 0) {
                        hasItems = true;
                        const itemId = input.name.replace('item_', '');
                        const itemCard = input.closest('.menu-card');
                        const itemName = itemCard.querySelector('.menu-card-title').textContent;
                        const itemPrice = parseFloat(itemCard.querySelector('.menu-card-price').textContent.replace('₱', ''));
                        const itemTotal = itemPrice * quantity;
                        
                        subtotal += itemTotal;
                        
                        const cartItem = document.createElement('div');
                        cartItem.className = 'cart-item';
                        cartItem.innerHTML = `
                            <div class="item-info">
                                <div class="cart-item-name">${itemName}</div>
                                <div class="cart-item-quantity">${quantity} x ₱${itemPrice.toFixed(2)}</div>
                            </div>
                            <div class="item-total">₱${itemTotal.toFixed(2)}</div>
                        `;
                        
                        cartItems.appendChild(cartItem);
                    }
                });
                
                // Show empty cart message if no items
                if (!hasItems) {
                    cartItems.appendChild(emptyCart);
                    checkoutBtn.disabled = true;
                } else {
                    checkoutBtn.disabled = false;
                }
                
                // Update totals
                const serviceFee = 15.00;
                const total = subtotal + serviceFee;
                
                document.getElementById('subtotal').textContent = `₱${subtotal.toFixed(2)}`;
                document.getElementById('total').textContent = `₱${total.toFixed(2)}`;
            }
            
            // Form submission
            document.getElementById('order-form').addEventListener('submit', function(e) {
                const hasItems = Array.from(quantityInputs).some(input => {
                    return parseInt(input.value) > 0;
                });
                
                if (!hasItems) {
                    e.preventDefault();
                    alert('Please select at least one item to order.');
                }
            });
            
            // Initialize cart
            updateCart();
        });
    </script>
</body>
</html