<?php
include 'project.php';
session_start();

// ===================== 1. AUTHENTICATION =====================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$allowed_roles = ['admin', 'staff'];
$current_role = $_SESSION['role'] ?? '';

if (!in_array($current_role, $allowed_roles, true)) {
    header("Location: login.php");
    exit();
}

$sold_by_user = $_SESSION['username'];
$user_id = $_SESSION['id'] ?? ($_SESSION['user_id'] ?? null);

// ===================== 2. DETERMINE SOURCE PAGE =====================
// This decides where to go back if there is an error.
// Admin usually uses p_os.php
// Staff usually uses staff_add_sale.php
$source_page = 'p_os.php';

if ($current_role === 'staff') {
    $source_page = 'staff_add_sale.php';
} elseif ($current_role === 'admin') {
    $source_page = 'p_os.php';
}

// Optional override from form hidden input
if (!empty($_POST['source_page'])) {
    $allowed_sources = ['p_os.php', 'staff_add_sale.php'];
    if (in_array($_POST['source_page'], $allowed_sources, true)) {
        $source_page = $_POST['source_page'];
    }
}

// ===================== 3. GET DATA FROM FORM =====================
$cart_data_json   = $_POST['cart_data'] ?? '[]';
$cart_items       = json_decode($cart_data_json, true);

$payment_type     = strtoupper(trim($_POST['payment_type'] ?? 'CASH'));
$total_amount_due = (float) ($_POST['total_amount'] ?? 0);
$cash_given       = (float) ($_POST['cash_given'] ?? 0);
$discount_amount  = (float) ($_POST['discount_amount'] ?? 0);
$change_amount    = max(0, $cash_given - $total_amount_due);

$customer_name    = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : null;
$customer_contact = isset($_POST['customer_contact']) ? trim($_POST['customer_contact']) : null;
$customer_address = isset($_POST['customer_address']) ? trim($_POST['customer_address']) : null;

// Normalize empty strings to NULL
$customer_name    = $customer_name !== '' ? $customer_name : null;
$customer_contact = $customer_contact !== '' ? $customer_contact : null;
$customer_address = $customer_address !== '' ? $customer_address : null;

// ===================== 4. VALIDATION =====================
if (!is_array($cart_items) || empty($cart_items)) {
    $_SESSION['error'] = "Sale failed: Cart is empty.";
    header("Location: $source_page");
    exit();
}

if ($total_amount_due < 0) {
    $_SESSION['error'] = "Sale failed: Total amount is invalid.";
    header("Location: $source_page");
    exit();
}

if ($payment_type === 'CASH' && $cash_given < $total_amount_due) {
    $_SESSION['error'] = "Sale failed: Cash given (₱" . number_format($cash_given, 2) . ") is less than the amount due (₱" . number_format($total_amount_due, 2) . ").";
    header("Location: $source_page");
    exit();
}

// ===================== 5. GET USER ID IF MISSING =====================
if (!$user_id && isset($_SESSION['username'])) {
    $username = $_SESSION['username'];

    $stmt_user = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    if ($stmt_user) {
        $stmt_user->bind_param("s", $username);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        $user_row = $result_user->fetch_assoc();
        $user_id = $user_row['id'] ?? null;
        $stmt_user->close();

        if ($user_id) {
            $_SESSION['id'] = $user_id;
            $_SESSION['user_id'] = $user_id;
        }
    }
}

if (!$user_id) {
    $_SESSION['error'] = "Sale failed: Could not determine user ID for the transaction.";
    header("Location: $source_page");
    exit();
}

// ===================== 6. DATABASE TRANSACTION =====================
$conn->begin_transaction();

try {
    // Step A: Create sale group
    $stmt_group = $conn->prepare("
        INSERT INTO sale_groups (
            user_id,
            customer_name,
            customer_contact,
            customer_address,
            discount_amount,
            total_amount,
            created_by,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$stmt_group) {
        throw new Exception("Failed to prepare sale group query: " . $conn->error);
    }

    $stmt_group->bind_param(
        "isssdds",
        $user_id,
        $customer_name,
        $customer_contact,
        $customer_address,
        $discount_amount,
        $total_amount_due,
        $sold_by_user
    );

    if (!$stmt_group->execute()) {
        throw new Exception("Failed to create sale group: " . $stmt_group->error);
    }

    $sale_group_id = $conn->insert_id;
    $stmt_group->close();

    if ($sale_group_id <= 0) {
        throw new Exception("Could not get sale group ID.");
    }

    // Step B: Create payment record
    $stmt_payment = $conn->prepare("
        INSERT INTO sale_payments (
            sale_group_id,
            payment_type,
            total_amount,
            cash_given,
            change_amount
        ) VALUES (?, ?, ?, ?, ?)
    ");

    if (!$stmt_payment) {
        throw new Exception("Failed to prepare payment query: " . $conn->error);
    }

    $stmt_payment->bind_param(
        "isddd",
        $sale_group_id,
        $payment_type,
        $total_amount_due,
        $cash_given,
        $change_amount
    );

    if (!$stmt_payment->execute()) {
        throw new Exception("Failed to record payment: " . $stmt_payment->error);
    }

    $stmt_payment->close();

    // Step C: Prepare item insert and stock update
    $stmt_sale_item = $conn->prepare("
        INSERT INTO sales (
            sale_group_id,
            product_id,
            quantity,
            total_price
        ) VALUES (?, ?, ?, ?)
    ");

    if (!$stmt_sale_item) {
        throw new Exception("Failed to prepare sales item query: " . $conn->error);
    }

    $stmt_update_stock = $conn->prepare("
        UPDATE products
        SET stock = stock - ?
        WHERE product_id = ? AND stock >= ?
    ");

    if (!$stmt_update_stock) {
        throw new Exception("Failed to prepare stock update query: " . $conn->error);
    }

    foreach ($cart_items as $item) {
        $product_id  = (int) ($item['product_id'] ?? 0);
        $quantity    = (float) ($item['quantity'] ?? 0);
        $total_price = (float) ($item['total_price'] ?? 0);

        if ($product_id <= 0 || $quantity <= 0 || $total_price < 0) {
            throw new Exception("Invalid cart item data.");
        }

        // Insert sale item
        $stmt_sale_item->bind_param("iidd", $sale_group_id, $product_id, $quantity, $total_price);
        if (!$stmt_sale_item->execute()) {
            throw new Exception("Failed to insert sale item for product ID $product_id: " . $stmt_sale_item->error);
        }

        // Update stock
        $stmt_update_stock->bind_param("did", $quantity, $product_id, $quantity);
        if (!$stmt_update_stock->execute()) {
            throw new Exception("Failed to update stock for product ID $product_id: " . $stmt_update_stock->error);
        }

        if ($stmt_update_stock->affected_rows === 0) {
            throw new Exception("Stock update failed for product ID $product_id. Insufficient stock.");
        }
    }

    $stmt_sale_item->close();
    $stmt_update_stock->close();

    // Step D: Commit
    $conn->commit();

    // ===================== 7. SUCCESS REDIRECT =====================
    // Use one receipt page for both, or split by role if you want.
    header("Location: invoice_receipt.php?sale_group_id=" . $sale_group_id);
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("POS Transaction Failed: " . $e->getMessage());

    $_SESSION['error'] = "Transaction failed: " . $e->getMessage();
    header("Location: $source_page");
    exit();

} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>