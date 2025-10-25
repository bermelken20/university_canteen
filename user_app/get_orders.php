<?php
require_once '../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

try {
    // SQL query to get orders
    $sql = "SELECT order_id, professor_name, professor_department, professor_id, items, total, payment_method, status, created_at 
            FROM orders 
            ORDER BY created_at DESC 
            LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $orders = [];

    while ($row = $stmt->fetch()) {
        $orders[] = [
            "id" => $row["order_id"],
            "professor" => [
                "name" => $row["professor_name"],
                "department" => $row["professor_department"],
                "id" => $row["professor_id"]
            ],
            "items" => json_decode($row["items"], true),
            "total" => (float) $row["total"],
            "paymentMethod" => $row["payment_method"],
            "status" => $row["status"],
            "date" => $row["created_at"]
        ];
    }

    echo json_encode($orders);

} catch (PDOException $e) {
    error_log("Database error in get_orders.php: " . $e->getMessage());
    echo json_encode(["error" => "Database error"]);
} catch (Exception $e) {
    error_log("General error in get_orders.php: " . $e->getMessage());
    echo json_encode(["error" => "An error occurred"]);
}
?>