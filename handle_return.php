<?php
include 'project.php';
session_start();

// Access and authentication check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: staff_returns.php");
    exit();
}

$sale_group_id = $_POST['sale_group_id'] ?? null;
$returns = $_POST['returns'] ?? []; // Array of [product_id => quantity]
$reasons = $_POST['reasons'] ?? []; // Array of [product_id => reason]
$user_id = $_SESSION['id'] ?? 0; // Assuming $_SESSION['id'] holds the staff user ID
$username = $_SESSION['username'] ?? 'System';

if (!$sale_group_id || empty($returns) || $user_id === 0) {
    // Redirect back if essential data is missing
    $message = urlencode("Error: Missing sale ID or items to return.");
    header("Location: staff_returns.php?error=true&message=" . $message);
    exit();
}

// ----------------------------------------------------------------------
// 1. Transaction Start
// ----------------------------------------------------------------------

// Determine a valid supplier_id for the audit log
$dummy_supplier_id = 1; // Default to 1 (You MUST ensure this ID exists in your `suppliers` table)
$sql_check_supplier = "SELECT supplier_id FROM suppliers LIMIT 1";
$result_check = $conn->query($sql_check_supplier);

if ($result_check && $result_check->num_rows > 0) {
    $supplier_data = $result_check->fetch_assoc();
    $dummy_supplier_id = (int)$supplier_data['supplier_id'];
} else {
    error_log("CRITICAL: No suppliers found. Using default ID '1' for return log.");
}

$conn->begin_transaction();
$success = true;
$total_refund_amount = 0;

try {
    // Iterate through each product being returned
    foreach ($returns as $product_id => $quantity) {
        $quantity = (int)$quantity;

        // Skip if quantity is zero or less
        if ($quantity <= 0) continue;

        // --- Fetch necessary data for inventory update and logging (Fixed query) ---
        $sql_fetch = "SELECT total_price, quantity AS sale_quantity FROM sales WHERE sale_group_id = ? AND product_id = ?";
        $stmt_fetch = $conn->prepare($sql_fetch);
        
        if (!$stmt_fetch) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt_fetch->bind_param("ii", $sale_group_id, $product_id);
        $stmt_fetch->execute();
        $result = $stmt_fetch->get_result();
        $sale_data = $result->fetch_assoc();
        $stmt_fetch->close();

        if (!$sale_data) {
            throw new Exception("Sale item not found for product ID: $product_id");
        }

        // Calculate the unit price (total_price / quantity)
        if ($sale_data['sale_quantity'] <= 0) {
             throw new Exception("Cannot calculate unit price: Sale quantity is zero for product ID: $product_id");
        }
        $unit_price = $sale_data['total_price'] / $sale_data['sale_quantity'];
        
        $item_refund = $quantity * $unit_price;
        $total_refund_amount += $item_refund;
        $return_reason = $reasons[$product_id] ?? 'General Return';
        
        // ----------------------------------------------------------------------
        // 2. Inventory Reversal (Add stock back)
        // ----------------------------------------------------------------------
        $sql_inventory = "UPDATE products SET stock = stock + ? WHERE product_id = ?";
        $stmt_inv = $conn->prepare($sql_inventory);
        
        if (!$stmt_inv) {
             throw new Exception("Inventory update prepare failed: " . $conn->error);
        }
        
        $stmt_inv->bind_param("ii", $quantity, $product_id);
        $stmt_inv->execute();
        
        if ($stmt_inv->affected_rows === 0) {
            throw new Exception("Failed to reverse inventory for product ID: $product_id");
        }
        $stmt_inv->close();

        // ----------------------------------------------------------------------
        // 3. Audit Logging (Record the movement as 'RETURN')
        // ----------------------------------------------------------------------
        
        $dummy_supplier_price = 0;
        
        $sql_log = "
            INSERT INTO stock_history 
            (product_id, supplier_id, movement_type, quantity, supplier_price, remarks, stocked_by)
            VALUES (?, ?, 'RETURN', ?, ?, ?, ?)
        ";
        $stmt_log = $conn->prepare($sql_log);
        
        if (!$stmt_log) {
             throw new Exception("Log prepare failed: " . $conn->error);
        }
        
        $log_remarks = "Return processed: " . $return_reason;

        $stmt_log->bind_param(
            "iiidss", 
            $product_id, 
            $dummy_supplier_id, 
            $quantity, 
            $dummy_supplier_price,
            $log_remarks,
            $username 
        );
        $stmt_log->execute();
        $stmt_log->close();
    }

    // ----------------------------------------------------------------------
    // 4. Financial Adjustment (Log the Refund/Credit)
    // ----------------------------------------------------------------------
    if ($total_refund_amount <= 0) {
        throw new Exception("No valid items selected for return/refund.");
    }
    
    // Log Refund Group (Acts as a negative sale transaction)
    // FIX 4: Removed the 'status' column reference which caused the 'Unknown column' error
    $sql_refund_log = "
        INSERT INTO sale_groups (user_id, created_at) VALUES (?, NOW())
    ";
    $stmt_group = $conn->prepare($sql_refund_log);
    $stmt_group->bind_param("i", $user_id);
    $stmt_group->execute();
    $refund_group_id = $stmt_group->insert_id;
    $stmt_group->close();
    
    // Log Payment as a Negative amount
    $sql_payment_log = "
        INSERT INTO sale_payments (sale_group_id, total_amount, payment_type) VALUES (?, ?, 'Refund')
    ";
    $stmt_payment = $conn->prepare($sql_payment_log);
    $negative_refund = -$total_refund_amount; 
    $stmt_payment->bind_param("id", $refund_group_id, $negative_refund);
    $stmt_payment->execute();
    
    if ($stmt_payment->affected_rows === 0) { 
        throw new Exception("Failed to log refund payment.");
    }
    $stmt_payment->close();


    // ----------------------------------------------------------------------
    // 5. Commit Transaction
    // ----------------------------------------------------------------------
    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    $success = false;
    // Log the detailed error message for debugging
    error_log("Return Transaction Failed: " . $e->getMessage()); 
    $error = "Transaction failed. Please check server logs for details. Error: " . $e->getMessage();
}

$conn->close();

// ----------------------------------------------------------------------
// 6. Redirect to Confirmation/Error
// ----------------------------------------------------------------------
if ($success) {
    $message = urlencode("Refund of ₱" . number_format($total_refund_amount, 2) . " processed successfully! Stock reversed.");
    header("Location: staff_returns.php?success=true&message=" . $message);
    exit();
} else {
    // Note: The $error variable contains the detailed message from the catch block
    $message = urlencode($error); 
    header("Location: staff_returns.php?error=true&message=" . $message);
    exit();
}
?>