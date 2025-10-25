<?php
require_once 'db.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admindashboard.php');
    exit();
}

// Get all admin accounts from the database
$admin_accounts = [];
try {
    $stmt = $pdo->prepare("SELECT admin_id, full_name, username FROM admin_users ORDER BY full_name");
    $stmt->execute();
    $admin_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching admin accounts: " . $e->getMessage());
}

// Check if admin_id is passed via GET (from successful signup)
$admin_id = isset($_GET['admin_id']) ? trim($_GET['admin_id']) : '';

// Process form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = trim($_POST['admin_id']);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($admin_id) || empty($password)) {
        $error = 'Admin ID and password are required.';

        // Log failed attempt (missing credentials)
        logLoginAttempt($pdo, $admin_id, '', false, 'Missing credentials');
    } else {
        try {
            // Check if admin exists
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE admin_id = ?");
            $stmt->execute([$admin_id]);

            if ($stmt->rowCount() === 1) {
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                $username = $admin['username'];

                // Log login attempt (start with failure assumption)
                $attempt_id = logLoginAttempt($pdo, $admin_id, $username, false, 'Pending verification');

                // Verify password
                if (password_verify($password, $admin['password_hash'])) {
                    // Update login attempt to success
                    updateLoginAttempt($pdo, $attempt_id, true, 'Success');

                    // Set session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_full_name'] = $admin['full_name'];

                    // Set success message with name and admin ID
                    $success = 'Welcome, ' . $admin['full_name'] . '! (Admin ID: ' . $admin['admin_id'] . ')';

                    // Redirect to dashboard after a brief delay
                    echo '<meta http-equiv="refresh" content="2;url=admindashboard.php">';
                } else {
                    // Update login attempt to failure
                    updateLoginAttempt($pdo, $attempt_id, false, 'Invalid password');
                    $error = 'Invalid password.';
                }
            } else {
                // Log failed attempt (admin not found)
                logLoginAttempt($pdo, $admin_id, '', false, 'Admin ID not found');
                $error = 'Admin ID not found.';
            }
        } catch (Exception $e) {
            error_log("Error during admin login: " . $e->getMessage());
            $error = 'An error occurred during login. Please try again.';

            // Log failed attempt (system error)
            logLoginAttempt($pdo, $admin_id, '', false, 'System error: ' . $e->getMessage());
        }
    }
}

/**
 * Log a login attempt to the database
 */
function logLoginAttempt($pdo, $admin_id, $username, $success, $reason)
{
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $stmt = $pdo->prepare("
            INSERT INTO admin_login_attempts 
            (admin_id, username, attempt_time, ip_address, user_agent, success, failure_reason, created_at) 
            VALUES (?, ?, NOW(), ?, ?, ?, ?, NOW())
        ");

        $success_flag = $success ? 'successful' : 'failed';
        $stmt->execute([$admin_id, $username, $ip_address, $user_agent, $success_flag, $reason]);

        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error logging login attempt: " . $e->getMessage());
        return null;
    }
}

/**
 * Update an existing login attempt record
 */
function updateLoginAttempt($pdo, $attempt_id, $success, $reason)
{
    if (!$attempt_id)
        return false;

    try {
        $stmt = $pdo->prepare("
            UPDATE admin_login_attempts 
            SET success = ?, failure_reason = ? 
            WHERE attempt_id = ?
        ");

        $success_flag = $success ? 'successful' : 'failed';
        $stmt->execute([$success_flag, $reason, $attempt_id]);

        return true;
    } catch (Exception $e) {
        error_log("Error updating login attempt: " . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Signin - University Canteen Kiosk</title>
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
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 450px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 28px;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .logo-text .canteen {
            color: var(--primary);
        }

        .logo-text .kiosk {
            color: var(--dark);
        }

        .tagline {
            color: var(--secondary);
            font-size: 1rem;
            margin-top: 5px;
        }

        .card {
            background: white;
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .card-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .card-subtitle {
            color: var(--secondary);
            font-size: 0.9rem;
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
            padding: 12px 15px;
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

        .input-with-icon {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary);
        }

        .input-with-icon .form-input, .input-with-icon .form-select {
            padding-left: 45px;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--secondary);
            cursor: pointer;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-link {
            background: none;
            color: var(--primary);
            text-decoration: underline;
            padding: 0;
            font-weight: 500;
        }

        .btn-link:hover {
            color: var(--primary-dark);
        }

        .alert {
            padding: 12px 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.15);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.15);
            color: var(--success);
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .alert-icon {
            margin-right: 10px;
            font-size: 18px;
        }

        .text-center {
            text-align: center;
        }

        .mt-3 {
            margin-top: 15px;
        }

        .mt-4 {
            margin-top: 20px;
        }

        .admin-id-note {
            font-size: 0.85rem;
            color: var(--secondary);
            margin-top: 5px;
        }

        .welcome-message {
            text-align: center;
            font-weight: 600;
            color: var(--success);
            margin: 15px 0;
            padding: 10px;
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: var(--radius);
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .account-selector {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .account-info {
            margin-left: 10px;
        }

        .account-name {
            font-weight: 600;
            color: var(--dark);
        }

        .account-id {
            font-size: 0.8rem;
            color: var(--secondary);
        }

        @media (max-width: 480px) {
            .card {
                padding: 20px;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-utensils"></i>
            </div>
            <div class="logo-text">
                <span class="canteen">Canteen</span><span class="kiosk">Kiosk</span>
            </div>
            <p class="tagline">University Food Service Management System</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Admin Sign In</h1>
                <p class="card-subtitle">Select your account and enter your password</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    <?php echo $success; ?>
                </div>
                <div class="welcome-message">
                    <p>Redirecting to dashboard...</p>
                </div>
            <?php endif; ?>

            <?php if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="admin_id" class="form-label">Select Admin Account</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user input-icon"></i>
                        <select id="admin_id" name="admin_id" class="form-select" required>
                            <option value="">-- Select your account --</option>
                            <?php foreach ($admin_accounts as $account): ?>
                                <option value="<?php echo $account['admin_id']; ?>" <?php echo ($admin_id === $account['admin_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($account['full_name'] . ' (' . $account['admin_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="admin-id-note">Select your account from the list</p>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" id="password-toggle">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>

            <div class="text-center mt-4">
                <p>Don't have an account? <a href="adminsignup.php" class="btn-link">Sign up here</a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('password-toggle');
            
            // Toggle password visibility
            if (passwordToggle) {
                passwordToggle.addEventListener('click', function() {
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        passwordToggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
                    } else {
                        passwordInput.type = 'password';
                        passwordToggle.innerHTML = '<i class="fas fa-eye"></i>';
                    }
                });
            }

            // Auto-focus password field when an account is selected
            const adminSelect = document.getElementById('admin_id');
            if (adminSelect) {
                adminSelect.addEventListener('change', function() {
                    if (this.value) {
                        passwordInput.focus();
                    }
                });
            }
        });
    </script>
</body>
</html>