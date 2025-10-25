<?php
// Allow CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database configuration
$servername = "localhost";
$username = "your_username";  // Palitan ng iyong username
$password = "your_password";  // Palitan ng iyong password
$dbname = "university_canteen";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if data is valid
if (!$data || !isset($data['professor']) || !isset($data['items'])) {
    echo json_encode(["success" => false, "message" => "Invalid data"]);
    exit;
}

// Extract professor data with default values
$professor_name = isset($data['professor']['name']) ? $data['professor']['name'] : '';
$professor_department = isset($data['professor']['department']) ? $data['professor']['department'] : '';
$professor_id = isset($data['professor']['id']) ? $data['professor']['id'] : '';

// Check if required fields are filled
if (empty($professor_name) || empty($professor_id)) {
    echo json_encode(["success" => false, "message" => "Professor name and ID are required"]);
    exit;
}

// Calculate total if not provided
if (isset($data['total'])) {
    $total = (float)$data['total'];
} else {
    // Calculate total from items
    $total = 0;
    foreach ($data['items'] as $item) {
        $itemTotal = isset($item['price']) ? $item['price'] : 0;
        $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
        $total += $itemTotal * $quantity;
    }
}

// Prepare the SQL query based on your table structure
$sql = "INSERT INTO orders (professor_name, professor_department, professor_id, items, total, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit;
}

// Bind parameters
$items_json = json_encode($data['items']);
$payment_method = isset($data['paymentMethod']) ? $data['paymentMethod'] : 'cod';
$status = isset($data['status']) ? $data['status'] : 'pending';

$stmt->bind_param("ssssdss", $professor_name, $professor_department, $professor_id, $items_json, $total, $payment_method, $status);

// Execute the statement
if ($stmt->execute()) {
    $order_id = $stmt->insert_id;
    echo json_encode([
        "success" => true, 
        "message" => "Order saved successfully", 
        "order_id" => $order_id
    ]);
} else {
    echo json_encode([
        "success" => false, 
        "message" => "Error: " . $stmt->error,
        "sql_error" => $conn->error
    ]);
}

// Close connections
$stmt->close();
$conn->close();
?>