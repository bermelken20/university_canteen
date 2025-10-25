
<?php
require_once 'db.php';
session_start();

// Redirect if not logged in
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
    // If user not found, redirect to signin
    session_destroy();
    header('Location: signin.php');
    exit();
}

// Get user's full name
$user_name = $user['first_name'] . ' ' . $user['last_name'];

// Process cancellation if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = $_POST['order_id'];

    // Verify that the order belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order && $order['status'] === 'pending') {
        // Update order status to cancelled
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ?");
        $stmt->execute([$order_id]);

        // Log the cancellation
        $stmt = $pdo->prepare("INSERT INTO order_status_log (order_id, status, changed_by, notes) VALUES (?, 'cancelled', ?, 'Order cancelled by customer')");
        $stmt->execute([$order_id, $user_id]);

        // Redirect to refresh the page and show updated status
        header('Location: order_history.php?cancelled=' . $order_id);
        exit();
    }
}

// Fetch order history for the user - FIXED TABLE NAME: order_items
$stmt = $pdo->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) as item_count
    FROM orders o 
    WHERE o.user_id = ? 
    ORDER BY o.order_date DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If a specific order is requested (from the success modal)
$highlight_order = isset($_GET['new_order']) ? intval($_GET['new_order']) : 0;
$cancelled_order = isset($_GET['cancelled']) ? intval($_GET['cancelled']) : 0;

// Fetch specific order details if viewing
$order_details = null;
$order_items = [];
$order_history = [];
$viewing_order_id = null;

if (isset($_GET['view'])) {
    $viewing_order_id = $_GET['view'];

    try {
        // Verify the order belongs to the user
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
        $stmt->execute([$viewing_order_id, $user_id]);
        $order_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order_details) {
            // Get order items - FIXED VERSION
            $stmt = $pdo->prepare("
                SELECT oi.*, mi.item_name, mi.price, mi.image_url, mi.category_name
                FROM order_items oi 
                LEFT JOIN menu_items mi ON oi.item_id = mi.item_id 
                WHERE oi.order_id = ?
            ");
            if ($stmt->execute([$viewing_order_id])) {
                $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Debug output
                if (empty($order_items)) {
                    error_log("DEBUG: No items found for order " . $viewing_order_id);
                    // Additional debug: check what's actually in the database
                    $debug_stmt = $pdo->prepare("SELECT * FROM order_ltems WHERE order_id = ?");
                    $debug_stmt->execute([$viewing_order_id]);
                    $debug_items = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
                    error_log("DEBUG: Raw items from order_ltems: " . print_r($debug_items, true));
                } else {
                    error_log("DEBUG: Found " . count($order_items) . " items for order " . $viewing_order_id);
                }
            } else {
                $error = $stmt->errorInfo();
                error_log("Database error: " . $error[2]);
            }

            // Get order status history
            $stmt = $pdo->prepare("
                SELECT osl.*, u.first_name, u.last_name
                FROM order_status_log osl 
                LEFT JOIN users u ON osl.changed_by = u.user_id 
                WHERE osl.order_id = ? 
                ORDER BY osl.change_date DESC
            ");
            $stmt->execute([$viewing_order_id]);
            $order_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error fetching order details: " . $e->getMessage());
        echo "<div class='alert-message alert-error'>Error loading order details: " . $e->getMessage() . "</div>";
    }
}

// Function to format status badge
function formatStatus($status)
{
    $status_classes = [
        'pending' => 'status-pending',
        'preparing' => 'status-preparing',
        'ready' => 'status-ready',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled'
    ];

    $status_icons = [
        'pending' => 'fa-clock',
        'preparing' => 'fa-utensils',
        'ready' => 'fa-check-circle',
        'completed' => 'fa-check-double',
        'cancelled' => 'fa-times-circle'
    ];

    $class = $status_classes[$status] ?? 'status-pending';
    $icon = $status_icons[$status] ?? 'fa-clock';
    $text = ucfirst($status);

    return "<span class='status-badge $class'><i class='fas $icon'></i> $text</span>";
}

// Function to check if order can be cancelled
function canCancelOrder($status, $order_date)
{
    // Only allow cancellation for pending orders within the last 5 minutes
    if ($status !== 'pending') {
        return false;
    }

    $order_time = strtotime($order_date);
    $current_time = time();
    $time_diff = $current_time - $order_time;

    // Allow cancellation within 5 minutes (300 seconds)
    return $time_diff <= 300;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - University Canteen Kiosk</title>
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

        .dashboard-container {
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

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar {
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

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-role {
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
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            padding: 0;
            cursor: pointer;
        }

        .logout-btn:hover {
            color: white;
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

        .new-order-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary);
            color: white;
            padding: 10px 15px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            margin-top: 10px;
        }

        .new-order-btn:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Orders Section */
        .orders-section {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .orders-count {
            background: var(--primary);
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .table-container {
            overflow-x: auto;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th {
            background: var(--light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border);
        }

        .orders-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .orders-table tr:hover {
            background: var(--light);
        }

        .order-id {
            font-weight: 600;
            color: var(--primary);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-preparing {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-ready {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #e2e3e5;
            color: #383d41;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-view {
            background: var(--info);
            color: white;
        }

        .btn-cancel {
            background: var(--danger);
            color: white;
        }

        .btn:disabled {
            background: var(--secondary);
            cursor: not-allowed;
        }

        .btn:hover:not(:disabled) {
            opacity: 0.9;
            transform: translateY(-1px);
        }

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

        /* Order Details Section */
        .order-details-section {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            border-left: 4px solid var(--primary);
        }

        .order-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .order-details-title {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-details-btn {
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: var(--radius);
            padding: 8px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
            text-decoration: none;
        }

        .close-details-btn:hover {
            background: var(--dark);
        }

        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-card {
            background: var(--light);
            border-radius: var(--radius);
            padding: 15px;
        }

        .info-title {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 5px;
        }

        .info-value {
            font-weight: 600;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th {
            background: var(--light);
            padding: 10px;
            text-align: left;
            font-weight: 600;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid var(--light-gray);
        }

        .item-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }

        .history-timeline {
            border-left: 2px solid var(--primary);
            padding-left: 20px;
            margin-left: 10px;
        }

        .history-item {
            margin-bottom: 15px;
            position: relative;
        }

        .history-item:before {
            content: '';
            position: absolute;
            left: -26px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
        }

        .history-date {
            font-size: 0.8rem;
            color: var(--secondary);
        }

        .history-action {
            font-weight: 500;
        }

        .history-admin {
            font-size: 0.8rem;
            color: var(--secondary);
        }

        .special-instructions {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: var(--radius);
            border-left: 4px solid var(--primary);
        }

        .instructions-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: var(--dark);
            font-weight: 600;
        }

        .alert-message {
            padding: 12px 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .highlighted {
            animation: highlight 2s ease-in-out;
        }

        @keyframes highlight {
            0% { background-color: rgba(235, 108, 30, 0.1); }
            100% { background-color: transparent; }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .orders-table {
                font-size: 0.8rem;
            }
            
            .action-buttons {
                flex-direction: column;
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
                        <span class="canteen">Canteen</span><span class="kiosk">Kiosk</span>
                    </div>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                        <div class="user-role">Customer</div>
                    </div>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="menu_order.php" class="menu-item">
                    <i class="fas fa-utensils"></i>
                    <span>Menu & Order</span>
                </a>
                <a href="order_history.php" class="menu-item active">
                    <i class="fas fa-history"></i>
                    <span>Order History</span>
                </a>
            </div>
            
            <div class="sidebar-footer">
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Order History</h1>
                    <p class="welcome-text">View your past and current orders</p>
                    <a href="menu_order.php" class="new-order-btn">
                        <i class="fas fa-plus"></i>
                        Place New Order
                    </a>
                </div>
                <div style="color: var(--secondary); font-size: 0.9rem;">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
                </div>
            </div>

            <?php if ($cancelled_order): ?>
            <div class="alert-message alert-success">
                <i class="fas fa-check-circle"></i>
                Order #<?php echo $cancelled_order; ?> has been successfully cancelled.
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['new_order'])): ?>
            <div class="alert-message alert-success">
                <i class="fas fa-check-circle"></i>
                Order #<?php echo htmlspecialchars($_GET['new_order']); ?> has been placed successfully!
            </div>
            <?php endif; ?>
            
            <!-- Debug Information -->
            <?php if ($order_details && empty($order_items)): ?>
            <div class="alert-message alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                Debug: Order #<?php echo $viewing_order_id; ?> exists but no items found. 
                Check database table 'order_ltems' for records with order_id = <?php echo $viewing_order_id; ?>
            </div>
            <?php endif; ?>
            
            <!-- Order Details Section (Shows when viewing an order) -->
            <?php if ($order_details): ?>
            <div class="order-details-section">
                <div class="order-details-header">
                    <h2 class="order-details-title">
                        <i class="fas fa-receipt"></i>
                        Order #<?php echo htmlspecialchars($order_details['order_id']); ?>
                    </h2>
                    <a href="order_history.php" class="close-details-btn">
                        <i class="fas fa-times"></i>
                        Close
                    </a>
                </div>
                
                <!-- Order Information -->
                <div class="order-info-grid">
                    <div class="info-card">
                        <div class="info-title">Order Status</div>
                        <div class="info-value">
                            <?php echo formatStatus($order_details['status']); ?>
                        </div>
                        <div class="info-details">
                            Ordered on <?php echo date('M j, Y g:i A', strtotime($order_details['order_date'])); ?>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-title">Order Summary</div>
                        <div class="info-value">₱<?php echo number_format($order_details['total'], 2); ?></div>
                        <div class="info-details">
                            <?php echo count($order_items); ?> items
                        </div>
                    </div>
                </div>

                <!-- Special Instructions -->
                <?php if (!empty($order_details['special_instructions'])): ?>
                <div class="special-instructions">
                    <div class="instructions-header">
                        <i class="fas fa-sticky-note"></i>
                        <span>Your Special Instructions:</span>
                    </div>
                    <div class="instructions-content">
                        <?php echo nl2br(htmlspecialchars($order_details['special_instructions'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Order Items -->
                <h3 style="margin-bottom: 15px;">Order Items</h3>
                <?php if (!empty($order_items)): ?>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $calculated_total = 0;
                        foreach ($order_items as $item):
                            $subtotal = $item['price'] * $item['quantity'];
                            $calculated_total += $subtotal;
                            ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                             class="item-image">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                            <i class="fas fa-utensils" style="color: #ccc;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                            <td>₱<?php echo number_format($subtotal, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background: var(--light);">
                            <td colspan="4" style="text-align: right; font-weight: 600;">Total:</td>
                            <td style="font-weight: 600;">₱<?php echo number_format($order_details['total'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <h4>No Items Found</h4>
                    <p>We couldn't find any items for this order in the database.</p>
                    <div style="margin-top: 10px; font-size: 0.9rem; color: var(--secondary);">
                        <strong>Order Total:</strong> ₱<?php echo number_format($order_details['total'], 2); ?><br>
                        <strong>Order Date:</strong> <?php echo date('M j, Y g:i A', strtotime($order_details['order_date'])); ?><br>
                        <strong>Order ID:</strong> <?php echo $order_details['order_id']; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Order History -->
                <?php if (!empty($order_history)): ?>
                <h3 style="margin: 25px 0 15px 0;">Order Updates</h3>
                <div class="history-timeline">
                    <?php foreach ($order_history as $history): ?>
                    <div class="history-item">
                        <div class="history-date">
                            <?php echo date('M j, g:i A', strtotime($history['change_date'])); ?>
                        </div>
                        <div class="history-action">
                            Status updated to: <?php echo formatStatus($history['status']); ?>
                        </div>
                        <?php if (!empty($history['first_name'])): ?>
                        <div class="history-admin">
                            Updated by: <?php echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($history['notes'])): ?>
                        <div class="history-admin">
                            Note: <?php echo htmlspecialchars($history['notes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Orders List Section -->
            <div class="orders-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-shopping-cart"></i>
                        My Orders
                        <span class="orders-count"><?php echo count($orders); ?></span>
                    </h2>
                </div>
                
                <?php if (!empty($orders)): ?>
                <div class="table-container">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Order Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr class="<?php echo ($order['order_id'] == $highlight_order || $order['order_id'] == $cancelled_order) ? 'highlighted' : ''; ?>" 
                                id="order-<?php echo $order['order_id']; ?>">
                                <td class="order-id">#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                <td><?php echo $order['item_count']; ?> items</td>
                                <td>₱<?php echo number_format($order['total'], 2); ?></td>
                                <td>
                                    <?php echo formatStatus($order['status']); ?>
                                </td>
                                <td><?php echo date('M j, g:i A', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?view=<?php echo $order['order_id']; ?>" class="btn btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if (canCancelOrder($order['status'], $order['order_date'])): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                <button type="submit" name="cancel_order" class="btn btn-cancel">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($order['status'] === 'pending'): ?>
                                            <button class="btn" disabled title="Cancellation period has expired">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>No Orders Yet</h3>
                    <p>You haven't placed any orders yet. Start your first order now!</p>
                    <a href="menu_order.php" class="new-order-btn" style="margin-top: 15px;">
                        <i class="fas fa-utensils"></i> Order Now
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Logout function
        function logout() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = 'logout.php';
            }
        }

        // Scroll to highlighted order if present
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($highlight_order || $cancelled_order): ?>
                const orderId = <?php echo $highlight_order ?: $cancelled_order; ?>;
                const orderElement = document.getElementById('order-' + orderId);
                if (orderElement) {
                    orderElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            <?php endif; ?>

            // Auto-hide success messages after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert-message');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);

            // If viewing an order, scroll to the order details section
            <?php if ($order_details): ?>
                const orderDetailsSection = document.querySelector('.order-details-section');
                if (orderDetailsSection) {
                    orderDetailsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>