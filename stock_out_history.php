<?php
include 'project.php';
session_start();

/* ============================================================
   STOCK-OUT HISTORY PAGE
   ============================================================ */

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'Admin';

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function money($value): string { return '₱' . number_format((float)$value, 2); }
function buildStockOutPaginationUrl(int $page, array $getParams): string {
    unset($getParams['page']);
    $getParams['page'] = $page;
    return 'stock_out_history.php?' . http_build_query($getParams);
}

$limit = 20;
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$where_clauses = [];
$params = [];
$types = '';
$search_query = trim($_GET['q'] ?? '');

if ($search_query !== '') {
    $search_term = '%' . $search_query . '%';
    $where_clauses[] = "(p.product_name LIKE ? OR p.brand LIKE ? OR p.variation LIKE ? OR so.reason LIKE ? OR so.stocked_by LIKE ? OR u.full_name LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssssss';
}

$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : '';

$summary_sql = "
    SELECT
        COUNT(so.id) AS total_transactions,
        COALESCE(SUM(so.quantity), 0) AS total_quantity_out,
        COALESCE(SUM(CASE WHEN so.total_cost > 0 THEN so.total_cost ELSE (so.quantity * COALESCE(so.supplier_price, 0)) END), 0) AS total_stock_out_cost,
        COALESCE(SUM(CASE WHEN so.reason = 'Expired batch stock-out' THEN 1 ELSE 0 END), 0) AS expired_stock_out_count
    FROM stock_out so 
    JOIN products p ON so.product_id = p.product_id 
    LEFT JOIN users u ON so.stocked_by = u.username
    $where_sql
";
$summary_stmt = $conn->prepare($summary_sql);
if ($summary_stmt) {
    if (!empty($params)) $summary_stmt->bind_param($types, ...$params);
    $summary_stmt->execute();
    $summary = $summary_stmt->get_result()->fetch_assoc() ?: ['total_transactions'=>0,'total_quantity_out'=>0,'total_stock_out_cost'=>0,'expired_stock_out_count'=>0];
    $summary_stmt->close();
}

$count_sql = "SELECT COUNT(so.id) AS total FROM stock_out so JOIN products p ON so.product_id = p.product_id LEFT JOIN users u ON so.stocked_by = u.username $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt) die("Error: " . $conn->error);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_transactions = (int)($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_transactions / $limit));
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

$sql = "
    SELECT so.id AS stock_out_id, so.created_at, so.quantity, so.reason, so.stocked_by,
           so.supplier_price, so.total_cost, p.product_name, p.unit, p.brand, p.variation,
           u.full_name
    FROM stock_out so 
    JOIN products p ON so.product_id = p.product_id
    LEFT JOIN users u ON so.stocked_by = u.username
    $where_sql ORDER BY so.created_at DESC LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Error: " . $conn->error);
$stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock-Out Records </title>
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin1.css">
    <style>
        :root {
            --bg:           #eef1f8;
            --brand-primary:       #2563eb;
            --brand-primary-light: #dbeafe;
            --brand-success:       #059669;
            --brand-success-light: #d1fae5;
            --brand-danger:        #dc2626;
            --brand-danger-light:  #fee2e2;
            --brand-amber:         #d97706;
            --brand-amber-light:   #fef3c7;
            --brand-slate:         #475569;
            --brand-slate-light:   #f1f5f9;
            --surface:    #ffffff;
            --surface-2:  #f8fafc;
            --surface-3:  #f1f5f9;
            --border:        #e2e8f0;
            --border-strong: #cbd5e1;
            --text-primary:   #0f172a;
            --text-secondary: #475569;
            --text-muted:     #94a3b8;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
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

        
        /* Page header */
        .page-header { display: flex; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: .75rem; }
        .page-header-icon {
            width: 42px; height: 42px; border-radius: var(--radius-md);
            background: var(--brand-danger-light); color: var(--brand-danger);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; margin-right: .85rem; flex-shrink: 0;
        }
        .page-header-left h4 { font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -.3px; }
        .page-header-left p  { font-size: .82rem; color: var(--text-muted); margin: 3px 0 0; }

        /* KPI grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 1.5rem; }
        @media (max-width: 1100px) { .kpi-grid { grid-template-columns: repeat(2,1fr); } }
        @media (max-width:  576px) { .kpi-grid { grid-template-columns: 1fr; } }

        .kpi-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
            padding: 1.1rem 1.25rem 1rem; position: relative; overflow: hidden;
            transition: box-shadow .2s, transform .2s;
        }
        .kpi-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
        .kpi-card.kpi-red::before    { background: var(--brand-danger); }
        .kpi-card.kpi-amber::before  { background: var(--brand-amber); }
        .kpi-card.kpi-blue::before   { background: var(--brand-primary); }
        .kpi-card.kpi-slate::before  { background: var(--brand-slate); }

        .kpi-top  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: .55rem; }
        .kpi-icon { width:36px; height:36px; border-radius: var(--radius-sm); display:flex; align-items:center; justify-content:center; font-size:.95rem; }
        .kpi-icon.red   { background: var(--brand-danger-light);  color: var(--brand-danger); }
        .kpi-icon.amber { background: var(--brand-amber-light);   color: var(--brand-amber); }
        .kpi-icon.blue  { background: var(--brand-primary-light); color: var(--brand-primary); }
        .kpi-icon.slate { background: var(--brand-slate-light);   color: var(--brand-slate); }

        .kpi-label { font-size:.73rem; font-weight:700; text-transform:uppercase; letter-spacing:.55px; color:var(--text-muted); margin-bottom:.2rem; }
        .kpi-value { font-size:1.7rem; font-weight:800; color:var(--text-primary); line-height:1.1; letter-spacing:-.5px; font-family:'DM Mono',monospace; }
        .kpi-sub   { font-size:.74rem; color:var(--text-muted); margin-top:.35rem; }

        /* Table card */
        .table-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden;
        }
        .table-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: .95rem 1.4rem; border-bottom: 1px solid var(--border);
        }
        .table-card-title { font-size:.93rem; font-weight:700; color:var(--text-primary); }
        .table-card-meta  { font-size:.75rem; color:var(--text-muted); margin-top:1px; }
        .records-badge {
            font-size:.75rem; font-weight:600;
            background:var(--surface-3); color:var(--text-secondary);
            border:1px solid var(--border-strong); border-radius:999px;
            padding:3px 12px; font-family:'DM Mono',monospace;
        }

        /* Search bar */
        .search-wrap { padding: 1rem 1.4rem; border-bottom: 1px solid var(--border); }
        .search-inner { display:flex; gap:8px; max-width:500px; }
        .search-input {
            flex:1; border:1px solid var(--border-strong); border-radius:var(--radius-sm);
            font-size:.875rem; padding:.45rem .9rem; height:40px;
            font-family:'Plus Jakarta Sans',sans-serif; color:var(--text-primary);
            background: var(--surface-2);
            transition: border-color .15s, box-shadow .15s;
        }
        .search-input:focus { border-color:var(--brand-primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); outline:none; background:#fff; }
        .btn-search {
            background:var(--brand-primary); color:#fff; border:none;
            border-radius:var(--radius-sm); height:40px; padding:0 1rem;
            display:inline-flex; align-items:center; gap:5px;
            font-size:.875rem; font-weight:600; cursor:pointer;
            transition:background .15s;
        }
        .btn-search:hover { background:#1d4ed8; }
        .btn-reset-search {
            background:transparent; color:var(--text-secondary);
            border:1px solid var(--border-strong); border-radius:var(--radius-sm);
            height:40px; padding:0 .9rem;
            font-size:.875rem; font-weight:500;
            display:inline-flex; align-items:center; gap:5px;
            text-decoration:none; transition:border-color .15s, color .15s, background .15s;
        }
        .btn-reset-search:hover { border-color:var(--brand-primary); color:var(--brand-primary); background:var(--brand-primary-light); }

        /* Table */
        .so-table { width:100%; border-collapse:separate; border-spacing:0; }
        .so-table thead th {
            background:var(--surface-3); font-size:.71rem; font-weight:700;
            text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted);
            padding:.65rem .9rem; border-bottom:1px solid var(--border); white-space:nowrap;
        }
        .so-table thead th:first-child { padding-left:1.4rem; }
        .so-table thead th:last-child  { padding-right:1.4rem; }
        .so-table tbody tr { border-bottom:1px solid var(--border); transition:background .12s; }
        .so-table tbody tr:last-child  { border-bottom:none; }
        .so-table tbody tr:hover { background:#f8fafd; }
        .so-table td { padding:.72rem .9rem; font-size:.86rem; vertical-align:middle; }
        .so-table td:first-child { padding-left:1.4rem; }
        .so-table td:last-child  { padding-right:1.4rem; }

        /* ID chip */
        .id-chip {
            font-family:'DM Mono',monospace; font-size:.75rem; font-weight:600;
            background:var(--surface-3); color:var(--text-muted);
            border:1px solid var(--border-strong); border-radius:6px;
            padding:2px 8px; display:inline-block;
        }

        /* Date cell */
        .td-date-main { font-weight:600; color:var(--text-primary); font-size:.83rem; }
        .td-date-time { font-size:.74rem; color:var(--text-muted); margin-top:1px; font-family:'DM Mono',monospace; }

        /* Product cell */
        .product-name { font-weight:700; color:var(--text-primary); }
        .product-meta { font-size:.75rem; color:var(--text-muted); margin-top:2px; }

        /* Qty badge */
        .qty-badge {
            display:inline-flex; align-items:center; gap:4px;
            background:var(--brand-danger-light); color:var(--brand-danger);
            font-size:.76rem; font-weight:700; font-family:'DM Mono',monospace;
            border-radius:6px; padding:3px 9px;
        }

        /* Reason */
        .reason-text { font-size:.82rem; color:var(--text-secondary); max-width:180px; }
        .reason-expired {
            display:inline-flex; align-items:center; gap:4px;
            background:#fef3c7; color:#92400e;
            font-size:.75rem; font-weight:600; border-radius:6px; padding:3px 8px;
        }

        /* User */
        .user-wrap { display:flex; align-items:center; gap:7px; }
        .user-avatar { width:28px; height:28px; border-radius:50%; background:var(--surface-3); color:var(--text-muted); display:flex; align-items:center; justify-content:center; font-size:.8rem; flex-shrink:0; }
        .user-name { font-size:.84rem; font-weight:500; color:var(--text-primary); }

        /* Cost */
        .cost-val { font-family:'DM Mono',monospace; font-weight:700; color:var(--brand-danger); font-size:.88rem; }
        .cost-note { font-size:.72rem; color:var(--text-muted); }

        /* Empty */
        .empty-state { text-align:center; padding:3.5rem 1rem; }
        .empty-state i { font-size:2.5rem; color:var(--text-muted); opacity:.4; display:block; margin-bottom:.75rem; }
        .empty-state p { font-size:.9rem; color:var(--text-muted); margin:0; }

        /* Pagination */
        .pagination-wrap { display:flex; flex-direction:column; align-items:center; gap:.5rem; padding:1rem 0 .5rem; }
        .pagination-info { font-size:.78rem; color:var(--text-muted); }
        .pg-list { display:flex; gap:4px; list-style:none; margin:0; padding:0; flex-wrap:wrap; justify-content:center; }
        .pg-list .pg-item a,
        .pg-list .pg-item span {
            display:flex; align-items:center; justify-content:center;
            min-width:34px; height:34px; padding:0 6px;
            border-radius:var(--radius-sm); font-size:.82rem; font-weight:600;
            text-decoration:none; border:1px solid var(--border-strong);
            color:var(--text-secondary); background:var(--surface);
            transition:background .13s, border-color .13s, color .13s;
        }
        .pg-list .pg-item a:hover { background:var(--brand-danger-light); border-color:var(--brand-danger); color:var(--brand-danger); }
        .pg-list .pg-item.active a { background:var(--brand-danger); border-color:var(--brand-danger); color:#fff; }
        .pg-list .pg-item.disabled span { background:var(--surface-3); color:var(--text-muted); cursor:not-allowed; }

        /* Sidebar toggle */
        .sidebar-toggle-btn { display:none; background:var(--brand-danger); color:#fff; border:none; border-radius:var(--radius-sm); padding:7px 12px; font-size:1.1rem; cursor:pointer; margin-bottom:1rem; }
        @media (max-width:991px) { .sidebar-toggle-btn { display:inline-flex; align-items:center; } }
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
            <li class="nav-item mb-2"><a class="nav-link active" href="stock_out_history.php"><i class="bi bi-box-arrow-up me-2"></i> Stock-Out Records</a></li>
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

    <!-- Main Content -->
    <div class="content flex-grow-1">

        <button class="sidebar-toggle-btn" id="sidebarToggle"><i class="bi bi-list"></i></button>

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-icon"><i class="bi bi-box-arrow-up"></i></div>
            <div class="page-header-left">
                <h4>Stock-Out Records</h4>
                <p>Detailed log of all products removed from inventory.</p>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-red">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Total Transactions</div>
                        <div class="kpi-value"><?= number_format((int)$summary['total_transactions']) ?></div>
                    </div>
                    <div class="kpi-icon red"><i class="bi bi-arrow-up-circle-fill"></i></div>
                </div>
                <div class="kpi-sub">All stock-out events</div>
            </div>

            <div class="kpi-card kpi-amber">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Total Qty Out</div>
                        <div class="kpi-value">
                            <?php 
                            $q = $summary['total_quantity_out'];
                            echo ($q == (int)$q) ? number_format((int)$q) : rtrim(rtrim(number_format($q, 4), '0'), '.');
                            ?>
                        </div>
                    </div>
                    <div class="kpi-icon amber"><i class="bi bi-boxes"></i></div>
                </div>
                <div class="kpi-sub">Units removed from stock</div>
            </div>

            <div class="kpi-card kpi-blue">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Total Cost</div>
                        <div class="kpi-value" style="font-size:1.3rem;"><?= money((float)$summary['total_stock_out_cost']) ?></div>
                    </div>
                    <div class="kpi-icon blue"><i class="bi bi-cash-stack"></i></div>
                </div>
                <div class="kpi-sub">Cumulative stock-out value</div>
            </div>

            <div class="kpi-card kpi-slate">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Expired Stock-Out</div>
                        <div class="kpi-value"><?= number_format((int)$summary['expired_stock_out_count']) ?></div>
                    </div>
                    <div class="kpi-icon slate"><i class="bi bi-exclamation-triangle"></i></div>
                </div>
                <div class="kpi-sub">Expired batch removals</div>
            </div>
        </div>

        <!-- Table card -->
        <div class="table-card">
            <div class="table-card-header">
                <div>
                    <div class="table-card-title">Stock-Out Log</div>
                    <div class="table-card-meta">
                        Showing <?= $total_transactions > 0 ? min($offset+1,$total_transactions) : 0 ?>–<?= $total_transactions > 0 ? min($offset+$limit,$total_transactions) : 0 ?> of <?= number_format($total_transactions) ?> records
                    </div>
                </div>
                <span class="records-badge"><?= number_format($total_transactions) ?> total</span>
            </div>

            <!-- Search -->
            <div class="search-wrap">
                <form action="stock_out_history.php" method="GET">
                    <div class="search-inner">
                        <input type="text" class="search-input" name="q"
                            placeholder="Search product, brand, reason, user…"
                            value="<?= e($search_query) ?>">
                        <button type="submit" class="btn-search">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <?php if ($search_query !== ''): ?>
                            <a href="stock_out_history.php" class="btn-reset-search">
                                <i class="bi bi-x-lg"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="so-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date &amp; Time</th>
                            <th>Product</th>
                            <th>Qty Out</th>
                            <th>Reason</th>
                            <th>User</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $qty       = (float)($row['quantity'] ?? 0);
                            $unitCost  = (float)($row['supplier_price'] ?? 0);
                            $savedTotal = (float)($row['total_cost'] ?? 0);
                            $finalTotal = ($savedTotal > 0) ? $savedTotal : ($qty * $unitCost);
                            $dt = !empty($row['created_at']) ? strtotime($row['created_at']) : null;
                            $isExpired = str_contains(($row['reason'] ?? ''), 'Expired');
                        ?>
                        <tr>
                            <td><span class="id-chip">#<?= (int)$row['stock_out_id'] ?></span></td>

                            <td>
                                <?php if ($dt): ?>
                                    <div class="td-date-main"><?= date('M d, Y', $dt) ?></div>
                                    <div class="td-date-time"><?= date('h:i A', $dt) ?></div>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="product-name"><?= e($row['product_name'] ?? '—') ?></div>
                                <div class="product-meta">
                                    <?= e($row['brand'] ?? '—') ?>
                                    <?php if (!empty($row['variation'])): ?> &middot; <?= e($row['variation']) ?><?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <span class="qty-badge">
                                    <i class="bi bi-dash"></i><?= formatQty($qty) ?><?= !empty($row['unit']) ? ' '.e($row['unit']) : '' ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($isExpired): ?>
                                    <span class="reason-expired">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <?= e($row['reason']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="reason-text"><?= nl2br(e($row['reason'] ?? '—')) ?></span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="user-wrap">
                                    <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                                    <span class="user-name"><?= e($row['full_name'] ?: $row['stocked_by'] ?: '—') ?></span>
                                </div>
                            </td>

                            <td>
                                <div class="cost-val"><?= money($finalTotal) ?></div>
                                <?php if ($savedTotal <= 0 && $unitCost > 0): ?>
                                    <div class="cost-note">auto-calculated</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p><?= $search_query !== '' ? 'No transactions matched your search.' : 'No stock-out transactions found.' ?></p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrap">
                <ul class="pg-list">
                    <li class="pg-item <?= ($page<=1)?'disabled':'' ?>">
                        <?php if ($page>1): ?><a href="<?= buildStockOutPaginationUrl($page-1,$_GET) ?>"><i class="bi bi-chevron-left" style="font-size:.7rem;"></i></a>
                        <?php else: ?><span><i class="bi bi-chevron-left" style="font-size:.7rem;"></i></span><?php endif; ?>
                    </li>
                    <?php
                    $sl = max(1,$page-2); $el = min($total_pages,$page+2);
                    if ($sl>1) { echo '<li class="pg-item"><a href="'.buildStockOutPaginationUrl(1,$_GET).'">1</a></li>'; if($sl>2) echo '<li class="pg-item disabled"><span>…</span></li>'; }
                    for ($i=$sl;$i<=$el;$i++): ?>
                        <li class="pg-item <?= ($i==$page)?'active':'' ?>"><a href="<?= buildStockOutPaginationUrl($i,$_GET) ?>"><?= $i ?></a></li>
                    <?php endfor;
                    if ($el<$total_pages) { if($el<$total_pages-1) echo '<li class="pg-item disabled"><span>…</span></li>'; echo '<li class="pg-item"><a href="'.buildStockOutPaginationUrl($total_pages,$_GET).'">'.$total_pages.'</a></li>'; }
                    ?>
                    <li class="pg-item <?= ($page>=$total_pages)?'disabled':'' ?>">
                        <?php if ($page<$total_pages): ?><a href="<?= buildStockOutPaginationUrl($page+1,$_GET) ?>"><i class="bi bi-chevron-right" style="font-size:.7rem;"></i></a>
                        <?php else: ?><span><i class="bi bi-chevron-right" style="font-size:.7rem;"></i></span><?php endif; ?>
                    </li>
                </ul>
                <div class="pagination-info">Page <?= $page ?> of <?= $total_pages ?></div>
            </div>
            <?php endif; ?>

        </div><!-- /table-card -->
    </div><!-- /content -->
</div><!-- /d-flex -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('show');
});
</script>
</body>
</html>