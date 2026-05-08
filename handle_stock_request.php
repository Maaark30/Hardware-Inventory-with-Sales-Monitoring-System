<?php
include 'project.php';
session_start();

// ✅ Only staff can submit
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

// ✅ Handle Stock Request Submission
if (isset($_POST['submit_stock'])) {
    $product_id  = intval($_POST['product_id']);
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
    $quantity    = intval($_POST['quantity']);
    $type        = $_POST['type']; // 'in' or 'out'
    $remarks     = trim($_POST['remarks'] ?? '');

    // ✅ Validate input
    if ($product_id <= 0 || $quantity <= 0 || !in_array($type, ['in', 'out'])) {
        echo "<script>alert('Invalid stock request data.'); window.history.back();</script>";
        exit();
    }

    // ✅ Get Staff ID
    $staff_username = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $staff_username);
    $stmt->execute();
    $staffData = $stmt->get_result()->fetch_assoc();
    $staff_id = $staffData['id'] ?? 0;
    $stmt->close();

    if ($staff_id === 0) {
        echo "<script>alert('Staff account not found.'); window.history.back();</script>";
        exit();
    }

    // ✅ Optional: Check current stock for Stock Out
    if ($type === 'out') {
        $stockCheck = $conn->prepare("SELECT stock FROM products WHERE product_id = ?");
        $stockCheck->bind_param("i", $product_id);
        $stockCheck->execute();
        $currentStock = $stockCheck->get_result()->fetch_assoc()['stock'] ?? 0;
        $stockCheck->close();

        if ($quantity > $currentStock) {
            echo "<script>alert('Cannot request stock out greater than available stock ($currentStock).'); window.history.back();</script>";
            exit();
        }
    }

    // ✅ Insert Stock Request
    $query = $conn->prepare("
        INSERT INTO stock_requests (product_id, supplier_id, staff_id, quantity, type, remarks)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $query->bind_param("iiiiss", $product_id, $supplier_id, $staff_id, $quantity, $type, $remarks);

    if ($query->execute()) {
        $action = ($type === 'in') ? 'Stock In' : 'Stock Out';
        echo "<script>alert('$action request submitted successfully! Waiting for admin approval.'); window.location='staff_products.php';</script>";
    } else {
        echo "<script>alert('Error submitting stock request. Please try again.'); window.location='staff_products.php';</script>";
    }

    $query->close();
}
?>
