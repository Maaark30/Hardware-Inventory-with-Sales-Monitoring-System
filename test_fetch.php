<?php
include 'project.php';

// Get first product ID
$result = $conn->query('SELECT product_id FROM products LIMIT 1');
$row = $result->fetch_assoc();
$product_id = $row['product_id'];

echo "Testing with product ID: $product_id\n";

session_start();
$_SESSION['username'] = 'test';
$_GET['product_id'] = $product_id;
include 'fetch_product_details.php';
?>