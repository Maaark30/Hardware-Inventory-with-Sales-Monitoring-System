<?php
include 'project.php';
session_start();

// ✅ Restrict to staff only
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

if (isset($_POST['submit_refund'])) {
    $sale_group_id = trim($_POST['sale_group_id']);
    $reason = trim($_POST['reason']);

    if (empty($sale_group_id) || empty($reason)) {
        echo "<script>alert('Please provide a refund reason.'); window.history.back();</script>";
        exit();
    }

    // ✅ Get staff info
    $staff_username = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $staff_username);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $staff_id = $staff['id'] ?? 0;
    $stmt->close();

    if ($staff_id === 0) {
        echo "<script>alert('Staff not found.'); window.location='purchased_history.php';</script>";
        exit();
    }

    // ✅ Check if refund request already exists for the same sale
    $check = $conn->prepare("SELECT id FROM refund_requests WHERE sale_group_id = ?");
    $check->bind_param("s", $sale_group_id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {
        echo "<script>alert('A refund request already exists for this sale.'); window.location='purchased_history.php';</script>";
        exit();
    }

    // ✅ Insert refund request
    $insert = $conn->prepare("
        INSERT INTO refund_requests (sale_group_id, staff_id, reason, status, requested_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $insert->bind_param("sis", $sale_group_id, $staff_id, $reason);

    if ($insert->execute()) {
        echo "<script>alert('Refund request submitted successfully!'); window.location='purchased_history.php';</script>";
    } else {
        echo "<script>alert('Error submitting refund request.'); window.location='purchased_history.php';</script>";
    }

    $insert->close();
}
?>
