<?php
include 'project.php';
session_start();

// ===================== AUTH CHECK =====================
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
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
        note LIKE ? OR
        u.full_name LIKE ?
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
            CONCAT('Ref: ', COALESCE(sib.reference_no, '-')) AS note,
            COUNT(sh.product_id) AS item_count,
            COALESCE(SUM(sh.quantity),0) AS total_quantity
        FROM stock_in_batches sib
        JOIN stock_history sh ON sh.batch_id = sib.batch_id
        GROUP BY sib.batch_id

        UNION ALL

        SELECT
            'STOCK OUT' AS trx_type,
            so.id AS batch_id,
            so.created_at,
            so.stocked_by,
            CONCAT('Reason: ', COALESCE(so.reason, '-')) AS note,
            COUNT(so.product_id) AS item_count,
            COALESCE(SUM(so.quantity),0) AS total_quantity
        FROM stock_out so
        GROUP BY so.id

        UNION ALL

        SELECT
            'RETURN' AS trx_type,
            r.return_id AS batch_id,
            r.created_at,
            r.processed_by AS stocked_by,
            CONCAT('Sale #', r.original_sale_group_id, ': ', COALESCE(r.return_reason, '-')) AS note,
            COUNT(ri.product_id) AS item_count,
            COALESCE(SUM(ri.quantity), 0) AS total_quantity
        FROM returns r
        JOIN return_items ri ON r.return_id = ri.return_id
        GROUP BY r.return_id
    ) AS t
";

// ===================== COUNT QUERY =====================
$count_sql = "SELECT COUNT(*) AS total_records FROM ($base_query) AS count_table LEFT JOIN users u ON count_table.stocked_by = u.username $where_sql";
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
$fetch_sql = "SELECT count_table.*, u.full_name FROM ($base_query) AS count_table LEFT JOIN users u ON count_table.stocked_by = u.username $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
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
    LEFT JOIN users u ON summary_table.stocked_by = u.username
    $summary_where_in
";
$summary_stmt_in = $conn->prepare($summary_sql_in);

if ($summary_stmt_in) {
    if (!empty($params)) {
        $summary_stmt_in->bind_param($types, ...$params);
    }
    $summary_stmt_in->execute();
    $summary_result_in = $summary_stmt_in->get_result();
    $summary_stock_in = (float)($summary_result_in->fetch_assoc()['total_items'] ?? 0);
    $summary_stmt_in->close();
}

$summary_sql_out = "
    SELECT COALESCE(SUM(item_count),0) AS total_items
    FROM ($base_query) AS summary_table
    LEFT JOIN users u ON summary_table.stocked_by = u.username
    $summary_where_out
";
$summary_stmt_out = $conn->prepare($summary_sql_out);

if ($summary_stmt_out) {
    if (!empty($params)) {
        $summary_stmt_out->bind_param($types, ...$params);
    }
    $summary_stmt_out->execute();
    $summary_result_out = $summary_stmt_out->get_result();
    $summary_stock_out = (float)($summary_result_out->fetch_assoc()['total_items'] ?? 0);
    $summary_stmt_out->close();
}

function buildPaginationUrl($page, $get_params) {
    unset($get_params['page']);
    $get_params['page'] = $page;
    return 'product_history.php?' . http_build_query($get_params);
}

function getActionBadgeClass($action) {
    $action = strtoupper($action);
    if (strpos($action, 'CREATE') !== false) return 'bg-success';
    if (strpos($action, 'UPDATE') !== false) return 'bg-warning text-dark';
    if (strpos($action, 'DELETE') !== false) return 'bg-danger';
    if (strpos($action, 'STOCK IN') !== false) return 'bg-primary';
    if (strpos($action, 'STOCK OUT') !== false) return 'bg-danger';
    return 'bg-secondary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product History</title>
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
            --border:     #e2e8f0;
            --border-strong: #cbd5e1;
            --text-primary:   #0f172a;
            --text-secondary: #475569;
            --text-muted:     #94a3b8;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
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
        .page-header { display: flex; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem; }
        .page-header-icon {
            width: 42px; height: 42px;
            border-radius: var(--radius-md);
            background: var(--brand-primary-light);
            color: var(--brand-primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            margin-right: 0.85rem;
            flex-shrink: 0;
        }
        .page-header-left h4 {
            font-size: 1.4rem; font-weight: 800;
            color: var(--text-primary); margin: 0; letter-spacing: -0.3px;
        }
        .page-header-left p { font-size: 0.82rem; color: var(--text-muted); margin: 3px 0 0; }

        /* ── KPI Cards ── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 1100px) { .kpi-grid { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 576px)  { .kpi-grid { grid-template-columns: 1fr; } }

        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 1.1rem 1.25rem 1rem;
            position: relative;
            overflow: hidden;
            transition: box-shadow .2s, transform .2s;
        }
        .kpi-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .kpi-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        .kpi-card.kpi-slate::before  { background: var(--brand-slate); }
        .kpi-card.kpi-blue::before   { background: var(--brand-primary); }
        .kpi-card.kpi-red::before    { background: var(--brand-danger); }
        .kpi-card.kpi-amber::before  { background: var(--brand-amber); }

        .kpi-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.55rem; }
        .kpi-icon {
            width: 36px; height: 36px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem;
        }
        .kpi-icon.slate { background: var(--brand-slate-light); color: var(--brand-slate); }
        .kpi-icon.blue  { background: var(--brand-primary-light); color: var(--brand-primary); }
        .kpi-icon.red   { background: var(--brand-danger-light);  color: var(--brand-danger); }
        .kpi-icon.amber { background: var(--brand-amber-light);   color: var(--brand-amber); }

        .kpi-label { font-size: 0.73rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.55px; color: var(--text-muted); margin-bottom: 0.2rem; }
        .kpi-value { font-size: 1.7rem; font-weight: 800; color: var(--text-primary); line-height: 1.1; letter-spacing: -0.5px; font-family: 'DM Mono', monospace; }
        .kpi-sub   { font-size: 0.74rem; color: var(--text-muted); margin-top: 0.35rem; }

        /* ── Filter card ── */
        .filter-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 1.25rem 1.5rem 1.4rem;
            margin-bottom: 1.5rem;
        }
        .filter-title {
            font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.8px;
            color: var(--text-muted); margin-bottom: 1rem;
        }
        .filter-card .form-label {
            font-size: 0.76rem; font-weight: 600;
            color: var(--text-secondary); margin-bottom: 0.3rem; display: block;
        }
        .filter-card .form-control,
        .filter-card .form-select {
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            color: var(--text-primary);
            background-color: var(--surface-2);
            height: 40px;
            padding: 0.45rem 0.85rem;
            transition: border-color .15s, box-shadow .15s;
        }
        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
            outline: none;
            background-color: #fff;
        }
        .btn-filter {
            background: var(--brand-primary); color: #fff; border: none;
            border-radius: var(--radius-sm); font-size: 0.875rem; font-weight: 600;
            padding: 0 1.1rem; height: 40px;
            display: inline-flex; align-items: center; gap: 6px;
            transition: background .15s, transform .1s;
            white-space: nowrap; cursor: pointer;
        }
        .btn-filter:hover { background: #1d4ed8; transform: translateY(-1px); }
        .btn-clear {
            background: transparent; color: var(--text-secondary);
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm); font-size: 0.875rem; font-weight: 500;
            padding: 0 1rem; height: 40px;
            display: inline-flex; align-items: center; gap: 6px;
            text-decoration: none;
            transition: border-color .15s, color .15s, background .15s;
            white-space: nowrap;
        }
        .btn-clear:hover { border-color: var(--brand-primary); color: var(--brand-primary); background: var(--brand-primary-light); }

        /* ── Table card ── */
        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .table-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.95rem 1.4rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }
        .table-card-title { font-size: 0.93rem; font-weight: 700; color: var(--text-primary); }
        .table-card-meta  { font-size: 0.75rem; color: var(--text-muted); margin-top: 1px; }
        .records-badge {
            font-size: 0.75rem; font-weight: 600;
            background: var(--surface-3); color: var(--text-secondary);
            border: 1px solid var(--border-strong);
            border-radius: 999px; padding: 3px 12px;
            font-family: 'DM Mono', monospace;
        }

        /* History table */
        .history-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .history-table thead th {
            background: var(--surface-3);
            font-size: 0.71rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--text-muted);
            padding: 0.65rem 0.9rem;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .history-table thead th:first-child { padding-left: 1.4rem; }
        .history-table thead th:last-child  { text-align: center; padding-right: 1.4rem; }

        .history-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .12s;
        }
        .history-table tbody tr:last-child { border-bottom: none; }
        .history-table tbody tr:hover { background: #f8fafd; }

        .history-table td {
            padding: 0.72rem 0.9rem;
            font-size: 0.86rem;
            vertical-align: middle;
        }
        .history-table td:first-child { padding-left: 1.4rem; }
        .history-table td:last-child  { text-align: center; padding-right: 1.4rem; }

        /* Date/time cell */
        .td-date-main { font-weight: 600; color: var(--text-primary); font-size: 0.83rem; }
        .td-date-time { font-size: 0.74rem; color: var(--text-muted); margin-top: 1px; font-family: 'DM Mono', monospace; }

        /* Batch cell */
        .batch-pill {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--brand-primary-light); color: var(--brand-primary);
            font-size: 0.76rem; font-weight: 700; font-family: 'DM Mono', monospace;
            border-radius: 6px; padding: 3px 9px;
        }

        /* Type badges */
        .type-badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.73rem; font-weight: 700;
            border-radius: 6px; padding: 4px 10px;
            white-space: nowrap;
            letter-spacing: 0.2px;
        }
        .type-badge.in  { background: var(--brand-success-light); color: var(--brand-success); }
        .type-badge.out { background: var(--brand-danger-light);  color: var(--brand-danger); }

        /* User cell */
        .user-wrap { display: flex; align-items: center; gap: 7px; }
        .user-avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: var(--surface-3);
            color: var(--text-muted);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .user-name { font-size: 0.84rem; font-weight: 500; color: var(--text-primary); }

        /* Number cells */
        .num-val {
            font-family: 'DM Mono', monospace;
            font-weight: 600; font-size: 0.88rem;
            color: var(--text-primary);
        }

        /* Note cell */
        .note-text { font-size: 0.8rem; color: var(--text-secondary); max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* View button */
        .btn-view {
            display: inline-flex; align-items: center; gap: 5px;
            background: transparent;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-size: 0.78rem; font-weight: 600;
            padding: 5px 12px;
            cursor: pointer;
            transition: background .13s, border-color .13s, color .13s;
        }
        .btn-view:hover { background: var(--brand-primary-light); border-color: var(--brand-primary); color: var(--brand-primary); }

        /* Empty state */
        .empty-state { text-align: center; padding: 3.5rem 1rem; }
        .empty-state i { font-size: 2.5rem; color: var(--text-muted); opacity: 0.4; display: block; margin-bottom: 0.75rem; }
        .empty-state p { font-size: 0.9rem; color: var(--text-muted); margin: 0; }

        /* ── Pagination ── */
        .pagination-wrap {
            display: flex; flex-direction: column; align-items: center;
            gap: 0.5rem; padding: 1rem 0 0.5rem;
        }
        .pagination-info { font-size: 0.78rem; color: var(--text-muted); }
        .pg-list { display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; flex-wrap: wrap; justify-content: center; }
        .pg-list .pg-item a,
        .pg-list .pg-item span {
            display: flex; align-items: center; justify-content: center;
            min-width: 34px; height: 34px; padding: 0 6px;
            border-radius: var(--radius-sm);
            font-size: 0.82rem; font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border-strong);
            color: var(--text-secondary);
            background: var(--surface);
            transition: background .13s, border-color .13s, color .13s;
        }
        .pg-list .pg-item a:hover { background: var(--brand-primary-light); border-color: var(--brand-primary); color: var(--brand-primary); }
        .pg-list .pg-item.active a { background: var(--brand-primary); border-color: var(--brand-primary); color: #fff; }
        .pg-list .pg-item.disabled span { background: var(--surface-3); color: var(--text-muted); cursor: not-allowed; }

        /* ── Modal improvements ── */
        .modal-content { border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); font-family: 'Plus Jakarta Sans', sans-serif; overflow: hidden; }
        .modal-header-custom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.4rem;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }
        .modal-header-custom .modal-title {
            font-size: 0.93rem; font-weight: 700; color: #fff;
            display: flex; align-items: center; gap: 8px;
        }
        .modal-header-custom .modal-title i { color: #60a5fa; }
        .modal-header-custom .btn-close-custom {
            background: rgba(255,255,255,0.12); border: none;
            color: rgba(255,255,255,0.8); border-radius: var(--radius-sm);
            width: 28px; height: 28px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 0.85rem;
            transition: background .13s, color .13s;
        }
        .modal-header-custom .btn-close-custom:hover { background: rgba(255,255,255,0.2); color: #fff; }
        .modal-body { padding: 1.25rem 1.4rem; }
        .modal-footer-custom {
            display: flex; align-items: center; justify-content: flex-end; gap: 8px;
            padding: 0.85rem 1.4rem;
            border-top: 1px solid var(--border);
            background: var(--surface-2);
        }
        .btn-modal-close {
            background: transparent; border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm); color: var(--text-secondary);
            font-size: 0.82rem; font-weight: 600;
            padding: 6px 16px; cursor: pointer;
            transition: background .13s;
        }
        .btn-modal-close:hover { background: var(--surface-3); }
        .btn-modal-print {
            background: var(--brand-primary); border: none;
            border-radius: var(--radius-sm); color: #fff;
            font-size: 0.82rem; font-weight: 600;
            padding: 6px 16px; cursor: pointer;
            display: inline-flex; align-items: center; gap: 5px;
            transition: background .13s;
        }
        .btn-modal-print:hover { background: #1d4ed8; }

        /* Loading spinner */
        .loading-state { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); }
        .spinner { width: 28px; height: 28px; border: 3px solid var(--border); border-top-color: var(--brand-primary); border-radius: 50%; animation: spin .65s linear infinite; margin: 0 auto 0.75rem; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Sidebar toggle */
        .sidebar-toggle-btn {
            display: none; background: var(--brand-primary); color: #fff;
            border: none; border-radius: var(--radius-sm);
            padding: 7px 12px; font-size: 1.1rem; cursor: pointer;
            margin-bottom: 1rem;
        }
        @media (max-width: 991px) { .sidebar-toggle-btn { display: inline-flex; align-items: center; } }
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
            <li class="nav-item mb-2"><a class="nav-link active" href="product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>
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
            <div class="page-header-icon"><i class="bi bi-clock-history"></i></div>
            <div class="page-header-left">
                <h4>Product &amp; Stock History</h4>
                <p>Monitor all stock-in and stock-out transactions in one place.</p>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-slate">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Total Records</div>
                        <div class="kpi-value"><?= number_format($summary_total) ?></div>
                    </div>
                    <div class="kpi-icon slate"><i class="bi bi-journal-text"></i></div>
                </div>
                <div class="kpi-sub">All transactions in range</div>
            </div>

            <div class="kpi-card kpi-blue">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Stock In Items</div>
                        <div class="kpi-value">
                            <?php 
                            echo ($summary_stock_in == (int)$summary_stock_in) ? number_format((int)$summary_stock_in) : rtrim(rtrim(number_format($summary_stock_in, 4), '0'), '.');
                            ?>
                        </div>
                    </div>
                    <div class="kpi-icon blue"><i class="bi bi-box-arrow-in-down"></i></div>
                </div>
                <div class="kpi-sub">Items added to inventory</div>
            </div>

            <div class="kpi-card kpi-red">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Stock Out Items</div>
                        <div class="kpi-value">
                            <?php 
                            echo ($summary_stock_out == (int)$summary_stock_out) ? number_format((int)$summary_stock_out) : rtrim(rtrim(number_format($summary_stock_out, 4), '0'), '.');
                            ?>
                        </div>
                    </div>
                    <div class="kpi-icon red"><i class="bi bi-box-arrow-up"></i></div>
                </div>
                <div class="kpi-sub">Items removed from inventory</div>
            </div>

            <div class="kpi-card kpi-amber">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Total Batches</div>
                        <div class="kpi-value"><?= number_format($total_records) ?></div>
                    </div>
                    <div class="kpi-icon amber"><i class="bi bi-layers"></i></div>
                </div>
                <div class="kpi-sub">Unique batch transactions</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <div class="filter-title"><i class="bi bi-sliders2 me-1"></i> Filter Logs</div>
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-xl-2 col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-xl-2 col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-xl-2 col-md-4">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="STOCK IN"  <?= ($type_filter === 'STOCK IN')  ? 'selected' : '' ?>>Stock In</option>
                        <option value="STOCK OUT" <?= ($type_filter === 'STOCK OUT') ? 'selected' : '' ?>>Stock Out</option>
                    </select>
                </div>
                <div class="col-xl-4 col-md-8">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                        placeholder="Search by batch ID, user, note…"
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-xl-2 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn-filter flex-grow-1">
                        <i class="bi bi-funnel-fill"></i> Filter
                    </button>
                    <a href="product_history.php" class="btn-clear">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div>
                    <div class="table-card-title">History Records</div>
                    <div class="table-card-meta">Showing <?= $result ? $result->num_rows : 0 ?> of <?= number_format($total_records) ?> records</div>
                </div>
                <span class="records-badge"><?= number_format($total_records) ?> total</span>
            </div>

            <div class="table-responsive">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Batch</th>
                            <th>Type</th>
                            <th>User</th>
                            <th style="text-align:center;">Items</th>
                            <th style="text-align:center;">Total Qty</th>
                            <th>Note</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                    $dt = !empty($row['created_at']) ? strtotime($row['created_at']) : null;
                                    $isIn = ($row['trx_type'] === 'STOCK IN');
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($dt): ?>
                                            <div class="td-date-main"><?= date('M d, Y', $dt) ?></div>
                                            <div class="td-date-time"><?= date('h:i A', $dt) ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="batch-pill">
                                            <i class="bi bi-hash" style="font-size:.7rem;"></i><?= (int)$row['batch_id'] ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="type-badge <?= $isIn ? 'in' : 'out' ?>">
                                            <i class="bi <?= $isIn ? 'bi-arrow-down-circle-fill' : 'bi-arrow-up-circle-fill' ?>"></i>
                                            <?= htmlspecialchars($row['trx_type']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="user-wrap">
                                            <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                                            <span class="user-name"><?= htmlspecialchars($row['full_name'] ?: $row['stocked_by'] ?: '—') ?></span>
                                        </div>
                                    </td>

                                    <td style="text-align:center;">
                                        <span class="num-val"><?= number_format((int)($row['item_count'] ?? 0)) ?></span>
                                    </td>

                                    <td style="text-align:center;">
                                        <span class="num-val">
                                            <?= formatQty($row['total_quantity'] ?? 0) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="note-text" title="<?= htmlspecialchars($row['note'] ?? '') ?>">
                                            <?= htmlspecialchars($row['note'] ?? '—') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <button class="btn-view"
                                            onclick="viewBatchDetails('<?= $isIn ? 'stock_in' : 'stock_out' ?>', <?= (int)$row['batch_id'] ?>)">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <p>No history records found for the selected filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <ul class="pg-list">
                <li class="pg-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <?php if ($page > 1): ?>
                        <a href="<?= buildPaginationUrl($page - 1, $_GET) ?>"><i class="bi bi-chevron-left" style="font-size:.7rem;"></i></a>
                    <?php else: ?>
                        <span><i class="bi bi-chevron-left" style="font-size:.7rem;"></i></span>
                    <?php endif; ?>
                </li>

                <?php
                $start_page = max(1, $page - 2);
                $end_page   = min($total_pages, $page + 2);

                if ($start_page > 1) {
                    echo '<li class="pg-item"><a href="' . buildPaginationUrl(1, $_GET) . '">1</a></li>';
                    if ($start_page > 2) echo '<li class="pg-item disabled"><span>…</span></li>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="pg-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a href="<?= buildPaginationUrl($i, $_GET) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<li class="pg-item disabled"><span>…</span></li>';
                    echo '<li class="pg-item"><a href="' . buildPaginationUrl($total_pages, $_GET) . '">' . $total_pages . '</a></li>';
                }
                ?>

                <li class="pg-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <?php if ($page < $total_pages): ?>
                        <a href="<?= buildPaginationUrl($page + 1, $_GET) ?>"><i class="bi bi-chevron-right" style="font-size:.7rem;"></i></a>
                    <?php else: ?>
                        <span><i class="bi bi-chevron-right" style="font-size:.7rem;"></i></span>
                    <?php endif; ?>
                </li>
            </ul>
            <div class="pagination-info">Page <?= $page ?> of <?= $total_pages ?></div>
        </div>
        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /d-flex -->

<!-- Batch Details Modal -->
<div class="modal fade" id="batchDetailsModal" tabindex="-1" aria-labelledby="batchDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header-custom">
                <div class="modal-title" id="batchDetailsModalLabel">
                    <i class="bi bi-layers-half"></i> Batch Details
                </div>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body" id="batchDetailsContent">
                <div class="loading-state">
                    <div class="spinner"></div>
                    <div style="font-size:.85rem;">Loading batch details…</div>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modal-close" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn-modal-print" id="printBatchBtn">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('show');
});

function viewBatchDetails(type, batchId) {
    const modal   = new bootstrap.Modal(document.getElementById('batchDetailsModal'));
    const content = document.getElementById('batchDetailsContent');
    const title   = document.getElementById('batchDetailsModalLabel');

    title.innerHTML = `<i class="bi bi-layers-half"></i> Batch #${batchId} &mdash; ${type === 'stock_in' ? 'Stock In' : 'Stock Out'}`;
    content.innerHTML = `
        <div class="loading-state">
            <div class="spinner"></div>
            <div style="font-size:.85rem;">Loading batch details…</div>
        </div>`;
    modal.show();

    fetch(`fetch_batch_details.php?type=${encodeURIComponent(type)}&batch_id=${encodeURIComponent(batchId)}`)
        .then(r => r.text())
        .then(html => content.innerHTML = html)
        .catch(() => {
            content.innerHTML = `<div style="padding:1.5rem;color:#dc2626;font-size:.875rem;display:flex;align-items:center;gap:8px;">
                <i class="bi bi-exclamation-triangle-fill"></i> Failed to load batch details. Please try again.</div>`;
        });

    document.getElementById('printBatchBtn').onclick = function () {
        const win = window.open('', '_blank');
        win.document.write(`<html><head><title>Batch ${batchId}</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
            <style>body{font-family:'Plus Jakarta Sans',sans-serif;padding:24px;}</style>
            </head><body>${content.innerHTML}</body></html>`);
        win.document.close();
        win.print();
    };
}
</script>
</body>
</html>