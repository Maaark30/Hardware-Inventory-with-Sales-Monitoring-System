<?php
/**
 * search_products.php
 * * Description:
 * Searches for products by name, SKU, or ID and returns matching results as JSON.
 */

include 'project.php';
header('Content-Type: application/json');

// Initialize
$query = $_GET['q'] ?? '';
$products = [];

// 1. Validate input (must have at least 2 characters)
if (empty($query) || strlen(trim($query)) < 2) {
    echo json_encode($products);
    exit;
}

// 2. Sanitize and prepare query parameters
$query_exact = trim($query);
$search_name = '%' . $query_exact . '%';
$product_id_search = is_numeric($query_exact) ? (int)$query_exact : 0;

// 3. SQL Query
$sql = "
    SELECT 
        p.product_id,
        p.product_name,
        p.sku,
        p.stock,
        p.unit,
        p.selling_price AS price,
        p.brand,
        p.variation,
        p.supplier_price,
        c.category_name,
        s.subcategory_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN subcategories s ON p.subcategory_id = s.subcategory_id
    WHERE p.sku = ?
       OR p.product_name LIKE ?
       OR p.sku LIKE ?
       OR p.product_id = ?
    LIMIT 10
";

// 4. Execute and fetch results
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("sssi", $query_exact, $search_name, $search_name, $product_id_search);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_id'       => $row['product_id'],
            'product_name'     => $row['product_name'],
            'sku'              => $row['sku'],
            'stock'            => (float) $row['stock'],
            'unit'             => !empty($row['unit']) ? $row['unit'] : 'pcs',
            'price'            => (float) $row['price'],
            'brand'            => $row['brand'] ?? '',
            'variation'        => $row['variation'] ?? '',
            'supplier_price'   => (float) $row['supplier_price'],
            'category_name'    => $row['category_name'] ?? '',
            'subcategory_name' => $row['subcategory_name'] ?? '',
        ];
    }

    $stmt->close();
} else {
    error_log("SQL Prepare Error in search_products.php: " . $conn->error);
}

// 5. Output JSON
echo json_encode($products);

// 6. Close connection
$conn->close();
?>