<?php
session_start();
include 'project.php';

// =================== ACCESS CONTROL =================== //
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}
// ====================================================== //

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['products'])) {
    $conn->begin_transaction();

    try {
        // 1️⃣ Create new sale group
        $createGroup = $conn->prepare("INSERT INTO sale_groups (created_at) VALUES (NOW())");
        $createGroup->execute();
        $sale_group_id = $conn->insert_id;
        $createGroup->close();

        // 2️⃣ Get data from POST
        $products = $_POST['products']; // array of arrays [id, quantity]
        $cashGiven = isset($_POST['cash_given']) ? floatval($_POST['cash_given']) : 0;
        $grandTotal = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
        $change = $cashGiven - $grandTotal;

        if ($grandTotal <= 0) {
            throw new Exception("Invalid total amount.");
        }

// 3️⃣ Prepare SQL statements
$insertSale = $conn->prepare("
    INSERT INTO sales (product_id, quantity, total_price, sale_group_id, sale_date)
    VALUES (?, ?, ?, ?, NOW())
");
$updateStock = $conn->prepare("
    UPDATE products SET stock = stock - ? WHERE product_id = ?
");

// 4️⃣ Process each product
foreach ($products as $item) {
    $product_id = intval($item['id']);
    $quantity = intval($item['quantity']);

    // Get product price and stock (FIXED COLUMN NAME)
    $getPrice = $conn->prepare("SELECT selling_price, stock FROM products WHERE product_id = ?");
    if (!$getPrice) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $getPrice->bind_param("i", $product_id);
    $getPrice->execute();
    $priceResult = $getPrice->get_result();
    $priceData = $priceResult->fetch_assoc();
    $getPrice->close();

    if (!$priceData) {
        throw new Exception("Product not found (ID: $product_id)");
    }

    if ($priceData['stock'] < $quantity) {
        throw new Exception("Not enough stock for product ID: $product_id");
    }

    $price = floatval($priceData['selling_price']); // FIXED KEY
    $totalPrice = $price * $quantity;

    // Insert sale
    $insertSale->bind_param("iidi", $product_id, $quantity, $totalPrice, $sale_group_id);
    $insertSale->execute();

    // Update stock
    $updateStock->bind_param("ii", $quantity, $product_id);
    $updateStock->execute();
}


        $insertSale->close();
        $updateStock->close();

        // 5️⃣ Record payment
        $insertPayment = $conn->prepare("
            INSERT INTO sale_payments (sale_group_id, cash_given, change_amount, total_amount, payment_date)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insertPayment->bind_param("iddd", $sale_group_id, $cashGiven, $change, $grandTotal);
        $insertPayment->execute();
        $insertPayment->close();

        // 6️⃣ Commit all
        $conn->commit();

        // 7️⃣ Redirect to receipt
        header("Location: receipt.php?sale_group_id=" . $sale_group_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        echo "<h3 style='color:red;'>Transaction Failed:</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "Invalid request.";
}
?>
