<?php
require 'project.php';
session_start();

$redirect_to = ($_SESSION['role'] ?? '') === 'staff' ? "staff_products.php" : "products.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity   = intval($_POST['quantity'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');
    $stocked_by = $_SESSION['username'] ?? 'Admin';

    // ✅ Validate inputs
    if ($product_id <= 0 || $quantity <= 0) {
        $_SESSION['error'] = "Invalid product or quantity.";
        header("Location: $redirect_to");
        exit();
    }

    // ✅ Check product
    $stmt = $conn->prepare("SELECT product_name, stock, supplier_price FROM products WHERE product_id = ?");
    if (!$stmt) {
        $_SESSION['error'] = "DB prepare failed: " . $conn->error;
        header("Location: $redirect_to");
        exit();
    }

    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    if (!$product) {
        $_SESSION['error'] = "Product not found.";
        header("Location: $redirect_to");
        exit();
    }

    $product_name = $product['product_name'];
    $current_stock = (int)$product['stock'];
    $supplier_price = (float)$product['supplier_price'];
    $total_cost = $supplier_price * $quantity;

    // ✅ Check stock availability
    if ($quantity > $current_stock) {
        $_SESSION['error'] = "Not enough stock available. Current stock: {$current_stock}.";
        header("Location: $redirect_to");
        exit();
    }

    // ✅ Insert record into stock_out (Your original logic)
    $insert = $conn->prepare("
        INSERT INTO stock_out (product_id, quantity, supplier_price, total_cost, reason, stocked_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$insert) {
        $_SESSION['error'] = "Prepare failed: " . $conn->error;
        header("Location: $redirect_to");
        exit();
    }

    $insert->bind_param("iiddss", $product_id, $quantity, $supplier_price, $total_cost, $reason, $stocked_by);

    if (!$insert->execute()) {
        $_SESSION['error'] = "Insert failed: " . $insert->error;
        $insert->close();
        header("Location: $redirect_to");
        exit();
    }
    $insert->close();

    // ✅ Update product stock (Your original logic)
    $new_stock = $current_stock - $quantity;
    $update = $conn->prepare("UPDATE products SET stock = ? WHERE product_id = ?");
    $update->bind_param("ii", $new_stock, $product_id);
    $update->execute();
    $update->close();

    // ---------------------------------------------------------
    // ✅ NEW: Log to Unified Product History
    // This is the ONLY new part needed to fix your issue.
    // ---------------------------------------------------------
    $log_action = "STOCK OUT";
    $log_desc = "Deducted {$quantity} units. Reason: " . ($reason ?: 'None');
    
    $hist_stmt = $conn->prepare("INSERT INTO product_history (product_id, user_username, action_type, description) VALUES (?, ?, ?, ?)");
    if ($hist_stmt) {
        $hist_stmt->bind_param("isss", $product_id, $stocked_by, $log_action, $log_desc);
        $hist_stmt->execute();
        $hist_stmt->close();
    }
    // ---------------------------------------------------------

    $_SESSION['success'] = "Stock out successful! Deducted {$quantity} from stock.";
    header("Location: $redirect_to");
    exit();
}
?>