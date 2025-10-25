<?php
// admin_reports.php
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
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to today
$report_type = $_GET['report_type'] ?? 'sales_summary';

// Initialize report data
$report_data = [];
$error = '';
$success = '';

try {
    // Generate reports based on type and date range
    switch ($report_type) {
        case 'sales_summary':
            // Sales Summary Report
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(total) as total_revenue,
                    AVG(total) as avg_order_value,
                    MIN(total) as min_order_value,
                    MAX(total) as max_order_value
                FROM orders 
                WHERE status = 'completed' 
                AND DATE(order_date) BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            $report_data['sales_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Daily sales trend
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(order_date) as order_day,
                    COUNT(*) as order_count,
                    SUM(total) as daily_revenue
                FROM orders 
                WHERE status = 'completed' 
                AND DATE(order_date) BETWEEN ? AND ?
                GROUP BY DATE(order_date)
                ORDER BY order_day
            ");
            $stmt->execute([$start_date, $end_date]);
            $report_data['daily_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'order_analysis':
            // Order Analysis Report
            $stmt = $pdo->prepare("
                SELECT 
                    status,
                    COUNT(*) as order_count,
                    SUM(total) as total_value
                FROM orders 
                WHERE DATE(order_date) BETWEEN ? AND ?
                GROUP BY status
                ORDER BY order_count DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $report_data['order_analysis'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Orders by time of day
            $stmt = $pdo->prepare("
                SELECT 
                    HOUR(order_date) as hour_of_day,
                    COUNT(*) as order_count
                FROM orders 
                WHERE DATE(order_date) BETWEEN ? AND ?
                GROUP BY HOUR(order_date)
                ORDER BY hour_of_day
            ");
            $stmt->execute([$start_date, $end_date]);
            $report_data['hourly_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'menu_performance':
            // Menu Performance Report
            $stmt = $pdo->prepare("
                SELECT 
                    mi.item_id,
                    mi.item_name,
                    mi.category_name,
                    mi.price,
                    COUNT(oi.item_id) as times_ordered,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.quantity * mi.price) as total_revenue
                FROM menu_items mi
                LEFT JOIN order_items oi ON mi.item_id = oi.item_id
                LEFT JOIN orders o ON oi.order_id = o.order_id
                WHERE o.status = 'completed' 
                AND DATE(o.order_date) BETWEEN ? AND ?
                GROUP BY mi.item_id
                ORDER BY total_revenue DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $report_data['menu_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Category performance
            $stmt = $pdo->prepare("
                SELECT 
                    mi.category_name,
                    COUNT(oi.item_id) as times_ordered,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.quantity * mi.price) as total_revenue
                FROM menu_items mi
                LEFT JOIN order_items oi ON mi.item_id = oi.item_id
                LEFT JOIN orders o ON oi.order_id = o.order_id
                WHERE o.status = 'completed' 
                AND DATE(o.order_date) BETWEEN ? AND ?
                GROUP BY mi.category_name
                ORDER BY total_revenue DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $report_data['category_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'customer_insights':
            // Customer Insights Report
            $stmt = $pdo->prepare("
                SELECT 
                    u.user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                    u.email,
                    COUNT(o.order_id) as order_count,
                    SUM(o.total) as total_spent,
                    MAX(o.order_date) as last_order_date
                FROM users u
                LEFT JOIN orders o ON u.user_id = o.user_id
                WHERE o.status = 'completed' 
                AND DATE(o.order_date) BETWEEN ? AND ?
                GROUP BY u.user_id
                HAVING order_count > 0
                ORDER BY total_spent DESC
                LIMIT 50
            ");
            $stmt->execute([$start_date, $end_date]);
            $report_data['top_customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // New customers
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as new_customers
                FROM users 
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            $new_customers = $stmt->fetch(PDO::FETCH_ASSOC);
            $report_data['new_customers'] = $new_customers['new_customers'];
            break;
    }

    // General statistics for all reports
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END) as total_revenue,
            COUNT(DISTINCT user_id) as unique_customers
        FROM orders 
        WHERE DATE(order_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $report_data['general_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error generating report: " . $e->getMessage());
    $error = "Failed to generate report. Please try again.";
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
    <title>Reports & Analytics - University Canteen Kiosk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Sidebar Styles (same as other admin pages) */
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

        /* Report Filters */
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

        .filter-select, .filter-input {
            padding: 10px 12px;
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
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
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

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Report Summary Cards */
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

        .orders-icon { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); }
        .revenue-icon { background: linear-gradient(135deg, var(--success) 0%, #3acf5d 100%); }
        .customers-icon { background: linear-gradient(135deg, var(--info) 0%, #3aafd9 100%); }
        .avg-icon { background: linear-gradient(135deg, var(--warning) 0%, #ffd351 100%); }

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

        /* Report Content */
        .report-section {
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

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }

        .table-container {
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th {
            background: var(--light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border);
        }

        .report-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .report-table tr:hover {
            background: var(--light);
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-preparing { background: #d1ecf1; color: #0c5460; }
        .status-ready { background: #d4edda; color: #155724; }
        .status-completed { background: #e2e3e5; color: #383d41; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

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

        /* Responsive Styles */
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                justify-content: stretch;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
            }
            
            .report-table {
                font-size: 0.8rem;
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
                <a href="admin_users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="admin_reports.php" class="menu-item active">
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
                    <h1 class="page-title">Reports & Analytics</h1>
                    <p class="welcome-text">Gain insights into your canteen operations and performance</p>
                </div>
                <div style="color: var(--secondary); font-size: 0.9rem;">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
                </div>
            </div>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Report Filters -->
            <div class="filters-section">
                <form method="GET" action="admin_reports.php">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">Report Type</label>
                            <select name="report_type" class="filter-select">
                                <option value="sales_summary" <?php echo $report_type === 'sales_summary' ? 'selected' : ''; ?>>Sales Summary</option>
                                <option value="order_analysis" <?php echo $report_type === 'order_analysis' ? 'selected' : ''; ?>>Order Analysis</option>
                                <option value="menu_performance" <?php echo $report_type === 'menu_performance' ? 'selected' : ''; ?>>Menu Performance</option>
                                <option value="customer_insights" <?php echo $report_type === 'customer_insights' ? 'selected' : ''; ?>>Customer Insights</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="filter-input">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">End Date</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="filter-input">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-chart-bar"></i> Generate Report
                        </button>
                        <a href="admin_reports.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Reset Filters
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Report Summary -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon orders-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-number"><?php echo $report_data['general_stats']['total_orders'] ?? 0; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon revenue-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-number">RM<?php echo number_format($report_data['general_stats']['total_revenue'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon customers-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $report_data['general_stats']['unique_customers'] ?? 0; ?></div>
                    <div class="stat-label">Unique Customers</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon avg-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-number">RM<?php
                    $total_orders = $report_data['general_stats']['total_orders'] ?? 0;
                    $total_revenue = $report_data['general_stats']['total_revenue'] ?? 0;
                    echo $total_orders > 0 ? number_format($total_revenue / $total_orders, 2) : '0.00';
                    ?></div>
                    <div class="stat-label">Average Order Value</div>
                </div>
            </div>
            
            <!-- Report Content -->
            <?php if ($report_type === 'sales_summary'): ?>
            <div class="report-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-chart-line"></i>
                        Sales Summary Report
                    </h2>
                </div>
                
                <?php if (!empty($report_data['sales_summary'])): ?>
                <div class="chart-container">
                    <canvas id="salesTrendChart"></canvas>
                </div>
                
                <div class="table-container">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th class="text-right">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Total Orders</td>
                                <td class="text-right"><?php echo $report_data['sales_summary']['total_orders']; ?></td>
                            </tr>
                            <tr>
                                <td>Total Revenue</td>
                                <td class="text-right">RM<?php echo number_format($report_data['sales_summary']['total_revenue'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Average Order Value</td>
                                <td class="text-right">RM<?php echo number_format($report_data['sales_summary']['avg_order_value'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Minimum Order Value</td>
                                <td class="text-right">RM<?php echo number_format($report_data['sales_summary']['min_order_value'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Maximum Order Value</td>
                                <td class="text-right">RM<?php echo number_format($report_data['sales_summary']['max_order_value'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <h3>No Sales Data</h3>
                    <p>No sales data found for the selected date range.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($report_type === 'order_analysis'): ?>
            <div class="report-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-shopping-cart"></i>
                        Order Analysis Report
                    </h2>
                </div>
                
                <?php if (!empty($report_data['order_analysis'])): ?>
                <div class="chart-container">
                    <canvas id="orderStatusChart"></canvas>
                </div>
                
                <div class="table-container">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-right">Order Count</th>
                                <th class="text-right">Total Value</th>
                                <th class="text-right">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_orders = $report_data['general_stats']['total_orders'] ?? 1;
                            foreach ($report_data['order_analysis'] as $analysis):
                                $percentage = ($analysis['order_count'] / $total_orders) * 100;
                                ?>
                            <tr>
                                <td>
                                    <span class="status-badge status-<?php echo $analysis['status']; ?>">
                                        <?php echo ucfirst($analysis['status']); ?>
                                    </span>
                                </td>
                                <td class="text-right"><?php echo $analysis['order_count']; ?></td>
                                <td class="text-right">RM<?php echo number_format($analysis['total_value'] ?? 0, 2); ?></td>
                                <td class="text-right"><?php echo number_format($percentage, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>No Order Data</h3>
                    <p>No order data found for the selected date range.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($report_type === 'menu_performance'): ?>
            <div class="report-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-utensils"></i>
                        Menu Performance Report
                    </h2>
                </div>
                
                <?php if (!empty($report_data['menu_performance'])): ?>
                <div class="chart-container">
                    <canvas id="menuPerformanceChart"></canvas>
                </div>
                
                <div class="table-container">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th class="text-right">Price</th>
                                <th class="text-right">Times Ordered</th>
                                <th class="text-right">Total Quantity</th>
                                <th class="text-right">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['menu_performance'] as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td class="text-right">RM<?php echo number_format($item['price'], 2); ?></td>
                                <td class="text-right"><?php echo $item['times_ordered']; ?></td>
                                <td class="text-right"><?php echo $item['total_quantity']; ?></td>
                                <td class="text-right">RM<?php echo number_format($item['total_revenue'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-utensils"></i>
                    <h3>No Menu Performance Data</h3>
                    <p>No menu performance data found for the selected date range.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($report_type === 'customer_insights'): ?>
            <div class="report-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-users"></i>
                        Customer Insights Report
                    </h2>
                </div>
                
                <?php if (!empty($report_data['top_customers'])): ?>
                <div class="table-container">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Email</th>
                                <th class="text-right">Order Count</th>
                                <th class="text-right">Total Spent</th>
                                <th>Last Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['top_customers'] as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td class="text-right"><?php echo $customer['order_count']; ?></td>
                                <td class="text-right">RM<?php echo number_format($customer['total_spent'], 2); ?></td>
                                <td><?php echo $customer['last_order_date'] ? date('M j, Y', strtotime($customer['last_order_date'])) : 'N/A'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Customer Data</h3>
                    <p>No customer data found for the selected date range.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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

        // Charts initialization
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($report_type === 'sales_summary' && !empty($report_data['daily_trend'])): ?>
            // Sales Trend Chart
            const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
            const salesTrendChart = new Chart(salesTrendCtx, {
                type: 'line',
                data: {
                    labels: [<?php
                    foreach ($report_data['daily_trend'] as $day) {
                        echo "'" . date('M j', strtotime($day['order_day'])) . "',";
                    }
                    ?>],
                    datasets: [{
                        label: 'Daily Revenue',
                        data: [<?php
                        foreach ($report_data['daily_trend'] as $day) {
                            echo $day['daily_revenue'] . ',';
                        }
                        ?>],
                        borderColor: '#eb6c1e',
                        backgroundColor: 'rgba(235, 108, 30, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'RM' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
            
            <?php if ($report_type === 'order_analysis' && !empty($report_data['order_analysis'])): ?>
            // Order Status Chart
            const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
            const orderStatusChart = new Chart(orderStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: [<?php
                    foreach ($report_data['order_analysis'] as $analysis) {
                        echo "'" . ucfirst($analysis['status']) . "',";
                    }
                    ?>],
                    datasets: [{
                        data: [<?php
                        foreach ($report_data['order_analysis'] as $analysis) {
                            echo $analysis['order_count'] . ',';
                        }
                        ?>],
                        backgroundColor: [
                            '#eb6c1e', '#ff8c3a', '#28a745', '#17a2b8', '#ffc107', '#dc3545'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
            <?php endif; ?>
            
            <?php if ($report_type === 'menu_performance' && !empty($report_data['category_performance'])): ?>
            // Menu Performance Chart
            const menuPerformanceCtx = document.getElementById('menuPerformanceChart').getContext('2d');
            const menuPerformanceChart = new Chart(menuPerformanceCtx, {
                type: 'bar',
                data: {
                    labels: [<?php
                    foreach ($report_data['category_performance'] as $category) {
                        echo "'" . htmlspecialchars($category['category_name']) . "',";
                    }
                    ?>],
                    datasets: [{
                        label: 'Revenue by Category',
                        data: [<?php
                        foreach ($report_data['category_performance'] as $category) {
                            echo $category['total_revenue'] . ',';
                        }
                        ?>],
                        backgroundColor: '#eb6c1e',
                        borderColor: '#eb6c1e',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'RM' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>