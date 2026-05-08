<?php
include 'project.php';
session_start();

// Restrict to staff only
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// ---------------- PAGINATION SETUP ----------------
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// ---------------- STOCK IN PROCESS ----------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['stock_in_submit'])) {
    $stocked_by = $_SESSION['username'];
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $reference_no = trim($_POST['reference_no'] ?? '');
    $items_json = $_POST['stock_in_data_json'] ?? '';
    $items_to_stock_in = json_decode($items_json, true);
    $success_count = 0;
    $errors = [];

    if ($supplier_id === 0) {
        $errors[] = "Supplier not selected.";
    }

    if (empty($items_to_stock_in) && empty($errors)) {
        $errors[] = "No items were selected for stock in.";
    }

    if (empty($errors)) {
        $conn->begin_transaction();

        $batch_id = 0;
        $stmt_insert_batch = $conn->prepare("
            INSERT INTO stock_in_batches (reference_no, supplier_id, stocked_by)
            VALUES (?, ?, ?)
        ");

        try {
            if (!$stmt_insert_batch) {
                throw new Exception("Error preparing batch statement: " . $conn->error);
            }

            $stmt_insert_batch->bind_param("sis", $reference_no, $supplier_id, $stocked_by);
            if (!$stmt_insert_batch->execute()) {
                throw new Exception("Error inserting stock-in batch header: " . $stmt_insert_batch->error);
            }

            $batch_id = $conn->insert_id;
            $stmt_insert_batch->close();

            $stmt_update_product_stock = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
            $stmt_update_product_price = $conn->prepare("UPDATE products SET supplier_price = ? WHERE product_id = ?");
            $stmt_insert_history = $conn->prepare("
                INSERT INTO stock_history (product_id, supplier_id, quantity, supplier_price, total_cost, item_desc, stocked_by, batch_id, movement_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'IN')
            ");

            foreach ($items_to_stock_in as $item) {
                $product_id = intval($item['product_id'] ?? 0);
                $quantity = intval($item['quantity'] ?? 0);
                $product_supplier_price = floatval($item['supplier_price'] ?? 0.00);

                if ($product_id <= 0 || $quantity <= 0) {
                    $errors[] = "Skipped invalid item (ID {$product_id} or quantity {$quantity}).";
                    continue;
                }

                $total_cost = $product_supplier_price * $quantity;

                $stmt_update_product_stock->bind_param("ii", $quantity, $product_id);
                if (!$stmt_update_product_stock->execute()) {
                    throw new Exception("Error updating product stock for {$product_id}.");
                }

                $stmt_update_product_price->bind_param("di", $product_supplier_price, $product_id);
                if (!$stmt_update_product_price->execute()) {
                    throw new Exception("Error updating product price for {$product_id}.");
                }

                $stmt_insert_history->bind_param(
                    "iiiddssi",
                    $product_id,
                    $supplier_id,
                    $quantity,
                    $product_supplier_price,
                    $total_cost,
                    $reference_no,
                    $stocked_by,
                    $batch_id
                );

                if (!$stmt_insert_history->execute()) {
                    throw new Exception("Error recording history for {$product_id}.");
                }

                $success_count += $quantity;
            }

            $stmt_update_product_stock->close();
            $stmt_update_product_price->close();
            $stmt_insert_history->close();

            if (!empty($errors)) {
                $conn->rollback();
            } else {
                $conn->commit();
            }

        } catch (Exception $e) {
            if ($conn->in_transaction) {
                $conn->rollback();
            }

            if (isset($stmt_insert_batch) && $stmt_insert_batch) {
                $stmt_insert_batch->close();
            }

            $errors[] = "Critical Stock-In Error: " . $e->getMessage();
        }
    }

    if ($success_count > 0 && empty($errors)) {
        $_SESSION['success'] = "Successfully stocked in {$success_count} item(s) in Batch #{$batch_id} (Ref: {$reference_no}).";
    } elseif ($success_count > 0 && !empty($errors)) {
        $_SESSION['warning'] = "Stock-In partially failed (Batch #{$batch_id}). Total logged: {$success_count}. Errors: " . implode("; ", array_unique($errors));
    } else {
        $_SESSION['error'] = "Stock-In failed. Errors: " . implode("; ", array_unique($errors));
    }

    header("Location: staff_products.php");
    exit();
}

// ---------------- LOW STOCK QUERY ----------------
$lowStockQuery = $conn->query("
    SELECT product_name, brand, variation, unit, stock, reorder_level
    FROM products
    WHERE stock > 0 AND stock <= reorder_level AND reorder_level > 0
    ORDER BY stock ASC
");

$lowStockProducts = [];
if ($lowStockQuery && $lowStockQuery->num_rows > 0) {
    while ($row = $lowStockQuery->fetch_assoc()) {
        $lowStockProducts[] = $row;
    }
}

// ---------------- OUT OF STOCK PRODUCTS ----------------
$outOfStockQuery = $conn->query("
    SELECT product_id, product_name, brand, variation, unit, stock, reorder_level, category_id
    FROM products
    WHERE stock = 0
    ORDER BY product_name ASC
");
$outOfStockProducts = [];
if ($outOfStockQuery && $outOfStockQuery->num_rows > 0) {
    while ($row = $outOfStockQuery->fetch_assoc()) {
        $outOfStockProducts[] = $row;
    }
}

// ---------------- PRODUCTS BY CATEGORY WITH STOCK INFO ----------------
$categoriesStockQuery = $conn->query("
    SELECT
        c.category_id,
        c.category_name,
        COUNT(p.product_id) AS total_products,
        SUM(p.stock) AS total_stock,
        SUM(CASE WHEN p.stock = 0 THEN 1 ELSE 0 END) AS out_of_stock_count,
        SUM(CASE WHEN p.reorder_level > 0 AND p.stock > 0 AND p.stock <= p.reorder_level THEN 1 ELSE 0 END) AS low_stock_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_name ASC
");
$productsByCategory = [];
if ($categoriesStockQuery && $categoriesStockQuery->num_rows > 0) {
    while ($row = $categoriesStockQuery->fetch_assoc()) {
        $productsByCategory[] = $row;
    }
}

// ---------------- SUMMARY COUNTS ----------------
$summary_row = $conn->query("\n    SELECT\n        COUNT(*) AS total_products,\n        SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) AS in_stock,\n        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) AS out_of_stock,\n        SUM(CASE WHEN reorder_level > 0 AND stock > 0 AND stock <= reorder_level THEN 1 ELSE 0 END) AS low_stock\n    FROM products\n")->fetch_assoc();

$total_products_summary = (int)($summary_row['total_products'] ?? 0);
$in_stock_count = (int)($summary_row['in_stock'] ?? 0);
$out_of_stock_count = (int)($summary_row['out_of_stock'] ?? 0);
$low_stock_count = (int)($summary_row['low_stock'] ?? 0);

// ---------------- FETCH CATEGORIES & SUBCATEGORIES ----------------
$categories_query = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
$subcategories_query = mysqli_query($conn, "SELECT * FROM subcategories ORDER BY subcategory_name ASC");

// ---------------- FILTER LOGIC ----------------
$where_clauses = [];
$params = [];
$types = '';

$brand_filter = trim($_GET['brand_filter'] ?? '');
$variant_filter = trim($_GET['variant_filter'] ?? '');

if (isset($_GET['q']) && $_GET['q'] != '') {
    $search_term = '%' . trim($_GET['q']) . '%';
    $where_clauses[] = '(p.product_name LIKE ? OR p.sku LIKE ? OR c.category_name LIKE ? OR s.subcategory_name LIKE ? OR p.brand LIKE ? OR p.variation LIKE ?)';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ssssss';
}

if (isset($_GET['category_id']) && $_GET['category_id'] != '') {
    $where_clauses[] = 'p.category_id = ?';
    $params[] = $_GET['category_id'];
    $types .= 'i';
}

if (isset($_GET['subcategory_id']) && $_GET['subcategory_id'] != '') {
    $where_clauses[] = 'p.subcategory_id = ?';
    $params[] = $_GET['subcategory_id'];
    $types .= 'i';
}

if ($brand_filter != '') {
    $where_clauses[] = 'p.brand LIKE ?';
    $params[] = '%' . $brand_filter . '%';
    $types .= 's';
}

if ($variant_filter != '') {
    $where_clauses[] = 'p.variation LIKE ?';
    $params[] = '%' . $variant_filter . '%';
    $types .= 's';
}

// ---------------- COUNT TOTAL PRODUCTS ----------------
$count_sql = "
    SELECT COUNT(p.product_id)
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN subcategories s ON p.subcategory_id = s.subcategory_id
";

if (!empty($where_clauses)) {
    $count_sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$count_stmt = $conn->prepare($count_sql);
if ($count_stmt === false) {
    die("Error preparing count statement: " . $conn->error);
}

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_stmt->bind_result($total_products);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_products / $limit);

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
} elseif ($total_pages == 0) {
    $page = 1;
    $offset = 0;
}

// Check if filters are active
$has_active_filters = !empty($_GET['q']) || !empty($_GET['category_id']) || !empty($_GET['subcategory_id']) || !empty($brand_filter) || !empty($variant_filter);

// ---------------- MAIN PRODUCT QUERY ----------------
$sql = "
    SELECT p.*, p.unit, p.brand, p.variation, p.sku, c.category_name, s.subcategory_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN subcategories s ON p.subcategory_id = s.subcategory_id
";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$pagination_types = $types . 'ii';
$pagination_params = array_merge($params, [$limit, $offset]);

if (!empty($pagination_params)) {
    $stmt->bind_param($pagination_types, ...$pagination_params);
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// ---------------- HELPER FUNCTION ----------------
function buildPaginationUrl($page, $get_params) {
    unset($get_params['page']);
    $get_params['page'] = $page;
    return 'staff_products.php?' . http_build_query($get_params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - The Daily Sprout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/admin1.css">
    <link rel="stylesheet" href="css/alert.css">
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


        /* ── Toast notifications ── */
        .toast-stack {
            position:fixed; top:20px; right:20px; z-index:9999;
            display:flex; flex-direction:column; gap:8px; min-width:300px; max-width:420px;
        }
        .toast-item {
            display:flex; align-items:flex-start; gap:12px;
            padding:14px 16px; border-radius:var(--r);
            font-size:.82rem; font-weight:500;
            box-shadow:var(--sh-lg); border:1px solid transparent;
            animation: toastIn .3s cubic-bezier(.22,1,.36,1);
        }
        @keyframes toastIn { from{opacity:0;transform:translateX(24px)} to{opacity:1;transform:translateX(0)} }
        .toast-item.success { background:#fff; border-color:#bbf7d0; }
        .toast-item.success .toast-icon { color:var(--green); }
        .toast-item.error   { background:#fff; border-color:#fecaca; }
        .toast-item.error   .toast-icon { color:var(--red); }
        .toast-item.warning { background:#fff; border-color:#fde68a; }
        .toast-item.warning .toast-icon { color:var(--amber); }
        .toast-icon { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
        .toast-text { flex:1; color:var(--ink-2); }
        .toast-close { background:none;border:none;color:var(--faint);cursor:pointer;font-size:.9rem;padding:0;line-height:1; }
        .toast-close:hover { color:var(--muted); }

        /* ── Page header ── */
        .page-hdr {
            display:flex; justify-content:space-between; align-items:flex-start;
            gap:16px; flex-wrap:wrap; margin-bottom:24px;
        }
        .page-hdr-left { display:flex; align-items:center; gap:14px; }
        .page-hdr-icon {
            width:48px; height:48px; border-radius:var(--r-sm);
            background:var(--blue); color:#fff;
            display:flex; align-items:center; justify-content:center;
            font-size:22px; flex-shrink:0;
            box-shadow:0 4px 12px rgba(37,99,235,.3);
        }
        .page-hdr h4 { font-size:1.3rem; font-weight:800; margin:0 0 3px; letter-spacing:-.4px; }
        .page-hdr p  { margin:0; font-size:.75rem; color:var(--muted); }
        .hdr-actions { display:flex; gap:10px; flex-wrap:wrap; }

        /* ── Buttons ── */
        .btn-hdr {
            display:inline-flex; align-items:center; gap:7px;
            padding:10px 18px; border:none; border-radius:var(--r-sm);
            font-family:var(--font); font-size:.8rem; font-weight:700;
            cursor:pointer; transition:all .15s; white-space:nowrap;
        }
        .btn-hdr.blue   { background:var(--blue); color:#fff; box-shadow:0 2px 8px rgba(37,99,235,.3); }
        .btn-hdr.blue:hover { background:var(--blue-dk); transform:translateY(-1px); box-shadow:0 4px 14px rgba(37,99,235,.4); }
        .btn-hdr.green  { background:var(--green); color:#fff; box-shadow:0 2px 8px rgba(5,150,105,.25); }
        .btn-hdr.green:hover { background:#047857; transform:translateY(-1px); }
        .btn-hdr.red    { background:var(--red); color:#fff; box-shadow:0 2px 8px rgba(220,38,38,.25); }
        .btn-hdr.red:hover { background:#b91c1c; transform:translateY(-1px); }

        /* ── Low stock banner ── */
        .alert-banner {
            display:flex; align-items:center; justify-content:space-between;
            gap:12px; flex-wrap:wrap;
            background:linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%);
            border-radius:var(--r); padding:14px 20px;
            margin-bottom:20px;
            box-shadow:0 4px 16px rgba(153,27,27,.3);
        }
        .alert-banner-left { display:flex; align-items:center; gap:12px; }
        .alert-banner-icon { font-size:1.3rem; color:#fca5a5; }
        .alert-banner-title { font-weight:700; color:#fff; font-size:.9rem; }
        .alert-banner-sub   { font-size:.74rem; color:#fca5a5; margin-top:1px; }
        .btn-alert-view {
            padding:7px 16px; border-radius:var(--r-sm); border:1px solid rgba(255,255,255,.25);
            background:rgba(255,255,255,.12); color:#fff;
            font-family:var(--font); font-size:.75rem; font-weight:700;
            cursor:pointer; transition:background .12s; white-space:nowrap;
        }
        .btn-alert-view:hover { background:rgba(255,255,255,.22); }

        /* ── Stat cards ── */
        .stat-row {
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:14px; margin-bottom:22px;
        }
        @media(max-width:1100px){.stat-row{grid-template-columns:repeat(2,1fr);}}
        @media(max-width:580px){.stat-row{grid-template-columns:1fr 1fr;}}

        .stat-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--r); padding:18px 20px;
            box-shadow:var(--sh-xs);
            position:relative; overflow:hidden;
            transition:box-shadow .15s,transform .15s;
        }
        .stat-card:hover { box-shadow:var(--sh); transform:translateY(-1px); }
        .stat-card::after {
            content:''; position:absolute; top:0; left:0; right:0; height:3px;
            background:var(--ac, var(--blue)); border-radius:var(--r) var(--r) 0 0;
        }
        .stat-card.g { --ac:var(--green); }
        .stat-card.a { --ac:var(--amber); }
        .stat-card.r { --ac:var(--red); }
        .stat-lbl { font-size:.64rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--muted); margin-bottom:10px; display:flex; align-items:center; gap:5px; }
        .stat-val { font-size:1.8rem; font-weight:800; letter-spacing:-.06em; line-height:1; }
        .stat-sub { font-size:.7rem; color:var(--faint); margin-top:5px; }

        /* ── Panel (filter / report) ── */
        .panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--r); box-shadow:var(--sh-xs);
            margin-bottom:16px; overflow:hidden;
        }
        .panel-header {
            display:flex; align-items:center; justify-content:space-between;
            padding:13px 20px; border-bottom:1px solid var(--border-light);
            cursor:pointer; user-select:none; transition:background .12s;
        }
        .panel-header:hover { background:var(--surface-2); }
        .panel-hl { display:flex; align-items:center; gap:8px; font-size:.82rem; font-weight:700; color:var(--ink-2); }
        .panel-hl i { color:var(--blue); }
        .panel-chevron { color:var(--muted); font-size:.8rem; transition:transform .2s; }
        .panel-chevron.open { transform:rotate(180deg); }
        .panel-body { padding:20px; display:none; }
        .panel-body.open { display:block; }

        /* Active filter dot */
        .filter-dot { width:7px; height:7px; border-radius:50%; background:var(--blue); display:inline-block; }
        .filter-active-pill { font-size:.63rem; font-weight:700; color:var(--blue); background:var(--blue-lt); padding:2px 8px; border-radius:20px; }

        /* ── Form inputs ── */
        .f-label { display:block; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin-bottom:5px; }
        .f-input {
            width:100%; padding:8px 12px;
            font-family:var(--font); font-size:.82rem;
            border:1.5px solid var(--border); border-radius:var(--r-sm);
            background:var(--surface-2); color:var(--ink);
            outline:none; transition:border-color .15s,box-shadow .15s;
        }
        .f-input:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(37,99,235,.1); background:#fff; }
        .f-input::placeholder { color:var(--faint); }
        .f-search-wrap { position:relative; }
        .f-search-wrap i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--faint); font-size:.95rem; pointer-events:none; }
        .f-search-wrap .f-input { padding-left:34px; }

        .filter-actions { display:flex; gap:10px; margin-top:18px; flex-wrap:wrap; }
        .btn-apply {
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 18px; border:none; border-radius:var(--r-sm);
            background:var(--blue); color:#fff;
            font-family:var(--font); font-size:.78rem; font-weight:700;
            cursor:pointer; transition:background .12s;
            box-shadow:0 2px 8px rgba(37,99,235,.25);
        }
        .btn-apply:hover { background:var(--blue-dk); }
        .btn-reset {
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 14px; border:1.5px solid var(--border); border-radius:var(--r-sm);
            background:#fff; color:var(--muted);
            font-family:var(--font); font-size:.78rem; font-weight:600;
            text-decoration:none; cursor:pointer; transition:all .12s;
        }
        .btn-reset:hover { background:var(--surface-2); color:var(--ink-2); }

        /* ── Table card ── */
        .table-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--r); box-shadow:var(--sh-xs); overflow:hidden;
        }
        .table-card-hdr {
            display:flex; align-items:center; justify-content:space-between;
            padding:14px 20px; border-bottom:1px solid var(--border-light);
        }
        .table-card-title { font-size:.85rem; font-weight:700; color:var(--ink); display:flex; align-items:center; gap:8px; }
        .table-card-title i { color:var(--blue); }
        .record-pill { font-size:.7rem; color:var(--muted); background:var(--surface-2); border:1px solid var(--border); padding:3px 10px; border-radius:20px; font-weight:600; }

        /* ── Data table ── */
        .data-tbl { width:100%; border-collapse:collapse; }
        .data-tbl thead th {
            padding:14px 16px; font-size:.7rem; font-weight:800;
            text-transform:uppercase; letter-spacing:.1em; color:var(--ink);
            background:var(--surface-2); border-bottom:2px solid var(--border);
            white-space:nowrap;
        }
        .data-tbl tbody td { padding:16px 16px; border-bottom:1px solid var(--border-light); vertical-align:middle; }
        .data-tbl tbody tr:last-child td { border-bottom:none; }
        .data-tbl tbody tr { transition:background .1s; }
        .data-tbl tbody tr:hover { background:#f8fafc; }

        /* Cells */
        .cell-cat { font-size:.8rem; font-weight:600; color:var(--ink-2); }
        .cell-subcat { font-size:.72rem; color:var(--muted); margin-top:2px; }
        .cell-name { font-size:.95rem; font-weight:800; color:var(--ink); letter-spacing:-.2px; }
        .cell-meta { font-size:.78rem; color:var(--ink-2); margin-top:3px; font-weight:500; }
        .cell-price { font-family:var(--mono); font-size:.9rem; font-weight:700; color:var(--ink); }
        .cell-price-sub { font-family:var(--mono); font-size:.72rem; color:var(--muted); margin-top:3px; }
        .cell-sku { font-family:var(--mono); font-size:.75rem; color:var(--muted); margin-top:4px; font-weight:500; }
        .cell-desc { font-size:.78rem; color:var(--ink-2); max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        /* Stock badge */
        .stock-badge {
            display:inline-flex; align-items:center; gap:6px;
            padding:6px 14px; border-radius:20px;
            font-size:.8rem; font-weight:700;
        }
        .stock-badge.ok      { background:var(--green-lt); color:var(--green); border:1.5px solid rgba(5,150,105,.1); }
        .stock-badge.low     { background:var(--amber-lt); color:var(--amber); border:1.5px solid rgba(217,119,6,.1); }
        .stock-badge.out     { background:var(--red-lt); color:var(--red); border:1.5px solid rgba(220,38,38,.1); }
        .stock-badge.expired { background:var(--violet-lt); color:var(--violet); border:1.5px solid rgba(124,58,237,.1); }
        .stock-num { font-family:var(--mono); font-size:.82rem; }
        .stock-status-lbl { font-size:.7rem; font-weight:700; color:var(--muted); margin-top:5px; text-transform:uppercase; letter-spacing:.05em; }

        /* Action dropdown */
        .tbl-actions-btn {
            width:30px; height:30px; border-radius:var(--r-sm);
            border:1.5px solid var(--border); background:var(--surface-2);
            color:var(--muted); cursor:pointer;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:.9rem; transition:all .12s;
        }
        .tbl-actions-btn:hover { border-color:var(--blue); color:var(--blue); background:var(--blue-lt); }
        .dropdown-menu {
            border:1px solid var(--border); border-radius:var(--r-sm);
            box-shadow:var(--sh-lg); padding:6px;
            font-family:var(--font); min-width:170px;
        }
        .dropdown-item {
            border-radius:var(--r-sm); font-size:.8rem; font-weight:500;
            padding:8px 12px; color:var(--ink-2); transition:background .1s;
        }
        .dropdown-item:hover { background:var(--surface-2); }
        .dropdown-item.di-danger { color:var(--red); }
        .dropdown-item.di-danger:hover { background:var(--red-lt); }
        .dropdown-item.di-success { color:var(--green); }
        .dropdown-item.di-success:hover { background:var(--green-lt); }
        .dropdown-item.di-warning { color:var(--amber); }
        .dropdown-item.di-warning:hover { background:var(--amber-lt); }
        .dropdown-item.di-info { color:var(--blue); }
        .dropdown-item.di-info:hover { background:var(--blue-lt); }
        .dropdown-divider { border-color:var(--border-light); margin:4px 0; }

        /* ── Empty state ── */
        .empty-state { text-align:center; padding:56px 20px; }
        .empty-icon { width:56px; height:56px; background:var(--blue-lt); color:var(--blue); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:22px; margin:0 auto 14px; }
        .empty-state h6 { font-size:.9rem; font-weight:700; margin-bottom:4px; }
        .empty-state p { font-size:.78rem; color:var(--muted); }

        /* ── Pagination ── */
        .pager-wrap { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-top:1px solid var(--border-light); background:var(--surface-2); flex-wrap:wrap; gap:10px; }
        .pager-info { font-size:.71rem; color:var(--muted); }
        .pager { display:flex; gap:4px; }
        .pager a,.pager span { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; font-size:.74rem; font-weight:600; border-radius:var(--r-sm); text-decoration:none; color:var(--ink-2); border:1.5px solid var(--border); background:#fff; transition:all .12s; }
        .pager a:hover:not(.active) { background:var(--blue-lt); border-color:rgba(37,99,235,.2); color:var(--blue); }
        .pager a.active { background:var(--blue); border-color:var(--blue); color:#fff; }
        .pager span.disabled { opacity:.35; cursor:not-allowed; }
        .pager span.dots { background:transparent; border-color:transparent; color:var(--faint); }

        /* ── Confirm modal ── */
        .confirm-modal { border:none; border-radius:var(--r); box-shadow:var(--sh-lg); font-family:var(--font); }
        .confirm-modal-body { padding:28px 24px 16px; text-align:center; }
        .confirm-icon { width:52px; height:52px; background:var(--red-lt); color:var(--red); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:22px; margin:0 auto 14px; }
        .confirm-message { font-size:.88rem; color:var(--ink-2); line-height:1.5; }
        .confirm-modal-footer { display:flex; gap:8px; padding:12px 24px 20px; justify-content:center; }
        .btn-confirm-cancel { padding:9px 20px; border-radius:var(--r-sm); border:1.5px solid var(--border); background:#fff; color:var(--muted); font-family:var(--font); font-size:.8rem; font-weight:600; cursor:pointer; }
        .btn-confirm-cancel:hover { background:var(--surface-2); }
        .btn-confirm-ok { padding:9px 20px; border-radius:var(--r-sm); border:none; background:var(--green); color:#fff; font-family:var(--font); font-size:.8rem; font-weight:700; cursor:pointer; }
        .btn-confirm-ok:hover { background:#15803d; }
        .btn-confirm-ok.red { background:var(--red); }
        .btn-confirm-ok.red:hover { background:#b91c1c; }
        .btn-confirm-ok.green { background:var(--green); }
        .btn-confirm-ok.green:hover { background:#15803d; }

        /* ── Stock alert modal ── */
        .modal-content { border:none; border-radius:var(--r); box-shadow:var(--sh-lg); font-family:var(--font); }
        .modal-header { border-bottom:1px solid var(--border-light); padding:18px 22px; }
        .modal-title { font-size:.95rem; font-weight:700; }
        .modal-body { padding:20px 22px; }
        .modal-footer { border-top:1px solid var(--border-light); padding:14px 22px; }
        .nav-tabs { border-bottom:1px solid var(--border); gap:4px; }
        .nav-tabs .nav-link { border:none; border-radius:var(--r-sm) var(--r-sm) 0 0; font-size:.78rem; font-weight:600; color:var(--muted); padding:8px 14px; }
        .nav-tabs .nav-link.active { background:var(--blue); color:#fff; }
        .alert-modal-tbl { width:100%; border-collapse:collapse; font-size:.78rem; }
        .alert-modal-tbl thead th { padding:8px 10px; font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); background:var(--surface-2); border-bottom:1px solid var(--border); }
        .alert-modal-tbl tbody td { padding:9px 10px; border-bottom:1px solid var(--border-light); }
        .alert-modal-tbl tbody tr:last-child td { border-bottom:none; }
    </style>
</head>
<body>
<div class="alert-toast-container" id="alertToastContainer">
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <span class="alert-icon"></span>
                        <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon"></span>
                        <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['warning'])): ?>
                    <div class="alert alert-warning">
                        <span class="alert-icon"></span>
                        <span><?= htmlspecialchars($_SESSION['warning']) ?></span>
                        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                    <?php unset($_SESSION['warning']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['info'])): ?>
                    <div class="alert alert-info">
                        <span class="alert-icon"></span>
                        <span><?= htmlspecialchars($_SESSION['info']) ?></span>
                        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                    <?php unset($_SESSION['info']); ?>
                <?php endif; ?>
            </div>
            <script>
                window.addEventListener('DOMContentLoaded', function() {
                  const alerts = document.querySelectorAll('.alert-toast-container .alert');
                  alerts.forEach(function(alert) {
                    setTimeout(function() {
                      alert.style.display = 'none';
                    }, 3000);
                  });
                });
            </script>
<div class="d-flex">
    <!-- SIDEBAR -->
  <div class="sidebar flex-column p-0" id="sidebar">
    <div class="sidebar-logo text-center">
      <img src="images/logo.png" alt="Inventory Logo">
      <h5 class="mt-2 text-white">Staff Panel</h5>
    </div>
    <hr class="text-white">
    <ul class="nav flex-column">
      <li class="sidebar-title">Main</li>
      <li class="nav-item mb-2"><a class="nav-link" href="staff_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
       <li class="nav-item mb-2"><a class="nav-link " href="staff_add_sale.php"><i class="bi bi-cart-plus me-2"></i> Add Sale</a></li>
    
      <li class="sidebar-title">Operations</li>
      <li class="nav-item mb-2"><a class="nav-link active" href="staff_products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="purchased_history.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="staff_returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="staff_product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>

      <li class="sidebar-title">Others</li>
      <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
    </ul>
  </div>

    <div class="content flex-grow-1">
        <div class="main-wrap">

            <!-- Toast notifications -->
            <div class="toast-stack" id="toastStack">
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="toast-item success">
                        <i class="bi bi-check-circle-fill toast-icon"></i>
                        <span class="toast-text"><?= htmlspecialchars($_SESSION['success']) ?></span>
                        <button class="toast-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="toast-item error">
                        <i class="bi bi-x-circle-fill toast-icon"></i>
                        <span class="toast-text"><?= htmlspecialchars($_SESSION['error']) ?></span>
                        <button class="toast-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['warning'])): ?>
                    <div class="toast-item warning">
                        <i class="bi bi-exclamation-triangle-fill toast-icon"></i>
                        <span class="toast-text"><?= htmlspecialchars($_SESSION['warning']) ?></span>
                        <button class="toast-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
                    </div>
                    <?php unset($_SESSION['warning']); ?>
                <?php endif; ?>
            </div>
            <script>
                window.addEventListener('DOMContentLoaded', () => {
                    document.querySelectorAll('.toast-item').forEach(t => {
                        setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(24px)'; t.style.transition='all .3s'; setTimeout(()=>t.remove(),300); }, 4000);
                    });
                });
            </script>

            <!-- Low stock banner -->
            <?php if (!empty($lowStockProducts) || !empty($outOfStockProducts)): ?>
            <div class="alert-banner">
                <div class="alert-banner-left">
                    <i class="bi bi-exclamation-triangle-fill alert-banner-icon"></i>
                    <div>
                        <div class="alert-banner-title">Stock Alert</div>
                        <div class="alert-banner-sub">
                            <?= count($outOfStockProducts) ?> out of stock • <?= count($lowStockProducts) ?> low stock
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-alert-view" data-bs-toggle="modal" data-bs-target="#lowStockModal">
                    View Alerts
                </button>
            </div>
            <?php endif; ?>

            <!-- Page header -->
            <div class="page-hdr">
                <div class="page-hdr-left">
                    <div class="page-hdr-icon"><i class="bi bi-box-seam-fill"></i></div>
                    <div>
                        <h4>Product Management</h4>
                        <p>Manage inventory and stock levels</p>
                    </div>
                </div>
                <div class="hdr-actions">
                    <button class="btn-hdr green" data-bs-toggle="modal" data-bs-target="#stockInModal">
                        <i class="bi bi-box-arrow-in-down"></i> Stock In
                    </button>
                    <button class="btn-hdr red" data-bs-toggle="modal" data-bs-target="#stockOutModal">
                        <i class="bi bi-box-arrow-up"></i> Stock Out
                    </button>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="stat-row">
                <div class="stat-card">
                    <div class="stat-lbl"><i class="bi bi-box-seam"></i> Total Products</div>
                    <div class="stat-val"><?= number_format($total_products_summary) ?></div>
                    <div class="stat-sub">All SKUs in system</div>
                </div>
                <div class="stat-card g">
                    <div class="stat-lbl"><i class="bi bi-check-circle"></i> In Stock</div>
                    <div class="stat-val" style="color:var(--green)"><?= number_format($in_stock_count) ?></div>
                    <div class="stat-sub">Products available</div>
                </div>
                <div class="stat-card a">
                    <div class="stat-lbl"><i class="bi bi-exclamation-circle"></i> Low Stock</div>
                    <div class="stat-val" style="color:var(--amber)"><?= number_format($low_stock_count) ?></div>
                    <div class="stat-sub">Below reorder level</div>
                </div>
                <div class="stat-card r">
                    <div class="stat-lbl"><i class="bi bi-x-circle"></i> Out of Stock</div>
                    <div class="stat-val" style="color:var(--red)"><?= number_format($out_of_stock_count) ?></div>
                    <div class="stat-sub">Zero inventory</div>
                </div>
            </div>

            <!-- Filter panel -->
            <div class="panel">
                <div class="panel-header" id="filterPanelBtn">
                    <div class="panel-hl">
                        <i class="bi bi-sliders"></i>
                        Filter Products
                        <?php if ($has_active_filters): ?>
                            <span class="filter-dot"></span>
                            <span class="filter-active-pill">Active</span>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-chevron-down panel-chevron <?= ($brand_filter || $variant_filter) ? 'open' : '' ?>" id="filterChevron"></i>
                </div>
                <div class="panel-body <?= ($brand_filter || $variant_filter) ? 'open' : '' ?>" id="filterPanelBody">
                    <form action="staff_products.php" method="GET" id="productFilterForm">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="f-label">Search</label>
                                <div class="f-search-wrap">
                                    <i class="bi bi-search"></i>
                                    <input type="text" class="f-input" name="q" placeholder="Name, SKU, category, brand, variation…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="f-label">Category</label>
                                <select class="f-input" name="category_id">
                                    <option value="">All categories</option>
                                    <?php
                                    mysqli_data_seek($categories_query, 0);
                                    while ($cat = mysqli_fetch_assoc($categories_query)): ?>
                                        <option value="<?= (int)$cat['category_id'] ?>" <?= (isset($_GET['category_id']) && $_GET['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="f-label">Subcategory</label>
                                <select class="f-input" name="subcategory_id">
                                    <option value="">All subcategories</option>
                                    <?php
                                    mysqli_data_seek($subcategories_query, 0);
                                    while ($sub = mysqli_fetch_assoc($subcategories_query)): ?>
                                        <option value="<?= (int)$sub['subcategory_id'] ?>" <?= (isset($_GET['subcategory_id']) && $_GET['subcategory_id'] == $sub['subcategory_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sub['subcategory_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="f-label">Brand</label>
                                <input type="text" class="f-input" name="brand_filter" value="<?= htmlspecialchars($brand_filter) ?>" placeholder="e.g. Makita">
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="f-label">Variation</label>
                                <input type="text" class="f-input" name="variant_filter" value="<?= htmlspecialchars($variant_filter) ?>" placeholder="e.g. 10mm">
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn-apply"><i class="bi bi-funnel-fill"></i> Apply</button>
                            <a href="staff_products.php" class="btn-reset"><i class="bi bi-x-circle"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Products table -->
            <div class="table-card">
                <div class="table-card-hdr">
                    <div class="table-card-title"><i class="bi bi-table"></i> Product Inventory</div>
                    <span class="record-pill"><?= number_format($total_products) ?> product<?= $total_products !== 1 ? 's' : '' ?></span>
                </div>

                <div style="overflow-x:auto;">
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <table class="data-tbl">
                        <thead>
                            <tr>
                                <th>Category / Sub</th>
                                <th>Product</th>
                                <th style="text-align:center;">Stock</th>
                                <th style="text-align:right;">Price</th>
                                <th>Details</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)):
                                $sl = (float)$row['stock'];
                                $rl = (int)($row['reorder_level'] ?? 0);
                                if ($sl === 0)   { $bc='out';     $bt='Out of Stock'; }
                                elseif ($rl > 0 && $sl <= $rl) { $bc='low'; $bt='Low Stock'; }
                                else                 { $bc='ok';      $bt='Sufficient'; }
                            ?>
                            <tr>
                                <td>
                                    <div class="cell-cat"><?= htmlspecialchars($row['category_name'] ?? 'Unknown') ?></div>
                                    <div class="cell-subcat"><?= htmlspecialchars($row['subcategory_name'] ?? '—') ?></div>
                                </td>
                                <td>
                                    <div class="cell-name"><?= htmlspecialchars($row['product_name']) ?></div>
                                    <?php $meta = []; if (!empty($row['brand'])) $meta[] = htmlspecialchars($row['brand']); if (!empty($row['variation'])) $meta[] = htmlspecialchars($row['variation']); ?>
                                    <?php if (!empty($meta)): ?>
                                        <div class="cell-meta"><?= implode(' · ', $meta) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="stock-badge <?= $bc ?>">
                                        <span class="stock-num">
                                            <?= formatQty($sl) ?>
                                            <?= htmlspecialchars($row['unit']??'') ?>
                                        </span>
                                    </span>
                                    <div class="stock-status-lbl"><?= $bt ?></div>
                                </td>
                                <td style="text-align:right;">
                                    <div class="cell-price">₱<?= number_format($row['selling_price']??0, 2) ?></div>
                                </td>
                                <td>
                                    <div class="cell-desc" title="<?= htmlspecialchars($row['description']??'') ?>"><?= htmlspecialchars($row['description'] ?: '—') ?></div>
                                    <div class="cell-sku"><?= htmlspecialchars($row['sku']??'—') ?></div>
                                </td>
                                <td style="text-align:right;">
                                    <div class="dropdown">
                                        <button class="tbl-actions-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><button class="dropdown-item di-info" data-bs-toggle="modal" data-bs-target="#productDetailsModal" data-product-id="<?= (int)$row['product_id'] ?>" data-product-name="<?= htmlspecialchars($row['product_name']) ?>"><i class="bi bi-eye me-2"></i>View Details</button></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-search"></i></div>
                        <h6>No products found</h6>
                        <p>Try adjusting your filters.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pager-wrap">
                    <div class="pager-info">
                        Showing <?= min($offset+1,$total_products) ?>–<?= min($offset+$limit,$total_products) ?> of <?= number_format($total_products) ?> · Page <?= $page ?>/<?= $total_pages ?>
                    </div>
                    <div class="pager">
                        <?php if ($page>1): ?><a href="<?= buildPaginationUrl($page-1,$_GET) ?>"><i class="bi bi-chevron-left" style="font-size:.7rem"></i></a><?php else: ?><span class="disabled"><i class="bi bi-chevron-left" style="font-size:.7rem"></i></span><?php endif; ?>
                        <?php $sp=max(1,$page-2); $ep=min($total_pages,$page+2);
                        if($sp>1){echo '<a href="'.buildPaginationUrl(1,$_GET).'">1</a>';if($sp>2)echo '<span class="dots">…</span>';}
                        for($i=$sp;$i<=$ep;$i++) echo '<a href="'.buildPaginationUrl($i,$_GET).'" class="'.($i===$page?'active':'').'">'.$i.'</a>';
                        if($ep<$total_pages){if($ep<$total_pages-1)echo '<span class="dots">…</span>';echo '<a href="'.buildPaginationUrl($total_pages,$_GET).'">'.$total_pages.'</a>';}
                        ?>
                        <?php if ($page<$total_pages): ?><a href="<?= buildPaginationUrl($page+1,$_GET) ?>"><i class="bi bi-chevron-right" style="font-size:.7rem"></i></a><?php else: ?><span class="disabled"><i class="bi bi-chevron-right" style="font-size:.7rem"></i></span><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php require 'product_modals.php'; ?>
<?php require 'stock_out_modal.php'; ?>
<?php require 'staff_stockin_modal.php'; ?>

<!-- STOCK ALERTS MODAL -->
<div class="modal fade" id="lowStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title" style="color:var(--red)"><i class="bi bi-exclamation-triangle-fill me-2"></i>Stock Alerts</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-out">
                        <i class="bi bi-x-circle me-1"></i>Out of Stock (<?= count($outOfStockProducts) ?>)
                    </button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-low">
                        <i class="bi bi-graph-down me-1"></i>Low Stock (<?= count($lowStockProducts) ?>)
                    </button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cat">
                        <i class="bi bi-tags me-1"></i>By Category
                    </button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-out">
                        <?php if (empty($outOfStockProducts)): ?>
                            <div style="text-align:center;padding:32px;color:var(--green);"><i class="bi bi-check-circle-fill" style="font-size:2rem;"></i><p class="mt-2">No out-of-stock items!</p></div>
                        <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="alert-modal-tbl">
                                <thead><tr><th>Product</th><th style="text-align:center;">Stock</th><th style="text-align:center;">Reorder Level</th><th style="text-align:center;">Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($outOfStockProducts as $p): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600;"><?= htmlspecialchars($p['product_name']) ?></div>
                                            <div style="font-size:0.75rem; color:var(--muted);">
                                                <?php 
                                                $meta = array_filter([$p['brand'] ?? '', $p['variation'] ?? '', $p['unit'] ?? '']);
                                                echo htmlspecialchars(implode(' · ', $meta));
                                                ?>
                                            </div>
                                        </td>
                                        <td style="text-align:center;"><span class="stock-badge out">0</span></td>
                                        <td style="text-align:center;font-family:var(--mono)">
                                            <?= formatQty($p['reorder_level'] ?? 0) ?>
                                        </td>
                                        <td style="text-align:center;"><span class="stock-badge out">Out of Stock</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade" id="tab-low">
                        <?php if (empty($lowStockProducts)): ?>
                            <div style="text-align:center;padding:32px;color:var(--green);"><i class="bi bi-check-circle-fill" style="font-size:2rem;"></i><p class="mt-2">No low stock items!</p></div>
                        <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="alert-modal-tbl">
                                <thead><tr><th>Product</th><th style="text-align:center;">Stock</th><th style="text-align:center;">Reorder Level</th><th style="text-align:center;">Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($lowStockProducts as $p): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600;"><?= htmlspecialchars($p['product_name']) ?></div>
                                            <div style="font-size:0.75rem; color:var(--muted);">
                                                <?php 
                                                $meta = array_filter([$p['brand'] ?? '', $p['variation'] ?? '', $p['unit'] ?? '']);
                                                echo htmlspecialchars(implode(' · ', $meta));
                                                ?>
                                            </div>
                                        </td>
                                        <td style="text-align:center;"><span class="stock-badge low">
                                            <?= formatQty($p['stock']) ?>
                                        </span></td>
                                        <td style="text-align:center;font-family:var(--mono)">
                                            <?= formatQty($p['reorder_level'] ?? 0) ?>
                                        </td>
                                        <td style="text-align:center;"><span class="stock-badge low">Low Stock</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade" id="tab-cat">
                        <?php if (empty($productsByCategory)): ?>
                            <div style="text-align:center;padding:32px;color:var(--muted);">No categories yet.</div>
                        <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="alert-modal-tbl">
                                <thead><tr><th>Category</th><th style="text-align:center;">Items</th><th style="text-align:center;">Total Stock</th><th style="text-align:center;">Out</th><th style="text-align:center;">Low</th></tr></thead>
                                <tbody>
                                    <?php foreach ($productsByCategory as $cat): ?>
                                    <tr>
                                        <td style="font-weight:600;"><?= htmlspecialchars($cat['category_name'] ?: 'Uncategorized') ?></td>
                                        <td style="text-align:center;"><?= (int)$cat['total_products'] ?></td>
                                        <td style="text-align:center;font-family:var(--mono)">
                                            <?= formatQty($cat['total_stock'] ?? 0) ?>
                                        </td>
                                        <td style="text-align:center;"><?= (int)($cat['out_of_stock_count']??0) ?></td>
                                        <td style="text-align:center;"><?= (int)($cat['low_stock_count']??0) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Filter panel toggle
    const filterPanelBtn = document.getElementById('filterPanelBtn');
    const filterPanelBody = document.getElementById('filterPanelBody');
    const filterChevron = document.getElementById('filterChevron');

    if (filterPanelBtn && filterPanelBody && filterChevron) {
        filterPanelBtn.addEventListener('click', () => {
            filterPanelBody.classList.toggle('open');
            filterChevron.classList.toggle('open');
        });
    }

    // Toast auto-close
    window.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.toast-item').forEach(t => {
            setTimeout(() => {
                t.style.opacity = '0';
                t.style.transform = 'translateX(24px)';
                t.style.transition = 'all .3s';
                setTimeout(() => t.remove(), 300);
            }, 4000);
        });
    });

    // Product Details Modal
    const productDetailsModal = document.getElementById('productDetailsModal');
    const productDetailsContent = document.getElementById('productDetailsContent');
    const productDetailsModalLabel = document.getElementById('productDetailsModalLabel');

    if (productDetailsModal && productDetailsContent && productDetailsModalLabel) {
        productDetailsModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const productId = button.getAttribute('data-product-id');
            const productName = button.getAttribute('data-product-name');

            productDetailsModalLabel.innerHTML = `<i class="bi bi-eye me-2"></i> ${productName} - Batch History`;

            fetch(`fetch_product_details.php?product_id=${productId}`)
                .then(response => response.text())
                .then(html => {
                    productDetailsContent.innerHTML = html;
                })
                .catch(error => {
                    productDetailsContent.innerHTML = `
                        <div style="padding:20px;text-align:center;color:var(--red);">
                            <i class="bi bi-exclamation-circle-fill" style="font-size:2rem;"></i>
                            <p class="mt-2" style="font-size:.9rem;">Failed to load product details.</p>
                        </div>
                    `;
                    console.error('Error loading product details:', error);
                });
        });

        productDetailsModal.addEventListener('hidden.bs.modal', function () {
            productDetailsContent.innerHTML = '';
        });
    }
</script>

<!-- Product Details Modal -->
<div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productDetailsModalLabel">
                    <i class="bi bi-eye me-2"></i> Product Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body" id="productDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

</body>
</html>