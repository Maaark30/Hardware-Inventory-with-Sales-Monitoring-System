<?php
// Include your database connection and start the session
session_start();
include 'project.php'; // Assumed connection file

/**
 * Executes the three-step process for completing a sale:
 * 1. Inserts a new record into the sales_groups table to get the definitive sale_group_id.
 * 2. Updates the temporary sales records in the 'sales' table with the definitive ID.
 * 3. Inserts the payment details (cash given, change, total) into the sale_payments table.
 * * @param mysqli $conn The database connection object.
 * @param float $finalTotal The grand total amount due for the transaction.
 * @param float $cashGiven The amount of cash the customer provided.
 * @param float $changeAmount The amount of change calculated and returned.
 * @param int $staffId The ID of the currently logged-in staff member (unused here, but good practice).
 * @param int $tempSaleId The temporary ID used to group items during the transaction (usually stored in $_SESSION).
 * @return int|bool The new sale_group_id on success, or false on failure.
 */
function recordTransactionAndPayment(
    $conn, 
    $finalTotal, 
    $cashGiven, 
    $changeAmount, 
    $staffId,
    $tempSaleId // New parameter for the temporary group ID
) {
    // --- STEP 1: Insert into sales_groups to generate the primary ID ---
    $groupStmt = $conn->prepare("INSERT INTO sales_groups (transaction_date) VALUES (NOW())");
    
    if (!$groupStmt->execute()) {
        error_log("Error inserting into sales_groups: " . $groupStmt->error);
        $groupStmt->close();
        return false;
    }
    
    $newSaleGroupId = $conn->insert_id;
    $groupStmt->close();

    // --- STEP 2: Link temporary sales items to the definitive sale_group_id ---
    // This updates the 'sales' table rows that were created during the transaction (e.g., in staff_products.php).
    // The items are identified by the temporary ID from the session.
    $updateSalesStmt = $conn->prepare("
        UPDATE sales 
        SET sale_group_id = ? 
        WHERE sale_group_id = ?
    ");
    
    // Bind parameters: i for int
    $updateSalesStmt->bind_param("ii", 
        $newSaleGroupId, 
        $tempSaleId
    );

    if (!$updateSalesStmt->execute()) {
        error_log("Error updating sales items with final sale_group_id: " . $updateSalesStmt->error);
        $updateSalesStmt->close();
        return false;
    }
    
    $updateSalesStmt->close();
    
    // --- STEP 3: Insert payment details into sale_payments table ---
    $paymentStmt = $conn->prepare("
        INSERT INTO sale_payments (sale_group_id, total_amount, cash_given, change_amount)
        VALUES (?, ?, ?, ?)
    ");
    
    // Bind parameters: i for int, d for double/float
    $paymentStmt->bind_param("iddd", 
        $newSaleGroupId, 
        $finalTotal, 
        $cashGiven, 
        $changeAmount
    );

    if (!$paymentStmt->execute()) {
        error_log("Error inserting into sale_payments: " . $paymentStmt->error);
        $paymentStmt->close();
        return false;
    }
    
    $paymentStmt->close();

    return $newSaleGroupId;
}

// --- MAIN EXECUTION BLOCK (Simulating a POST request from POS) ---

// 1. Check for required POST data from the final POS screen
if ($_SERVER["REQUEST_METHOD"] == "POST" && 
    isset($_POST['final_total']) && 
    isset($_POST['cash_given']) && 
    isset($_POST['change_amount'])) 
{
    // Sanitize and convert inputs
    $finalTotal = floatval($_POST['final_total']);
    $cashGiven = floatval($_POST['cash_given']);
    $changeAmount = floatval($_POST['change_amount']);
    
    // --- CRITICAL: Get the temporary ID from the session ---
    // This ID was used when items were added to the 'sales' table.
    if (!isset($_SESSION['temp_sale_group_id'])) {
        // Handle error: No ongoing transaction found
        echo "<h1>Transaction Error</h1>";
        echo "<p>No active transaction found. Please start a new sale.</p>";
        $conn->close();
        exit();
    }
    $tempSaleId = intval($_SESSION['temp_sale_group_id']);

    // Ensure the staff ID is available (if not, use a default/error state)
    $staffId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

    // 2. Record the transaction and payment
    $saleGroupId = recordTransactionAndPayment(
        $conn, 
        $finalTotal, 
        $cashGiven, 
        $changeAmount, 
        $staffId,
        $tempSaleId // Pass the temporary ID to the function
    );

    // 3. Handle success or failure
    if ($saleGroupId) {
        // Transaction complete, clear the temporary session ID
        unset($_SESSION['temp_sale_group_id']);
        
        // SUCCESS: Redirect to the receipt page with the definitive ID
        header("Location: receipt.php?sale_group_id=" . $saleGroupId);
        exit();
    } else {
        // FAILURE: Display an error message
        echo "<h1>Transaction Failed!</h1>";
        echo "<p>There was an error recording the payment. Please check logs for details.</p>";
    }

} else {
    // If not a POST request or missing data
    echo "<h1>Invalid Access</h1>";
    echo "<p>This page should only be accessed after a completed payment.</p>";
}

// Don't forget to close the connection if you haven't used persistent connections
$conn->close(); 
?>
