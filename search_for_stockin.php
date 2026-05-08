<?php
include 'project.php';
header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q === '') {
    echo json_encode([]);
    exit;
}

// Search across product_name, sku, brand, and variation
$stmt = $conn->prepare("
    SELECT 
        p.product_id AS id,
        p.product_name,
        p.brand,
        p.variation,
        p.sku,
        p.stock,
        c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.product_name LIKE CONCAT('%', ?, '%')
       OR p.sku LIKE CONCAT('%', ?, '%')
       OR p.brand LIKE CONCAT('%', ?, '%')
       OR p.variation LIKE CONCAT('%', ?, '%')
    ORDER BY p.product_name ASC
    LIMIT 10
");

$stmt->bind_param("ssss", $q, $q, $q, $q);
$stmt->execute();
$result = $stmt->get_result();

$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = [
        "id" => $row["id"],
        "name" => $row["product_name"],
        "sku" => $row["sku"] ?: "No SKU",
        "brand" => $row["brand"] ?: "Unbranded",
        "category_name" => $row["category_name"] ?: "Uncategorized",
        "stock" => $row["stock"],
        "variation" => $row["variation"] ?: "",
    ];
}

echo json_encode($products);
?>
