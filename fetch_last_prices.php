<?php
include 'project.php';
header('Content-Type: application/json');

/**
 * fetch_last_prices.php
 * Endpoint to get the most recent supplier_price for a set of products from a specific supplier.
 */

$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$product_ids_raw = isset($_GET['product_ids']) ? $_GET['product_ids'] : '';

if ($supplier_id <= 0 || empty($product_ids_raw)) {
    echo json_encode([]);
    exit;
}

$product_ids = array_unique(array_filter(explode(',', $product_ids_raw), 'is_numeric'));

if (empty($product_ids)) {
    echo json_encode([]);
    exit;
}

$results = [];
$placeholders = implode(',', array_fill(0, count($product_ids), '?'));

// Get the latest 'IN' movement price for each requested product from this supplier
$sql = "SELECT sh.product_id, sh.supplier_price 
        FROM stock_history sh
        INNER JOIN (
            SELECT product_id, MAX(created_at) as max_date
            FROM stock_history
            WHERE supplier_id = ? AND movement_type = 'IN'
            GROUP BY product_id
        ) latest ON sh.product_id = latest.product_id AND sh.created_at = latest.max_date
        WHERE sh.supplier_id = ? 
          AND sh.movement_type = 'IN'
          AND sh.product_id IN ($placeholders)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$types = 'ii' . str_repeat('i', count($product_ids));
$params = array_merge([$supplier_id, $supplier_id], $product_ids);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $results[$row['product_id']] = (float)$row['supplier_price'];
}

echo json_encode($results);
