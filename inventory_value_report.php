<?php
include 'project.php';
session_start();

// Access control (Assumed staff role)
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$total_inventory_value = 0;

// Fetch all products, joining the latest supplier price (purchase cost)
// This query uses a subquery to find the most recent 'IN' price from stock_history
$query = "
    SELECT 
        p.product_id,
        p.product_name,
        p.stock,
        p.unit,
        p.sku,
        (
            SELECT sh.supplier_price
            FROM stock_history sh
            WHERE sh.product_id = p.product_id 
              AND sh.movement_type = 'IN' 
            ORDER BY sh.created_at DESC
            LIMIT 1
        ) AS latest_cost
    FROM products p
    WHERE p.stock > 0
    ORDER BY p.product_name ASC
";

$result = $conn->query($query);
$inventory_data = $result->fetch_all(MYSQLI_ASSOC);

// Calculate individual item values and the grand total
$report_items = [];
foreach ($inventory_data as $item) {
    // If latest_cost is NULL (product never stocked in), treat cost as 0
    $cost = $item['latest_cost'] ?? 0;
    $item_value = $item['stock'] * $cost;
    $total_inventory_value += $item_value;

    $report_items[] = [
        'product_name' => $item['product_name'],
        'stock' => $item['stock'],
        'unit' => $item['unit'],
        'latest_cost' => $cost,
        'item_value' => $item_value,
        'sku' => $item['sku']
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Value Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
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
            <li class="nav-item mb-2"><a class="nav-link" href="staff_stock_history.php"><i class="bi bi-clock-history me-2"></i> Stock History</a></li>
            <li class="nav-item mb-2"><a class="nav-link active" href="inventory_value_report.php"><i class="fa fa-hand-holding-dollar me-2"></i> Inventory Value</a></li>
            <li class="nav-item mb-2"><a class="nav-link " href="seasonal_demand_report.php"><i class="fa fa-calendar-alt me-2"></i> Seasonal Demand</a></li>
            <li class="sidebar-title">Account</li>
            <!-- <li class="nav-item mb-2"><a class="nav-link" href="my_profile.php"><i class="fa fa-user me-2"></i> My Profile</a></li> -->
            <li class="sidebar-title">Others</li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="fa fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content flex-grow-1 p-4">
        <h4 class="fw-bold text-success"><i class="fa fa-chart-pie me-2"></i> Inventory Value Report (COGS)</h4>
        <p class="text-muted">Calculated based on the latest purchase price (Cost of Goods Sold).</p>

        <div class="card bg-success text-white shadow-lg mb-4">
            <div class="card-body">
                <h5 class="card-title text-uppercase">Total Inventory Value</h5>
                <h1 class="card-text fw-bold">₱<?= number_format($total_inventory_value, 2) ?></h1>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body table-responsive">
                <table class="table table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Product Name / SKU</th>
                            <th class="text-center">Current Stock</th>
                            <th class="text-end">Latest Purchase Price (₱)</th>
                            <th class="text-end">Inventory Value (Stock x Price) (₱)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($report_items)): ?>
                            <?php foreach ($report_items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($item['sku'] ?? 'N/A') ?></small>
                                    </td>
                                    <td class="text-center"><?= number_format($item['stock']) ?> <?= htmlspecialchars($item['unit'] ?? '') ?></td>
                                    <td class="text-end">₱<?= number_format($item['latest_cost'], 2) ?></td>
                                    <td class="text-end fw-bold text-success">₱<?= number_format($item['item_value'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No active stock to value.</td></tr>
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