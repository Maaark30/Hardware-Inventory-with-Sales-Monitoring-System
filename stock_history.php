<?php
include 'project.php';
session_start();

// ✅ Restrict to Admin or Staff
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// ✅ Fetch Stock History with Product and Supplier details
$query = "
    SELECT 
        sh.stock_in_id,
        p.product_name,
        s.supplier_name,
        sh.quantity,
        sh.supplier_price,
        sh.total_cost,
        sh.item_desc,
        COALESCE(u.full_name, sh.stocked_by) AS full_name,
        sh.created_at
    FROM stock_history sh
    JOIN products p ON sh.product_id = p.product_id
    JOIN suppliers s ON sh.supplier_id = s.supplier_id
    LEFT JOIN users u ON sh.stocked_by = u.username
    ORDER BY sh.created_at DESC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock-In History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/admin1.css">
</head>
<body>
<div class="d-flex">

    <!-- ✅ Sidebar (matches your admin/staff design) -->
    <div class="sidebar flex-column p-0" id="sidebar">
        <div class="sidebar-logo text-center">
            <img src="images/logo.png" alt="Inventory Logo">
            <h5 class="mt-2 text-white">Inventory System</h5>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
               <li class="sidebar-title">Main</li>
               <li class="nav-item mb-2"><a class="nav-link " href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
               <li class="sidebar-title">Management</li>
               <li class="nav-item mb-2"><a class="nav-link " href="products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="categories.php"><i class="bi bi-tags me-2"></i> Categories</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="sales.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
              <li class="nav-item mb-2"><a class="nav-link" href="p_os.php"><i class="bi bi-receipt me-2"></i> Invoice</a></li>
               <li class="nav-item mb-2"><a class="nav-link " href="returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
                <li class="nav-item mb-2"><a class="nav-link active" href="stock_history.php"><i class="bi bi-clock-history me-2"></i> Stock-In History</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line me-2"></i> Reports</a></li>
                <li class="nav-item mb-2"><a class="nav-link " href="admin_stock_requests.php"><i class="bi bi-clipboard-check me-2"></i> Stock Requests</a></li>
               <li class="nav-item mb-2"><a class="nav-link" href="supplier.php"><i class="bi bi-truck me-2"></i> Suppliers</a></li>
               <li class="sidebar-title">Users</li>
               <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>
               <li class="sidebar-title">Settings</li>
               <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
               <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
    </div>

    <!-- ✅ Main Content -->
    <div class="content flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold text-primary"><i class="bi bi-clock-history me-2"></i> Stock-In History</h4>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body table-responsive">
                <?php if ($result->num_rows > 0): ?>
                <table class="table table-striped align-middle">
                    <thead class="table-primary text-center">
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th>Quantity</th>
                            <th>Supplier Price</th>
                            <th>Total Cost</th>
                            <th>Description</th>
                            <th>Stocked By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php $i=1; while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= htmlspecialchars($row['product_name']); ?></td>
                            <td><?= htmlspecialchars($row['supplier_name']); ?></td>
                            <td><?= $row['quantity']; ?></td>
                            <td>₱<?= number_format($row['supplier_price'], 2); ?></td>
                            <td>₱<?= number_format($row['total_cost'], 2); ?></td>
                            <td><?= htmlspecialchars($row['item_desc']); ?></td>
                            <td><?= htmlspecialchars($row['full_name'] ?: $row['stocked_by'] ?: '—'); ?></td>
                            <td><?= date("F j, Y g:ia", strtotime($row['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-center text-muted my-3">No stock-in records found yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
