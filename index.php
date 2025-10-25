<?php
// Check which app to redirect to
$path = $_SERVER['REQUEST_URI'] ?? '';

if (strpos($path, '/admin') === 0) {
    // Admin access
    include 'admin_app/admin_index.php';
} else {
    // User access (default)
    include 'user_app/user_index.php';
}
?>
