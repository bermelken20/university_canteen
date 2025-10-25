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

// Process order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_order_status'])) {
        $order_id = $_POST['order_id'];
        $new_status = $_POST['status'];
        $notify_customer = isset($_POST['notify_customer']) ? 1 : 0;

        try {
            // Update order status
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->execute([$new_status, $order_id]);

            // Log the status change
            $stmt = $pdo->prepare("INSERT INTO order_status_log (order_id, status, changed_by, notes) VALUES (?, ?, ?, ?)");
            $notes = "Status changed to " . $new_status . ($notify_customer ? " - Customer notified" : "");
            $stmt->execute([$order_id, $new_status, $admin_id, $notes]);

            // Redirect to avoid form resubmission
            header("Location: admin_orders.php?updated=" . $order_id . "&status=" . $new_status);
            exit();
        } catch (Exception $e) {
            error_log("Error updating order status: " . $e->getMessage());
            $update_error = "Failed to update order status.";
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$search_query = $_GET['search'] ?? '';

// Build the query with filters - FIXED: Properly joining with users table
$query = "
    SELECT 
        o.*, 
        u.first_name, 
        u.last_name, 
        u.email, 
        u.user_id, 
        u.phone,
        COUNT(oi.item_id) as item_count,
        GROUP_CONCAT(mi.name SEPARATOR ', ') as item_names
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.user_id 
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN menu_items mi ON oi.item_id = mi.item_id
    WHERE 1=1
";

$params = [];

if ($status_filter !== 'all') {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(o.order_date) = ?";
    $params[] = $date_filter;
}

if (!empty($search_query)) {
    $query .= " AND (o.order_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Group by order to avoid duplicates from JOINs
$query .= " GROUP BY o.order_id ORDER BY o.order_date DESC";

// Fetch orders
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
}

// Alternative query if the above fails
if (empty($orders)) {
    try {
        // Simple fallback query to check if orders exist
        $stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders");
        $stmt->execute();
        $order_count = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order_count['order_count'] > 0) {
            error_log("Orders exist but query might have issues. Using alternative query.");

            // Alternative simpler query
            $alt_query = "
                SELECT o.*, u.first_name, u.last_name, u.email, u.phone
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.user_id 
                WHERE 1=1
            ";

            $alt_params = [];

            if ($status_filter !== 'all') {
                $alt_query .= " AND o.status = ?";
                $alt_params[] = $status_filter;
            }

            if (!empty($date_filter)) {
                $alt_query .= " AND DATE(o.order_date) = ?";
                $alt_params[] = $date_filter;
            }

            if (!empty($search_query)) {
                $alt_query .= " AND (o.order_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
                $search_param = "%$search_query%";
                $alt_params[] = $search_param;
                $alt_params[] = $search_param;
                $alt_params[] = $search_param;
                $alt_params[] = $search_param;
                $alt_params[] = $search_param;
            }

            $alt_query .= " ORDER BY o.order_date DESC";

            $stmt = $pdo->prepare($alt_query);
            $stmt->execute($alt_params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get item counts separately
            foreach ($orders as &$order) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as item_count FROM order_items WHERE order_id = ?");
                $stmt->execute([$order['order_id']]);
                $item_count = $stmt->fetch(PDO::FETCH_ASSOC);
                $order['item_count'] = $item_count['item_count'];

                $stmt = $pdo->prepare("
                    SELECT GROUP_CONCAT(mi.name SEPARATOR ', ') as item_names
                    FROM order_items oi 
                    LEFT JOIN menu_items mi ON oi.item_id = mi.item_id 
                    WHERE oi.order_id = ?
                ");
                $stmt->execute([$order['order_id']]);
                $item_names = $stmt->fetch(PDO::FETCH_ASSOC);
                $order['item_names'] = $item_names['item_names'] ?? $order['item_count'] . ' items';
            }
            unset($order);
        }
    } catch (Exception $e) {
        error_log("Error in alternative order fetch: " . $e->getMessage());
    }
}

// Fetch specific order details if viewing a single order
$order_details = null;
$order_items = [];
$order_history = [];

if (isset($_GET['view'])) {
    $order_id = $_GET['view'];

    try {
        // Get order details - INCLUDING SPECIAL INSTRUCTIONS
        $stmt = $pdo->prepare("
            SELECT o.*, u.first_name, u.last_name, u.email, u.phone, u.user_id
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.user_id 
            WHERE o.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order_details) {
            // Get order items
            $stmt = $pdo->prepare("
                SELECT oi.*, mi.name, mi.price, mi.image_url, mi.category
                FROM order_items oi 
                LEFT JOIN menu_items mi ON oi.item_id = mi.item_id 
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get order status history
            $stmt = $pdo->prepare("
                SELECT osl.*, a.username as admin_username, a.full_name as admin_full_name
                FROM order_status_log osl 
                LEFT JOIN admins a ON osl.changed_by = a.admin_id 
                WHERE osl.order_id = ? 
                ORDER BY osl.change_date DESC
            ");
            $stmt->execute([$order_id]);
            $order_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Exception $e) {
        error_log("Error fetching order details: " . $e->getMessage());
    }
}

// Process logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: adminsignin.php');
    exit();
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

    $status_text = ucfirst($status);
    $class = $status_classes[$status] ?? 'status-pending';

    return "<span class='status-badge $class'>$status_text</span>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - University Canteen Kiosk</title>
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

        .dashboard-link {
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

        .dashboard-link:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Filters Section */
        .filters-section {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 5px;
            color: var(--secondary);
        }

        .filter-select, .filter-input, .filter-search {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.9rem;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Orders Table */
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
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
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

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .btn-view {
            background: var(--info);
            color: white;
        }

        .btn-edit {
            background: var(--warning);
            color: white;
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

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: var(--radius);
            padding: 15px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 0.8rem;
        }

        /* Order Details Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: var(--radius);
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
        }

        .modal-body {
            padding: 20px;
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

        /* NEW: Instructions indicator in table */
        .has-instructions {
            position: relative;
        }

        .instructions-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                justify-content: stretch;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
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
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="logo-text">
                        <span style="color: var(--primary);">Canteen</span><span>Kiosk</span>
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
                <a href="admin_orders.php" class="menu-item active">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
                <a href="admin_menu.php" class="menu-item">
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
                    <h1 class="page-title">Order Management</h1>
                    <p class="welcome-text">Manage all customer orders and track their status.</p>
                    <a href="admindashboard.php" class="dashboard-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Go to Dashboard for quick approval
                    </a>
                </div>
                <div style="color: var(--secondary); font-size: 0.9rem;">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
                </div>
            </div>

            <?php if (isset($_GET['updated'])): ?>
            <div class="alert-message alert-success">
                <i class="fas fa-check-circle"></i>
                Order #<?php echo htmlspecialchars($_GET['updated']); ?> has been updated to <?php echo htmlspecialchars($_GET['status']); ?> successfully.
            </div>
            <?php endif; ?>

            <?php if (isset($update_error)): ?>
            <div class="alert-message alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $update_error; ?></span>
            </div>
            <?php endif; ?>

            <!-- Debug information (can be removed in production) -->
            <?php if (empty($orders)): ?>
            <div class="debug-info">
                <strong>Debug Info:</strong><br>
                Total orders in database: 
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo $result['total'];
                } catch (Exception $e) {
                    echo "Error: " . $e->getMessage();
                }
                ?>
                <br>
                Query: <?php echo htmlspecialchars($query); ?><br>
                Parameters: <?php echo implode(', ', $params); ?>
            </div>
            <?php endif; ?>
            
            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="admin_orders.php">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select name="status" class="filter-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="preparing" <?php echo $status_filter === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                <option value="ready" <?php echo $status_filter === 'ready' ? 'selected' : ''; ?>>Ready</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Date</label>
                            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" class="filter-input">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Order ID, Customer Name, Email or Phone" class="filter-search">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="admin_orders.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Orders Section -->
            <div class="orders-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-shopping-cart"></i>
                        All Orders
                        <span class="orders-count"><?php echo count($orders); ?></span>
                    </h2>
                </div>
                
                <?php if (!empty($orders)): ?>
                <div class="table-container">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Order Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="order-id">
                                    #<?php echo htmlspecialchars($order['order_id']); ?>
                                    <?php if (!empty($order['special_instructions'])): ?>
                                    <span class="instructions-indicator" title="Has special instructions">
                                        <i class="fas fa-sticky-note"></i> Notes
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $customer_name = 'Unknown Customer';
                                    if (!empty($order['first_name']) || !empty($order['last_name'])) {
                                        $customer_name = htmlspecialchars(trim($order['first_name'] . ' ' . $order['last_name']));
                                    }
                                    echo $customer_name;
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['email'] ?? 'N/A'); ?><br>
                                    <small><?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $item_count = $order['item_count'] ?? 0;
                                    $item_names = $order['item_names'] ?? $item_count . ' items';
                                    echo htmlspecialchars(substr($item_names, 0, 50) . (strlen($item_names) > 50 ? '...' : ''));
                                    ?>
                                    <br><small><?php echo $item_count; ?> items</small>
                                </td>
                                <td>RM<?php echo number_format($order['total'], 2); ?></td>
                                <td>
                                    <?php echo formatStatus($order['status']); ?>
                                </td>
                                <td><?php echo date('M j, g:i A', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?view=<?php echo $order['order_id']; ?>" class="btn btn-view btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($order['status'] === 'pending'): ?>
                                        <a href="admindashboard.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
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
                    <i class="fas fa-search"></i>
                    <h3>No Orders Found</h3>
                    <p>No orders match your current filters. Try adjusting your search criteria.</p>
                    <?php if (empty($status_filter) && empty($date_filter) && empty($search_query)): ?>
                    <p style="margin-top: 10px; color: var(--danger);">
                        <strong>Note:</strong> It appears there are no orders in the system yet, or there might be a database connection issue.
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <?php if ($order_details): ?>
    <div class="modal-overlay" id="orderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Order #<?php echo htmlspecialchars($order_details['order_id']); ?></h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Order Information -->
                <div class="order-info-grid">
                    <div class="info-card">
                        <div class="info-title">Customer Information</div>
                        <div class="info-value">
                            <?php
                            $customer_name = 'Unknown Customer';
                            if (!empty($order_details['first_name']) || !empty($order_details['last_name'])) {
                                $customer_name = htmlspecialchars(trim($order_details['first_name'] . ' ' . $order_details['last_name']));
                            }
                            echo $customer_name;
                            ?>
                        </div>
                        <div><?php echo htmlspecialchars($order_details['email'] ?? 'N/A'); ?></div>
                        <div><?php echo htmlspecialchars($order_details['phone'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-title">Order Information</div>
                        <div class="info-value">RM<?php echo number_format($order_details['total'], 2); ?></div>
                        <div><?php echo formatStatus($order_details['status']); ?></div>
                        <div><?php echo date('F j, Y g:i A', strtotime($order_details['order_date'])); ?></div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-title">Delivery Information</div>
                        <div class="info-value"><?php echo htmlspecialchars($order_details['delivery_address'] ?? 'Pickup'); ?></div>
                        <div><?php echo htmlspecialchars($order_details['delivery_time'] ?? 'ASAP'); ?></div>
                    </div>
                </div>

                <!-- Special Instructions -->
                <?php if (!empty($order_details['special_instructions'])): ?>
                <div class="special-instructions">
                    <div class="instructions-header">
                        <i class="fas fa-sticky-note"></i>
                        <span>Special Instructions</span>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($order_details['special_instructions'])); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Order Items -->
                <h3 style="margin-bottom: 15px;">Order Items</h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <small style="color: var(--secondary);"><?php echo htmlspecialchars($item['category'] ?? ''); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>RM<?php echo number_format($item['price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                            <td>RM<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--light);">
                            <td colspan="3" style="text-align: right; font-weight: 600;">Total:</td>
                            <td style="font-weight: 600;">RM<?php echo number_format($order_details['total'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
                
                <!-- Status Update Form -->
                <h3 style="margin-bottom: 15px;">Update Order Status</h3>
                <form method="POST" action="admin_orders.php">
                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_details['order_id']); ?>">
                    
                    <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 20px;">
                        <select name="status" style="padding: 8px; border: 1px solid var(--border); border-radius: var(--radius); flex: 1;">
                            <option value="pending" <?php echo $order_details['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="preparing" <?php echo $order_details['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                            <option value="ready" <?php echo $order_details['status'] === 'ready' ? 'selected' : ''; ?>>Ready</option>
                            <option value="completed" <?php echo $order_details['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $order_details['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" name="notify_customer" value="1" checked>
                            Notify Customer
                        </label>
                        
                        <button type="submit" name="update_order_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </div>
                </form>
                
                <!-- Order History -->
                <h3 style="margin-bottom: 15px;">Order History</h3>
                <div class="history-timeline">
                    <?php if (!empty($order_history)): ?>
                        <?php foreach ($order_history as $history): ?>
                        <div class="history-item">
                            <div class="history-date"><?php echo date('M j, g:i A', strtotime($history['change_date'])); ?></div>
                            <div class="history-action">Status changed to: <?php echo formatStatus($history['status']); ?></div>
                            <div class="history-admin">
                                By: <?php echo htmlspecialchars($history['admin_full_name'] ?? $history['admin_username'] ?? 'System'); ?>
                                <?php if (!empty($history['notes'])): ?>
                                - <?php echo htmlspecialchars($history['notes']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--secondary);">No history available for this order.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Close modal function
        function closeModal() {
            window.location.href = 'admin_orders.php';
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Auto-close success message after 5 seconds
        setTimeout(function() {
            const alertMsg = document.querySelector('.alert-message');
            if (alertMsg) {
                alertMsg.style.opacity = '0';
                setTimeout(() => alertMsg.remove(), 300);
            }
        }, 5000);
    </script>
</body>
</html>