<?php
include 'project.php';

// ----------------- DELETE PRODUCT -----------------
if(isset($_GET['delete_id'])){
    $id = intval($_GET['delete_id']);

    // Delete image from server if exists
    $res = mysqli_query($conn, "SELECT image_path FROM products WHERE product_id=$id");
    $row = mysqli_fetch_assoc($res);
    if(!empty($row['image_path']) && file_exists($row['image_path'])){
        unlink($row['image_path']);
    }

    // Delete from database
    $sql = "DELETE FROM products WHERE product_id=$id";
    mysqli_query($conn, $sql);
    header("Location: products.php");
    exit();
}

?>
