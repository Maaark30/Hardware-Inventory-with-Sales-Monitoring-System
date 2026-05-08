<?php
include 'project.php';

if(isset($_POST['add_product'])){
    $name = trim($_POST['product_name']);
    $category = intval($_POST['category_id']);
    $subcategory = intval($_POST['subcategory_id']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $barcode = trim($_POST['barcode']);



    $description = trim($_POST['description']);

// Add to SQL
$stmt = $conn->prepare("INSERT INTO products (product_name, category_id, subcategory_id, price, stock, image_path, barcode, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("siidisss", $name, $category, $subcategory, $price, $stock, $imagePath, $barcode, $description);
   $stmt->execute();


    header("Location: products.php");
    exit();
}
?>
