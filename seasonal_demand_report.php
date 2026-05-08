<?php
include 'project.php';
session_start();

// Access control (Restrict access to staff/admin roles)
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Determine the month to analyze (default to current month)
$selected_month = (int)($_GET['month'] ?? date('n')); 
if (!in_array($selected_month, array_keys($months))) {
    $selected_month = date('n');
}
$month_name = $months[$selected_month];

// Find Top 5 Products Sold in the Selected Month Across All Years
$query = "
    SELECT 
        p.product_name, 
        SUM(s.quantity) AS total_sold 
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
    WHERE MONTH(sg.created_at) = ?
    GROUP BY p.product_name
    ORDER BY total_sold DESC
    LIMIT 10 
"; // Limit set to 10 for a more useful report

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $selected_month);
$stmt->execute();
$seasonal_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Seasonal Demand Report</title>
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
            <li class="nav-item mb-2"><a class="nav-link  " href="staff_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
 <li class="nav-item mb-2"><a class="nav-link " href="staff_products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
            <li class="nav-item mb-2"><a class="nav-link " href="purchased_history.php"><i class="fa fa-history me-2"></i> Sale Records</a></li>
                       <li class="nav-item mb-2"><a class="nav-link " href="staff_returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
            <li class="nav-item mb-2"><a class="nav-link " href="staff_supplier.php"><i class="fa fa-truck me-2"></i> Suppliers</a></li>
            <li class="nav-item mb-2"><a class="nav-link active" href="seasonal_demand_report.php"><i class="fa fa-calendar-alt me-2"></i> Seasonal Demand</a></li>
            <li class="sidebar-title">Account</li>
            <!-- <li class="nav-item mb-2"><a class="nav-link" href="my_profile.php"><i class="fa fa-user me-2"></i> My Profile</a></li> -->
            <li class="sidebar-title">Others</li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="fa fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content flex-grow-1 p-4">
        <h4 class="fw-bold text-info"><i class="fa fa-calendar-alt me-2"></i> Seasonal Demand Analysis</h4>
        <p class="text-muted">Analyze the top-selling products for specific times of the year.</p>

        <div class="card shadow-sm p-4 mb-4">
            <form method="GET" action="seasonal_demand_report.php" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="month" class="form-label">Select Month to Analyze</label>
                    <select class="form-select" name="month" id="month" required>
                        <?php foreach ($months as $num => $name): ?>
                            <option value="<?= $num ?>" <?= ($selected_month == $num) ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-info w-100 text-white"><i class="fa fa-chart-bar me-2"></i> Show Demand</button>
                </div>
            </form>
        </div>

        <div class="card shadow-lg">
            <div class="card-header bg-info text-white fw-bold">
                Top Sellers for <?= htmlspecialchars($month_name) ?> (All Years)
            </div>
            <div class="card-body">
                <?php if (!empty($seasonal_data)): ?>
                    <table class="table table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Product Name</th>
                                <th class="text-end">Total Units Sold in <?= htmlspecialchars($month_name) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($seasonal_data as $index => $item): ?>
                                <tr>
                                    <td class="fw-bold"><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td class="text-end fw-bold text-primary"><?= number_format($item['total_sold']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-warning text-center">No sales recorded for <?= htmlspecialchars($month_name) ?> yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>