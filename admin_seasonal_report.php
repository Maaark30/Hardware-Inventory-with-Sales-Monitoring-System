<?php
include 'project.php';
session_start();

/* ============================================================
   AUTH CHECK (Admin Only)
   ============================================================ */
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

/* ============================================================
   1. CONFIGURATION
   ============================================================ */

// Available Years
$years_query = $conn->query("
    SELECT DISTINCT YEAR(created_at) AS year
    FROM sale_groups
    WHERE created_at IS NOT NULL
    ORDER BY year DESC
");
$available_years = [];
if ($years_query) {
    while ($row = $years_query->fetch_assoc()) {
        $available_years[] = (int)$row['year'];
    }
}

// Available Categories
$categories_query = $conn->query("
    SELECT category_id, category_name
    FROM categories
    ORDER BY category_name ASC
");
$available_categories = [];
if ($categories_query) {
    while ($row = $categories_query->fetch_assoc()) {
        $available_categories[] = $row;
    }
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$seasons = [
    'Q1'     => ['label' => 'Q1 (Jan - Mar)', 'months' => [1, 2, 3]],
    'Q2'     => ['label' => 'Q2 (Apr - Jun)', 'months' => [4, 5, 6]],
    'Q3'     => ['label' => 'Q3 (Jul - Sep)', 'months' => [7, 8, 9]],
    'Q4'     => ['label' => 'Q4 (Oct - Dec)', 'months' => [10, 11, 12]],
    'SUMMER' => ['label' => 'Summer Season (Mar - May)', 'months' => [3, 4, 5]],
    'BER'    => ['label' => 'Ber Months / Holiday (Sep - Dec)', 'months' => [9, 10, 11, 12]]
];

/* ============================================================
   2. HANDLE FILTERS + VALIDATION
   ============================================================ */

$filter_year = $_GET['year'] ?? 'all';
$filter_season = $_GET['season'] ?? 'all';
$filter_month = $_GET['month'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';

// Validate year
if ($filter_year !== 'all') {
    $filter_year = (int)$filter_year;
    if (!in_array($filter_year, $available_years, true)) {
        $filter_year = 'all';
    }
}

// Validate month
if ($filter_month !== 'all') {
    $filter_month = (int)$filter_month;
    if (!array_key_exists($filter_month, $months)) {
        $filter_month = 'all';
    }
}

// Validate season
if ($filter_season !== 'all' && !isset($seasons[$filter_season])) {
    $filter_season = 'all';
}

// Validate category
$valid_category_ids = array_map(fn($c) => (int)$c['category_id'], $available_categories);
if ($filter_category !== 'all') {
    $filter_category = (int)$filter_category;
    if (!in_array($filter_category, $valid_category_ids, true)) {
        $filter_category = 'all';
    }
}

$has_active_filters = (
    $filter_year !== 'all' ||
    $filter_season !== 'all' ||
    $filter_month !== 'all' ||
    $filter_category !== 'all'
);

/* ============================================================
   3. BUILD FILTERS
   ============================================================ */

$where_sql = " WHERE 1=1 ";
$params = [];
$types = "";
$report_title = "Best Sellers (All Years)";
$selected_period_label = "All Time";

// Year filter
if ($filter_year !== 'all') {
    $where_sql .= " AND YEAR(sg.created_at) = ?";
    $params[] = $filter_year;
    $types .= "i";
    $report_title = "Best Sellers for Year " . htmlspecialchars((string)$filter_year);
    $selected_period_label = (string)$filter_year;
}

// Category filter
$selected_category_name = "All Categories";
if ($filter_category !== 'all') {
    $where_sql .= " AND p.category_id = ?";
    $params[] = $filter_category;
    $types .= "i";

    foreach ($available_categories as $cat) {
        if ((int)$cat['category_id'] === $filter_category) {
            $selected_category_name = $cat['category_name'];
            break;
        }
    }
}

// Month or season filter
$comparison_mode = 'all';
$comparison_months = [];
$comparison_label = '';
$comparison_year = null;

if ($filter_month !== 'all') {
    $where_sql .= " AND MONTH(sg.created_at) = ?";
    $params[] = $filter_month;
    $types .= "i";

    $report_title .= " - " . $months[$filter_month];
    $selected_period_label = ($filter_year !== 'all' ? $filter_year . ' - ' : '') . $months[$filter_month];
    $comparison_mode = 'month';
    $comparison_months = [$filter_month];
    $comparison_label = $months[$filter_month];
} elseif ($filter_season !== 'all') {
    $season_months = $seasons[$filter_season]['months'];
    $in_placeholders = implode(',', array_fill(0, count($season_months), '?'));
    $where_sql .= " AND MONTH(sg.created_at) IN ($in_placeholders)";

    foreach ($season_months as $m) {
        $params[] = $m;
        $types .= "i";
    }

    $report_title .= " - " . $seasons[$filter_season]['label'];
    $selected_period_label = ($filter_year !== 'all' ? $filter_year . ' - ' : '') . $seasons[$filter_season]['label'];
    $comparison_mode = 'season';
    $comparison_months = $season_months;
    $comparison_label = $seasons[$filter_season]['label'];
}

/* ============================================================
   4. MAIN TABLE QUERY
   ============================================================ */

$query = "
    SELECT
        p.product_id,
        p.product_name,
        p.brand,
        p.variation,
        COALESCE(SUM(s.quantity), 0) AS total_sold,
        COALESCE(SUM(s.total_price), 0) AS total_revenue,
        (
            SELECT GROUP_CONCAT(DISTINCT sup.supplier_name ORDER BY sup.supplier_name SEPARATOR ', ')
            FROM stock_history sh
            LEFT JOIN suppliers sup ON sh.supplier_id = sup.supplier_id
            WHERE sh.product_id = p.product_id
        ) AS suppliers
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
    $where_sql
    GROUP BY p.product_id, p.product_name, p.brand, p.variation
    ORDER BY total_sold DESC, total_revenue DESC
    LIMIT 15
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$sales_data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

/* ============================================================
   5. SUMMARY CARDS
   ============================================================ */

// Total unique products sold
$summary_products_sql = "
    SELECT COUNT(DISTINCT p.product_id) AS total_products
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
    $where_sql
";
$stmt = $conn->prepare($summary_products_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$summary_products = $stmt->get_result()->fetch_assoc();
$total_products_sold = (int)($summary_products['total_products'] ?? 0);
$stmt->close();

// Total units + revenue
$summary_totals_sql = "
    SELECT
        COALESCE(SUM(s.quantity), 0) AS total_units_sold,
        COALESCE(SUM(s.total_price), 0) AS total_revenue
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
    $where_sql
";
$stmt = $conn->prepare($summary_totals_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$summary_totals = $stmt->get_result()->fetch_assoc();
$total_units_sold = (int)($summary_totals['total_units_sold'] ?? 0);
$total_revenue = (float)($summary_totals['total_revenue'] ?? 0);
$stmt->close();

// Top product
$top_product_name = !empty($sales_data) ? $sales_data[0]['product_name'] : 'N/A';

/* ============================================================
   6. COMPARISON WITH PREVIOUS YEAR
   Only works when a specific year is selected and month/season/all is chosen
   ============================================================ */

$comparison_data = null;

if ($filter_year !== 'all') {
    $comparison_year = $filter_year - 1;

    $compare_where = " WHERE YEAR(sg.created_at) = ? ";
    $compare_params = [$comparison_year];
    $compare_types = "i";

    if ($filter_category !== 'all') {
        $compare_where .= " AND p.category_id = ? ";
        $compare_params[] = $filter_category;
        $compare_types .= "i";
    }

    if ($comparison_mode === 'month' && !empty($comparison_months)) {
        $compare_where .= " AND MONTH(sg.created_at) = ? ";
        $compare_params[] = $comparison_months[0];
        $compare_types .= "i";
    } elseif ($comparison_mode === 'season' && !empty($comparison_months)) {
        $compare_placeholders = implode(',', array_fill(0, count($comparison_months), '?'));
        $compare_where .= " AND MONTH(sg.created_at) IN ($compare_placeholders) ";
        foreach ($comparison_months as $m) {
            $compare_params[] = $m;
            $compare_types .= "i";
        }
    }

    $comparison_sql = "
        SELECT
            COALESCE(SUM(s.quantity), 0) AS total_units_sold,
            COALESCE(SUM(s.total_price), 0) AS total_revenue
        FROM sales s
        JOIN products p ON s.product_id = p.product_id
        JOIN sale_groups sg ON s.sale_group_id = sg.sale_group_id
        $compare_where
    ";
    $stmt = $conn->prepare($comparison_sql);
    $stmt->bind_param($compare_types, ...$compare_params);
    $stmt->execute();
    $comparison_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $prev_units = (int)($comparison_row['total_units_sold'] ?? 0);
    $prev_revenue = (float)($comparison_row['total_revenue'] ?? 0);

    $unit_change = $total_units_sold - $prev_units;
    $revenue_change = $total_revenue - $prev_revenue;

    $unit_change_pct = ($prev_units > 0) ? (($unit_change / $prev_units) * 100) : null;
    $revenue_change_pct = ($prev_revenue > 0) ? (($revenue_change / $prev_revenue) * 100) : null;

    $comparison_data = [
        'label' => ($comparison_mode === 'all')
            ? "Compared with Year " . $comparison_year
            : "Compared with {$comparison_label} {$comparison_year}",
        'prev_units' => $prev_units,
        'prev_revenue' => $prev_revenue,
        'unit_change' => $unit_change,
        'revenue_change' => $revenue_change,
        'unit_change_pct' => $unit_change_pct,
        'revenue_change_pct' => $revenue_change_pct
    ];
}

/* ============================================================
   7. CHART DATA
   Top 10 instead of 15 for cleaner chart
   ============================================================ */
$chart_data = array_slice($sales_data, 0, 10);
$chart_labels = [];
$chart_values = [];

foreach ($chart_data as $item) {
    $label = $item['product_name'];
    if (!empty($item['variation'])) {
        $label .= ' - ' . $item['variation'];
    }
    $chart_labels[] = $label;
    $chart_values[] = (int)$item['total_sold'];
}

$chart_labels_json = json_encode($chart_labels);
$chart_values_json = json_encode($chart_values);

$conn->close();

/* ============================================================
   HELPER
   ============================================================ */
function formatChange($value, $isPercent = false) {
    if ($value === null) {
        return 'N/A';
    }
    $prefix = $value > 0 ? '+' : '';
    return $prefix . number_format($value, $isPercent ? 2 : 0) . ($isPercent ? '%' : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
       <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <title>Seasonal Analysis - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin1.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg:           #eef1f8;
            --brand-primary: #2563eb;
            --brand-primary-light: #dbeafe;
            --brand-accent: #0ea5e9;
            --brand-success: #059669;
            --brand-success-light: #d1fae5;
            --brand-warning: #d97706;
            --brand-warning-light: #fef3c7;
            --brand-danger: #dc2626;
            --brand-danger-light: #fee2e2;
            --brand-purple: #7c3aed;
            --brand-purple-light: #ede9fe;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --surface-3: #f1f5f9;
            --border: #e2e8f0;
            --border-strong: #cbd5e1;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
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
            width: 42px; height: 42px;
            border-radius: var(--radius-md);
            background: var(--brand-primary-light);
            color: var(--brand-primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
            margin-right: 0.85rem;
            flex-shrink: 0;
        }

        /* ── Filter panel ── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            padding: 1rem 1.4rem;
            cursor: pointer;
            background: var(--surface-2);
            transition: background 0.15s;
        }
        .panel-header:hover { background: rgba(37,99,235,0.04); }
        .panel-hl {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .panel-hl i { font-size: 1.1rem; color: var(--brand-primary); }
        .panel-chevron {
            font-size: 1rem;
            color: var(--text-secondary);
            transition: transform 0.25s ease;
        }
        .panel-chevron.open { transform: rotate(180deg); }
        .panel-body {
            max-height: 0;
            overflow: hidden;
            padding: 0 1.4rem;
            transition: max-height 0.28s ease, padding 0.28s ease;
        }
        .panel-body.open {
            max-height: 1200px;
            padding: 1rem 1.4rem 1.4rem;
        }
        .filter-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .filter-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--brand-success);
            display: inline-block;
        }
        .filter-active-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.18rem 0.75rem;
            border-radius: 999px;
            background: var(--brand-primary-light);
            color: var(--brand-primary);
            font-size: 0.72rem;
            font-weight: 700;
        }
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.35rem;
            display: block;
        }
        .form-select {
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            color: var(--text-primary);
            background-color: var(--surface-2);
            padding: 0.5rem 0.85rem;
            transition: border-color 0.15s, box-shadow 0.15s;
            height: 40px;
            width: 100%;
        }
        .form-select:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
            outline: none;
            background-color: #fff;
        }
        .btn-analyze {
            background: var(--brand-primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.5rem 1.1rem;
            height: 40px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s, transform 0.1s;
            white-space: nowrap;
        }
        .btn-analyze:hover { background: #1d4ed8; transform: translateY(-1px); color:#fff; }
        .btn-reset {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            height: 40px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: border-color 0.15s, color 0.15s, background 0.15s;
            white-space: nowrap;
            text-decoration: none;
        }
        .btn-reset:hover { border-color: var(--brand-primary); color: var(--brand-primary); background: var(--brand-primary-light); }

        /* ── Summary cards ── */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        @media (max-width: 1100px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .kpi-grid { grid-template-columns: 1fr; } }

        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 1.2rem 1.3rem 1.1rem;
            display: flex;
            flex-direction: column;
            gap: 0;
            position: relative;
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .kpi-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        .kpi-card.kpi-blue::before  { background: var(--brand-primary); }
        .kpi-card.kpi-green::before { background: var(--brand-success); }
        .kpi-card.kpi-amber::before { background: var(--brand-warning); }
        .kpi-card.kpi-red::before   { background: var(--brand-danger); }

        .kpi-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.6rem; }
        .kpi-icon {
            width: 38px; height: 38px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
        }
        .kpi-icon.blue  { background: var(--brand-primary-light);  color: var(--brand-primary); }
        .kpi-icon.green { background: var(--brand-success-light); color: var(--brand-success); }
        .kpi-icon.amber { background: var(--brand-warning-light); color: var(--brand-warning); }
        .kpi-icon.red   { background: var(--brand-danger-light);  color: var(--brand-danger); }

        .kpi-label { font-size: 0.76rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.2rem; }
        .kpi-value { font-size: 1.65rem; font-weight: 800; color: var(--text-primary); line-height: 1.1; letter-spacing: -0.5px; }
        .kpi-sub { font-size: 0.76rem; color: var(--text-muted); margin-top: 0.4rem; }

        /* ── Comparison panel ── */
        .compare-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .compare-card-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.95rem 1.4rem;
            background: var(--surface-3);
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-secondary);
        }
        .compare-card-header i { color: var(--brand-primary); font-size: 1rem; }
        .compare-body { padding: 1.2rem 1.4rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 600px) { .compare-body { grid-template-columns: 1fr; } }

        .compare-panel {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1rem 1.1rem;
        }
        .compare-panel-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-muted); margin-bottom: 0.75rem; }
        .compare-row { display: flex; justify-content: space-between; align-items: baseline; font-size: 0.85rem; padding: 4px 0; }
        .compare-row .label { color: var(--text-secondary); }
        .compare-row .val { font-weight: 700; color: var(--text-primary); font-family: 'DM Mono', monospace; }
        .compare-divider { border: none; border-top: 1px dashed var(--border-strong); margin: 8px 0; }
        .change-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 999px;
            font-size: 0.78rem; font-weight: 700;
            margin-top: 6px;
        }
        .change-badge.positive { background: var(--brand-success-light); color: var(--brand-success); }
        .change-badge.negative { background: var(--brand-danger-light);  color: var(--brand-danger); }
        .change-badge.neutral  { background: var(--surface-3); color: var(--text-muted); }

        /* ── Chart card ── */
        .chart-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 1.4rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .chart-card-header {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.1rem;
        }
        .chart-card-title {
            font-size: 0.95rem; font-weight: 700; color: var(--text-primary);
            display: flex; align-items: center; gap: 8px;
        }
        .chart-card-title i { color: var(--brand-primary); }

        /* ── Best sellers table card ── */
        .sellers-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .sellers-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.4rem;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-bottom: none;
        }
        .sellers-card-title {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.93rem; font-weight: 700; color: #fff;
        }
        .sellers-card-title i.bi-trophy-fill { color: #fbbf24; }
        .btn-print {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px;
            font-size: 0.78rem; font-weight: 600;
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: var(--radius-sm);
            color: rgba(255,255,255,0.85);
            background: rgba(255,255,255,0.07);
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }
        .btn-print:hover { background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.5); color: #fff; }

        /* Table */
        .sellers-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .sellers-table thead th {
            background: var(--surface-3);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            padding: 0.7rem 0.9rem;
            border-bottom: 1px solid var(--border);
        }
        .sellers-table thead th:first-child { padding-left: 1.4rem; }
        .sellers-table thead th:last-child { text-align: right; padding-right: 1.4rem; }

        .sellers-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.13s;
        }
        .sellers-table tbody tr:last-child { border-bottom: none; }
        .sellers-table tbody tr:hover { background: var(--surface-2); }
        .sellers-table td {
            padding: 0.75rem 0.9rem;
            font-size: 0.875rem;
            vertical-align: middle;
        }
        .sellers-table td:first-child { padding-left: 1.4rem; }
        .sellers-table td:last-child { text-align: right; padding-right: 1.4rem; }

        /* Rank badges */
        .rank-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 50%;
            font-size: 0.75rem; font-weight: 800;
        }
        .rank-1 { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #fff; box-shadow: 0 2px 8px rgba(245,158,11,0.4); }
        .rank-2 { background: linear-gradient(135deg, #94a3b8, #cbd5e1); color: #fff; box-shadow: 0 2px 8px rgba(148,163,184,0.35); }
        .rank-3 { background: linear-gradient(135deg, #b45309, #d97706); color: #fff; box-shadow: 0 2px 8px rgba(180,83,9,0.35); }
        .rank-other { background: var(--surface-3); color: var(--text-muted); font-size: 0.72rem; }

        .product-name { font-weight: 700; color: var(--text-primary); }
        .product-meta { font-size: 0.78rem; color: var(--text-muted); }
        .units-val { font-family: 'DM Mono', monospace; font-size: 1rem; font-weight: 700; color: var(--text-primary); }
        .revenue-val { font-family: 'DM Mono', monospace; font-weight: 700; color: var(--brand-success); font-size: 0.9rem; }

        /* Empty state */
        .empty-state {
            text-align: center; padding: 3.5rem 1rem;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; opacity: 0.4; }
        .empty-state p { font-size: 0.9rem; margin: 0; }

        /* Sidebar toggle btn */
        .sidebar-toggle-btn {
            display: none;
            background: var(--brand-primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            padding: 7px 12px;
            font-size: 1.1rem;
            cursor: pointer;
            margin-bottom: 1rem;
        }
        @media (max-width: 991px) { .sidebar-toggle-btn { display: inline-flex; align-items: center; } }

        /* print */
        .no-print { display: inline-flex; }
        @media print {
            .no-print { display: none !important; }
            .sidebar, .filter-card, .btn, .nav { display: none !important; }
            .content { margin: 0; width: 100%; }
            .kpi-card:hover { box-shadow: none; transform: none; }
        }

        /* content area */
        .content { padding: 1.5rem 1.75rem; }
        @media (max-width: 768px) { .content { padding: 1rem; } }
    </style>
</head>
<body>
<div class="d-flex">

    <!-- Sidebar (kept from original) -->
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
            <li class="nav-item mb-2"><a class="nav-link active" href="admin_seasonal_report.php"><i class="bi bi-calendar-range me-2"></i> Seasonal Analysis</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line me-2"></i> Reports</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="supplier.php"><i class="bi bi-truck me-2"></i> Suppliers</a></li>
            <li class="sidebar-title">Users</li>
            <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>
            <li class="sidebar-title">Settings</li>
            <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main content -->
    <div class="content flex-grow-1">

       

        <!-- Page header -->
        <div class="page-header">
            <div style="display:flex; align-items:center;">
                <div class="page-header-icon">
                    <i class="bi bi-calendar-range"></i>
                </div>
                <div class="page-header-left">
                    <h4>Seasonal Demand Analysis</h4>
                    <p>Top 15 best-selling products by period, category, and season</p>
                </div>
            </div>
        </div>

        

        <!-- KPI Summary Cards -->
        <div class="kpi-grid">
            <!-- Period -->
            <div class="kpi-card kpi-blue">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Selected Period</div>
                        <div class="kpi-value" style="font-size:1.1rem; margin-top:4px;"><?= htmlspecialchars($selected_period_label) ?></div>
                    </div>
                    <div class="kpi-icon blue"><i class="bi bi-calendar-event-fill"></i></div>
                </div>
                <div class="kpi-sub"><?= htmlspecialchars($selected_category_name) ?></div>
            </div>

            <!-- Products -->
            <div class="kpi-card kpi-green">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Unique Products</div>
                        <div class="kpi-value"><?= number_format($total_products_sold) ?></div>
                    </div>
                    <div class="kpi-icon green"><i class="bi bi-box-seam-fill"></i></div>
                </div>
                <div class="kpi-sub">Products with recorded sales</div>
            </div>

            <!-- Units -->
            <div class="kpi-card kpi-amber">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Total Units Sold</div>
                        <div class="kpi-value"><?= number_format($total_units_sold) ?></div>
                    </div>
                    <div class="kpi-icon amber"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
                <div class="kpi-sub">Combined sold quantity</div>
            </div>

            <!-- Revenue -->
            <div class="kpi-card kpi-red">
                <div class="kpi-top">
                    <div>
                        <div class="kpi-label">Total Revenue</div>
                        <div class="kpi-value" style="font-size:1.3rem;">₱<?= number_format($total_revenue, 2) ?></div>
                    </div>
                    <div class="kpi-icon red"><i class="bi bi-cash-stack"></i></div>
                </div>
                <div class="kpi-sub">Top: <?= htmlspecialchars($top_product_name) ?></div>
            </div>
        </div>

        <!-- Filter panel -->
        <div class="panel no-print">
            <div class="panel-header" id="filterPanelBtn">
                <div class="panel-hl">
                    <i class="bi bi-sliders2"></i>
                    Filter Options
                    <?php if ($has_active_filters): ?>
                        <span class="filter-dot"></span>
                        <span class="filter-active-pill">Active</span>
                    <?php endif; ?>
                </div>
                <i class="bi bi-chevron-down panel-chevron <?= $has_active_filters ? 'open' : '' ?>" id="filterChevron"></i>
            </div>
            <div class="panel-body <?= $has_active_filters ? 'open' : '' ?>" id="filterPanelBody">
                <form method="GET" action="admin_seasonal_report.php">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">Year</label>
                            <select class="form-select" name="year">
                                <option value="all">All Years (Aggregate)</option>
                                <?php foreach ($available_years as $yr): ?>
                                    <option value="<?= $yr ?>" <?= ($filter_year === $yr) ? 'selected' : '' ?>><?= $yr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">Season / Quarter</label>
                            <select class="form-select" name="season">
                                <option value="all">— No Specific Season —</option>
                                <?php foreach ($seasons as $key => $val): ?>
                                    <option value="<?= $key ?>" <?= ($filter_season === $key) ? 'selected' : '' ?>><?= $val['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Specific Month</label>
                            <select class="form-select" name="month">
                                <option value="all">— All Months —</option>
                                <?php foreach ($months as $num => $name): ?>
                                    <option value="<?= $num ?>" <?= ($filter_month === $num) ? 'selected' : '' ?>><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="all">All Categories</option>
                                <?php foreach ($available_categories as $cat): ?>
                                    <option value="<?= (int)$cat['category_id'] ?>" <?= ($filter_category === (int)$cat['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4 col-12 ms-auto d-flex justify-content-end gap-2">
                            <button type="submit" class="btn-analyze">
                                <i class="bi bi-funnel-fill"></i> Analyze
                            </button>
                            <a href="admin_seasonal_report.php" class="btn-reset">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Year-over-Year Comparison -->
        <?php if ($comparison_data): ?>
        <div class="compare-card">
            <div class="compare-card-header">
                <i class="bi bi-arrow-left-right"></i>
                <?= htmlspecialchars($comparison_data['label']) ?>
            </div>
            <div class="compare-body">
                <!-- Units comparison -->
                <div class="compare-panel">
                    <div class="compare-panel-title"><i class="bi bi-box me-1"></i> Units Sold</div>
                    <div class="compare-row">
                        <span class="label">Current period</span>
                        <span class="val"><?= number_format($total_units_sold) ?></span>
                    </div>
                    <div class="compare-row">
                        <span class="label">Previous period</span>
                        <span class="val"><?= number_format($comparison_data['prev_units']) ?></span>
                    </div>
                    <hr class="compare-divider">
                    <?php
                        $uc = $comparison_data['unit_change'];
                        $ucClass = $uc > 0 ? 'positive' : ($uc < 0 ? 'negative' : 'neutral');
                        $ucIcon  = $uc > 0 ? 'bi-arrow-up-right' : ($uc < 0 ? 'bi-arrow-down-right' : 'bi-dash');
                    ?>
                    <span class="change-badge <?= $ucClass ?>">
                        <i class="bi <?= $ucIcon ?>"></i>
                        <?= formatChange($uc) ?> units
                        <?php if ($comparison_data['unit_change_pct'] !== null): ?>
                            &nbsp;(<?= formatChange($comparison_data['unit_change_pct'], true) ?>)
                        <?php endif; ?>
                    </span>
                </div>

                <!-- Revenue comparison -->
                <div class="compare-panel">
                    <div class="compare-panel-title"><i class="bi bi-cash me-1"></i> Revenue</div>
                    <div class="compare-row">
                        <span class="label">Current period</span>
                        <span class="val">₱<?= number_format($total_revenue, 2) ?></span>
                    </div>
                    <div class="compare-row">
                        <span class="label">Previous period</span>
                        <span class="val">₱<?= number_format($comparison_data['prev_revenue'], 2) ?></span>
                    </div>
                    <hr class="compare-divider">
                    <?php
                        $rc = $comparison_data['revenue_change'];
                        $rcClass = $rc > 0 ? 'positive' : ($rc < 0 ? 'negative' : 'neutral');
                        $rcIcon  = $rc > 0 ? 'bi-arrow-up-right' : ($rc < 0 ? 'bi-arrow-down-right' : 'bi-dash');
                    ?>
                    <span class="change-badge <?= $rcClass ?>">
                        <i class="bi <?= $rcIcon ?>"></i>
                        <?= ($rc > 0 ? '+' : '') ?>₱<?= number_format($rc, 2) ?>
                        <?php if ($comparison_data['revenue_change_pct'] !== null): ?>
                            &nbsp;(<?= formatChange($comparison_data['revenue_change_pct'], true) ?>)
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Chart -->
        <?php if (!empty($chart_data)): ?>
        <div class="chart-card">
            <div class="chart-card-header">
                <div class="chart-card-title">
                    <i class="bi bi-bar-chart-fill"></i> Top 10 Products by Units Sold
                </div>
            </div>
            <div style="height: 360px;">
                <canvas id="seasonalTopProductsChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Best Sellers Table -->
        <div class="sellers-card" id="bestSellersSection">
            <div class="sellers-card-header">
                <div class="sellers-card-title">
                    <i class="bi bi-trophy-fill"></i>
                    <?= htmlspecialchars($report_title) ?>
                </div>
                <button type="button" class="btn-print no-print" id="printBestSellersBtn">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
            <?php if (!empty($sales_data)): ?>
            <div class="table-responsive">
                <table class="sellers-table">
                    <thead>
                        <tr>
                            <th style="width:58px;">Rank</th>
                            <th>Product</th>
                            <th>Brand</th>
                            <th>Variation</th>
                            <th>Supplier(s)</th>
                            <th style="text-align:center;">Units Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_data as $index => $item): ?>
                        <tr>
                            <td>
                                <?php
                                    $rn = $index + 1;
                                    $rc = $rn === 1 ? 'rank-1' : ($rn === 2 ? 'rank-2' : ($rn === 3 ? 'rank-3' : 'rank-other'));
                                ?>
                                <span class="rank-badge <?= $rc ?>"><?= $rn ?></span>
                            </td>
                            <td>
                                <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                            </td>
                            <td><span class="product-meta"><?= htmlspecialchars($item['brand'] ?? '—') ?></span></td>
                            <td><span class="product-meta"><?= htmlspecialchars($item['variation'] ?? '—') ?></span></td>
                            <td><span class="product-meta"><?= htmlspecialchars($item['suppliers'] ?? '—') ?></span></td>
                            <td style="text-align:center;">
                                <span class="units-val"><?= number_format((int)$item['total_sold']) ?></span>
                            </td>
                            <td>
                                <span class="revenue-val">₱<?= number_format((float)$item['total_revenue'], 2) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-clipboard-x"></i>
                <p>No sales data found for the selected period.</p>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /content -->
</div><!-- /d-flex -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function makeToggle(btnId, bodyId, chevId) {
    const btn = document.getElementById(btnId);
    const body = document.getElementById(bodyId);
    const chev = document.getElementById(chevId);
    if (!btn) return;
    btn.addEventListener('click', () => {
        body.classList.toggle('open');
        chev.classList.toggle('open');
    });
}

document.getElementById('sidebarToggle')?.addEventListener('click', function () {
    document.getElementById('sidebar')?.classList.toggle('show');
});

makeToggle('filterPanelBtn','filterPanelBody','filterChevron');

const chartLabels = <?= $chart_labels_json ?>;
const chartValues = <?= $chart_values_json ?>;

if (chartLabels.length > 0) {
    const ctx = document.getElementById('seasonalTopProductsChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Units Sold',
                data: chartValues,
                backgroundColor: [
                    'rgba(37,99,235,0.82)',
                    'rgba(5,150,105,0.82)',
                    'rgba(217,119,6,0.82)',
                    'rgba(220,38,38,0.82)',
                    'rgba(124,58,237,0.82)',
                    'rgba(14,165,233,0.82)',
                    'rgba(236,72,153,0.82)',
                    'rgba(20,184,166,0.82)',
                    'rgba(249,115,22,0.82)',
                    'rgba(100,116,139,0.82)'
                ],
                borderRadius: 6,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.formattedValue + ' units'
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: { font: { family: 'DM Mono, monospace', size: 11 }, color: '#94a3b8' }
                },
                y: {
                    grid: { display: false },
                    ticks: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 12, weight: '600' }, color: '#334155' }
                }
            }
        }
    });
}

document.getElementById('printBestSellersBtn')?.addEventListener('click', function () {
    const section = document.getElementById('bestSellersSection');
    if (!section) { window.print(); return; }

    const reportTitle    = `<?= htmlspecialchars($report_title) ?>`;
    const selectedPeriod = `<?= htmlspecialchars($selected_period_label) ?>`;
    const selectedCategory = `<?= htmlspecialchars($selected_category_name) ?>`;
    const printedDate = new Date().toLocaleString('en-US', {
        year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit'
    });

    const html = `
        <!DOCTYPE html><html lang="en"><head>
        <meta charset="UTF-8">
        <title>Best Sellers Report</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; margin: 28px 32px; color: #0f172a; }
            .rpt-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; gap:16px; }
            .rpt-brand { display:flex; align-items:center; gap:14px; }
            .rpt-brand img { width:64px; height:auto; object-fit:contain; }
            .rpt-title { font-size:22px; font-weight:800; letter-spacing:-0.3px; margin-bottom:3px; }
            .rpt-sub { font-size:13px; color:#64748b; }
            .rpt-info { font-size:12px; color:#64748b; line-height:1.9; text-align:right; }
            .divider { border:none; border-top:1px solid #e2e8f0; margin:18px 0; }
            table { width:100%; border-collapse:collapse; }
            thead th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; background:#f8fafc; padding:8px 10px; border-bottom:1px solid #e2e8f0; }
            tbody td { padding:9px 10px; font-size:13px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
            .rank-badge { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:50%; font-size:11px; font-weight:800; }
            .rank-1 { background:#f59e0b; color:#fff; }
            .rank-2 { background:#94a3b8; color:#fff; }
            .rank-3 { background:#b45309; color:#fff; }
            .rank-other { background:#f1f5f9; color:#64748b; font-size:11px; }
            .rev { font-family:'DM Mono',monospace; font-weight:700; color:#059669; }
            .units { font-family:'DM Mono',monospace; font-weight:700; }
        </style></head><body>
        <div class="rpt-header">
            <div class="rpt-brand">
                <img src="images/logo.png" alt="Logo" onerror="this.style.display='none'">
                <div>
                    <div class="rpt-title">Inventory System</div>
                    <div class="rpt-sub">Best Sellers Report</div>
                </div>
            </div>
            <div class="rpt-info">
                <div><strong>Printed:</strong> ${printedDate}</div>
                <div><strong>Period:</strong> ${selectedPeriod}</div>
                <div><strong>Category:</strong> ${selectedCategory}</div>
            </div>
        </div>
        <hr class="divider">
        ${section.outerHTML}
        </body></html>`;

    const pw = window.open('', '_blank', 'width=1200,height=800');
    if (!pw) { alert('Please allow popups to print.'); return; }
    pw.document.write(html);
    pw.document.close();
    pw.focus();
    pw.onload = () => pw.print();
});
</script>
</body>
</html>