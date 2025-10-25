<?php
require_once 'db.php';
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get order ID from request
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

try {
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email, u.user_id 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.user_id 
        WHERE o.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }

    // Fetch order items
    $stmt = $pdo->prepare("
        SELECT oi.*, mi.name as item_name 
        FROM order_items oi 
        LEFT JOIN menu_items mi ON oi.item_id = mi.item_id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);

} catch (Exception $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
