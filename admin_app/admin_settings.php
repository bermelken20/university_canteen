<?php
// admin_settings.php
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
$error = '';
$success = '';
$active_tab = $_GET['tab'] ?? 'general';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_general_settings'])) {
            // Update general settings
            $canteen_name = $_POST['canteen_name'];
            $contact_email = $_POST['contact_email'];
            $contact_phone = $_POST['contact_phone'];
            $opening_time = $_POST['opening_time'];
            $closing_time = $_POST['closing_time'];

            // Save to database (you'll need to create a settings table)
            $success = "General settings updated successfully!";

        } elseif (isset($_POST['update_notification_settings'])) {
            // Update notification settings
            $order_email_notifications = isset($_POST['order_email_notifications']) ? 1 : 0;
            $order_sms_notifications = isset($_POST['order_sms_notifications']) ? 1 : 0;
            $low_stock_alerts = isset($_POST['low_stock_alerts']) ? 1 : 0;

            $success = "Notification settings updated successfully!";

        } elseif (isset($_POST['add_admin'])) {
            // Add new admin
            $new_username = $_POST['new_username'];
            $new_full_name = $_POST['new_full_name'];
            $new_email = $_POST['new_email'];
            $new_role = $_POST['new_role'];
            $temp_password = bin2hex(random_bytes(4));

            // Hash password and save to database
            $success = "Admin user added successfully! Temporary password: " . $temp_password;

        } elseif (isset($_POST['update_profile'])) {
            // Update admin profile
            $full_name = $_POST['full_name'];
            $email = $_POST['email'];
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];

            // Verify current password and update
            $success = "Profile updated successfully!";
        }

    } catch (Exception $e) {
        error_log("Error updating settings: " . $e->getMessage());
        $error = "Failed to update settings. Please try again.";
    }
}

// Process logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: adminsignin.php');
    exit();
}

// Sample settings data (replace with database queries)
$settings = [
    'canteen_name' => 'University Canteen',
    'contact_email' => 'canteen@university.edu',
    'contact_phone' => '+1 (555) 123-4567',
    'opening_time' => '07:00',
    'closing_time' => '20:00',
    'order_email_notifications' => true,
    'order_sms_notifications' => false,
    'low_stock_alerts' => true
];

$admin_users = [
    ['id' => 1, 'username' => 'admin', 'full_name' => 'System Administrator', 'role' => 'superadmin', 'last_login' => '2025-09-24 10:30:00'],
    ['id' => 2, 'username' => 'manager', 'full_name' => 'Canteen Manager', 'role' => 'manager', 'last_login' => '2025-09-24 09:15:00']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - University Canteen Kiosk</title>
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

        /* Settings Layout */
        .settings-container {
            display: flex;
            gap: 20px;
        }
        
        .settings-sidebar {
            width: 250px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px 0;
        }
        
        .settings-tab {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--secondary);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }
        
        .settings-tab:hover, .settings-tab.active {
            background: var(--light);
            color: var(--primary);
            border-left-color: var(--primary);
        }
        
        .settings-tab i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .settings-content {
            flex: 1;
        }
        
        .settings-section {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(235, 108, 30, 0.1);
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .admin-table th {
            background: var(--light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .admin-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .role-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .role-superadmin { background: #ffe6e6; color: #d63031; }
        .role-manager { background: #e3f2fd; color: #1976d2; }
        .role-staff { background: #e8f5e8; color: #388e3c; }

        /* Button Styles */
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

        /* Responsive Design */
        @media (max-width: 992px) {
            .settings-container {
                flex-direction: column;
            }
            
            .settings-sidebar {
                width: 100%;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .sidebar-menu {
                display: flex;
                overflow-x: auto;
                padding: 10px 0;
            }
            
            .menu-item {
                white-space: nowrap;
                border-left: none;
                border-bottom: 3px solid transparent;
            }
            
            .menu-item:hover, .menu-item.active {
                border-left-color: transparent;
                border-bottom-color: var(--primary);
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
                <a href="admin_settings.php" class="menu-item active">
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
                    <h1 class="page-title">System Settings</h1>
                    <p class="welcome-text">Configure system preferences and manage administrator accounts</p>
                </div>
                <div style="color: var(--secondary); font-size: 0.9rem;">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
                </div>
            </div>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
            <?php endif; ?>
            
            <div class="settings-container">
                <!-- Settings Sidebar -->
                <div class="settings-sidebar">
                    <a href="?tab=general" class="settings-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span>General Settings</span>
                    </a>
                    <a href="?tab=notifications" class="settings-tab <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </a>
                    <a href="?tab=admins" class="settings-tab <?php echo $active_tab === 'admins' ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog"></i>
                        <span>Admin Management</span>
                    </a>
                    <a href="?tab=profile" class="settings-tab <?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user-edit"></i>
                        <span>My Profile</span>
                    </a>
                </div>
                
                <!-- Settings Content -->
                <div class="settings-content">
                    <?php if ($active_tab === 'general'): ?>
                    <!-- General Settings -->
                    <div class="settings-section">
                        <h2 class="section-title">
                            <i class="fas fa-cog"></i>
                            General Settings
                        </h2>
                        
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Canteen Name</label>
                                    <input type="text" name="canteen_name" class="form-input" 
                                           value="<?php echo htmlspecialchars($settings['canteen_name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" name="contact_email" class="form-input" 
                                           value="<?php echo htmlspecialchars($settings['contact_email']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Contact Phone</label>
                                    <input type="tel" name="contact_phone" class="form-input" 
                                           value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Opening Time</label>
                                    <input type="time" name="opening_time" class="form-input" 
                                           value="<?php echo htmlspecialchars($settings['opening_time']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Closing Time</label>
                                    <input type="time" name="closing_time" class="form-input" 
                                           value="<?php echo htmlspecialchars($settings['closing_time']); ?>">
                                </div>
                            </div>
                            
                            <button type="submit" name="update_general_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($active_tab === 'notifications'): ?>
                    <!-- Notification Settings -->
                    <div class="settings-section">
                        <h2 class="section-title">
                            <i class="fas fa-bell"></i>
                            Notification Settings
                        </h2>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-checkbox">
                                    <input type="checkbox" name="order_email_notifications" 
                                           <?php echo $settings['order_email_notifications'] ? 'checked' : ''; ?>>
                                    <span>Send email notifications for new orders</span>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-checkbox">
                                    <input type="checkbox" name="order_sms_notifications"
                                           <?php echo $settings['order_sms_notifications'] ? 'checked' : ''; ?>>
                                    <span>Send SMS notifications for order status updates</span>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-checkbox">
                                    <input type="checkbox" name="low_stock_alerts"
                                           <?php echo $settings['low_stock_alerts'] ? 'checked' : ''; ?>>
                                    <span>Send low stock alerts</span>
                                </label>
                            </div>
                            
                            <button type="submit" name="update_notification_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($active_tab === 'admins'): ?>
                    <!-- Admin Management -->
                    <div class="settings-section">
                        <h2 class="section-title">
                            <i class="fas fa-users-cog"></i>
                            Administrator Accounts
                        </h2>
                        
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Role</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admin_users as $admin): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $admin['role']; ?>">
                                            <?php echo ucfirst($admin['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($admin['last_login'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm">Edit</button>
                                        <?php if ($admin['username'] !== 'admin'): ?>
                                        <button class="btn btn-danger btn-sm">Delete</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <h3 style="margin-top: 30px; margin-bottom: 15px;">Add New Admin</h3>
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="new_username" class="form-input" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="new_full_name" class="form-input" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="new_email" class="form-input" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Role</label>
                                    <select name="new_role" class="form-select" required>
                                        <option value="staff">Staff</option>
                                        <option value="manager">Manager</option>
                                        <option value="superadmin">Super Admin</option>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" name="add_admin" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Add Admin
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($active_tab === 'profile'): ?>
                    <!-- My Profile -->
                    <div class="settings-section">
                        <h2 class="section-title">
                            <i class="fas fa-user-edit"></i>
                            My Profile
                        </h2>
                        
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-input" 
                                           value="<?php echo htmlspecialchars($admin_full_name); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-input" 
                                           value="<?php echo htmlspecialchars($admin_username); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-input">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-input">
                                </div>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
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
    </script>
</body>
</html>