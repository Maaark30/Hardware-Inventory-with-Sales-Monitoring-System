<?php
include 'dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = mysqli_real_escape_string($con, $_POST['product_name']);
    $barcode      = mysqli_real_escape_string($con, $_POST['barcode']);
    $category_id  = intval($_POST['category_id']);
    $price        = floatval($_POST['price']);
    $stock        = intval($_POST['stock']);

    // ✅ Handle product image upload
    $image_path = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $image_path = time() . "_" . basename($_FILES['product_image']['name']);
        $targetFile = $uploadDir . $image_path;

        if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $targetFile)) {
            $image_path = null; // fallback if upload fails
        }
    }

    // ✅ Insert into database
    $query = "INSERT INTO products 
                (product_name, category_id, price, stock, created_at, image_path, barcode) 
              VALUES 
                ('$product_name', '$category_id', '$price', '$stock', NOW(), '$image_path', '$barcode')";

    if (mysqli_query($con, $query)) {
        echo "<script>alert('✅ Product added successfully!'); window.location.href='products.php';</script>";
    } else {
        echo "❌ Error: " . mysqli_error($con);
    }
}
?>
