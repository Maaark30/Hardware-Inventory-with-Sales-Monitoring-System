<?php
include 'project.php'; // Make sure project.php path is correct relative to this file
header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

// Basic input validation
if (strlen($query) < 2) {
    echo json_encode([]); // Return empty array if query is too short
    exit;
}

$searchTerm = "%" . $query . "%";

// Prepare SQL statement to prevent SQL injection
$sql = "SELECT product_id, product_name, stock, sku, supplier_price
        FROM products
        WHERE (product_name LIKE ? OR sku LIKE ?)
        LIMIT 10";

$stmt = $conn->prepare($sql);

// Check if statement preparation was successful
if ($stmt === false) {
    // Log error details (don't show detailed errors to users in production)
    error_log("Failed to prepare statement: " . $conn->error);
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database query failed.']);
    exit;
}

$stmt->bind_param("ss", $searchTerm, $searchTerm);

// Execute the statement and check for errors
if (!$stmt->execute()) {
    error_log("Failed to execute statement: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['error' => 'Database query execution failed.']);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close(); // Close connection after use

// Output the results as JSON
echo json_encode($products);
?>