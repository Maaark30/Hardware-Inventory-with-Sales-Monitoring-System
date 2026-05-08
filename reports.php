<?php
include 'project.php';
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'Admin';

/* ============================================================
   ✅ AJAX ENDPOINT FOR REACTIVE SLOW-MOVING ANALYTICS
   ============================================================ */
if (isset($_GET['ajax_slow_moving'])) {
    $timeframe = $_GET['timeframe'] ?? 'monthly';
    $interval = '1 MONTH';
    if ($timeframe === 'weekly') $interval = '7 DAY';
    elseif ($timeframe === 'yearly') $interval = '1 YEAR';

    $stmt = $conn->prepare("
        SELECT 
            p.product_name, p.brand, p.variation, p.unit,
            COALESCE(SUM(CASE WHEN sg.created_at >= DATE_SUB(NOW(), INTERVAL $interval) THEN s.quantity ELSE 0 END), 0) AS total_quantity
        FROM products p
        LEFT JOIN sales s ON p.product_id = s.product_id
        LEFT JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
        GROUP BY p.product_id, p.product_name, p.brand, p.variation, p.unit
        ORDER BY total_quantity ASC, p.product_name ASC
        LIMIT 10
    ");
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

$default_start = date('Y-m-d', strtotime('-30 days'));
$default_end   = date('Y-m-d');

$start_date = $_GET['start_date'] ?? $default_start;
$end_date   = $_GET['end_date'] ?? $default_end;

if (strtotime($start_date) > strtotime($end_date)) {
    $start_date = $default_start;
    $end_date   = $default_end;
}

$date_filter_clause = "sg.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";

// KPI: Revenue
$kpi_revenue_stmt = $conn->prepare("SELECT COALESCE(SUM(sp.total_amount),0) AS total_revenue FROM sale_payments sp JOIN sale_groups sg ON sp.sale_group_id=sg.sale_group_id WHERE {$date_filter_clause}");
$kpi_revenue_stmt->bind_param("ss", $start_date, $end_date);
$kpi_revenue_stmt->execute();
$total_revenue = (float)($kpi_revenue_stmt->get_result()->fetch_assoc()['total_revenue'] ?? 0);
$kpi_revenue_stmt->close();

// KPI: Transactions
$kpi_transactions_stmt = $conn->prepare("SELECT COUNT(DISTINCT sg.sale_group_id) AS total_transactions FROM sale_groups sg WHERE {$date_filter_clause}");
$kpi_transactions_stmt->bind_param("ss", $start_date, $end_date);
$kpi_transactions_stmt->execute();
$total_transactions = (int)($kpi_transactions_stmt->get_result()->fetch_assoc()['total_transactions'] ?? 0);
$kpi_transactions_stmt->close();

// KPI: Items Sold
$kpi_items_stmt = $conn->prepare("SELECT COALESCE(SUM(s.quantity),0) AS total_items FROM sales s JOIN sale_groups sg ON s.sale_group_id=sg.sale_group_id WHERE {$date_filter_clause}");
$kpi_items_stmt->bind_param("ss", $start_date, $end_date);
$kpi_items_stmt->execute();
$total_items_sold = (int)($kpi_items_stmt->get_result()->fetch_assoc()['total_items'] ?? 0);
$kpi_items_stmt->close();

// Daily Sales
$daily_sales_stmt = $conn->prepare("SELECT DATE(sg.created_at) AS sale_date, COALESCE(SUM(sp.total_amount),0) AS daily_total FROM sale_groups sg JOIN sale_payments sp ON sg.sale_group_id=sp.sale_group_id WHERE {$date_filter_clause} GROUP BY sale_date ORDER BY sale_date ASC");
$daily_sales_stmt->bind_param("ss", $start_date, $end_date);
$daily_sales_stmt->execute();
$daily_sales_data = $daily_sales_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$daily_sales_stmt->close();

// Top Products
$top_products_stmt = $conn->prepare("SELECT p.product_name, p.brand, p.variation, COALESCE(SUM(s.quantity),0) AS total_quantity FROM sales s JOIN products p ON s.product_id=p.product_id JOIN sale_groups sg ON s.sale_group_id=sg.sale_group_id WHERE {$date_filter_clause} GROUP BY p.product_id, p.product_name, p.brand, p.variation ORDER BY total_quantity DESC LIMIT 5");
$top_products_stmt->bind_param("ss", $start_date, $end_date);
$top_products_stmt->execute();
$top_products_data = $top_products_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$top_products_stmt->close();

// Gross Sales
$gross_sale_stmt = $conn->prepare("SELECT COALESCE(SUM(sp.total_amount),0) AS total_sale_revenue FROM sale_payments sp JOIN sale_groups sg ON sp.sale_group_id=sg.sale_group_id WHERE {$date_filter_clause}");
$gross_sale_stmt->bind_param("ss", $start_date, $end_date);
$gross_sale_stmt->execute();
$total_sale_revenue = (float)($gross_sale_stmt->get_result()->fetch_assoc()['total_sale_revenue'] ?? 0);
$gross_sale_stmt->close();

// Refunds (Accounts for Prorated Discounts)
$refund_stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(
            ri.quantity * (
                s.total_price - (
                    sg.discount_amount * s.total_price / 
                    NULLIF((SELECT SUM(s2.total_price) FROM sales s2 WHERE s2.sale_group_id = s.sale_group_id), 0)
                )
            ) / NULLIF(s.quantity, 0)
        ), 0) AS total_refunded_amount 
    FROM returns r 
    JOIN return_items ri ON r.return_id = ri.return_id 
    JOIN sales s ON r.original_sale_group_id = s.sale_group_id AND ri.product_id = s.product_id 
    JOIN sale_groups sg ON sg.sale_group_id = r.original_sale_group_id
    WHERE r.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
");
$refund_stmt->bind_param("ss", $start_date, $end_date);
$refund_stmt->execute();
$total_refunded = (float)($refund_stmt->get_result()->fetch_assoc()['total_refunded_amount'] ?? 0);
$refund_stmt->close();

$net_revenue = max(0, $total_sale_revenue - $total_refunded);
$revenue_breakdown_data = [
    ['label'=>'Gross Sales','amount'=>$total_sale_revenue],
    ['label'=>'Refunds','amount'=>$total_refunded],
    ['label'=>'Net Revenue','amount'=>$net_revenue]
];

// Top Stock Value
$top_stock_value_result = $conn->query("SELECT product_name, brand, variation, (stock*selling_price) AS stock_value FROM products ORDER BY stock_value DESC LIMIT 5");
$top_stock_value_data = $top_stock_value_result ? $top_stock_value_result->fetch_all(MYSQLI_ASSOC) : [];

// Sales by Category
$category_sales_stmt = $conn->prepare("SELECT c.category_name, COALESCE(SUM(s.total_price),0) AS category_total FROM sales s JOIN products p ON s.product_id=p.product_id JOIN categories c ON p.category_id=c.category_id JOIN sale_groups sg ON s.sale_group_id=sg.sale_group_id WHERE {$date_filter_clause} GROUP BY c.category_id, c.category_name ORDER BY category_total DESC");
$category_sales_stmt->bind_param("ss", $start_date, $end_date);
$category_sales_stmt->execute();
$sales_by_category_data = $category_sales_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$category_sales_stmt->close();

// Monthly Summary
$monthly_summary_result = $conn->query("SELECT DATE_FORMAT(sg.created_at,'%Y-%m') AS sale_month, COALESCE(SUM(sp.total_amount),0) AS monthly_total FROM sale_groups sg JOIN sale_payments sp ON sg.sale_group_id=sp.sale_group_id WHERE sg.created_at>=DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY sale_month ORDER BY sale_month ASC");
$monthly_summary_data = $monthly_summary_result ? $monthly_summary_result->fetch_all(MYSQLI_ASSOC) : [];

// Total Products
$total_products = $conn->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'];

// Slow Moving Products Cleanup & Dynamic Logic
$slow_timeframe = $_GET['slow_timeframe'] ?? 'monthly';
$slow_interval = '1 MONTH';
if ($slow_timeframe === 'weekly') $slow_interval = '7 DAY';
elseif ($slow_timeframe === 'yearly') $slow_interval = '1 YEAR';

$slow_products_stmt = $conn->prepare("
    SELECT 
        p.product_name, p.brand, p.variation, p.unit,
        COALESCE(SUM(CASE WHEN sg.created_at >= DATE_SUB(NOW(), INTERVAL $slow_interval) THEN s.quantity ELSE 0 END), 0) AS total_quantity
    FROM products p
    LEFT JOIN sales s ON p.product_id = s.product_id
    LEFT JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
    GROUP BY p.product_id, p.product_name, p.brand, p.variation, p.unit
    ORDER BY total_quantity ASC, p.product_name ASC
    LIMIT 10
");
$slow_products_stmt->execute();
$slow_products_data = $slow_products_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$slow_products_stmt->close();
$slow_products_json = json_encode($slow_products_data);

$daily_sales_json       = json_encode($daily_sales_data);
$top_products_json      = json_encode($top_products_data);
$revenue_breakdown_json = json_encode($revenue_breakdown_data);
$top_stock_value_json   = json_encode($top_stock_value_data);
$sales_by_category_json = json_encode($sales_by_category_data);
$monthly_summary_json   = json_encode($monthly_summary_data);

$conn->close();
?>
<?php if (!isset($_GET['print'])): ?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports — Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="css/admin1.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<style>
:root {
            --bg:           #eef1f8;
            --surface:      #ffffff;
            --surface-2:    #f7f9fc;
            --border:       #e2e8f0;
            --border-light: #edf2f7;
            --ink:          #0f172a;
            --ink-2:        #334155;
            --muted:        #64748b;
            --faint:        #94a3b8;
            --blue:         #2563eb;
            --blue-dk:      #1d4ed8;
            --blue-lt:      #eff6ff;
            --blue-mid:     #dbeafe;
            --green:        #059669;
            --green-lt:     #ecfdf5;
            --amber:        #d97706;
            --amber-lt:     #fffbeb;
            --red:          #dc2626;
            --red-lt:       #fef2f2;
            --violet:       #7c3aed;
            --violet-lt:    #f5f3ff;
            --r:            12px;
            --r-sm:         8px;
            --r-lg:         18px;
            --sh-xs:        0 1px 3px rgba(0,0,0,.05);
            --sh-sm:        0 2px 8px rgba(0,0,0,.06);
            --sh:           0 4px 20px rgba(0,0,0,.08);
            --sh-lg:        0 8px 32px rgba(0,0,0,.1);
            --font:         'Plus Jakarta Sans', sans-serif;
            --mono:         'JetBrains Mono', monospace;
        }
        *,*::before,*::after{box-sizing:border-box;}
        body { font-family:var(--font); background:var(--bg); color:var(--ink); font-size:14px; }
        .content { background:var(--bg); min-height:100vh; }
        .main-wrap { padding:28px 28px 64px; }
        .dropdown-toggle::after { display:none; }

/* ── Page header ── */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.page-header-left h4 {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0;
    letter-spacing: -0.3px;
}
.page-header-left p {
    font-size: 0.83rem;
    color: var(--text-muted);
    margin: 3px 0 0;
}
.page-header-icon {
    width: 46px; height: 46px;
    border-radius: 15px;
    background: rgba(37,99,235,0.12);
    color: #2563eb;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
    margin-right: 0.85rem;
    flex-shrink: 0;
}

/* ── Filter card ── */
.filter-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 20px 24px;
  margin-bottom: 24px;
}

.filter-card .filter-title {
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: #6b7280;
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  gap: 7px;
}

.filter-row {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  flex-wrap: wrap;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
  flex: 1;
  min-width: 160px;
}

.filter-label {
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #9ca3af;
}

.filter-input {
  padding: 9px 13px;
  border: 1px solid #e5e7eb;
  border-radius: 9px;
  font-size: 14px;
  font-family: 'DM Sans', sans-serif;
  color: #111827;
  outline: none;
  background: #f9fafb;
  transition: border-color 0.2s, background 0.2s;
}

.filter-input:focus {
  border-color: #2563eb;
  background: #fff;
}

.btn-filter {
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 9px;
  padding: 9px 20px;
  font-size: 13.5px;
  font-weight: 500;
  font-family: 'DM Sans', sans-serif;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 7px;
  white-space: nowrap;
  transition: background 0.2s;
}

.btn-filter:hover { background: #1d4ed8; }

.btn-reset {
  background: #fff;
  color: #374151;
  border: 1px solid #e5e7eb;
  border-radius: 9px;
  padding: 9px 18px;
  font-size: 13.5px;
  font-weight: 500;
  font-family: 'DM Sans', sans-serif;
  cursor: pointer;
  white-space: nowrap;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 7px;
  transition: background 0.2s;
}

.btn-reset:hover { background: #f9fafb; color: #374151; }

/* ── Print selector card ── */
.print-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 20px 24px;
  margin-bottom: 24px;
}

.print-card .print-title {
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: #6b7280;
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  gap: 7px;
}

.check-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 10px;
  margin-bottom: 16px;
}

.check-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 9px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
  user-select: none;
}

.check-item:hover { background: #f9fafb; border-color: #d1d5db; }
.check-item.checked { background: #eff6ff; border-color: #bfdbfe; }

.check-item input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: #2563eb;
  cursor: pointer;
  flex-shrink: 0;
}

.check-item label {
  font-size: 13.5px;
  color: #374151;
  cursor: pointer;
  margin: 0;
}

.print-actions {
  display: flex;
  justify-content: flex-end;
}

.btn-print {
  background: #16a34a;
  color: #fff;
  border: none;
  border-radius: 9px;
  padding: 9px 22px;
  font-size: 13.5px;
  font-weight: 500;
  font-family: 'DM Sans', sans-serif;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 7px;
  transition: background 0.2s;
}

.btn-print:hover { background: #15803d; }

/* ── KPI Cards ── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 28px;
}

.kpi-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 20px 22px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.kpi-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
}

.kpi-icon.blue   { background: #eff6ff; color: #2563eb; }
.kpi-icon.green  { background: #f0fdf4; color: #16a34a; }
.kpi-icon.amber  { background: #fffbeb; color: #d97706; }

.kpi-label {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
  margin-bottom: 4px;
}

.kpi-value {
  font-size: 24px;
  font-weight: 600;
  color: #111827;
  line-height: 1;
  font-family: 'DM Mono', monospace;
  letter-spacing: -0.5px;
}

/* ── Section heading ── */
.section-heading {
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: #6b7280;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-heading::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #e5e7eb;
}

/* ── Chart cards ── */
.chart-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 22px 22px 18px;
}

.chart-card-title {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.chart-card-sub {
  font-size: 12px;
  color: #9ca3af;
  margin-bottom: 16px;
}

.chart-icon {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  flex-shrink: 0;
}

.chart-icon.green  { background: #f0fdf4; color: #16a34a; }
.chart-icon.blue   { background: #eff6ff; color: #2563eb; }
.chart-icon.amber  { background: #fffbeb; color: #d97706; }
.chart-icon.red    { background: #fef2f2; color: #dc2626; }
.chart-icon.purple { background: #f5f3ff; color: #7c3aed; }
.chart-icon.gray   { background: #f9fafb; color: #6b7280; }

@media print {
  .sidebar, .btn, #sidebarToggle, #printReportBtn { display: none !important; }
  .content { margin-left: 0 !important; width: 100% !important; }
  .chart-card { box-shadow: none !important; border: 1px solid #ddd !important; }
  canvas { max-width: 100% !important; height: auto !important; }
}
</style>
</head>
<body>
<div class="d-flex">

  <!-- Sidebar -->
  <div class="sidebar flex-column p-0" id="sidebar">
    <div class="sidebar-logo text-center">
      <img src="images/logo.png" alt="Inventory Logo">
      <h5 class="mt-2 text-white">Inventory System</h5>
    </div>
    <hr class="text-white">
    <ul class="nav flex-column">
      <li class="sidebar-title">Main</li>
      <li class="nav-item mb-2"><a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
      <li class="sidebar-title">Management</li>
      <li class="nav-item mb-2"><a class="nav-link" href="products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="categories.php"><i class="bi bi-tags me-2"></i> Categories</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="sales.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="p_os.php"><i class="bi bi-receipt me-2"></i> Invoice</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="stock_in_batches.php"><i class="bi bi-box-arrow-down me-2"></i> Stock-In Records</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="stock_out_history.php"><i class="bi bi-box-arrow-up me-2"></i> Stock-Out Records</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="admin_seasonal_report.php"><i class="bi bi-calendar-range me-2"></i> Seasonal Analysis</a></li>
      <li class="nav-item mb-2"><a class="nav-link active" href="reports.php"><i class="bi bi-bar-chart-line me-2"></i> Reports</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="supplier.php"><i class="bi bi-truck me-2"></i> Suppliers</a></li>
      <li class="sidebar-title">Users</li>
      <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>
      <li class="sidebar-title">Settings</li>
      <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
      <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
    </ul>
  </div>

  <!-- Content -->
  <div class="content flex-grow-1 p-4">

    <!-- Page header -->
    <div class="page-header">
        <div style="display:flex; align-items:center;">
            <div class="page-header-icon">
                <i class="bi bi-bar-chart-line"></i>
            </div>
            <div class="page-header-left">
                <h4>Sales &amp; Inventory Reports</h4>
                <p>Analytics for <?= date('M j, Y', strtotime($start_date)) ?> — <?= date('M j, Y', strtotime($end_date)) ?></p>
            </div>
        </div>
        <button class="btn btn-primary d-lg-none" id="sidebarToggle" style="border-radius:9px;">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
      <div class="filter-title"><i class="bi bi-funnel-fill"></i> Date Range</div>
      <form method="GET">
        <div class="filter-row">
          <div class="filter-field">
            <label class="filter-label">Start Date</label>
            <input type="date" class="filter-input" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
          </div>
          <div class="filter-field">
            <label class="filter-label">End Date</label>
            <input type="date" class="filter-input" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
          </div>
          <button type="submit" class="btn-filter"><i class="bi bi-funnel"></i> Apply</button>
          <a href="reports.php" class="btn-reset"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
        </div>
      </form>
    </div>

    <!-- Print selector -->
    <div class="print-card">
      <div class="print-title"><i class="bi bi-printer"></i> Print Options</div>
      <div class="check-grid">
        <label class="check-item checked">
          <input type="checkbox" id="printSummary" value="summary" checked>
          <span>Summary (Revenue, Transactions, Items)</span>
        </label>
        <label class="check-item checked">
          <input type="checkbox" id="printDaily" value="daily" checked>
          <span>Daily Sales Summary</span>
        </label>
        <label class="check-item checked">
          <input type="checkbox" id="printMonthly" value="monthly" checked>
          <span>Monthly Sales Summary</span>
        </label>
        <label class="check-item checked">
          <input type="checkbox" id="printBestSelling" value="bestselling" checked>
          <span>Best-Selling Products</span>
        </label>
        <label class="check-item checked">
          <input type="checkbox" id="printSlowMoving" value="slowmoving" checked>
          <span>Slow-Moving Products</span>
        </label>
      </div>
      <div class="print-actions">
        <button id="printReportBtn" class="btn-print"><i class="bi bi-printer-fill"></i> Print Report</button>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="section-heading"><i class="bi bi-grid-1x2"></i> Summary</div>
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-icon blue"><i class="bi bi-currency-dollar"></i></div>
        <div>
          <div class="kpi-label">Total Revenue</div>
          <div class="kpi-value">₱<?= number_format($total_revenue, 2) ?></div>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon green"><i class="bi bi-receipt"></i></div>
        <div>
          <div class="kpi-label">Total Transactions</div>
          <div class="kpi-value"><?= number_format($total_transactions) ?></div>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon amber"><i class="bi bi-box-seam"></i></div>
        <div>
          <div class="kpi-label">Items Sold</div>
          <div class="kpi-value"><?= number_format($total_items_sold) ?></div>
        </div>
      </div>
    </div>

    <!-- Charts -->
    <div class="section-heading"><i class="bi bi-bar-chart-line"></i> Visual Reports</div>
    <div class="row g-4">

      <div class="col-lg-7">
        <div class="chart-card">
          <div class="chart-card-title">
            <div class="chart-icon green"><i class="bi bi-graph-up"></i></div>
            Sales Trend
          </div>
          <div class="chart-card-sub"><?= date('M j, Y', strtotime($start_date)) ?> — <?= date('M j, Y', strtotime($end_date)) ?></div>
          <div style="height:320px;"><canvas id="dailySalesTrendChart"></canvas></div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="chart-card">
          <div class="chart-card-title">
            <div class="chart-icon blue"><i class="bi bi-cash-stack"></i></div>
            Net Revenue Breakdown
          </div>
          <div class="chart-card-sub">Gross sales vs refunds vs net</div>
          <div style="height:320px;"><canvas id="paymentChart"></canvas></div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="chart-card">
          <div class="chart-card-title">
            <div class="chart-icon amber"><i class="bi bi-trophy"></i></div>
            Top 5 Best-Selling Products
          </div>
          <div class="chart-card-sub">By quantity sold in selected period</div>
          <div style="height:300px;"><canvas id="topProductsChart"></canvas></div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="chart-card">
          <div class="chart-card-title">
            <div class="chart-icon red"><i class="bi bi-box-seam"></i></div>
            Top 5 Valuable Inventory Items
          </div>
          <div class="chart-card-sub">Current snapshot (stock × price)</div>
          <div style="height:300px;"><canvas id="stockValueChart"></canvas></div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="chart-card">
          <div class="chart-card-title">
            <div class="chart-icon blue"><i class="bi bi-calendar-month"></i></div>
            Monthly Revenue
          </div>
          <div class="chart-card-sub">Last 12 months</div>
          <div style="height:300px;"><canvas id="monthlySummaryChart"></canvas></div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="chart-card">
          <div class="chart-card-title">
            <div class="chart-icon purple"><i class="bi bi-pie-chart-fill"></i></div>
            Sales by Category
          </div>
          <div class="chart-card-sub">Revenue breakdown by product category</div>
          <div style="height:300px;"><canvas id="salesByCategoryChart"></canvas></div>
        </div>
      </div>

      <!-- ── Reactive Slow-Moving Inventory Card ── -->
      <div class="col-lg-12">
        <div class="chart-card position-relative" id="slowMovingSection">
          <!-- Loading Overlay -->
          <div class="loading-overlay d-none" id="slowLoading">
             <div class="spinner-border text-primary" role="status"></div>
          </div>

          <div class="chart-card-title d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
              <div class="chart-icon gray"><i class="bi bi-graph-down"></i></div>
              <div>
                Slow-Moving Products <span class="badge bg-light text-muted border ms-1" id="slowTimeLabel"><?= ucfirst($slow_timeframe) ?></span>
              </div>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary slow-btn" data-time="weekly">Weekly</button>
                <button type="button" class="btn btn-outline-secondary slow-btn active" data-time="monthly">Monthly</button>
                <button type="button" class="btn btn-outline-secondary slow-btn" data-time="yearly">Yearly</button>
            </div>
          </div>
          <div class="chart-card-sub">Items with lowest sales quantity in selected period</div>
          
          <div class="row g-4">
            <div class="col-12">
              <div class="table-responsive" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-light); border-radius: 12px;">
                <table class="table table-hover mb-0" style="font-size: 14px;">
                  <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                    <tr>
                      <th class="ps-4 border-0">Product Name</th>
                      <th class="border-0">Brand</th>
                      <th class="border-0 text-center">Variation</th>
                      <th class="border-0 text-center">Unit</th>
                      <th class="text-end pe-4 border-0">Units Sold</th>
                    </tr>
                  </thead>
                  <tbody id="slowTableBody">
                    <?php if (empty($slow_products_data)): ?>
                      <tr><td colspan="5" class="text-center py-4 text-muted">No data available</td></tr>
                    <?php else: ?>
                      <?php foreach ($slow_products_data as $p): ?>
                        <tr>
                          <td class="ps-4 fw-600"><?= htmlspecialchars($p['product_name']) ?></td>
                          <td><span class="text-muted small"><?= htmlspecialchars($p['brand'] ?? '') ?></span></td>
                          <td class="text-center text-muted small"><?= htmlspecialchars($p['variation'] ?? '') ?></td>
                          <td class="text-center text-muted small"><?= htmlspecialchars($p['unit'] ?? '') ?></td>
                          <td class="text-end pe-4">
                               <?= formatQty($p['total_quantity']) ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /row -->
  </div><!-- /content -->
</div><!-- /d-flex -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click', function() {
  document.getElementById('sidebar').classList.toggle('show');
});

// Check-item toggle styling
document.querySelectorAll('.check-item input[type="checkbox"]').forEach(function(cb) {
  cb.addEventListener('change', function() {
    this.closest('.check-item').classList.toggle('checked', this.checked);
  });
});

// Print button
let currentSlowTimeframe = '<?= $slow_timeframe ?>';

document.getElementById('printReportBtn').addEventListener('click', function() {
  const selected = [...document.querySelectorAll('.check-item input:checked')].map(c => c.value);
  if (!selected.length) { alert('Please select at least one section to print.'); return; }
  window.open('reports.php?print=true&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&sections=' + selected.join(',') + '&slow_timeframe=' + currentSlowTimeframe, '_blank');
});

// Chart data
const dailySalesData       = <?= $daily_sales_json ?>;
const topProductsData      = <?= $top_products_json ?>;
const revenueBreakdownData = <?= $revenue_breakdown_json ?>;
const topStockValueData    = <?= $top_stock_value_json ?>;
const salesByCategoryData  = <?= $sales_by_category_json ?>;
const monthlySummaryData   = <?= $monthly_summary_json ?>;

const palette = ['#2563eb','#16a34a','#d97706','#dc2626','#7c3aed','#0891b2','#059669','#b45309'];
const paletteAlpha = (i, a=0.85) => palette[i % palette.length].replace('#','') && hexToRgba(palette[i % palette.length], a);

function hexToRgba(hex, alpha) {
  const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
  return `rgba(${r},${g},${b},${alpha})`;
}

function getColors(n, a=0.85) { return Array.from({length:n},(_,i)=>hexToRgba(palette[i%palette.length],a)); }

const fmtCurrency = v => '₱' + parseFloat(v).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});

const baseOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } }
};

// Daily Sales Trend
if (dailySalesData.length) {
  new Chart(document.getElementById('dailySalesTrendChart'), {
    type: 'line',
    data: {
      labels: dailySalesData.map(d => d.sale_date),
      datasets: [{
        data: dailySalesData.map(d => parseFloat(d.daily_total)),
        borderColor: '#16a34a',
        backgroundColor: 'rgba(22,163,74,0.08)',
        borderWidth: 2.5,
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#16a34a',
        pointRadius: 3,
        pointHoverRadius: 6
      }]
    },
    options: { ...baseOpts,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => fmtCurrency(c.parsed.y) } } },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { callback: v => fmtCurrency(v), font: { family: 'DM Mono', size: 11 } } },
        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
      }
    }
  });
}

// Revenue Breakdown Doughnut
if (revenueBreakdownData.length) {
  new Chart(document.getElementById('paymentChart'), {
    type: 'doughnut',
    data: {
      labels: revenueBreakdownData.map(d => d.label),
      datasets: [{ data: revenueBreakdownData.map(d => parseFloat(d.amount)), backgroundColor: ['rgba(22,163,74,0.9)','rgba(220,53,69,0.9)','rgba(37,99,235,0.9)'], hoverOffset: 12, borderWidth: 2, borderColor: '#fff' }]
    },
    options: { ...baseOpts,
      plugins: { legend: { display: true, position: 'bottom', labels: { padding: 16, font: { family: 'DM Sans', size: 12 } } }, tooltip: { callbacks: { label: c => c.label + ': ' + fmtCurrency(c.parsed) } } },
      cutout: '65%'
    }
  });
}

// Top Products
if (topProductsData.length) {
  const labels = topProductsData.map(d => {
    let label = d.product_name;
    if (d.brand || d.variation) {
      label += ' (' + (d.brand || '') + (d.variation && d.brand ? ' - ' : '') + (d.variation || '') + ')';
    }
    return label;
  }).reverse();
  new Chart(document.getElementById('topProductsChart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{ data: topProductsData.map(d => parseInt(d.total_quantity)).reverse(), backgroundColor: getColors(labels.length), borderRadius: 8, borderSkipped: false, barThickness: 22 }]
    },
    options: { ...baseOpts,
      indexAxis: 'y',
      plugins: { legend: { display: false }, datalabels: { anchor: 'end', align: 'right', color: '#6b7280', font: { family: 'DM Mono', size: 11, weight: '500' } } },
      scales: {
        x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 } } },
        y: { grid: { display: false }, ticks: { font: { size: 12 } } }
      }
    },
    plugins: [ChartDataLabels]
  });
}

// Stock Value
if (topStockValueData.length) {
  const stockLabels = topStockValueData.map(d => {
    let label = d.product_name;
    if (d.brand || d.variation) {
      label += ' (' + (d.brand || '') + (d.variation && d.brand ? ' - ' : '') + (d.variation || '') + ')';
    }
    return label;
  });
  new Chart(document.getElementById('stockValueChart'), {
    type: 'bar',
    data: {
      labels: stockLabels,
      datasets: [{ data: topStockValueData.map(d => parseFloat(d.stock_value)), backgroundColor: getColors(topStockValueData.length, 0.8), borderRadius: 7, borderWidth: 0 }]
    },
    options: { ...baseOpts,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => fmtCurrency(c.parsed.y) } } },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { callback: v => fmtCurrency(v), font: { family: 'DM Mono', size: 11 } } },
        x: { grid: { display: false }, ticks: { font: { size: 11 }, maxRotation: 30 } }
      }
    }
  });
}

// Monthly Summary
if (monthlySummaryData.length) {
  new Chart(document.getElementById('monthlySummaryChart'), {
    type: 'bar',
    data: {
      labels: monthlySummaryData.map(d => { const [y,m] = d.sale_month.split('-'); return new Date(y,m-1,1).toLocaleString('default',{month:'short',year:'2-digit'}); }),
      datasets: [{ data: monthlySummaryData.map(d => parseFloat(d.monthly_total)), backgroundColor: getColors(monthlySummaryData.length, 0.8), borderRadius: 7, borderWidth: 0 }]
    },
    options: { ...baseOpts,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => fmtCurrency(c.parsed.y) } } },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { callback: v => fmtCurrency(v), font: { family: 'DM Mono', size: 11 } } },
        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
      }
    }
  });
}

// Sales by Category
if (salesByCategoryData.length) {
  new Chart(document.getElementById('salesByCategoryChart'), {
    type: 'doughnut',
    data: {
      labels: salesByCategoryData.map(d => d.category_name),
      datasets: [{ data: salesByCategoryData.map(d => parseFloat(d.category_total)), backgroundColor: getColors(salesByCategoryData.length, 0.88), hoverOffset: 12, borderWidth: 2, borderColor: '#fff' }]
    },
    options: { ...baseOpts,
      plugins: { legend: { display: true, position: 'bottom', labels: { padding: 14, font: { family: 'DM Sans', size: 12 } } }, tooltip: { callbacks: { label: c => c.label + ': ' + fmtCurrency(c.parsed) } } },
      cutout: '60%'
    }
  });
}

function updateSlowUI(data, time) {
  currentSlowTimeframe = time;
  slowTimeLabel.textContent = time.charAt(0).toUpperCase() + time.slice(1);

  // Update Table
  let html = '';
  if (data.length === 0) {
    html = '<tr><td colspan="5" class="text-center py-4 text-muted">No data for this period</td></tr>';
  } else {
    data.forEach(p => {
      const badgeColor = p.total_quantity == 0 ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary';
      const v = parseFloat(p.total_quantity);
      const qtyFormatted = (v % 1 === 0) ? parseInt(v) : parseFloat(v.toFixed(4));
      html += `
        <tr>
          <td class="ps-4 fw-600">${p.product_name}</td>
          <td><span class="text-muted small">${p.brand || ''}</span></td>
          <td class="text-center text-muted small">${p.variation || '-'}</td>
          <td class="text-center text-muted small">${p.unit || '-'}</td>
          <td class="text-end pe-4">
             <span class="badge ${badgeColor}" style="font-size: 13px; padding: 6px 12px;">${qtyFormatted.toLocaleString()}</span>
          </td>
        </tr>
      `;
    });
  }
  slowTableBody.innerHTML = html;
}

// Initial Load (already handled by PHP in the new full-width table layout)

// Click Handlers
document.querySelectorAll('.slow-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const time = this.getAttribute('data-time');
    
    // UI Update
    document.querySelectorAll('.slow-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    
    slowLoading.classList.remove('d-none');

    // Fetch Data
    fetch(`reports.php?ajax_slow_moving=1&timeframe=${time}`)
      .then(res => res.json())
      .then(data => {
        updateSlowUI(data, time);
      })
      .catch(err => console.error('Slow Move Error:', err))
      .finally(() => {
        slowLoading.classList.add('d-none');
      });
  });
});
</script>
</body>
</html>

<?php endif; ?>
<?php if (isset($_GET['print'])):
  $selected_sections = isset($_GET['sections']) ? explode(',', $_GET['sections']) : [];
  $show_summary     = in_array('summary',     $selected_sections);
  $show_daily       = in_array('daily',       $selected_sections);
  $show_monthly     = in_array('monthly',     $selected_sections);
  $show_bestselling = in_array('bestselling', $selected_sections);
  $show_slowmoving  = in_array('slowmoving',  $selected_sections);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Report — K&amp;J B Hardware</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --primary: #1e40af;
    --text-main: #111827;
    --text-muted: #6b7280;
    --border: #e5e7eb;
    --bg-light: #f9fafb;
  }
  
  body { 
    font-family: 'Inter', -apple-system, sans-serif; 
    background: #fff; 
    color: var(--text-main); 
    margin: 40px; 
    line-height: 1.5;
    -webkit-print-color-adjust: exact;
  }
  
  .report-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: flex-start; 
    border-bottom: 3px solid var(--primary); 
    padding-bottom: 20px; 
    margin-bottom: 30px; 
  }
  
  .header-left { display: flex; align-items: center; gap: 18px; }
  .header-left img { width: 70px; height: 70px; object-fit: contain; }
  
  .header-text h1 { 
    margin: 0; 
    font-size: 22px; 
    font-weight: 800; 
    color: #000;
    letter-spacing: -0.5px;
  }
  .header-text p { 
    margin: 4px 0 0; 
    font-size: 14px; 
    color: var(--text-muted); 
    font-weight: 500;
  }
  
  .header-right { 
    text-align: right; 
    font-size: 12px; 
    color: var(--text-muted);
  }
  .header-right strong { color: var(--text-main); display: block; margin-bottom: 2px; }
  
  h2 { 
    font-size: 16px; 
    font-weight: 700; 
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--primary);
    border-bottom: 1px solid var(--border);
    padding-bottom: 8px;
    margin: 35px 0 15px; 
    display: flex;
    align-items: center;
  }
  
  h3 { 
    font-size: 14px; 
    font-weight: 600; 
    margin: 20px 0 10px; 
    color: #374151; 
  }
  
  .summary-cards { 
    display: flex; 
    gap: 15px; 
    margin-bottom: 25px; 
  }
  .summary-card { 
    flex: 1; 
    background: var(--bg-light);
    border: 1px solid var(--border); 
    border-radius: 12px; 
    padding: 16px; 
    text-align: center; 
  }
  .summary-card h4 { 
    font-size: 11px; 
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted); 
    margin: 0 0 6px; 
    font-weight: 600;
  }
  .summary-card p { 
    font-size: 22px; 
    font-weight: 700; 
    margin: 0; 
    color: var(--text-main);
    font-family: 'Inter', sans-serif;
  }
  
  table { 
    width: 100%; 
    border-collapse: collapse; 
    font-size: 13px; 
    margin-bottom: 20px;
  }
  th, td { 
    border: 1px solid var(--border); 
    padding: 10px 14px; 
    text-align: left; 
  }
  th { 
    background: var(--bg-light); 
    font-size: 11px; 
    font-weight: 700; 
    text-transform: uppercase; 
    letter-spacing: 0.06em; 
    color: var(--text-muted); 
  }
  
  tr:nth-child(even) { background-color: #fafafa; }
  
  .footer { 
    text-align: center; 
    margin-top: 50px; 
    border-top: 1px solid var(--border); 
    padding-top: 15px; 
    font-size: 12px; 
    color: var(--text-muted); 
  }
  
  @media print { 
    body { margin: 20mm 15mm; } 
    .no-print { display: none; }
  }
</style>
</head>
<body>
<div class="report-header">
  <div class="header-left">
    <img src="images/logo.png" alt="Logo">
    <div class="header-text">
      <h1>K&amp;J B Hardware &amp; Construction Supplies</h1>
      <p>Sales &amp; Inventory Report — <?= date('M j, Y', strtotime($start_date)) ?> to <?= date('M j, Y', strtotime($end_date)) ?></p>
    </div>
  </div>
  <div class="header-right">
    <div>Generated: <?= date('M j, Y g:i A') ?></div>
    <div>Total products: <?= $total_products ?></div>
  </div>
</div>

<?php if ($show_summary): ?>
<h2>Summary</h2>
<div class="summary-cards">
  <div class="summary-card"><h4>Total Revenue</h4><p>₱<?= number_format($total_revenue,2) ?></p></div>
  <div class="summary-card"><h4>Total Transactions</h4><p><?= number_format($total_transactions) ?></p></div>
  <div class="summary-card"><h4>Items Sold</h4><p><?= formatQty($total_items_sold) ?></p></div>
</div>
<?php endif; ?>

<?php if ($show_daily || $show_monthly): ?>
<h2>Sales Summary</h2>
<?php if ($show_daily): ?>
<h3>Daily Sales</h3>
<table><thead><tr><th>Date</th><th>Revenue</th></tr></thead><tbody>
<?php foreach ($daily_sales_data as $s): ?>
<tr><td><?= htmlspecialchars($s['sale_date']) ?></td><td>₱<?= number_format($s['daily_total'],2) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<?php if ($show_monthly): ?>
<h3 style="margin-top:16px;">Monthly Revenue</h3>
<table><thead><tr><th>Month</th><th>Revenue</th></tr></thead><tbody>
<?php foreach ($monthly_summary_data as $m): ?>
<tr><td><?= htmlspecialchars($m['sale_month']) ?></td><td>₱<?= number_format($m['monthly_total'],2) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<?php endif; ?>

<?php if ($show_bestselling || $show_slowmoving): ?>
<h2>Itemized Product Report</h2>
<?php if ($show_bestselling): ?>
<h3>Best-Selling Products (Top 5)</h3>
<table>
  <thead>
    <tr>
      <th>Product</th>
      <th>Brand</th>
      <th>Variation</th>
      <th>Qty Sold</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($top_products_data as $p): ?>
<tr>
  <td><?= htmlspecialchars($p['product_name']) ?></td>
  <td><?= htmlspecialchars($p['brand'] ?? '') ?></td>
  <td><?= htmlspecialchars($p['variation'] ?? '-') ?></td>
  <td><?= formatQty($p['total_quantity']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<?php if ($show_slowmoving): ?>
<h2 style="margin-top:24px; border-left: 4px solid #64748b; padding-left: 10px;">Slow-Moving Products (<?= ucfirst($_GET['slow_timeframe'] ?? 'monthly') ?>)</h2>
<table>
  <thead>
    <tr>
      <th>Product</th>
      <th>Brand</th>
      <th>Variation</th>
      <th>Qty Sold</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($slow_products_data as $p): ?>
<tr>
  <td><?= htmlspecialchars($p['product_name']) ?></td>
  <td><?= htmlspecialchars($p['brand'] ?? '') ?></td>
  <td><?= htmlspecialchars($p['variation'] ?? '-') ?></td>
  <td><?= formatQty($p['total_quantity']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<?php endif; ?>

<div class="footer">Generated by Inventory System — <?= date('Y-m-d H:i:s') ?></div>
<script>window.onload=function(){window.print();};</script>
</body>
</html>
<?php endif; ?>