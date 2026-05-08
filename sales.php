<?php
include 'project.php';
session_start();

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['username'];
$current_role = $_SESSION['role'];

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function money($value): string {
    return '₱' . number_format((float)$value, 2);
}
function buildSalesPaginationUrl(int $page, array $get_params): string {
    unset($get_params['page']);
    $get_params['page'] = $page;
    return 'sales.php?' . http_build_query($get_params);
}

$limit  = 20;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$where_clauses = [];
$params = [];
$types  = '';

$start_date          = trim($_GET['start_date'] ?? '');
$end_date            = trim($_GET['end_date'] ?? '');
$customer_filter     = '';
$payment_type_filter = trim($_GET['payment_type'] ?? '');
$receipt_filter      = trim($_GET['receipt_no'] ?? '');

if (!empty($start_date) && !empty($end_date) && $end_date < $start_date) {
    [$start_date, $end_date] = [$end_date, $start_date];
}

if ($start_date !== '') { $where_clauses[] = "sg.created_at >= ?"; $params[] = $start_date . ' 00:00:00'; $types .= 's'; }
if ($end_date   !== '') { $where_clauses[] = "sg.created_at <= ?"; $params[] = $end_date   . ' 23:59:59'; $types .= 's'; }

if (!empty($_GET['customer_name'])) {
    $customer_filter = trim($_GET['customer_name']);
    /* Only match rows where customer_name is set (not walk-ins) */
    $where_clauses[] = "sg.customer_name IS NOT NULL AND sg.customer_name <> '' AND sg.customer_name LIKE ?";
    $params[] = "%" . $customer_filter . "%";
    $types .= 's';
}

if ($payment_type_filter !== '') { $where_clauses[] = "sp.payment_type = ?"; $params[] = $payment_type_filter; $types .= 's'; }
if ($receipt_filter !== '' && is_numeric($receipt_filter)) { $where_clauses[] = "sg.sale_group_id = ?"; $params[] = (int)$receipt_filter; $types .= 'i'; }

$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : '';

$summary_sql = "SELECT COUNT(sg.sale_group_id) AS total_records,
    COALESCE(SUM(sp.total_amount), 0) AS gross_sales,
    COALESCE(SUM(sg.discount_amount), 0) AS total_discounts,
    COALESCE(SUM(CASE WHEN UPPER(sp.payment_type) = 'CASH' THEN 1 ELSE 0 END), 0) AS cash_count,
    COALESCE(SUM(CASE WHEN UPPER(sp.payment_type) <> 'CASH' THEN 1 ELSE 0 END), 0) AS non_cash_count
    FROM sale_groups sg JOIN sale_payments sp ON sg.sale_group_id = sp.sale_group_id $where_sql";

$summary_stmt = $conn->prepare($summary_sql);
if (!$summary_stmt) die("Error: " . $conn->error);
if (!empty($params)) $summary_stmt->bind_param($types, ...$params);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc() ?: ['total_records'=>0,'gross_sales'=>0,'total_discounts'=>0,'cash_count'=>0,'non_cash_count'=>0];
$summary_stmt->close();

$count_sql = "SELECT COUNT(sg.sale_group_id) FROM sale_groups sg JOIN sale_payments sp ON sg.sale_group_id = sp.sale_group_id $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt) die("Error: " . $conn->error);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total_sales);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_sales / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

$sales_sql = "SELECT sg.sale_group_id, sg.created_at AS sale_date,
    COALESCE(u.full_name, sg.created_by) AS cashier_name,
    sg.customer_name,
    sp.total_amount AS grand_total, sg.discount_amount, sp.payment_type,
    (SELECT SUM(quantity) FROM sales WHERE sale_group_id = sg.sale_group_id) AS total_qty_sold,
    (SELECT COALESCE(SUM(ri.quantity), 0) FROM returns r JOIN return_items ri ON r.return_id = ri.return_id WHERE r.original_sale_group_id = sg.sale_group_id) AS total_qty_returned
    FROM sale_groups sg
    JOIN sale_payments sp ON sg.sale_group_id = sp.sale_group_id
    LEFT JOIN users u ON sg.created_by = u.username
    $where_sql ORDER BY sg.created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sales_sql);
if (!$stmt) die("Error: " . $conn->error);
$all_params = array_merge($params, [$limit, $offset]);
$stmt->bind_param($types . 'ii', ...$all_params);
$stmt->execute();
$sales_result = $stmt->get_result();
$stmt->close();

$payment_types_result = $conn->query("SELECT DISTINCT payment_type FROM sale_payments WHERE payment_type IS NOT NULL AND payment_type <> '' ORDER BY payment_type ASC");

$dashboard_link = ($current_role === 'admin') ? 'admin_dashboard.php' : 'staff_dashboard.php';

$has_active_filters = ($start_date || $end_date || $customer_filter || $payment_type_filter || $receipt_filter);
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History</title>
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin1.css">
    

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
            --card-radius:  16px;
            --card-shadow:  0 2px 16px rgba(0,0,0,0.07);
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
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 16px;
        }
        .page-header-left { display: flex; align-items: center; gap: 14px; }
        .page-header-icon {
            width: 46px; height: 46px;
            background: var(--blue);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(37,99,235,.3);
        }
        .page-header h4 {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0 0 3px;
            letter-spacing: -.3px;
            color: var(--ink);
        }
        .page-header p {
            margin: 0;
            font-size: .75rem;
            color: var(--muted);
        }

        /* ── Summary cards ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 1100px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px)  { .stat-grid { grid-template-columns: 1fr; } }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--card-radius);
            padding: 20px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: box-shadow .15s, transform .15s;
        }
        .stat-card:hover { box-shadow: var(--shadow); transform: translateY(-1px); }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--accent-color, var(--blue));
            border-radius: var(--radius) var(--radius) 0 0;
        }
        .stat-card.green  { --accent-color: var(--green); }
        .stat-card.amber  { --accent-color: var(--amber); }
        .stat-card.violet { --accent-color: var(--violet); }

        .stat-label {
            font-size: .67rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .stat-label i { font-size: .85rem; }
        .stat-value {
            font-size: 1.55rem;
            font-weight: 700;
            letter-spacing: -.5px;
            color: var(--ink);
            line-height: 1;
            margin-bottom: 6px;
        }
        .stat-value.mono { font-family: var(--mono); font-size: 1.25rem; }
        .stat-sub {
            font-size: .7rem;
            color: var(--faint);
        }

        /* ── Filter panel ── */
        .filter-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-xs);
            overflow: hidden;
        }
        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-light);
            cursor: pointer;
            user-select: none;
            transition: background .12s;
        }
        .filter-header:hover { background: var(--surface-2); }
        .filter-header-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .82rem;
            font-weight: 700;
            color: var(--ink-2);
        }
        .filter-header-left i { color: var(--blue); font-size: 1rem; }
        .filter-active-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--blue);
            display: <?= $has_active_filters ? 'inline-block' : 'none' ?>;
        }
        .filter-toggle-icon { color: var(--muted); font-size: .85rem; transition: transform .2s; }
        .filter-toggle-icon.open { transform: rotate(180deg); }

        .filter-body { padding: 20px; display: none; }
        .filter-body.open { display: block; }

        .f-label {
            display: block;
            font-size: .67rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            margin-bottom: 5px;
        }
        .f-input {
            width: 100%;
            padding: 8px 11px;
            font-family: var(--font);
            font-size: .82rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface-2);
            color: var(--ink);
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .f-input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
            background: #fff;
        }
        .f-input::placeholder { color: var(--faint); }

        .filter-actions { display: flex; gap: 10px; margin-top: 20px; }

        .btn-apply {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px;
            font-family: var(--font); font-size: .8rem; font-weight: 700;
            border: none; border-radius: 10px;
            background: var(--blue); color: #fff; cursor: pointer;
            transition: background .12s, box-shadow .12s;
            box-shadow: 0 2px 8px rgba(37,99,235,.25);
        }
        .btn-apply:hover { background: var(--blue-dark); }

        .btn-reset {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 16px;
            font-family: var(--font); font-size: .8rem; font-weight: 600;
            border: 1.5px solid var(--border); border-radius: 10px;
            background: #fff; color: var(--ink-2); cursor: pointer;
            text-decoration: none;
            transition: background .12s, border-color .12s;
        }
        .btn-reset:hover { background: var(--surface-2); border-color: var(--muted); }

        /* ── Table card ── */
        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        .table-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-light);
        }
        .table-card-title {
            font-size: .85rem;
            font-weight: 700;
            color: var(--ink);
            display: flex; align-items: center; gap: 8px;
        }
        .table-card-title i { color: var(--blue); }
        .record-count {
            font-size: .72rem;
            color: var(--muted);
            background: var(--surface-2);
            border: 1px solid var(--border);
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        /* ── Data table ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table thead th {
            padding: 10px 16px;
            font-size: .64rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--muted);
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .data-table thead th:first-child { border-radius: 0; }
        .data-table tbody td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--border-light);
            color: var(--ink-2);
            vertical-align: middle;
        }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr { transition: background .1s; }
        .data-table tbody tr:hover { background: #fafbff; }

        /* Cells */
        .cell-receipt {
            font-family: var(--mono);
            font-size: .8rem;
            font-weight: 600;
            color: var(--blue);
            letter-spacing: .02em;
        }
        .cell-date {
            font-size: .8rem;
            color: var(--ink-2);
        }
        .cell-date span {
            display: block;
            font-size: .7rem;
            color: var(--faint);
            margin-top: 1px;
        }
        .cell-cashier {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .8rem;
            font-weight: 500;
        }
        .avatar-xs {
            width: 26px; height: 26px;
            border-radius: 50%;
            background: var(--blue-light);
            color: var(--blue);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .cell-discount {
            font-family: var(--mono);
            font-size: .78rem;
            color: var(--muted);
        }
        .cell-discount.has-discount { color: var(--red); font-weight: 600; }
        .cell-total {
            font-family: var(--mono);
            font-size: .9rem;
            font-weight: 700;
            color: var(--ink);
        }

        /* Payment badge */
        .pay-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .pay-badge.cash    { background: var(--green-light); color: var(--green); }
        .pay-badge.invoice { background: var(--violet-light); color: var(--violet); }
        .pay-badge.other   { background: var(--surface-2); color: var(--muted); border: 1px solid var(--border); }

        /* Action buttons */
        .action-group { display: flex; gap: 6px; justify-content: flex-end; }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            font-family: var(--font);
            font-size: .72rem;
            font-weight: 600;
            border-radius: 10px;
            text-decoration: none;
            border: 1.5px solid transparent;
            transition: background .12s, border-color .12s, transform .1s;
            white-space: nowrap;
        }
        .btn-action:hover { transform: translateY(-1px); }
        .btn-action.view {
            background: var(--blue-light);
            color: var(--blue);
            border-color: rgba(37,99,235,.2);
        }
        .btn-action.view:hover { background: #dbeafe; }
        .btn-action.ret {
            background: var(--amber-light);
            color: var(--amber);
            border-color: rgba(217,119,6,.2);
        }
        .btn-action.ret:hover { background: #fde68a; }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
        .empty-state-icon {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: var(--blue-light);
            color: var(--blue);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            margin: 0 auto 16px;
        }
        .empty-state h6 { font-size: .9rem; font-weight: 700; color: var(--ink-2); margin-bottom: 4px; }
        .empty-state p  { font-size: .78rem; }

        /* ── Pagination ── */
        .pager-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-top: 1px solid var(--border-light);
            background: var(--surface-2);
            gap: 12px;
            flex-wrap: wrap;
        }
        .pager-info {
            font-size: .72rem;
            color: var(--muted);
        }
        .pager {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .pager a, .pager span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px; height: 32px;
            font-size: .75rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--ink-2);
            border: 1.5px solid var(--border);
            background: #fff;
            transition: background .12s, border-color .12s, color .12s;
        }
        .pager a:hover:not(.active) { background: var(--blue-light); border-color: rgba(37,99,235,.2); color: var(--blue); }
        .pager a.active { background: var(--blue); border-color: var(--blue); color: #fff; }
        .pager span.dots { background: transparent; border-color: transparent; color: var(--faint); cursor: default; }
        .pager span.disabled { opacity: .35; cursor: not-allowed; }
    </style>
</head>
<body>
<div class="d-flex">

    <!-- ── Sidebar ── -->
    <div class="sidebar flex-column p-0" id="sidebar">
        <div class="sidebar-logo text-center">
            <img src="images/logo.png" alt="Inventory Logo">
            <h5 class="mt-2 text-white">Inventory System</h5>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="sidebar-title">Main</li>
            <li class="nav-item mb-2"><a class="nav-link" href="<?= e($dashboard_link) ?>"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
            <li class="sidebar-title">Management</li>
            <li class="nav-item mb-2"><a class="nav-link" href="products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="categories.php"><i class="bi bi-tags me-2"></i> Categories</a></li>
            <li class="nav-item mb-2"><a class="nav-link active" href="sales.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="p_os.php"><i class="bi bi-receipt me-2"></i> Invoice</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="stock_in_batches.php"><i class="bi bi-box-arrow-down me-2"></i> Stock-In Records</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="stock_out_history.php"><i class="bi bi-box-arrow-up me-2"></i> Stock-Out Records</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="admin_seasonal_report.php"><i class="bi bi-calendar-range me-2"></i> Seasonal Analysis</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line me-2"></i> Reports</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="supplier.php"><i class="bi bi-truck me-2"></i> Suppliers</a></li>
            <li class="sidebar-title">Users</li>
            <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>
            <li class="sidebar-title">Settings</li>
            <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>

    <!-- ── Content ── -->
    <div class="content flex-grow-1">
        <div class="main-wrap">

            <!-- Page header -->
            <div class="page-header">
                <div class="page-header-left">
                    <div class="page-header-icon"><i class="bi bi-cart-check-fill"></i></div>
                    <div>
                        <h4>Sales Transaction History</h4>
                        <p>View, filter, and manage all sales records</p>
                    </div>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-label"><i class="bi bi-receipt" style="color:var(--blue)"></i> Total Transactions</div>
                    <div class="stat-value"><?= number_format((int)$summary['total_records']) ?></div>
                    <div class="stat-sub">
                        Processed transations
                    </div>
                </div>
                <div class="stat-card green">
                    <div class="stat-label"><i class="bi bi-cash-stack" style="color:var(--green)"></i> Gross Sales</div>
                    <div class="stat-value mono"><?= money($summary['gross_sales']) ?></div>
                    <div class="stat-sub">Total revenue collected</div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-label"><i class="bi bi-tag" style="color:var(--amber)"></i> Total Discounts</div>
                    <div class="stat-value mono"><?= money($summary['total_discounts']) ?></div>
                    <div class="stat-sub">Across all transactions</div>
                </div>
                <div class="stat-card violet">
                    <div class="stat-label"><i class="bi bi-graph-up" style="color:var(--violet)"></i> Net Revenue</div>
                    <div class="stat-value mono"><?= money((float)$summary['gross_sales'] - (float)$summary['total_discounts']) ?></div>
                    <div class="stat-sub">After discounts</div>
                </div>
            </div>

            <!-- Filter panel -->
            <div class="filter-panel">
                <div class="filter-header" id="filterToggleBtn">
                    <div class="filter-header-left">
                        <i class="bi bi-sliders"></i>
                        Filter Transactions
                        <span class="filter-active-dot" id="filterDot"></span>
                        <?php if ($has_active_filters): ?>
                            <span style="font-size:.65rem; color:var(--blue); font-weight:600; background:var(--blue-light); padding:2px 8px; border-radius:20px;">Active</span>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-chevron-down filter-toggle-icon <?= $has_active_filters ? 'open' : '' ?>" id="filterChevron"></i>
                </div>

                <div class="filter-body <?= $has_active_filters ? 'open' : '' ?>" id="filterBody">
                    <form action="sales.php" method="GET">
                        <div class="row g-3">
                            <div class="col-md-3 col-sm-6">
                                <label class="f-label">Start Date</label>
                                <input type="date" class="f-input" name="start_date" value="<?= e($start_date) ?>">
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="f-label">End Date</label>
                                <input type="date" class="f-input" name="end_date" value="<?= e($end_date) ?>">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="f-label"><i class="bi bi-person-circle" style="margin-right:4px;"></i>Customer Name</label>
                                <input type="text" class="f-input" name="customer_name" placeholder="e.g. Mark" value="<?= e($customer_filter) ?>">
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <label class="f-label">Receipt #</label>
                                <input type="text" class="f-input" name="receipt_no" placeholder="e.g. 15" value="<?= e($receipt_filter) ?>">
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn-apply"><i class="bi bi-funnel-fill"></i> Apply Filter</button>
                            <a href="sales.php" class="btn-reset"><i class="bi bi-x-circle"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table card -->
            <div class="table-card">
                <div class="table-card-header">
                    <div class="table-card-title"><i class="bi bi-table"></i> Sales Log</div>
                    <span class="record-count"><?= number_format($total_sales) ?> record<?= $total_sales !== 1 ? 's' : '' ?></span>
                </div>

                <div style="overflow-x:auto;">
                    <?php if ($sales_result && $sales_result->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Date &amp; Time</th>
                                <th>Customer</th>
                                <th style="text-align:right;">Discount</th>
                                <th style="text-align:right;">Total Amount</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $sales_result->fetch_assoc()):
                                $has_customer = !empty($row['customer_name']);
                                $display_name = $has_customer ? $row['customer_name'] : 'Walk-in';
                                $initials = strtoupper(substr($display_name, 0, 2));
                                $pt = strtoupper($row['payment_type'] ?? '');
                                $pay_class = ($pt === 'CASH') ? 'cash' : (($pt === 'INVOICE' || $pt === 'PHYSICAL_CASH') ? 'invoice' : 'other');
                                $has_disc = (float)$row['discount_amount'] > 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="cell-receipt">#<?= e(str_pad($row['sale_group_id'], 6, '0', STR_PAD_LEFT)) ?></span>
                                    <?php 
                                        $sold = (float)($row['total_qty_sold'] ?? 0);
                                        $ret  = (float)($row['total_qty_returned'] ?? 0);
                                        if ($ret > 0): 
                                            if ($ret >= $sold): ?>
                                                <span class="badge bg-danger text-white border border-danger ms-1" style="font-size: 0.65rem; padding: 2px 6px;">Fully Returned</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" style="font-size: 0.65rem; padding: 2px 6px;">Partial Return</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                </td>
                                <td>
                                    <div class="cell-date">
                                        <?= date("M d, Y", strtotime($row['sale_date'])) ?>
                                        <span><?= date("h:i A", strtotime($row['sale_date'])) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($has_customer): ?>
                                        <span class="cell-cashier">
                                            <span class="avatar-xs" style="background:#dbeafe;color:#2563eb;"><?= e($initials) ?></span>
                                            <?= e($row['customer_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:.78rem;color:#9ca3af;">
                                            <i class="bi bi-person-dash" style="font-size:.85rem;"></i>
                                            <span style="background:#f3f4f6;border-radius:6px;padding:2px 8px;font-size:.72rem;font-weight:600;">Walk-in</span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <span class="cell-discount <?= $has_disc ? 'has-discount' : '' ?>">
                                        <?= $has_disc ? '−' . money($row['discount_amount']) : '—' ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <span class="cell-total"><?= money($row['grand_total']) ?></span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <a href="invoice_receipt.php?sale_group_id=<?= (int)$row['sale_group_id'] ?>" class="btn-action view" title="View Receipt">
                                            <i class="bi bi-receipt"></i> View
                                        </a>
                                        <a href="returns.php?search_sale_id=<?= (int)$row['sale_group_id'] ?>" class="btn-action ret" title="Process Return">
                                            <i class="bi bi-arrow-return-left"></i> Return
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-search"></i></div>
                        <h6>No transactions found</h6>
                        <p>Try adjusting your filters or clearing the search criteria.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pager-wrap">
                    <div class="pager-info">
                        Page <?= $page ?> of <?= $total_pages ?> &middot; <?= number_format($total_sales) ?> total &middot; <?= $limit ?>/page
                    </div>
                    <div class="pager">
                        <?php if ($page > 1): ?>
                            <a href="<?= buildSalesPaginationUrl($page - 1, $_GET) ?>" title="Previous"><i class="bi bi-chevron-left" style="font-size:.7rem;"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="bi bi-chevron-left" style="font-size:.7rem;"></i></span>
                        <?php endif; ?>

                        <?php
                        $s = max(1, $page - 2);
                        $e = min($total_pages, $page + 2);
                        if ($s > 1) {
                            echo '<a href="' . buildSalesPaginationUrl(1, $_GET) . '">1</a>';
                            if ($s > 2) echo '<span class="dots">…</span>';
                        }
                        for ($i = $s; $i <= $e; $i++):
                        ?>
                            <a href="<?= buildSalesPaginationUrl($i, $_GET) ?>" class="<?= ($i === $page) ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor;
                        if ($e < $total_pages) {
                            if ($e < $total_pages - 1) echo '<span class="dots">…</span>';
                            echo '<a href="' . buildSalesPaginationUrl($total_pages, $_GET) . '">' . $total_pages . '</a>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?= buildSalesPaginationUrl($page + 1, $_GET) ?>" title="Next"><i class="bi bi-chevron-right" style="font-size:.7rem;"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="bi bi-chevron-right" style="font-size:.7rem;"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Filter accordion toggle
    const filterBtn  = document.getElementById('filterToggleBtn');
    const filterBody = document.getElementById('filterBody');
    const chevron    = document.getElementById('filterChevron');

    filterBtn.addEventListener('click', () => {
        filterBody.classList.toggle('open');
        chevron.classList.toggle('open');
    });
</script>
</body>
</html>