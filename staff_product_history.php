<?php
include 'project.php';
session_start();

// ===================== AUTH CHECK =====================
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header("Location: login.php");
    exit();
}

// ===================== PAGINATION =====================
$limit = 15;
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// ===================== FILTERS =====================
$where_clauses = [];
$params = [];
$types = "";

// Search
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $search_term = "%{$search}%";
    $where_clauses[] = "(
        CAST(batch_id AS CHAR) LIKE ? OR
        stocked_by LIKE ? OR
        full_name LIKE ? OR
        note LIKE ?
    )";
    $params = array_merge($params, [
        $search_term,
        $search_term,
        $search_term,
        $search_term
    ]);
    $types .= "ssss";
}

// Type filter (Stock In / Stock Out)
$type_filter = trim($_GET['type'] ?? '');
if ($type_filter !== '') {
    $where_clauses[] = "trx_type = ?";
    $params[] = strtoupper($type_filter);
    $types .= "s";
}

// Date range
$start_date = trim($_GET['start_date'] ?? '');
$end_date   = trim($_GET['end_date'] ?? '');

if ($start_date !== '' && $end_date !== '') {
    $where_clauses[] = "DATE(created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
} elseif ($start_date !== '') {
    $where_clauses[] = "DATE(created_at) >= ?";
    $params[] = $start_date;
    $types .= "s";
} elseif ($end_date !== '') {
    $where_clauses[] = "DATE(created_at) <= ?";
    $params[] = $end_date;
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// ===================== BASE QUERY =====================
$base_query = "
    SELECT * FROM (
        SELECT
            'STOCK IN' AS trx_type,
            sib.batch_id AS batch_id,
            sib.stock_in_date AS created_at,
            sib.stocked_by,
            u.full_name,
            CONCAT('Ref: ', COALESCE(sib.reference_no, '-')) AS note,
            COUNT(sh.product_id) AS item_count,
            COALESCE(SUM(sh.quantity),0) AS total_quantity
        FROM stock_in_batches sib
        JOIN stock_history sh ON sh.batch_id = sib.batch_id
        LEFT JOIN users u ON sib.stocked_by = u.username
        GROUP BY sib.batch_id

        UNION ALL

        SELECT
            'STOCK OUT' AS trx_type,
            so.id AS batch_id,
            so.created_at,
            so.stocked_by,
            u.full_name,
            CONCAT('Reason: ', COALESCE(so.reason, '-')) AS note,
            COUNT(so.product_id) AS item_count,
            COALESCE(SUM(so.quantity),0) AS total_quantity
        FROM stock_out so
        LEFT JOIN users u ON so.stocked_by = u.username
        GROUP BY so.id

        UNION ALL

        SELECT
            'RETURN' AS trx_type,
            r.return_id AS batch_id,
            r.created_at,
            r.processed_by AS stocked_by,
            u.full_name,
            CONCAT('Sale #', r.original_sale_group_id, ': ', COALESCE(r.return_reason, '-')) AS note,
            COUNT(ri.product_id) AS item_count,
            COALESCE(SUM(ri.quantity), 0) AS total_quantity
        FROM returns r
        JOIN return_items ri ON r.return_id = ri.return_id
        LEFT JOIN users u ON r.processed_by = u.username
        GROUP BY r.return_id
    ) AS t
";

// ===================== COUNT QUERY =====================
$count_sql = "SELECT COUNT(*) AS total_records FROM ($base_query) AS count_table $where_sql";
$count_stmt = $conn->prepare($count_sql);

if (!$count_stmt) {
    die("Count query preparation failed: " . $conn->error);
}

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = 0;
if ($count_result && $row_count = $count_result->fetch_assoc()) {
    $total_records = (int)$row_count['total_records'];
}
$count_stmt->close();

$total_pages = max(1, ceil($total_records / $limit));

// ===================== FETCH QUERY =====================
$fetch_sql = "$base_query $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($fetch_sql);

if (!$stmt) {
    die("Fetch query preparation failed: " . $conn->error);
}

$fetch_params = $params;
$fetch_types = $types;

$fetch_params[] = $limit;
$fetch_params[] = $offset;
$fetch_types .= "ii";

$stmt->bind_param($fetch_types, ...$fetch_params);
$stmt->execute();
$result = $stmt->get_result();

// ===================== SUMMARY COUNTS =====================
$summary_total = $total_records;
$summary_stock_in = 0;
$summary_stock_out = 0;

$summary_where_in = $where_sql ? $where_sql . " AND trx_type = 'STOCK IN'" : "WHERE trx_type = 'STOCK IN'";
$summary_where_out = $where_sql ? $where_sql . " AND trx_type = 'STOCK OUT'" : "WHERE trx_type = 'STOCK OUT'";

$summary_sql_in = "
    SELECT COALESCE(SUM(item_count),0) AS total_items
    FROM ($base_query) AS summary_table
    $summary_where_in
";
$summary_stmt_in = $conn->prepare($summary_sql_in);

if ($summary_stmt_in) {
    if (!empty($params)) {
        $summary_stmt_in->bind_param($types, ...$params);
    }
    $summary_stmt_in->execute();
    $summary_result_in = $summary_stmt_in->get_result();
    $summary_stock_in = (int)($summary_result_in->fetch_assoc()['total_items'] ?? 0);
    $summary_stmt_in->close();
}

$summary_sql_out = "
    SELECT COALESCE(SUM(item_count),0) AS total_items
    FROM ($base_query) AS summary_table
    $summary_where_out
";
$summary_stmt_out = $conn->prepare($summary_sql_out);

if ($summary_stmt_out) {
    if (!empty($params)) {
        $summary_stmt_out->bind_param($types, ...$params);
    }
    $summary_stmt_out->execute();
    $summary_result_out = $summary_stmt_out->get_result();
    $summary_stock_out = (int)($summary_result_out->fetch_assoc()['total_items'] ?? 0);
    $summary_stmt_out->close();
}

function buildPaginationUrl($page, $get_params) {
    unset($get_params['page']);
    $get_params['page'] = $page;
    return 'staff_product_history.php?' . http_build_query($get_params);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product History - Staff</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
             /* --card-radius:  16px;
            --card-shadow:  0 2px 16px rgba(0,0,0,0.07); */
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
        /* Header Card */
        .product-header-card { background: transparent; border-radius: 12px; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: none; margin-bottom: 24px; border: none; }
        .ph-left { display: flex; align-items: center; gap: 16px; }
        .ph-icon { width: 52px; height: 52px; background: #3b82f6; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 24px; box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
        .ph-title { margin: 0; font-size: 1.35rem; font-weight: 700; color: #1e293b; letter-spacing: -0.01em; }
        .ph-subtitle { margin: 4px 0 0 0; font-size: 13px; color: #64748b; }

        /* KPI Cards */
        .kpi-card-new { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); border-top: 4px solid transparent; border-left: 1px solid rgba(0,0,0,0.03); border-right: 1px solid rgba(0,0,0,0.03); border-bottom: 1px solid rgba(0,0,0,0.03); height: 100%; transition: transform 0.2s; }
        .kpi-card-new:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.04); }
        .kpi-card-new.blue { border-top-color: #3b82f6; }
        .kpi-card-new.green { border-top-color: #10b981; }
        .kpi-card-new.orange { border-top-color: #f59e0b; }
        .kpi-card-new.red { border-top-color: #ef4444; }
        .kpi-card-new .kpi-title { font-size: 11px; font-weight: 700; text-transform: uppercase; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; letter-spacing: 0.05em; color: #64748b; }
        .kpi-card-new.blue .kpi-title i { color: #3b82f6; }
        .kpi-card-new.green .kpi-title i { color: #10b981; }
        .kpi-card-new.red .kpi-title i { color: #ef4444; }
        .kpi-card-new .kpi-value { font-size: 1.85rem; font-weight: 800; color: #0f172a; line-height: 1; margin-bottom: 8px; }
        .kpi-card-new .kpi-subtitle { font-size: 12px; color: #94a3b8; font-weight: 500; }

        /* Filter Card (Accordion Style to match Products) */
        .accordion-item { border: 1px solid rgba(0,0,0,0.03); background: #fff; border-radius: 12px !important; margin-bottom: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); overflow: hidden; }
        .accordion-button { font-size: 14.5px; font-weight: 700; color: #1e293b; padding: 18px 24px; background: #fff !important; box-shadow: none !important; display: flex; align-items: center; gap: 12px; }
        .accordion-button:not(.collapsed) { color: #1e293b; border-bottom: 1px solid #f1f5f9; }
        .accordion-button i { font-size: 18px; color: #3b82f6; }

        /* Table UI */
        .main-table-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); overflow: hidden; border: 1px solid rgba(0,0,0,0.03); margin-bottom: 24px; }
        .mtc-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; background: #fff; border-bottom: 1px solid #f1f5f9; }
        .mtc-title { font-size: 15px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; margin: 0; }
        .mtc-title i { color: #3b82f6; font-size: 18px; }
        .mtc-count { background: #f8fafc; color: #64748b; font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 20px; border: 1px solid #e2e8f0; }

        .table-new { border-collapse: separate; border-spacing: 0; margin: 0; width: 100%; border:0; }
        .table-new thead th { background: #fff; color: #0f172a; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px 24px; border-bottom: 2px solid #f1f5f9; }
        .table-new tbody td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 14px; color: #1e293b; }
        .table-new tbody tr:hover { background: #f8fafc; }
        .table-new tbody tr:last-child td { border-bottom: none; }

        .badge-type { font-size: 10px; font-weight: 800; padding: 5px 12px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.03em; }
        .badge-in { background: #eff6ff; color: #3b82f6; border: 1px solid #dbeafe; }
        .badge-out { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }

        .btn-view-details { background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 6px; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-view-details:hover { background: #f1f5f9; color: #1e293b; border-color: #cbd5e1; }

        /* Pagination */
        .pagination .page-link { border-radius: 8px; margin: 0 4px; font-weight: 600; font-size: 13px; color: #475569; border: 1px solid #e2e8f0; }
        .pagination .page-item.active .page-link { background-color: #3b82f6; border-color: #3b82f6; color: #fff; }
        .pagination .page-link:hover:not(.disabled) { background-color: #f1f5f9; }

        .modal-content { border-radius: 12px; border: 0; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .modal-header { border-bottom: 1px solid #f1f5f9; padding: 20px 24px; }
        .modal-footer { border-top: 1px solid #f1f5f9; padding: 16px 24px; }

        /* Custom Wide Modal */
        @media (min-width: 1400px) {
            .modal-wide { max-width: 1320px !important; }
        }
        .modal-header-glass {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            border-bottom: 0;
            border-radius: 12px 12px 0 0;
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

<div class="d-flex text-start">
    <!-- Sidebar -->
    <div class="sidebar flex-column p-0" id="sidebar">
        <div class="sidebar-logo text-center">
            <img src="images/logo.png" alt="Inventory Logo">
            <h5 class="mt-2 text-white">Staff Panel</h5>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="sidebar-title">Main</li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_add_sale.php"><i class="bi bi-cart-plus me-2"></i> Add Sale</a></li>

            <li class="sidebar-title">Operations</li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="purchased_history.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="staff_returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
            <li class="nav-item mb-2"><a class="nav-link active" href="staff_product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>

            <li class="sidebar-title">Others</li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Content -->
    <div class="content flex-grow-1">
        <div class="main-wrap">
            <div class="ph-left">
                    <div class="ph-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <h2 class="ph-title">Product History</h2>
                        <p class="ph-subtitle">Monitor product stock activities organized by batch</p>
                    </div>
                </div>
            <br>
            <!-- Dashboard Stats -->
            <div class="row g-4 mb-4 text-start">
                <div class="col-lg-3 col-md-6 text-start">
                    <div class="kpi-card-new blue text-start">
                        <div class="kpi-title text-start"><i class="bi bi-layers"></i> TOTAL BATCHES</div>
                        <div class="kpi-value text-start"><?= number_format($summary_total) ?></div>
                        <div class="kpi-subtitle">Transaction records</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 text-start">
                    <div class="kpi-card-new green text-start">
                        <div class="kpi-title text-start"><i class="bi bi-box-arrow-in-down"></i> STOCK IN ITEMS</div>
                        <div class="kpi-value text-start"><?= number_format($summary_stock_in) ?></div>
                        <div class="kpi-subtitle">Total units added</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 text-start">
                    <div class="kpi-card-new red text-start">
                        <div class="kpi-title text-start"><i class="bi bi-box-arrow-up"></i> STOCK OUT ITEMS</div>
                        <div class="kpi-value text-start"><?= number_format($summary_stock_out) ?></div>
                        <div class="kpi-subtitle">Total units removed</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 text-start">
                    <div class="kpi-card-new orange text-start">
                        <div class="kpi-title text-start"><i class="bi bi-activity"></i> TOTAL LOGS</div>
                        <div class="kpi-value text-start"><?= number_format($summary_stock_in + $summary_stock_out) ?></div>
                        <div class="kpi-subtitle">Total items moved</div>
                    </div>
                </div>
            </div>

            <!-- Filters Accordion -->
            <div class="accordion mb-4" id="filtersAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFilters">
                            <i class="bi bi-sliders"></i> Filter Records
                        </button>
                    </h2>
                    <div id="collapseFilters" class="accordion-collapse collapse" data-bs-parent="#filtersAccordion">
                        <div class="accordion-body border-top">
                            <form method="GET" class="row g-3 px-2 py-1">
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Start Date</label>
                                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">End Date</label>
                                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Type</label>
                                    <select name="type" class="form-select form-select-sm">
                                        <option value="">All Transactions</option>
                                        <option value="STOCK IN" <?= ($type_filter === 'STOCK IN') ? 'selected' : '' ?>>Stock In</option>
                                        <option value="STOCK OUT" <?= ($type_filter === 'STOCK OUT') ? 'selected' : '' ?>>Stock Out</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Search</label>
                                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search batch ID, user, or note..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <div class="col-md-2 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Apply</button>
                                    <a href="staff_product_history.php" class="btn btn-light btn-sm border w-100 fw-bold">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="main-table-card">
                <div class="mtc-header">
                    <h3 class="mtc-title"><i class="bi bi-list-task"></i> Batch Movement Logs</h3>
                    <div class="mtc-count"><?= number_format($total_records) ?> records</div>
                </div>
                <div class="table-responsive">
                    <table class="table-new">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Batch ID</th>
                                <th>Transaction Type</th>
                                <th class="text-center">Unique Items</th>
                                <th class="text-center">Total Qty</th>
                                <th>Note / Remarks</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-muted">
                                            <?= !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : '-' ?>
                                            <div style="font-size:11px;"><?= !empty($row['created_at']) ? date('h:i A', strtotime($row['created_at'])) : '' ?></div>
                                        </td>
                                        <td><span class="fw-bold text-primary">#<?= (int)$row['batch_id'] ?></span></td>
                                        <td>
                                            <span class="badge-type <?= ($row['trx_type'] === 'STOCK IN') ? 'badge-in' : 'badge-out' ?>">
                                                <?= htmlspecialchars($row['trx_type']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center fw-bold"><?= (int)($row['item_count'] ?? 0) ?></td>
                                        <td class="text-center">
                                            <span class="badge rounded-pill bg-light text-dark border px-3">
                                                <?php 
                                                $tq = (float)($row['total_quantity'] ?? 0);
                                                echo ($tq == (int)$tq) ? (int)$tq : rtrim(rtrim(number_format($tq, 4), '0'), '.');
                                                ?>
                                            </span>
                                        </td>
                                        <td class="text-muted" style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12px;">
                                            <?= htmlspecialchars($row['note'] ?? '-') ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-view-details" onclick="viewBatchDetails('<?= $row['trx_type'] === 'STOCK IN' ? 'stock_in' : 'stock_out' ?>', <?= (int)$row['batch_id'] ?>)">
                                                <i class="bi bi-eye"></i> View details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="py-4">
                                            <i class="bi bi-inbox fs-1 text-muted opacity-25 d-block mb-3"></i>
                                            <h5 class="text-secondary fw-bold">No Transaction Records Found</h5>
                                            <p class="text-muted small">Try adjusting your filters or search terms to find what you're looking for.</p>
                                            <a href="staff_product_history.php" class="btn btn-sm btn-outline-primary mt-2">Clear All Filters</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="p-4 border-top border-light">
                    <nav>
                        <ul class="pagination pagination-sm justify-content-center mb-1">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildPaginationUrl($page - 1, $_GET) ?>">&laquo;</a>
                            </li>
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= buildPaginationUrl($i, $_GET) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildPaginationUrl($page + 1, $_GET) ?>">&raquo;</a>
                            </li>
                        </ul>
                        <div class="text-center text-muted mt-2" style="font-size: 11px;">
                            Showing page <?= $page ?> of <?= $total_pages ?> (Total <?= number_format($total_records) ?> batches)
                        </div>
                    </nav>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Modal for details -->
<div class="modal fade" id="batchDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-wide modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0 overflow-hidden" style="border-radius: 12px;">
            <div class="modal-header border-0 p-4" style="background: #1e3a8a; color: #fff;">
                <h5 class="modal-title fw-bold" id="batchDetailsModalLabel">
                    <i class="bi bi-box-seam me-2"></i> Batch Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="batchDetailsContent" style="background: #f8fafc;">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 text-muted">Fetching batch data...</p>
                </div>
            </div>
            <div class="modal-footer border-0 p-3" style="background: #f8fafc;">
                <button type="button" class="btn btn-light border fw-bold px-4" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
                <button type="button" class="btn btn-primary fw-bold px-4" id="printBatchBtn" style="border-radius: 8px; background: #1e3a8a; border-color: #1e3a8a;">
                    <i class="bi bi-printer me-2"></i> Print Report
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function viewBatchDetails(type, batchId) {
        const modal = new bootstrap.Modal(document.getElementById('batchDetailsModal'));
        const content = document.getElementById('batchDetailsContent');
        const title = document.getElementById('batchDetailsModalLabel');

        title.innerHTML = `<i class="bi bi-info-circle me-2"></i> Batch #${batchId} Details (${type === 'stock_in' ? 'Stock In' : 'Stock Out'})`;
        content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3 text-muted">Fetching batch data...</p></div>';
        modal.show();

        fetch(`fetch_batch_details.php?type=${encodeURIComponent(type)}&batch_id=${encodeURIComponent(batchId)}`)
            .then(response => response.text())
            .then(html => content.innerHTML = html)
            .catch(error => {
                content.innerHTML = '<div class="alert alert-danger">Failed to load batch details. Please try again.</div>';
                console.error(error);
            });

        document.getElementById('printBatchBtn').onclick = function () {
            const startDate = document.querySelector('input[name="start_date"]')?.value;
            const endDate = document.querySelector('input[name="end_date"]')?.value;
            const selectedPeriod = startDate && endDate ? `${startDate} to ${endDate}` : startDate ? `From ${startDate}` : endDate ? `Until ${endDate}` : 'All Time';
            const selectedCategory = type === 'stock_in' ? 'Stock In' : 'Stock Out';

            const win = window.open('', '_blank');
            win.document.write('<html><head><title>Batch Report #' + batchId + '</title>');
            win.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">');
            win.document.write('<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">');
            win.document.write('<style>');
            win.document.write('body { font-family: "Plus Jakarta Sans", sans-serif; margin: 28px 32px; color: #0f172a; background: #f8fafc; }');
            win.document.write('.rpt-wrapper { width: 100%; max-width: 1080px; margin: 0 auto; background: #fff; padding: 28px 32px; border-radius: 16px; box-shadow: 0 18px 60px rgba(15, 23, 42, 0.08); }');
            win.document.write('.rpt-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; gap:16px; }');
            win.document.write('.rpt-brand { display:flex; align-items:center; gap:14px; }');
            win.document.write('.rpt-brand img { width:64px; height:auto; object-fit:contain; }');
            win.document.write('.rpt-title { font-size:22px; font-weight:800; letter-spacing:-0.3px; margin-bottom:3px; }');
            win.document.write('.rpt-sub { font-size:13px; color:#64748b; }');
            win.document.write('.rpt-info { font-size:12px; color:#64748b; line-height:1.9; text-align:right; }');
            win.document.write('.rpt-info strong { color: #0f172a; font-weight: 700; }');
            win.document.write('.divider { border:none; border-top:1px solid #e2e8f0; margin:18px 0; }');
            win.document.write('table { width:100%; border-collapse:collapse; }');
            win.document.write('thead th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; background:#f8fafc; padding:10px; border-bottom:1px solid #e2e8f0; }');
            win.document.write('tbody td { padding:10px; font-size:13px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }');
            win.document.write('.footer-msg { margin-top:30px; text-align:center; font-size:0.88rem; color:#64748b; }');
            win.document.write('@media print { body { background: none; margin: 0; padding: 0; } .rpt-wrapper { box-shadow: none; border-radius: 0; } .no-print { display: none !important; } }');
            win.document.write('</style>');
            win.document.write('</head><body>');
            win.document.write('<div class="rpt-wrapper">');
            win.document.write('<div class="rpt-header">');
            win.document.write('<div class="rpt-brand">');
            win.document.write('<img src="images/logo.png" alt="Logo">');
            win.document.write('<div>');
            win.document.write('<div class="rpt-title">Inventory System</div>');
            win.document.write('<div class="rpt-sub">Batch Movement Report</div>');
            win.document.write('</div>');
            win.document.write('</div>');
            win.document.write('<div class="rpt-info">');
            win.document.write('<div><strong>Printed:</strong> ' + new Date().toLocaleString() + '</div>');
            win.document.write('<div><strong>Period:</strong> ' + selectedPeriod + '</div>');
            win.document.write('<div><strong>Category:</strong> ' + selectedCategory + '</div>');
            win.document.write('</div>');
            win.document.write('</div>');
            win.document.write('<hr class="divider">');
            win.document.write(content.innerHTML);
            win.document.write('<div class="footer-msg">');
            win.document.write('<p>This report is generated from the Inventory System.</p>');
            win.document.write('<p><strong>K&J B HARDWARE & CONST. SUPPLIES</strong></p>');
            win.document.write('</div>');
            win.document.write('</div>');
            win.document.write('</body></html>');
            win.document.close();
            win.onload = function() {
                win.print();
            };
        };
    }
</script>
</body>
</html>