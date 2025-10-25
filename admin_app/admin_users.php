<?php
// admin_users.php
require_once 'db.php';
session_start();

// Redirect if not logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: adminsignin.php');
    exit();
}

// Get admin information from session
$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];
$admin_role = $_SESSION['admin_role'];
$admin_full_name = $_SESSION['admin_full_name'];

// Initialize variables
$search_query = '';
$users = [];
$error = '';
$success = '';

// Process search
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $search_query = trim($_GET['search']);

    try {
        if (!empty($search_query)) {
            // Search users by ID, name, or email
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       (SELECT COUNT(*) FROM orders WHERE user_id = u.user_id) as order_count,
                       (SELECT MAX(order_date) FROM orders WHERE user_id = u.user_id) as last_order_date
                FROM users u 
                WHERE u.user_id LIKE ? 
                   OR u.first_name LIKE ? 
                   OR u.last_name LIKE ? 
                   OR u.email LIKE ?
                ORDER BY u.created_at DESC
            ");
            $search_param = "%$search_query%";
            $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Get all users if no search query
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       (SELECT COUNT(*) FROM orders WHERE user_id = u.user_id) as order_count,
                       (SELECT MAX(order_date) FROM orders WHERE user_id = u.user_id) as last_order_date
                FROM users u 
                ORDER BY u.created_at DESC 
                LIMIT 50
            ");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error searching users: " . $e->getMessage());
        $error = "Failed to search users. Please try again.";
    }
}

// Process user actions (delete, toggle status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = $_POST['user_id'];
        $action = $_POST['action'];

        try {
            switch ($action) {
                case 'delete':
                    // Check if user has orders before deleting
                    $stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $order_count = $stmt->fetch(PDO::FETCH_ASSOC)['order_count'];

                    if ($order_count > 0) {
                        $error = "Cannot delete user with existing orders. Archive instead.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        $success = "User deleted successfully.";
                    }
                    break;

                case 'toggle_status':
                    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $success = "User status updated successfully.";
                    break;

                case 'reset_password':
                    // Generate temporary password
                    $temp_password = bin2hex(random_bytes(4)); // 8 character temporary password
                    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $success = "Password reset successfully. Temporary password: <strong>$temp_password</strong>";
                    break;
            }

            // Refresh the user list after action
            header("Location: admin_users.php?search=" . urlencode($search_query));
            exit();

        } catch (Exception $e) {
            error_log("Error processing user action: " . $e->getMessage());
            $error = "Failed to process action. Please try again.";
        }
    }
}

// Get user statistics
$user_stats = [];
try {
    // Total users
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users");
    $stmt->execute();
    $user_stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

    // Active users
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_users FROM users WHERE is_active = 1");
    $stmt->execute();
    $user_stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_users'];

    // Users with orders
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as users_with_orders FROM orders");
    $stmt->execute();
    $user_stats['users_with_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['users_with_orders'];

    // New users this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as new_users_month FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->execute();
    $user_stats['new_users_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['new_users_month'];

} catch (Exception $e) {
    error_log("Error fetching user statistics: " . $e->getMessage());
    $user_stats = [
        'total_users' => 0,
        'active_users' => 0,
        'users_with_orders' => 0,
        'new_users_month' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - University Canteen Kiosk</title>
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

        /* Sidebar Styles (same as admin dashboard) */
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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            color: white;
        }

        .users-icon { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); }
        .active-icon { background: linear-gradient(135deg, var(--success) 0%, #3acf5d 100%); }
        .orders-icon { background: linear-gradient(135deg, var(--info) 0%, #3aafd9 100%); }
        .new-icon { background: linear-gradient(135deg, var(--warning) 0%, #ffd351 100%); }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .stat-label {
            font-size: 14px;
            color: var(--secondary);
        }

        /* Search Section */
        .search-section {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            position: relative;
        }

        .search-input input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 16px;
            transition: var(--transition);
        }

        .search-input input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(235, 108, 30, 0.1);
        }

        .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            padding: 12px 24px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .search-btn:hover {
            background: var(--primary-dark);
        }

        .clear-btn {
            background: var(--light-gray);
            color: var(--dark);
            border: none;
            border-radius: var(--radius);
            padding: 12px 24px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clear-btn:hover {
            background: #dde1e7;
        }

        /* Users Table */
        .users-table-container {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .table-header {
            padding: 20px 24px;
            background: var(--light);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .results-count {
            color: var(--secondary);
            font-size: 14px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: var(--light);
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 1px solid var(--border);
        }

        .users-table td {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-details {
            line-height: 1.4;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark);
        }

        .user-id {
            font-size: 12px;
            color: var(--secondary);
        }

        .user-email {
            font-size: 14px;
            color: var(--secondary);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-inactive {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
        }

        .btn-toggle {
            background: var(--warning);
            color: white;
        }

        .btn-toggle:hover {
            background: #e0a800;
        }

        .btn-reset {
            background: var(--info);
            color: white;
        }

        .btn-reset:hover {
            background: #138496;
        }

        .btn-delete {
            background: var(--danger);
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-orders {
            background: var(--primary);
            color: white;
        }

        .btn-orders:hover {
            background: var(--primary-dark);
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
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert i {
            font-size: 20px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--light-gray);
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark);
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .users-table {
                display: block;
                overflow-x: auto;
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
                <a href="admin_menu.php" class="menu-item">
                    <i class="fas fa-utensils"></i>
                    <span>Menu</span>
                </a>
                <a href="admin_users.php" class="menu-item active">
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
                    <h1 class="page-title">User Management</h1>
                    <p class="welcome-text">Manage user accounts and monitor user activity</p>
                </div>
            </div>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
            <?php endif; ?>
            
            <!-- User Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $user_stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon active-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $user_stats['active_users']; ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orders-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-number"><?php echo $user_stats['users_with_orders']; ?></div>
                    <div class="stat-label">Users with Orders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon new-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-number"><?php echo $user_stats['new_users_month']; ?></div>
                    <div class="stat-label">New Users This Month</div>
                </div>
            </div>
            
            <!-- Search Section -->
            <div class="search-section">
                <form method="GET" class="search-form">
                    <div class="search-input">
                        <input type="text" name="search" placeholder="Search users by ID, name, or email..." 
                               value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search_query)): ?>
                    <a href="admin_users.php" class="clear-btn">
                        <i class="fas fa-times"></i> Clear
                    </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="users-table-container">
                <div class="table-header">
                    <div class="table-title">User Accounts</div>
                    <div class="results-count"><?php echo count($users); ?> user(s) found</div>
                </div>
                
                <?php if (count($users) > 0): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact Info</th>
                            <th>Status</th>
                            <th>Orders</th>
                            <th>Last Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                        <div class="user-id">ID: <?php echo htmlspecialchars($user['user_id']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                <div style="font-size: 12px; color: var(--secondary);">
                                    Joined: <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo $user['order_count']; ?></strong> orders
                            </td>
                            <td>
                                <?php if ($user['last_order_date']): ?>
                                    <?php echo date('M j, Y g:i A', strtotime($user['last_order_date'])); ?>
                                <?php else: ?>
                                    <span style="color: var(--secondary);">No orders yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <button type="submit" class="btn btn-toggle btn-sm" 
                                                title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password for <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>? A temporary password will be generated.');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="action" value="reset_password">
                                        <button type="submit" class="btn btn-reset btn-sm" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                    </form>
                                    
                                    <?php if ($user['order_count'] == 0): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>? This action cannot be undone.');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-delete btn-sm" title="Delete User">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <a href="admin_orders.php?user_id=<?php echo $user['user_id']; ?>" class="btn btn-orders btn-sm" title="View Orders">
                                        <i class="fas fa-list"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Users Found</h3>
                    <p><?php echo empty($search_query) ? 'No users in the system yet.' : 'No users match your search criteria.'; ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Confirm before sensitive actions
        document.querySelectorAll('form').forEach(form => {
            const button = form.querySelector('button[type="submit"]');
            if (button && (button.classList.contains('btn-delete') || button.classList.contains('btn-reset'))) {
                form.addEventListener('submit', function(e) {
                    const action = this.querySelector('input[name="action"]').value;
                    const userId = this.querySelector('input[name="user_id"]').value;
                    
                    let message = '';
                    if (action === 'delete') {
                        message = `Are you sure you want to delete this user? This action cannot be undone.`;
                    } else if (action === 'reset_password') {
                        message = `Are you sure you want to reset this user's password? A temporary password will be generated.`;
                    }
                    
                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>