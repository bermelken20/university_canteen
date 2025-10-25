<?php
// Get the current path
$path = $_SERVER['REQUEST_URI'] ?? '';

// Route to admin if /admin path is accessed
if (strpos($path, '/admin') === 0) {
    header('Location: /admin/adminsignin.php');
    exit();
}

// Default route to user signin
header('Location: /user/signin.php');
exit();
?>
