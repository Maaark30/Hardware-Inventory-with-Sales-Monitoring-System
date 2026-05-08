<?php
include 'project.php';
session_start();

/* ============================================================
   STOCK-IN BATCHES PAGE
   FIXED + ORGANIZED VERSION
   ============================================================ */

/* ============================================================
   1) AUTH
   ============================================================ */
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'Admin';

/* ============================================================
   2) HELPERS
   ============================================================ */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function buildStockInPaginationUrl(int $page, array $getParams): string
{
    unset($getParams['page']);
    $getParams['page'] = $page;
    return 'stock_in_batches.php?' . http_build_query($getParams);
}

/* ============================================================
   3) PAGINATION
   ============================================================ */
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

/* ============================================================
   4) SEARCH / FILTER
   ============================================================ */
$whereClauses = [];
$params = [];
$types = '';

$searchQuery = trim($_GET['q'] ?? '');

if ($searchQuery !== '') {
    $searchTerm = '%' . $searchQuery . '%';
    $whereClauses[] = "(b.reference_no LIKE ? OR s.supplier_name LIKE ? OR b.stocked_by LIKE ? OR u.full_name LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ssss';
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereClauses);
}

/* ============================================================
   5) SUMMARY
   ============================================================ */
$summarySql = "
    SELECT
        COUNT(DISTINCT b.batch_id) AS total_batches,
        COALESCE(SUM(sh.quantity), 0) AS total_quantity_stocked,
        COUNT(DISTINCT sh.product_id) AS total_distinct_products,
        MAX(b.stock_in_date) AS latest_batch_date
    FROM stock_in_batches b
    LEFT JOIN suppliers s ON b.supplier_id = s.supplier_id
    LEFT JOIN users u ON b.stocked_by = u.username
    LEFT JOIN stock_history sh ON sh.batch_id = b.batch_id
    $whereSql
";

$summaryStmt = $conn->prepare($summarySql);
if (!$summaryStmt) {
    die("Error preparing summary query: " . $conn->error);
}

if (!empty($params)) {
    $summaryStmt->bind_param($types, ...$params);
}

$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();
$summary = $summaryResult->fetch_assoc() ?: [
    'total_batches' => 0,
    'total_quantity_stocked' => 0,
    'total_distinct_products' => 0,
    'latest_batch_date' => null
];
$summaryStmt->close();

/* ============================================================
   6) COUNT TOTAL BATCHES
   ============================================================ */
$countSql = "
    SELECT COUNT(b.batch_id)
    FROM stock_in_batches b
    LEFT JOIN suppliers s ON b.supplier_id = s.supplier_id
    LEFT JOIN users u ON b.stocked_by = u.username
    $whereSql
";

$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    die("Error preparing count statement: " . $conn->error);
}

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$countStmt->bind_result($totalBatches);
$countStmt->fetch();
$countStmt->close();

$totalBatches = (int)$totalBatches;
$totalPages = max(1, (int)ceil($totalBatches / $limit));

if ($page > $totalPages) {
    $page = $totalPages;
}
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

/* ============================================================
   7) MAIN QUERY
   ============================================================ */
$mainSql = "
    SELECT
        b.*,
        s.supplier_name,
        u.full_name AS staff_name,
        COALESCE((
            SELECT SUM(sh.quantity)
            FROM stock_history sh
            WHERE sh.batch_id = b.batch_id
        ), 0) AS total_quantity,
        COALESCE((
            SELECT COUNT(DISTINCT sh.product_id)
            FROM stock_history sh
            WHERE sh.batch_id = b.batch_id
        ), 0) AS distinct_items
    FROM stock_in_batches b
    LEFT JOIN suppliers s ON b.supplier_id = s.supplier_id
    LEFT JOIN users u ON b.stocked_by = u.username
    $whereSql
    ORDER BY b.stock_in_date DESC
    LIMIT ? OFFSET ?
";

$mainStmt = $conn->prepare($mainSql);
if (!$mainStmt) {
    die("Error preparing main query: " . $conn->error);
}

$mainTypes = $types . 'ii';
$mainParams = array_merge($params, [$limit, $offset]);
$mainStmt->bind_param($mainTypes, ...$mainParams);
$mainStmt->execute();
$result = $mainStmt->get_result();
$mainStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock-In Batches</title>
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

        /* Layout fix */
        .d-flex { display: flex !important; align-items: stretch; min-height: 100vh; }
        .sidebar {
            position: sticky !important; top: 0 !important; left: auto !important;
            width: 250px !important; min-width: 250px !important; max-width: 250px !important;
            min-height: 100vh !important; height: 100vh !important;
            flex-shrink: 0 !important; transform: none !important;
            z-index: 100; overflow-y: auto;
        }
        @media (max-width:991px) {
            .sidebar { position: fixed !important; left: -260px !important; transition: left .25s; z-index: 999; }
            .sidebar.show { left: 0 !important; }
            /* .main-content { margin-left: 0 !important; } */
        }
        .main-content { flex: 1; min-width: 0; padding: 28px 28px 48px; overflow: hidden;  }

        /* Page title */
        .page-title-wrap { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 28px; }
        .page-title-icon {
            width: 48px; height: 48px; border-radius: 12px;
            background: #fee2e2; display: grid; place-items: center;
            color: #ef4444; font-size: 1.3rem; flex-shrink: 0;
        }
        .page-title h1 { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0 0 4px; }
        .page-title p  { font-size: .85rem; color: #6b7280; margin: 0; }

        /* Stat cards */
        .stat-cards { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 28px; }
        @media(max-width:1000px){.stat-cards{grid-template-columns:repeat(2,1fr);}}
        .stat-card {
            background: #fff; border-radius: 12px;
            padding: 20px 20px 16px;
            border-top: 4px solid transparent;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            display: flex; justify-content: space-between; align-items: flex-start;
        }
        .stat-card.red    { border-top-color: #ef4444; }
        .stat-card.orange { border-top-color: #f97316; }
        .stat-card.blue   { border-top-color: #3b82f6; }
        .stat-card.yellow { border-top-color: #eab308; }
        .stat-card-left .label {
            font-size: .65rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: #6b7280; margin-bottom: 8px;
        }
        .stat-card-left .value { font-size: 1.75rem; font-weight: 700; color: #111827; line-height: 1; }
        .stat-card-left .value.sm { font-size: 1.1rem; }
        .stat-card-left .sub { font-size: .78rem; color: #9ca3af; margin-top: 8px; }
        .stat-card-icon {
            width: 36px; height: 36px; border-radius: 8px;
            display: grid; place-items: center; font-size: 1rem; flex-shrink: 0;
        }
        .stat-card.red    .stat-card-icon { background: #fee2e2; color: #ef4444; }
        .stat-card.orange .stat-card-icon { background: #ffedd5; color: #f97316; }
        .stat-card.blue   .stat-card-icon { background: #dbeafe; color: #3b82f6; }
        .stat-card.yellow .stat-card-icon { background: #fef9c3; color: #eab308; }

        /* Table panel */
        .table-panel {
            background: #fff; border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .table-panel-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid #f3f4f6;
            display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;
        }
         .panel-title-block .title { font-size: 1rem; font-weight: 700; color: #111827; margin: 0 0 2px; }
        .panel-title-block .subtitle { font-size: .78rem; color: #9ca3af; margin: 0; }
        .total-badge {
            background: #f3f4f6; border-radius: 99px;
            padding: 5px 14px; font-size: .78rem; font-weight: 600; color: #374151;
        }

        /* Search */
        .search-row { padding: 16px 24px; display: flex; gap: 10px; }
        .search-field {
            flex: 1; max-width: 440px;
            border: 1.5px solid #e5e7eb; border-radius: 8px;
            padding: 9px 14px; font-size: .85rem; font-family: inherit;
            color: #111827; outline: none; transition: border-color .15s;
        }
        .search-field::placeholder { color: #9ca3af; }
        .search-field:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
        .search-btn-main {
            background: #2563eb; color: #fff; border: none;
            border-radius: 8px; padding: 9px 20px;
            font-size: .85rem; font-weight: 600; font-family: inherit;
            display: inline-flex; align-items: center; gap: 6px;
            cursor: pointer; transition: background .15s;
        }
        .search-btn-main:hover { background: #1d4ed8; }
        .clear-btn {
            background: none; border: 1.5px solid #e5e7eb;
            border-radius: 8px; padding: 9px 14px;
            font-size: .82rem; color: #6b7280; font-family: inherit;
            cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
            transition: border-color .15s, color .15s;
        }
        .clear-btn:hover { border-color: #3b82f6; color: #3b82f6; }

        /* Table */
        table.records-table { width: 100%; border-collapse: collapse; }
        .records-table thead th {
            font-size: .7rem; font-weight: 700; letter-spacing: .07em;
            text-transform: uppercase; color: #6b7280;
            padding: 10px 16px;
            background: #f9fafb; border-bottom: 1px solid #f3f4f6;
            white-space: nowrap;
        }
        .records-table tbody tr { border-bottom: 1px solid #f9fafb; transition: background .1s; }
        .records-table tbody tr:last-child { border-bottom: none; }
        .records-table tbody tr:hover { background: #f9fafb; }
        .records-table td { padding: 14px 16px; vertical-align: middle; font-size: .85rem; }

        .id-chip {
            display: inline-block; background: #f3f4f6;
            border-radius: 6px; padding: 4px 8px;
            font-size: .78rem; font-weight: 600; color: #374151;
        }
        .date-primary { font-weight: 500; color: #111827; }
        .date-sub { font-size: .75rem; color: #9ca3af; }
        .product-name { font-weight: 600; color: #111827; }
        .product-sub  { font-size: .75rem; color: #9ca3af; }

        .qty-badge {
            display: inline-flex; align-items: center;
            background: #fef2f2; color: #ef4444;
            border-radius: 99px; padding: 4px 10px;
            font-size: .78rem; font-weight: 700;
            gap: 3px; white-space: nowrap;
        }
        .qty-badge.green { background: #f0fdf4; color: #16a34a; }

        .reason-tag {
            display: inline-flex; align-items: center; gap: 5px;
            background: #fef3c7; color: #92400e;
            border-radius: 6px; padding: 4px 10px;
            font-size: .75rem; font-weight: 500;
        }

        .staff-cell { display: flex; align-items: center; gap: 7px; font-size: .83rem; color: #374151; }
        .staff-cell i { color: #9ca3af; }

        .ref-code {
            display: inline-block; background: #f1f5f9;
            border-radius: 5px; padding: 3px 8px;
            font-size: .75rem; font-weight: 500; color: #475569;
            font-family: 'Courier New', monospace;
        }

        .view-btn {
            display: inline-flex; align-items: center; gap: 5px;
            background: #eff6ff; color: #2563eb;
            border: 1.5px solid #bfdbfe; border-radius: 7px;
            padding: 5px 12px; font-size: .78rem; font-weight: 600;
            cursor: pointer; transition: background .15s, color .15s;
            white-space: nowrap; font-family: inherit;
        }
        .view-btn:hover { background: #2563eb; color: #fff; border-color: #2563eb; }

        /* Empty */
        .empty-row td { text-align: center; padding: 56px 20px; color: #9ca3af; font-size: .85rem; }
        .empty-row .empty-icon { font-size: 2rem; display: block; margin-bottom: 8px; opacity: .35; }

        /* Pagination */
        .pag-wrap {
            padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap;
            border-top: 1px solid #f3f4f6;
        }
        .pag-info { font-size: .78rem; color: #6b7280; }
        .pag-info strong { color: #111827; }
        .pag-pages { display: flex; gap: 4px; }
        .pag-btn {
            min-width: 32px; height: 32px; border-radius: 7px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .78rem; font-weight: 600;
            border: 1.5px solid #e5e7eb; color: #374151; background: #fff;
            text-decoration: none; padding: 0 8px;
            transition: all .12s;
        }
        .pag-btn:hover { background: #eff6ff; border-color: #bfdbfe; color: #2563eb; }
        .pag-btn.active { background: #2563eb; border-color: #2563eb; color: #fff; }
        .pag-btn.disabled { opacity: .4; pointer-events: none; }
        .pag-sep { display: flex; align-items: center; color: #9ca3af; font-size: .8rem; padding: 0 2px; }

        /* Modal */
        .modal-content { border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.15); }
        .modal-header { background: #1e3a8a; border: none; padding: 18px 22px; }
        .modal-title  { color: #fff; font-size: .95rem; font-weight: 700; }
        .modal-header .btn-close { filter: invert(1) opacity(.6); }
        .modal-body   { padding: 0; }
        .modal-body-content {
            min-height: 250px; max-height: 70vh; overflow-y: auto; padding: 20px;
        }
        .loading-box {
            min-height: 250px; display: flex; align-items: center;
            justify-content: center; flex-direction: column; gap: 12px;
            color: #9ca3af; font-size: .82rem;
        }
        .loading-box .spinner-border { color: #3b82f6 !important; width: 1.4rem; height: 1.4rem; border-width: 2px; }

        /* Custom Wide Modal */
        @media (min-width: 1400px) {
            .modal-wide { max-width: 1320px !important; }
        }
        .modal-header-glass {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: #fff;
            border-bottom: 0;
            border-radius: 16px 16px 0 0;
        }
        .summary-card {
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .summary-card:hover {
            transform: translateY(-2px);
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59,130,246,0.08);
            background: #fff;
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
            <li class="nav-item mb-2"><a class="nav-link active" href="stock_in_batches.php"><i class="bi bi-box-arrow-down me-2"></i> Stock-In Records</a></li>
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

    <!-- Main -->
    <div class="main-content">

        <!-- <button class="sidebar-toggle-btn" id="sidebarToggle"><i class="bi bi-list"></i></button> -->

        <!-- Page title -->
        <div class="page-title-wrap">
            <div class="page-title-icon"><i class="bi bi-box-arrow-in-down"></i></div>
            <div class="page-title">
                <h1>Stock-In Records</h1>
                <p>History of grouped product receipts from suppliers.</p>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="stat-cards">
            <div class="stat-card red">
                <div class="stat-card-left">
                    <div class="label">Total Batches</div>
                    <div class="value"><?= number_format((int)$summary['total_batches']) ?></div>
                    <div class="sub">All stock-in events</div>
                </div>
                <div class="stat-card-icon"><i class="bi bi-collection"></i></div>
            </div>
            <div class="stat-card orange">
                <div class="stat-card-left">
                    <div class="label">Total Qty Stocked</div>
                    <div class="value"><?= number_format((int)$summary['total_quantity_stocked']) ?></div>
                    <div class="sub">Units received into stock</div>
                </div>
                <div class="stat-card-icon"><i class="bi bi-boxes"></i></div>
            </div>
            <div class="stat-card blue">
                <div class="stat-card-left">
                    <div class="label">Distinct Products</div>
                    <div class="value"><?= number_format((int)$summary['total_distinct_products']) ?></div>
                    <div class="sub">Unique SKUs received</div>
                </div>
                <div class="stat-card-icon"><i class="bi bi-grid-3x3-gap"></i></div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-card-left">
                    <div class="label">Latest Batch</div>
                    <div class="value sm"><?= !empty($summary['latest_batch_date']) ? date('M d, Y', strtotime($summary['latest_batch_date'])) : '—' ?></div>
                    <div class="sub"><?= !empty($summary['latest_batch_date']) ? date('h:i A', strtotime($summary['latest_batch_date'])) : '' ?></div>
                </div>
                <div class="stat-card-icon"><i class="bi bi-calendar-event"></i></div>
            </div>
        </div>

        <!-- Table panel -->
        <div class="table-panel">

            <!-- Header -->
            <div class="table-panel-header">
                <div class="panel-title-block">
                    <p class="title">Stock-In Log</p>
                    <p class="subtitle">Showing <?= $totalBatches > 0 ? min($offset+1,$totalBatches) : 0 ?>–<?= min($offset+$limit,$totalBatches) ?> of <?= number_format($totalBatches) ?> records</p>
                </div>
                <span class="total-badge"><?= number_format($totalBatches) ?> total</span>
            </div>

            <!-- Search -->
            <div class="search-row">
                <form action="stock_in_batches.php" method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <input
                        type="text"
                        class="search-field"
                        name="q"
                        placeholder="Search reference no, supplier, user..."
                        value="<?= e($searchQuery) ?>"
                        style="width:300px;"
                    >
                    <button class="search-btn-main" type="submit"><i class="bi bi-search"></i> Search</button>
                    <?php if ($searchQuery !== ''): ?>
                        <a href="stock_in_batches.php" class="clear-btn"><i class="bi bi-x"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <div style="overflow-x:auto;">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date &amp; Time</th>
                            <th>Reference No.</th>
                            <th>Supplier</th>
                            <th>Items</th>
                            <th>Total Qty</th>
                            <th>Stocked By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="id-chip">#<?= (int)$row['batch_id'] ?></span></td>
                                    <td>
                                        <?php if (!empty($row['stock_in_date'])): ?>
                                            <div class="date-primary"><?= date('F j, Y g:ia', strtotime($row['stock_in_date'])) ?></div>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td><span class="ref-code"><?= e($row['reference_no']) ?></span></td>
                                    <td>
                                        <div class="product-name"><?= e($row['supplier_name'] ?? 'N/A') ?></div>
                                    </td>
                                    <td>
                                        <span class="qty-badge green">+ <?= (int)$row['distinct_items'] ?> item<?= (int)$row['distinct_items'] !== 1 ? 's' : '' ?></span>
                                    </td>
                                    <td>
                                        <span class="qty-badge green">+ <?= number_format((int)$row['total_quantity']) ?> units</span>
                                    </td>
                                    <td>
                                        <div class="staff-cell"><i class="bi bi-person-fill"></i> <?= e($row['staff_name'] ?: $row['stocked_by']) ?></div>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="view-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#batchDetailsModal"
                                            data-batch-id="<?= (int)$row['batch_id'] ?>"
                                            data-reference-no="<?= e($row['reference_no']) ?>"
                                        >
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr class="empty-row">
                                <td colspan="8">
                                    <i class="bi bi-inbox empty-icon"></i>
                                    <?= $searchQuery !== '' ? 'No batches matched your search.' : 'No stock-in batches recorded yet.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1):
                $startLoop = max(1, $page - 2);
                $endLoop   = min($totalPages, $page + 2);
            ?>
            <div class="pag-wrap">
                <div class="pag-info">
                    Showing <strong><?= $totalBatches > 0 ? min($offset+1,$totalBatches) : 0 ?></strong>–<strong><?= min($offset+$limit,$totalBatches) ?></strong>
                    of <strong><?= number_format($totalBatches) ?></strong> batches
                </div>
                <div class="pag-pages">
                    <a class="pag-btn <?= $page<=1?'disabled':'' ?>" href="<?= buildStockInPaginationUrl($page-1,$_GET) ?>">
                        <i class="bi bi-chevron-left" style="font-size:.7rem;"></i>
                    </a>
                    <?php if ($startLoop > 1): ?>
                        <a class="pag-btn" href="<?= buildStockInPaginationUrl(1,$_GET) ?>">1</a>
                        <?php if ($startLoop > 2): ?><span class="pag-sep">…</span><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $startLoop; $i <= $endLoop; $i++): ?>
                        <a class="pag-btn <?= $i==$page?'active':'' ?>" href="<?= buildStockInPaginationUrl($i,$_GET) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($endLoop < $totalPages): ?>
                        <?php if ($endLoop < $totalPages - 1): ?><span class="pag-sep">…</span><?php endif; ?>
                        <a class="pag-btn" href="<?= buildStockInPaginationUrl($totalPages,$_GET) ?>"><?= $totalPages ?></a>
                    <?php endif; ?>
                    <a class="pag-btn <?= $page>=$totalPages?'disabled':'' ?>" href="<?= buildStockInPaginationUrl($page+1,$_GET) ?>">
                        <i class="bi bi-chevron-right" style="font-size:.7rem;"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /.table-panel -->
    </div><!-- /.main-content -->
</div>

<!-- Modal -->
<div class="modal fade" id="batchDetailsModal" tabindex="-1" aria-labelledby="batchDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-wide modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <div class="modal-header modal-header-glass">
                <h5 class="modal-title" id="batchDetailsModalLabel">
                    <i class="bi bi-box-seam me-2"></i> Batch Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="modal-body-content" id="batchDetailsContent">
                    <div class="loading-box">
                        <div class="spinner-border" role="status"></div>
                        <div>Loading batch details...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('show');
});
document.addEventListener('DOMContentLoaded', function () {
    const modal   = document.getElementById('batchDetailsModal');
    const content = document.getElementById('batchDetailsContent');
    const label   = document.getElementById('batchDetailsModalLabel');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (e) {
        const btn  = e.relatedTarget;
        const id   = btn.getAttribute('data-batch-id');
        const ref  = btn.getAttribute('data-reference-no');
        label.innerHTML = `<i class="bi bi-box-seam me-2"></i> Batch Details — ${ref}`;
        content.innerHTML = `<div class="loading-box"><div class="spinner-border" role="status"></div><div>Loading batch details...</div></div>`;
        fetch(`stock_in_batch_modal.php?fetch=1&batch_id=${id}`)
            .then(r => r.text())
            .then(html => { content.innerHTML = html; })
            .catch(err => {
                content.innerHTML = `<div class="alert alert-danger m-3">Failed to load batch details.</div>`;
                console.error(err);
            });
    });

    modal.addEventListener('hidden.bs.modal', function () {
        label.innerHTML = `<i class="bi bi-box-seam me-2"></i> Batch Details`;
        content.innerHTML = `<div class="loading-box"><div class="spinner-border" role="status"></div><div>Loading batch details...</div></div>`;
    });
});
</script>
</body>
</html>