<?php
include 'project.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category_name = trim($_POST['category_name']);

    if (!empty($category_name)) {
        $stmt = $conn->prepare("INSERT INTO categories (category_name, created_at) VALUES (?, NOW())");
        $stmt->bind_param("s", $category_name);

        

        if ($stmt->execute()) {
            header("Location: categories.php?success=1");
            exit();
        } else {
            header("Location: categories.php?error=1");
            exit();
        }
    } else {
        header("Location: categories.php?error=empty");
        exit();
    }
} else {
    header("Location: categories.php");
    exit();
}
?>
