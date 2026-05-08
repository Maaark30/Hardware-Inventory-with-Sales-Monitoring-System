<?php
include 'project.php';

$result = $conn->query('SELECT COUNT(*) as count FROM products');
$row = $result->fetch_assoc();
echo 'Products count: ' . $row['count'] . PHP_EOL;

$result = $conn->query('SELECT COUNT(*) as count FROM stock_history');
$row = $result->fetch_assoc();
echo 'Stock history count: ' . $row['count'] . PHP_EOL;

$result = $conn->query('SELECT COUNT(*) as count FROM stock_in_batches');
$row = $result->fetch_assoc();
echo 'Stock in batches count: ' . $row['count'] . PHP_EOL;

$result = $conn->query('SELECT COUNT(*) as count FROM stock_out');
$row = $result->fetch_assoc();
echo 'Stock out count: ' . $row['count'] . PHP_EOL;
?>