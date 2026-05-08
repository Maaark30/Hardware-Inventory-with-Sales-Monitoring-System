<?php
include 'project.php';

$name      = trim($_GET['name'] ?? '');
$brand     = trim($_GET['brand'] ?? '');
$variation = trim($_GET['variation'] ?? '');

$exists = false;

if ($name !== '' && $brand !== '' && $variation !== '') {
    $st = $conn->prepare("SELECT COUNT(*) FROM products WHERE product_name = ? AND brand = ? AND variation = ?");
    $st->bind_param("sss", $name, $brand, $variation);
    $st->execute();
    $st->bind_result($count);
    $st->fetch();
    $st->close();
    
    if ($count > 0) {
        $exists = true;
    }
}

echo json_encode(['exists' => $exists]);
?>
