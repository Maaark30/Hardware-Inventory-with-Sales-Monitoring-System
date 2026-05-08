<?php
include 'project.php';
session_start();

// Restrict access to staff only
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// Fetch ALL stock movements (IN, OUT, DAMAGE, RETURN) to create a complete audit log
$query = "
    SELECT 
        sh.stock_in_id AS history_id,
        p.product_name,
        s.supplier_name,
        sh.quantity,
        sh.supplier_price,
        sh.remarks,
        sh.stocked_by,
        sh.movement_type,  
        sh.created_at
    FROM stock_history sh
    LEFT JOIN products p ON sh.product_id = p.product_id
    LEFT JOIN suppliers s ON sh.supplier_id = s.supplier_id
    ORDER BY sh.created_at DESC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory Movement Log</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> 
<link rel="stylesheet" href="css/user.css">
</head>
<body>
<div class="d-flex">

    <div class="sidebar flex-column p-3" id="sidebar">
        <div class="sidebar-logo text-center">
            <img src="images/logo.png" alt="Staff Logo">
            <h5 class="mt-2">Staff Panel</h5>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="sidebar-title">Main</li>
            <li class="nav-item mb-2"><a class="nav-link " href="staff_dashboard.php"><i class="fa fa-home me-2"></i> Dashboard</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_products.php"><i class="fa-solid fa-box me-2"></i> Products</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="purchased_history.php"><i class="fa fa-history me-2"></i> Purchased History</a></li>
            <li class="nav-item mb-2"><a class="nav-link " href="staff_returns.php"><i class="fa fa-undo me-2"></i> Returns & Refunds</a></li> 
            <li class="nav-item mb-2"><a class="nav-link" href="staff_supplier.php"><i class="fa fa-truck me-2"></i> Suppliers</a></li>
            <li class="nav-item mb-2"><a class="nav-link active" href="staff_stock_history.php"><i class="bi bi-clock-history me-2"></i> Stock History</a></li>
            <li class="nav-item mb-2"><a class="nav-link " href="inventory_value_report.php"><i class="fa fa-hand-holding-dollar me-2"></i> Inventory Value</a></li>
            <li class="nav-item mb-2"><a class="nav-link " href="seasonal_demand_report.php"><i class="fa fa-calendar-alt me-2"></i> Seasonal Demand</a></li>
            <li class="sidebar-title">Account</li>
            <!-- <li class="nav-item mb-2"><a class="nav-link" href="my_profile.php"><i class="fa fa-user me-2"></i> My Profile</a></li> -->
            <li class="sidebar-title">Others</li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="fa fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold text-primary">
                <i class="bi bi-clock-history me-2"></i> Inventory Movement Log
            </h4>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body table-responsive">
                <table class="table table-striped align-middle text-center">
                    <thead class="table-primary">
                        <tr>
                            <th>#</th>
                            <th>Type</th> 
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Cost/Supplier</th>
                            <th>Staff</th> 
                            <th>Date & Time</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): $i = 1; ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                // Determine styling based on movement type
                                $is_in = ($row['movement_type'] == 'IN');
                                $qty_sign = $is_in ? '+' : '-';
                                $type = $row['movement_type'];
                                
                                // Set visual colors based on movement type
                                $row_class = 'table-light'; // Default light color
                                $type_badge = 'bg-secondary';
                                
                                if ($type == 'IN') {
                                    $row_class = 'table-success';
                                    $type_badge = 'bg-success';
                                } elseif ($type == 'OUT') {
                                    $row_class = 'table-danger';
                                    $type_badge = 'bg-danger';
                                } elseif ($type == 'DAMAGE') {
                                    $row_class = 'table-warning';
                                    $type_badge = 'bg-warning text-dark';
                                } elseif ($type == 'RETURN') {
                                    $row_class = 'table-info';
                                    $type_badge = 'bg-info text-dark';
                                }
                            ?>
                                <tr class="<?= $row_class; ?>">
                                    <td><?= $i++; ?></td>
                                    <td><span class="badge <?= $type_badge; ?>"><?= htmlspecialchars($type); ?></span></td> 
                                    <td><?= htmlspecialchars($row['product_name'] ?? 'N/A'); ?></td>
                                    <td class="fw-bold"><?= $qty_sign . htmlspecialchars($row['quantity']); ?></td> 
                                    
                                    <td>
                                        <?php 
                                            // Only show supplier details for Stock IN movements
                                            if ($type == 'IN') {
                                                echo '<strong>' . htmlspecialchars($row['supplier_name'] ?? 'N/A') . '</strong><br>';
                                                echo '(₱' . number_format($row['supplier_price'], 2) . ')';
                                            } else {
                                                echo '<span class="text-muted">N/A</span>';
                                            }
                                        ?>
                                    </td>
                                    
                                    <td><?= htmlspecialchars($row['stocked_by']); ?></td>
                                    <td><?= date('Y-m-d h:i A', strtotime($row['created_at'])); ?></td>
                                    <td><?= htmlspecialchars($row['remarks']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-muted text-center py-3">No inventory movement records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>