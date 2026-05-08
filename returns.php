<?php
include 'project.php';
session_start();

/* ============================================================
   RETURNS PAGE
   CLEAN + ORGANIZED VERSION
   ============================================================ */

/* ============================================================
   1) AUTH
   ============================================================ */
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['username'];
$current_role = $_SESSION['role'];
$dashboard_link = ($current_role === 'admin') ? 'admin_dashboard.php' : 'staff_dashboard.php';

/* ============================================================
   2) HELPERS
   ============================================================ */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return '₱' . number_format((float)$value, 2);
}

function buildReturnPaginationUrl(int $pageNum): string
{
    $params = $_GET;
    $params['history_page'] = $pageNum;
    return 'returns.php?' . http_build_query($params);
}

/* ============================================================
   3) PAGE STATE
   ============================================================ */
$sale_group_id_searched = null;
$sale_group = null;
$sale_items = [];
$search_error = '';
$return_details_map = [];

/* ============================================================
   4) PAGINATION FOR RETURN HISTORY
   ============================================================ */
$history_limit = 20;
$history_page = isset($_GET['history_page']) && is_numeric($_GET['history_page']) && (int)$_GET['history_page'] > 0
    ? (int)$_GET['history_page']
    : 1;
$history_offset = ($history_page - 1) * $history_limit;

/* ============================================================
   5) PROCESS RETURN
   - prevents over-returning
   - adds stock back only to products.stock
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    $original_sale_id = (int)($_POST['original_sale_group_id'] ?? 0);
    $return_reason = trim($_POST['return_reason'] ?? '');
    $items_to_return_data = $_POST['items_to_return'] ?? [];
    $total_value_returned  = 0.00;
    $total_returned_gross  = 0.00; // sum of original (pre-discount) line values for returned portions

    $items_to_return = [];
    foreach ($items_to_return_data as $product_id => $quantity) {
        $qty_float = (float)$quantity;
        if ($qty_float > 0) {
            $items_to_return[(int)$product_id] = $qty_float;
        }
    }

    if ($original_sale_id <= 0 || empty($items_to_return) || $return_reason === '') {
        $_SESSION['error'] = "Return failed: Missing sale ID, items to return, or reason.";
        header("Location: returns.php?search_sale_id=" . $original_sale_id);
        exit();
    }

    $conn->begin_transaction();

    try {
        /* 1) Create main return record */
        $stmt_return = $conn->prepare("
            INSERT INTO returns (original_sale_group_id, return_reason, processed_by, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        if (!$stmt_return) {
            throw new Exception("Prepare failed (returns): " . $conn->error);
        }

        $stmt_return->bind_param("iss", $original_sale_id, $return_reason, $current_user);

        if (!$stmt_return->execute()) {
            throw new Exception("Failed to create return record: " . $stmt_return->error);
        }

        $return_id = $conn->insert_id;
        $stmt_return->close();

        if ($return_id <= 0) {
            throw new Exception("Could not get new return ID.");
        }

        /* 2-pre) Fetch sale-level discount and gross total for prorated refund */
        $stmt_disc_info = $conn->prepare("
            SELECT
                sg.discount_amount,
                COALESCE(SUM(s2.total_price), 0) AS gross_total
            FROM sale_groups sg
            LEFT JOIN sales s2 ON s2.sale_group_id = sg.sale_group_id
            WHERE sg.sale_group_id = ?
            GROUP BY sg.sale_group_id, sg.discount_amount
            LIMIT 1
        ");
        if (!$stmt_disc_info) {
            throw new Exception("Prepare failed (discount info): " . $conn->error);
        }
        $stmt_disc_info->bind_param("i", $original_sale_id);
        $stmt_disc_info->execute();
        $disc_info = $stmt_disc_info->get_result()->fetch_assoc();
        $stmt_disc_info->close();
        $sale_discount_amount = (float)($disc_info['discount_amount'] ?? 0);
        $sale_gross_total     = (float)($disc_info['gross_total']     ?? 0);

        /* 2) Prepare reusable statements */
        $stmt_get_sale_item = $conn->prepare("
            SELECT
                s.quantity AS sold_qty,
                s.total_price,
                COALESCE(SUM(ri.quantity), 0) AS already_returned_qty
            FROM sales s
            LEFT JOIN returns r
                ON r.original_sale_group_id = s.sale_group_id
            LEFT JOIN return_items ri
                ON ri.return_id = r.return_id
               AND ri.product_id = s.product_id
            WHERE s.sale_group_id = ?
              AND s.product_id = ?
            GROUP BY s.sale_id, s.quantity, s.total_price
            LIMIT 1
        ");
        if (!$stmt_get_sale_item) {
            throw new Exception("Prepare failed (get sale item): " . $conn->error);
        }

        $stmt_return_item = $conn->prepare("
            INSERT INTO return_items (return_id, product_id, quantity)
            VALUES (?, ?, ?)
        ");
        if (!$stmt_return_item) {
            throw new Exception("Prepare failed (return_items): " . $conn->error);
        }

        $stmt_update_stock = $conn->prepare("
            UPDATE products
            SET stock = stock + ?
            WHERE product_id = ?
        ");
        if (!$stmt_update_stock) {
            throw new Exception("Prepare failed (update stock): " . $conn->error);
        }

        /* 3) Process each returned item */
        foreach ($items_to_return as $product_id => $quantity_to_return) {
            if ($quantity_to_return <= 0) {
                continue;
            }

            $stmt_get_sale_item->bind_param("ii", $original_sale_id, $product_id);
            if (!$stmt_get_sale_item->execute()) {
                throw new Exception("Failed to fetch sale details for product #{$product_id}: " . $stmt_get_sale_item->error);
            }

            $sale_item_details = $stmt_get_sale_item->get_result()->fetch_assoc();

            if (!$sale_item_details) {
                throw new Exception("Sale item not found for product #{$product_id} under sale #{$original_sale_id}.");
            }

            $sold_qty = (float)$sale_item_details['sold_qty'];
            $already_returned_qty = (float)$sale_item_details['already_returned_qty'];
            $remaining_returnable_qty = max(0.0, $sold_qty - $already_returned_qty);

            if ($remaining_returnable_qty <= 0) {
                throw new Exception("Product #{$product_id} has already been fully returned.");
            }

            if ($quantity_to_return > $remaining_returnable_qty) {
                throw new Exception("Return quantity for product #{$product_id} exceeds remaining returnable quantity ({$remaining_returnable_qty}).");
            }

            /* Accumulate the original (pre-discount) line value for the returned portion.
               We compute the final refund proportionally AFTER the loop to avoid
               per-item floating-point rounding drift (e.g. ₱15,299.97 vs ₱15,300.00). */
            $item_line_total      = (float)$sale_item_details['total_price'];
            $total_returned_gross += $item_line_total * ($quantity_to_return / max(1, $sold_qty));

            $stmt_return_item->bind_param("iid", $return_id, $product_id, $quantity_to_return);
            if (!$stmt_return_item->execute()) {
                throw new Exception("Failed to add return item for product #{$product_id}: " . $stmt_return_item->error);
            }

            $stmt_update_stock->bind_param("di", $quantity_to_return, $product_id);
            if (!$stmt_update_stock->execute()) {
                throw new Exception("Failed to update stock for product #{$product_id}: " . $stmt_update_stock->error);
            }
        }

        $stmt_get_sale_item->close();
        $stmt_return_item->close();
        $stmt_update_stock->close();

        /* Compute final refund in ONE proportional step — eliminates per-item rounding drift.
           Formula: refund = (gross of returned items) / (gross of all items) × net amount paid */
        $sale_net_paid        = max(0.0, $sale_gross_total - $sale_discount_amount);
        $total_value_returned = ($sale_gross_total > 0)
            ? round($total_returned_gross / $sale_gross_total * $sale_net_paid, 2)
            : round($total_returned_gross, 2);

        $conn->commit();

        $_SESSION['success'] =
            "Return processed successfully for Sale ID #{$original_sale_id}. Financial value returned: " .
            money($total_value_returned) . ".";

        header("Location: returns.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Return failed: " . $e->getMessage();
        error_log("Return Processing Error: " . $e->getMessage());
        header("Location: returns.php?search_sale_id=" . $original_sale_id);
        exit();
    }
}

/* ============================================================
   6) SEARCH SALE
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_sale_id'])) {
    $sale_group_id_searched = (int)($_GET['search_sale_id'] ?? 0);

    if ($sale_group_id_searched > 0) {
        $group_sql = "
            SELECT
                sg.sale_group_id,
                sg.created_at,
                COALESCE(u.full_name, sg.created_by) AS created_by,
                sp.total_amount
            FROM sale_groups sg
            LEFT JOIN sale_payments sp ON sg.sale_group_id = sp.sale_group_id
            LEFT JOIN users u ON sg.created_by = u.username
            WHERE sg.sale_group_id = ?
            LIMIT 1
        ";
        $stmt_group = $conn->prepare($group_sql);
        $stmt_group->bind_param("i", $sale_group_id_searched);
        $stmt_group->execute();
        $sale_group = $stmt_group->get_result()->fetch_assoc();
        $stmt_group->close();

        if ($sale_group) {
            $items_sql = "
                SELECT
                    s.sale_id,
                    s.product_id,
                    p.product_name,
                    p.brand,
                    p.variation,
                    s.quantity AS sold_qty,
                    s.total_price,
                    (s.total_price / NULLIF(s.quantity, 0)) AS unit_price,
                    sg.discount_amount,
                    p.unit,
                    (SELECT COALESCE(SUM(s2.total_price), 0)
                     FROM sales s2 WHERE s2.sale_group_id = s.sale_group_id) AS group_gross_total,
                    COALESCE(SUM(ri.quantity), 0) AS already_returned_qty
                FROM sales s
                JOIN products p ON s.product_id = p.product_id
                JOIN sale_groups sg ON sg.sale_group_id = s.sale_group_id
                LEFT JOIN returns r
                    ON r.original_sale_group_id = s.sale_group_id
                LEFT JOIN return_items ri
                    ON ri.return_id = r.return_id
                   AND ri.product_id = s.product_id
                WHERE s.sale_group_id = ?
                GROUP BY
                    s.sale_id,
                    s.product_id,
                    p.product_name,
                    p.brand,
                    p.variation,
                    s.quantity,
                    s.total_price,
                    sg.discount_amount,
                    p.unit
                ORDER BY p.product_name ASC
            ";
            $stmt_items = $conn->prepare($items_sql);
            $stmt_items->bind_param("i", $sale_group_id_searched);
            $stmt_items->execute();
            $items_result = $stmt_items->get_result();

            while ($row = $items_result->fetch_assoc()) {
                $row['remaining_returnable_qty'] = max(0.0, (float)$row['sold_qty'] - (float)$row['already_returned_qty']);
                /* Compute discounted unit price for display and JS */
                $g_line   = (float)$row['total_price'];
                $g_gross  = (float)$row['group_gross_total'];
                $g_disc   = (float)$row['discount_amount'];
                $g_ratio  = ($g_gross > 0) ? ($g_line / $g_gross) : 0;
                $g_prorated = $g_disc * $g_ratio;
                $g_discounted_line = max(0.0, $g_line - $g_prorated);
                $row['discounted_unit_price'] = $g_discounted_line / max(0.001, (float)$row['sold_qty']);
                $row['prorated_discount']     = $g_prorated;
                $sale_items[] = $row;
            }
            $stmt_items->close();

            if (empty($sale_items)) {
                $search_error = "No items found for Sale ID #{$sale_group_id_searched}. Cannot process return.";
                $sale_group = null;
            }
        } else {
            $search_error = "Sale ID #{$sale_group_id_searched} not found.";
        }
    } else {
        $search_error = "Please enter a valid Sale ID (Receipt #).";
    }
}

/* ============================================================
   7) KPI SUMMARY
   ============================================================ */
$kpi_query = "
    SELECT
        COUNT(DISTINCT r.return_id) AS total_return_records,
        COALESCE(SUM(ri.quantity), 0) AS total_items_returned,
        COALESCE(SUM(
            ri.quantity *
            GREATEST(0,
                s.total_price - (
                    sg.discount_amount * s.total_price /
                    NULLIF((SELECT SUM(s2.total_price) FROM sales s2 WHERE s2.sale_group_id = s.sale_group_id), 0)
                )
            ) / NULLIF(s.quantity, 0)
        ), 0) AS total_loss_value
    FROM returns r
    JOIN return_items ri ON r.return_id = ri.return_id
    JOIN sales s
        ON s.sale_group_id = r.original_sale_group_id
       AND s.product_id = ri.product_id
    JOIN sale_groups sg ON sg.sale_group_id = r.original_sale_group_id
";
$kpi_result = $conn->query($kpi_query);
$kpi_row = $kpi_result ? $kpi_result->fetch_assoc() : null;

$total_return_records = (int)($kpi_row['total_return_records'] ?? 0);
$total_items_returned = (float)($kpi_row['total_items_returned'] ?? 0);
$total_loss_value = (float)($kpi_row['total_loss_value'] ?? 0);

/* ============================================================
   8) RECENT RETURN HISTORY WITH PAGINATION
   ============================================================ */
$history_count_query = "SELECT COUNT(*) AS total_returns FROM returns";
$history_count_result = $conn->query($history_count_query);
$history_total_rows = 0;

if ($history_count_result) {
    $history_total_rows = (int)($history_count_result->fetch_assoc()['total_returns'] ?? 0);
}

$history_total_pages = ($history_total_rows > 0) ? (int)ceil($history_total_rows / $history_limit) : 1;

if ($history_page > $history_total_pages) {
    $history_page = $history_total_pages;
    $history_offset = ($history_page - 1) * $history_limit;
}
if ($history_page < 1) {
    $history_page = 1;
    $history_offset = 0;
}

$history_query = "
    SELECT
        r.return_id,
        r.original_sale_group_id,
        r.return_reason,
        COALESCE(u.full_name, r.processed_by) AS processed_by,
        r.created_at,
        SUM(ri.quantity) AS total_items_returned,
        COALESCE(SUM(
            ri.quantity *
            GREATEST(0,
                s.total_price - (
                    sg.discount_amount * s.total_price /
                    NULLIF((SELECT SUM(s2.total_price) FROM sales s2 WHERE s2.sale_group_id = s.sale_group_id), 0)
                )
            ) / NULLIF(s.quantity, 0)
        ), 0) AS financial_value_returned
    FROM returns r
    JOIN return_items ri ON r.return_id = ri.return_id
    JOIN sales s
        ON s.sale_group_id = r.original_sale_group_id
       AND s.product_id = ri.product_id
    JOIN sale_groups sg ON sg.sale_group_id = r.original_sale_group_id
    LEFT JOIN users u ON r.processed_by = u.username
    GROUP BY
        r.return_id,
        r.original_sale_group_id,
        r.return_reason,
        r.processed_by,
        r.created_at
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";

$history_rows = [];
$stmt_history = $conn->prepare($history_query);

if ($stmt_history) {
    $stmt_history->bind_param("ii", $history_limit, $history_offset);
    $stmt_history->execute();
    $history_result = $stmt_history->get_result();

    while ($row = $history_result->fetch_assoc()) {
        $history_rows[] = $row;
    }

    $stmt_history->close();
}

/* ============================================================
   9) RETURN DETAILS FOR VIEW MODALS
   ============================================================ */
$history_detail_query = "
    SELECT
        r.return_id,
        p.product_name,
        p.brand,
        p.variation,
        p.unit,
        ri.quantity,
        GREATEST(0,
            s.total_price - (
                sg.discount_amount * s.total_price /
                NULLIF((SELECT SUM(s2.total_price) FROM sales s2 WHERE s2.sale_group_id = s.sale_group_id), 0)
            )
        ) / NULLIF(s.quantity, 0) AS unit_price,
        ri.quantity * GREATEST(0,
            s.total_price - (
                sg.discount_amount * s.total_price /
                NULLIF((SELECT SUM(s2.total_price) FROM sales s2 WHERE s2.sale_group_id = s.sale_group_id), 0)
            )
        ) / NULLIF(s.quantity, 0) AS subtotal
    FROM returns r
    JOIN return_items ri ON r.return_id = ri.return_id
    JOIN products p ON p.product_id = ri.product_id
    JOIN sales s
        ON s.sale_group_id = r.original_sale_group_id
       AND s.product_id = ri.product_id
    JOIN sale_groups sg ON sg.sale_group_id = r.original_sale_group_id
    ORDER BY r.return_id DESC, p.product_name ASC
";
$history_detail_result = $conn->query($history_detail_query);
if ($history_detail_result) {
    while ($row = $history_detail_result->fetch_assoc()) {
        $rid = (int)$row['return_id'];
        if (!isset($return_details_map[$rid])) {
            $return_details_map[$rid] = [];
        }
        $return_details_map[$rid][] = $row;
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Return</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin1.css">
    <link rel="stylesheet" href="css/alert.css">
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

        /* Page header */
        .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; flex-wrap: wrap; }
        .page-header-icon {
            width: 44px; height: 44px; border-radius: 12px;
            background: #fee2e2; color: #ef4444;
            display: grid; place-items: center; font-size: 1.2rem; flex-shrink: 0;
        }
        .page-header-text h1 { font-size: 1.35rem; font-weight: 700; color: #111827; margin: 0 0 2px; }
        .page-header-text p  { font-size: .82rem; color: #6b7280; margin: 0; }

        /* Sidebar toggle */
        .sidebar-toggle-btn { display:none; background:#2563eb; color:#fff; border:none; border-radius:6px; padding:7px 12px; font-size:1.1rem; cursor:pointer; margin-bottom:1rem; }
        @media (max-width:991px) { .sidebar-toggle-btn { display:inline-flex; align-items:center; } }

        /* KPI cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 24px; }
        @media(max-width:900px){ .kpi-grid { grid-template-columns: repeat(2,1fr); } }
        @media(max-width:580px){ .kpi-grid { grid-template-columns: 1fr; } }
        .kpi-card {
            background: #fff; border-radius: 12px; padding: 18px 20px 14px;
            border-top: 4px solid transparent;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            display: flex; justify-content: space-between; align-items: flex-start;
        }
        .kpi-card.blue   { border-top-color: #3b82f6; }
        .kpi-card.orange { border-top-color: #f97316; }
        .kpi-card.red    { border-top-color: #ef4444; }
        .kpi-label { font-size:.65rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#6b7280; margin-bottom:6px; }
        .kpi-value { font-size:1.65rem; font-weight:700; color:#111827; line-height:1; }
        .kpi-value.sm { font-size: 1.15rem; }
        .kpi-sub   { font-size:.75rem; color:#9ca3af; margin-top:6px; }
        .kpi-icon  { width:36px; height:36px; border-radius:8px; display:grid; place-items:center; font-size:1rem; flex-shrink:0; }
        .kpi-icon.blue   { background:#dbeafe; color:#3b82f6; }
        .kpi-icon.orange { background:#ffedd5; color:#f97316; }
        .kpi-icon.red    { background:#fee2e2; color:#ef4444; }

        /* Panel */
        .panel {
            background: #fff; border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            margin-bottom: 20px; overflow: hidden;
        }
        .panel-header {
            padding: 16px 20px; border-bottom: 1px solid #f3f4f6;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
        }
        .panel-title { font-size:.95rem; font-weight:700; color:#111827; margin:0; }
        .panel-subtitle { font-size:.78rem; color:#9ca3af; margin:2px 0 0; }
        .panel-body { padding: 20px; }

        /* Search sale form */
        .search-form-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .search-form-row .form-group { flex: 1; min-width: 200px; }
        .field-label { font-size:.78rem; font-weight:600; color:#374151; margin-bottom:5px; display:block; }
        .form-select-styled, .form-input-styled {
            border: 1.5px solid #e5e7eb; border-radius: 8px;
            padding: 8px 12px; font-size:.85rem; font-family:inherit;
            color:#111827; background:#f9fafb; width:100%;
            transition: border-color .15s, box-shadow .15s; outline:none;
            height: 40px;
        }
        .form-select-styled:focus, .form-input-styled:focus {
            border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1); background:#fff;
        }
        .btn-find {
            background:#2563eb; color:#fff; border:none; border-radius:8px;
            height:40px; padding:0 22px; font-size:.85rem; font-weight:600;
            display:inline-flex; align-items:center; gap:6px; cursor:pointer;
            transition:background .15s; white-space:nowrap; font-family:inherit;
        }
        .btn-find:hover { background:#1d4ed8; }
        .alert-inline {
            background:#fff7ed; border:1px solid #fed7aa; border-radius:8px;
            padding:10px 14px; font-size:.83rem; color:#92400e;
            display:flex; align-items:center; gap:8px; margin-top:14px;
        }

        /* Return form card */
        .sale-meta-bar {
            background: #f8fafc; border-bottom: 1px solid #f3f4f6;
            padding: 12px 20px; display:flex; flex-wrap:wrap; gap:16px;
            font-size:.8rem; color:#6b7280;
        }
        .sale-meta-bar strong { color:#111827; }
        .sale-meta-bar .sale-id-badge {
            font-size:.85rem; font-weight:700; color:#2563eb;
        }

        /* Table */
        .rtable { width:100%; border-collapse:collapse; font-size:.83rem; }
        .rtable thead th {
            font-size:.68rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase;
            color:#6b7280; background:#f9fafb; padding:10px 14px;
            border-bottom:1px solid #f3f4f6; white-space:nowrap;
        }
        .rtable tbody tr { border-bottom:1px solid #f9fafb; transition:background .1s; }
        .rtable tbody tr:last-child { border-bottom:none; }
        .rtable tbody tr:hover { background:#fafbfd; }
        .rtable tbody tr.row-disabled { opacity:.6; }
        .rtable td { padding:12px 14px; vertical-align:middle; }
        .rtable tfoot td, .rtable tfoot th {
            padding:12px 14px; background:#f9fafb;
            border-top:1px solid #e5e7eb; font-size:.83rem;
        }

        .product-name { font-weight:600; color:#111827; }
        .product-meta { font-size:.74rem; color:#9ca3af; margin-top:2px; }
        .qty-badge-sold   { display:inline-block; background:#eff6ff; color:#2563eb; border-radius:6px; padding:2px 8px; font-size:.74rem; font-weight:600; }
        .qty-badge-ret    { display:inline-block; background:#fee2e2; color:#ef4444; border-radius:6px; padding:2px 8px; font-size:.74rem; font-weight:600; }
        .qty-badge-avail  { display:inline-block; background:#f0fdf4; color:#16a34a; border-radius:6px; padding:2px 8px; font-size:.74rem; font-weight:700; }
        .qty-badge-none   { display:inline-block; background:#f3f4f6; color:#9ca3af; border-radius:6px; padding:2px 8px; font-size:.74rem; font-weight:600; }
        .price-cell { font-family:'Courier New',monospace; font-size:.82rem; font-weight:500; color:#374151; }
        .subtotal-cell { font-family:'Courier New',monospace; font-size:.83rem; font-weight:700; color:#111827; }
        .total-value { font-family:'Courier New',monospace; font-size:.95rem; font-weight:700; color:#ef4444; }
        .fully-returned-badge { display:inline-block; background:#f3f4f6; color:#9ca3af; border-radius:99px; padding:3px 10px; font-size:.72rem; font-weight:600; }

        .return-qty-input {
            border:1.5px solid #e5e7eb; border-radius:7px; padding:5px 8px;
            font-size:.83rem; font-family:inherit; text-align:center; width:90px;
            transition:border-color .15s; outline:none;
        }
        .return-qty-input:focus { border-color:#f97316; box-shadow:0 0 0 3px rgba(249,115,22,.1); }

        .reason-label { font-size:.78rem; font-weight:600; color:#374151; margin-bottom:6px; display:block; }
        .reason-textarea {
            border:1.5px solid #e5e7eb; border-radius:8px; padding:10px 12px;
            font-size:.85rem; font-family:inherit; color:#111827; width:100%;
            transition:border-color .15s; outline:none; resize:vertical;
        }
        .reason-textarea:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1); }

        .form-actions {
            padding:16px 20px; border-top:1px solid #f3f4f6;
            display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;
        }
        .btn-cancel {
            background:#fff; color:#374151; border:1.5px solid #e5e7eb; border-radius:8px;
            padding:8px 18px; font-size:.85rem; font-weight:600; font-family:inherit;
            cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px;
            transition:border-color .15s, color .15s;
        }
        .btn-cancel:hover { border-color:#6b7280; color:#111827; }
        .btn-process {
            background:#f97316; color:#fff; border:none; border-radius:8px;
            padding:8px 20px; font-size:.85rem; font-weight:600; font-family:inherit;
            cursor:pointer; display:inline-flex; align-items:center; gap:6px;
            transition:background .15s; opacity:1;
        }
        .btn-process:hover { background:#ea6c0a; }
        .btn-process:disabled { background:#fcd0a8; cursor:not-allowed; }

        /* History table */
        .hist-table { width:100%; border-collapse:collapse; font-size:.83rem; }
        .hist-table thead th {
            font-size:.68rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase;
            color:#6b7280; background:#f9fafb; padding:10px 14px;
            border-bottom:1px solid #f3f4f6; white-space:nowrap;
        }
        .hist-table tbody tr { border-bottom:1px solid #f9fafb; transition:background .1s; }
        .hist-table tbody tr:last-child { border-bottom:none; }
        .hist-table tbody tr:hover { background:#fafbfd; }
        .hist-table td { padding:12px 14px; vertical-align:middle; }

        .id-chip { display:inline-block; background:#f3f4f6; border-radius:6px; padding:3px 8px; font-size:.74rem; font-weight:600; color:#374151; }
        .sale-ref-chip { display:inline-block; background:#eff6ff; border-radius:6px; padding:3px 8px; font-size:.74rem; font-weight:600; color:#2563eb; }
        .items-badge { display:inline-block; background:#fee2e2; color:#ef4444; border-radius:99px; padding:2px 9px; font-size:.74rem; font-weight:700; }
        .reason-clip { max-width:220px; font-size:.8rem; color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .staff-cell { display:flex; align-items:center; gap:6px; font-size:.82rem; color:#374151; }
        .staff-cell i { color:#9ca3af; }
        .cost-cell { font-family:'Courier New',monospace; font-weight:700; color:#ef4444; font-size:.84rem; }
        .date-main { font-weight:500; color:#111827; font-size:.8rem; }
        .date-sub  { font-size:.72rem; color:#9ca3af; }
        .view-btn {
            display:inline-flex; align-items:center; gap:5px;
            background:#eff6ff; color:#2563eb; border:1.5px solid #bfdbfe;
            border-radius:7px; padding:5px 11px; font-size:.74rem; font-weight:600;
            cursor:pointer; transition:all .12s; font-family:inherit;
        }
        .view-btn:hover { background:#2563eb; color:#fff; border-color:#2563eb; }

        .empty-state { text-align:center; padding:48px 20px; color:#9ca3af; }
        .empty-state i { font-size:2.2rem; opacity:.3; display:block; margin-bottom:10px; }
        .empty-state p { font-size:.85rem; margin:0; }

        /* Pagination */
        .pag-wrap { padding:14px 20px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; border-top:1px solid #f3f4f6; }
        .pag-info { font-size:.75rem; color:#6b7280; }
        .pag-info strong { color:#111827; }
        .pag-pages { display:flex; gap:4px; }
        .pag-btn {
            min-width:32px; height:32px; border-radius:7px; display:inline-flex; align-items:center; justify-content:center;
            font-size:.76rem; font-weight:600; border:1.5px solid #e5e7eb; color:#374151; background:#fff;
            text-decoration:none; padding:0 7px; transition:all .12s;
        }
        .pag-btn:hover { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
        .pag-btn.active { background:#2563eb; border-color:#2563eb; color:#fff; }
        .pag-btn.disabled { opacity:.4; pointer-events:none; }
        .pag-sep { display:flex; align-items:center; color:#9ca3af; font-size:.8rem; padding:0 2px; }

        /* Modal */
        .modal-content { border:none; border-radius:16px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.15); }
        .modal-header { background:#1e3a8a; border:none; padding:16px 20px; }
        .modal-title  { color:#fff; font-size:.92rem; font-weight:700; }
        .modal-header .btn-close { filter:invert(1) opacity(.6); }
        .modal-body { padding:20px; }
        .modal-footer { border-top:1px solid #f3f4f6; padding:14px 20px; }
        .detail-row { display:flex; gap:6px; margin-bottom:6px; font-size:.83rem; }
        .detail-lbl { font-weight:600; color:#374151; min-width:140px; flex-shrink:0; }
        .detail-val { color:#111827; }

        /* Confirm modal */
        .confirm-icon { width:52px; height:52px; background:#fff7ed; border-radius:50%; display:grid; place-items:center; font-size:1.4rem; color:#f97316; margin:0 auto 12px; }

        /* Alerts */
        .alert-toast-container { margin-bottom: 24px; }
        .alert-toast-container .alert {
            padding: 16px 20px; border-radius: 14px;
            display: flex; align-items: center; gap: 14px;
            font-size: .88rem; font-weight: 600;
            border: 1px solid transparent; box-shadow: 0 4px 12px rgba(0,0,0,.04);
            position: relative;
        }
        .alert-toast-container .alert-success { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .alert-toast-container .alert-error   { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .alert-close {
            background: none; border: none; color: #9ca3af; font-size: 1.4rem;
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            line-height: 1; cursor: pointer; transition: color .15s;
        }
        .alert-close:hover { color: #4b5563; }
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
            <li class="nav-item mb-2">
                <a class="nav-link" href="<?= e($dashboard_link) ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-title">Management</li>
            <li class="nav-item mb-2"><a class="nav-link" href="products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="categories.php"><i class="bi bi-tags me-2"></i> Categories</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="sales.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="p_os.php"><i class="bi bi-receipt me-2"></i> Invoice</a></li>
            <li class="nav-item mb-2"><a class="nav-link active" href="returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="stock_in_batches.php"><i class="bi bi-box-arrow-down me-2"></i> Stock-In Records</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="stock_out_history.php"><i class="bi bi-box-arrow-up me-2"></i> Stock-Out Records</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="admin_seasonal_report.php"><i class="bi bi-calendar-range me-2"></i> Seasonal Analysis</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-line me-2"></i> Reports</a></li>
            <li class="nav-item mb-2"><a class="nav-link" href="supplier.php"><i class="bi bi-truck me-2"></i> Suppliers</a></li>
            <?php if ($current_role === 'admin'): ?>
            <li class="sidebar-title">Users</li>
            <li class="nav-item mb-2"><a class="nav-link" href="manageUser.php"><i class="bi bi-people me-2"></i> Manage Users</a></li>
            <?php endif; ?>
            <li class="sidebar-title">Settings</li>
            <li class="nav-item mb-2"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <button class="sidebar-toggle-btn" id="sidebarToggle"><i class="bi bi-list"></i></button>

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-icon"><i class="bi bi-arrow-return-left"></i></div>
            <div class="page-header-text">
                <h1>Process Customer Return</h1>
                <p>Search a sale and select items to return to stock.</p>
            </div>
        </div>

        <!-- Alerts -->
        <div class="alert-toast-container" id="alertToastContainer">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= e($_SESSION['success']) ?></span>
                    <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span><?= e($_SESSION['error']) ?></span>
                    <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        </div>
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.alert-toast-container .alert').forEach(function(a) {
                    setTimeout(function() { a.style.display='none'; }, 3500);
                });
            });
        </script>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card blue">
                <div>
                    <div class="kpi-label">Total Return Records</div>
                    <div class="kpi-value"><?= number_format($total_return_records) ?></div>
                    <div class="kpi-sub">All processed returns</div>
                </div>
                <div class="kpi-icon blue"><i class="bi bi-arrow-return-left"></i></div>
            </div>
            <div class="kpi-card orange">
                <div>
                    <div class="kpi-label">Total Items Returned</div>
                    <div class="kpi-value"><?= number_format($total_items_returned) ?></div>
                    <div class="kpi-sub">Units back in stock</div>
                </div>
                <div class="kpi-icon orange"><i class="bi bi-boxes"></i></div>
            </div>
            <div class="kpi-card red">
                <div>
                    <div class="kpi-label">Financial Value Returned</div>
                    <div class="kpi-value sm"><?= money($total_loss_value) ?></div>
                    <div class="kpi-sub">Cumulative return value</div>
                </div>
                <div class="kpi-icon red"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>

        <!-- Search Sale Panel -->
        <div class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-title">Find Sale by Receipt #</p>
                    <p class="panel-subtitle">Select from recent sales or enter a Sale ID manually</p>
                </div>
            </div>
            <div class="panel-body">
                <form action="returns.php" method="GET">
                    <div class="search-form-row">

                        <div class="form-group" style="max-width:200px;">
                            <label class="field-label" for="searchSaleId">Or enter Sale ID</label>
                            <input
                                type="number"
                                class="form-input-styled"
                                id="searchSaleId"
                                name="search_sale_id"
                                value="<?= e($sale_group_id_searched ?? '') ?>"
                                placeholder="e.g. 42"
                            >
                        </div>
                        <div style="padding-top:20px;">
                            <button type="submit" class="btn-find">
                                <i class="bi bi-search"></i> Find Sale
                            </button>
                        </div>
                    </div>
                </form>
                <?php if ($search_error): ?>
                    <div class="alert-inline">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?= e($search_error) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Return Form -->
        <?php if ($sale_group && !empty($sale_items)): ?>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-title">
                        Return Items for
                        <span style="color:#2563eb;">Sale #<?= (int)$sale_group['sale_group_id'] ?></span>
                    </p>
                    <p class="panel-subtitle">Select quantities to return and provide a reason</p>
                </div>
            </div>
            <!-- Sale meta bar -->
            <div class="sale-meta-bar">
                <span><strong>Date:</strong> <?= date("M d, Y h:i A", strtotime($sale_group['created_at'])) ?></span>
                <span><strong>Cashier:</strong> <?= e($sale_group['created_by'] ?? '—') ?></span>
                <span><strong>Gross Total:</strong> <?= money(array_sum(array_column($sale_items, 'total_price'))) ?></span>
                <?php if ((float)($sale_group['discount_amount'] ?? 0) > 0): ?>
                <span style="color:#dc2626;"><strong>Discount:</strong> −<?= money((float)$sale_group['discount_amount']) ?></span>
                <?php endif; ?>
                <span><strong>Amount Paid:</strong> <?= money((float)($sale_group['total_amount'] ?? 0)) ?></span>
            </div>
            
            <div style="background: #eff6ff; padding: 10px 20px; border-bottom: 1px solid #dbeafe; font-size: 0.78rem; color: #1e40af; display: flex; align-items: center; gap: 8px;">
                <i class="bi bi-info-circle-fill"></i>
                <span><strong>Proportional Refund Active:</strong> Because a discount was applied to this sale, the refund is calculated based on the <em>net price paid</em> (Amount Paid ÷ Quantity). This ensures the correct portion of the discount is reversed.</span>
            </div>
            <form action="returns.php" method="POST" id="returnProcessForm">
                <input type="hidden" name="process_return" value="1">
                <input type="hidden" name="original_sale_group_id" value="<?= (int)$sale_group['sale_group_id'] ?>">
                <?php
                    $js_sale_gross = array_sum(array_column($sale_items, 'total_price'));
                    $js_sale_net   = (float)($sale_group['total_amount'] ?? 0);
                ?>

                <div style="overflow-x:auto;">
                    <table class="rtable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th style="text-align:center;">Qty Sold</th>
                                <th style="text-align:center;">Already Returned</th>
                                <th style="text-align:center;">Remaining</th>
                                <th style="text-align:right;">Orig. Unit</th>
                                <th style="text-align:right; color:#059669;">Discounted Unit</th>
                                <th style="text-align:center; background:#fff7ed;">Qty to Return</th>
                                <th style="text-align:right;">Refund</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sale_items as $item):
                                $remaining_qty = (int)$item['remaining_returnable_qty'];
                                $row_disabled = $remaining_qty <= 0;
                            ?>
                            <tr class="<?= $row_disabled ? 'row-disabled' : '' ?>">
                                <td>
                                    <div class="product-name"><?= e($item['product_name']) ?></div>
                                    <div class="product-meta">
                                        <?= e($item['brand'] ?? '—') ?>
                                        <?php if (!empty($item['variation'])): ?> &middot; <?= e($item['variation']) ?><?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align:center;"><span class="qty-badge-sold"><?= (float)$item['sold_qty'] ?> <small style="font-size: 0.7rem; color: #6b7280;"><?= e($item['unit'] ?? '') ?></small></span></td>
                                <td style="text-align:center;"><span class="qty-badge-ret"><?= (float)$item['already_returned_qty'] ?> <small style="font-size: 0.7rem; color: #6b7280;"><?= e($item['unit'] ?? '') ?></small></span></td>
                                <td style="text-align:center;">
                                    <?php if ($remaining_qty > 0): ?>
                                        <span class="qty-badge-avail"><?= (float)$item['remaining_returnable_qty'] ?> <small style="font-size: 0.7rem; color: #6b7280;"><?= e($item['unit'] ?? '') ?></small></span>
                                    <?php else: ?>
                                        <span class="qty-badge-none">0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;" class="price-cell" style="text-decoration:line-through; color:#9ca3af;"><?= money((float)$item['unit_price']) ?></td>
                                <td style="text-align:right; color:#059669; font-weight:700;" class="price-cell"><?= money((float)$item['discounted_unit_price']) ?></td>
                                <td style="text-align:center; background:#fff7ed;">
                                    <?php if ($remaining_qty > 0): ?>
                                        <input
                                            type="number"
                                            class="return-qty-input return-qty"
                                            name="items_to_return[<?= (int)$item['product_id'] ?>]"
                                            value="0" min="0" max="<?= (float)$item['remaining_returnable_qty'] ?>"
                                            step="any"
                                            data-max-qty="<?= (float)$item['remaining_returnable_qty'] ?>"
                                            data-orig-price="<?= number_format((float)$item['unit_price'], 4, '.', '') ?>"
                                            data-sold-qty="<?= (float)$item['sold_qty'] ?>"
                                            data-unit-price="<?= number_format((float)$item['discounted_unit_price'], 4, '.', '') ?>"
                                        >
                                    <?php else: ?>
                                        <span class="fully-returned-badge">Fully Returned</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;" class="subtotal-cell item-subtotal"><?= money(0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="7" style="text-align:right; font-weight:600; color:#374151;">Estimated Refund (after discount)</td>
                                <td style="text-align:right;" class="total-value" id="estimatedReturnTotal"><?= money(0) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="padding:16px 20px 0;">
                    <label class="reason-label" for="returnReason">
                        Reason for Return <span style="color:#ef4444;">*</span>
                    </label>
                    <textarea
                        class="reason-textarea"
                        id="returnReason"
                        name="return_reason"
                        rows="3"
                        required
                        placeholder="Describe why the item(s) are being returned..."
                    ></textarea>
                </div>

                <div class="form-actions">
                    <a href="returns.php" class="btn-cancel"><i class="bi bi-x"></i> Cancel</a>
                    <button type="button" class="btn-process" id="submitReturnBtn" disabled onclick="showReturnConfirmModal()">
                        <i class="bi bi-arrow-return-left"></i> Process Return
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- History -->
        <div class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-title">Recent Return History</p>
                    <p class="panel-subtitle"><?= number_format($history_total_rows) ?> total records</p>
                </div>
                <span style="background:#f3f4f6; border-radius:99px; padding:4px 12px; font-size:.75rem; font-weight:600; color:#374151;"><?= number_format($history_total_rows) ?> total</span>
            </div>

            <div style="overflow-x:auto;">
                <?php if (!empty($history_rows)): ?>
                <table class="hist-table">
                    <thead>
                        <tr>
                            <th>Return ID</th>
                            <th>Sale Ref</th>
                            <th style="text-align:center;">Items</th>
                            <th>Reason</th>
                            <th>Processed By</th>
                            <th>Value</th>
                            <th>Date</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_rows as $row): ?>
                        <tr>
                            <td><span class="id-chip">#<?= (int)$row['return_id'] ?></span></td>
                            <td><span class="sale-ref-chip">SALE #<?= (int)$row['original_sale_group_id'] ?></span></td>
                            <td style="text-align:center;"><span class="items-badge"><?= (float)$row['total_items_returned'] ?></span></td>
                            <td><div class="reason-clip" title="<?= e($row['return_reason']) ?>"><?= e($row['return_reason']) ?></div></td>
                            <td><div class="staff-cell"><i class="bi bi-person-fill"></i> <?= e($row['processed_by']) ?></div></td>
                            <td><span class="cost-cell"><?= money((float)$row['financial_value_returned']) ?></span></td>
                            <td>
                                <div class="date-main"><?= date("M d, Y", strtotime($row['created_at'])) ?></div>
                                <div class="date-sub"><?= date("h:i A", strtotime($row['created_at'])) ?></div>
                            </td>
                            <td style="text-align:center;">
                                <button type="button" class="view-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#returnViewModal<?= (int)$row['return_id'] ?>">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-clock-history"></i>
                    <p>No return records found.</p>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($history_total_pages > 1):
                $start_loop = max(1, $history_page - 2);
                $end_loop   = min($history_total_pages, $history_page + 2);
            ?>
            <div class="pag-wrap">
                <div class="pag-info">
                    Page <strong><?= $history_page ?></strong> of <strong><?= $history_total_pages ?></strong>
                    &nbsp;·&nbsp; <?= number_format($history_total_rows) ?> records
                </div>
                <div class="pag-pages">
                    <a class="pag-btn <?= $history_page<=1?'disabled':'' ?>" href="<?= buildReturnPaginationUrl($history_page-1) ?>">
                        <i class="bi bi-chevron-left" style="font-size:.7rem;"></i>
                    </a>
                    <?php if ($start_loop > 1): ?>
                        <a class="pag-btn" href="<?= buildReturnPaginationUrl(1) ?>">1</a>
                        <?php if ($start_loop > 2): ?><span class="pag-sep">…</span><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i=$start_loop; $i<=$end_loop; $i++): ?>
                        <a class="pag-btn <?= $i==$history_page?'active':'' ?>" href="<?= buildReturnPaginationUrl($i) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($end_loop < $history_total_pages): ?>
                        <?php if ($end_loop < $history_total_pages-1): ?><span class="pag-sep">…</span><?php endif; ?>
                        <a class="pag-btn" href="<?= buildReturnPaginationUrl($history_total_pages) ?>"><?= $history_total_pages ?></a>
                    <?php endif; ?>
                    <a class="pag-btn <?= $history_page>=$history_total_pages?'disabled':'' ?>" href="<?= buildReturnPaginationUrl($history_page+1) ?>">
                        <i class="bi bi-chevron-right" style="font-size:.7rem;"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div><!-- /.panel history -->

    </div><!-- /.main-content -->
</div><!-- /.d-flex -->

<!-- Return Detail Modals -->
<?php foreach ($history_rows as $row): ?>
    <?php $rid = (int)$row['return_id']; ?>
    <div class="modal fade" id="returnViewModal<?= $rid ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-return-left me-2"></i>Return #<?= $rid ?> Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div style="background:#f9fafb; border-radius:10px; padding:14px 16px; margin-bottom:16px;">
                        <div class="detail-row"><span class="detail-lbl">Sale Reference</span><span class="detail-val">SALE #<?= (int)$row['original_sale_group_id'] ?></span></div>
                        <div class="detail-row"><span class="detail-lbl">Processed By</span><span class="detail-val"><?= e($row['processed_by']) ?></span></div>
                        <div class="detail-row"><span class="detail-lbl">Date</span><span class="detail-val"><?= date("M d, Y h:i A", strtotime($row['created_at'])) ?></span></div>
                        <div class="detail-row"><span class="detail-lbl">Reason</span><span class="detail-val"><?= e($row['return_reason']) ?></span></div>
                        <div class="detail-row"><span class="detail-lbl">Financial Value</span><span class="detail-val" style="font-weight:700; color:#ef4444;"><?= money((float)$row['financial_value_returned']) ?></span></div>
                    </div>

                    <p style="font-size:.85rem; font-weight:700; color:#111827; margin-bottom:10px;">Returned Items</p>
                    <div style="overflow-x:auto;">
                        <table class="rtable">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Brand</th>
                                    <th>Variation</th>
                                    <th style="text-align:center;">Qty</th>
                                    <th style="text-align:right;">Unit Price</th>
                                    <th style="text-align:right;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($return_details_map[$rid])): ?>
                                    <?php foreach ($return_details_map[$rid] as $detail): ?>
                                    <tr>
                                        <td class="product-name"><?= e($detail['product_name']) ?></td>
                                        <td style="font-size:.8rem; color:#6b7280;"><?= e($detail['brand'] ?? '—') ?></td>
                                        <td style="font-size:.8rem; color:#6b7280;"><?= e($detail['variation'] ?? '—') ?></td>
                                        <td style="text-align:center;">
                                            <span class="items-badge"><?= (float)$detail['quantity'] ?></span>
                                            <small style="font-size: 0.75rem; color: #6b7280; margin-left: 2px;"><?= e($detail['unit'] ?? '') ?></small>
                                        </td>
                                        <td style="text-align:right;" class="price-cell"><?= money((float)$detail['unit_price']) ?></td>
                                        <td style="text-align:right;" class="subtotal-cell"><?= money((float)$detail['subtotal']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center; color:#9ca3af; padding:20px;">No item details found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('sidebar')?.classList.toggle('show');
    });



    const returnForm = document.getElementById('returnProcessForm');
    if (returnForm) {
        const returnQtyInputs = returnForm.querySelectorAll('.return-qty');
        const reasonTextarea  = returnForm.querySelector('#returnReason');
        const submitReturnBtn = returnForm.querySelector('#submitReturnBtn');
        const estimatedTotal  = returnForm.querySelector('#estimatedReturnTotal');

        /* Sale-level values injected from PHP for proportional refund */
        const saleGross = <?= json_encode($js_sale_gross) ?>;
        const saleNet   = <?= json_encode($js_sale_net) ?>;

        function formatPeso(v) {
            return '\u20b1' + Number(v).toLocaleString('en-US', { minimumFractionDigits:2, maximumFractionDigits:2 });
        }
        function checkValidity() {
            let totalQty = 0, returnedGross = 0;
            returnQtyInputs.forEach(input => {
                let qty = parseFloat(input.value);
                const maxQty    = parseFloat(input.dataset.maxQty);
                const origPrice = parseFloat(input.dataset.origPrice || '0');
                const soldQty   = parseFloat(input.dataset.soldQty) || 1;
                const discUnit  = parseFloat(input.dataset.unitPrice || '0');
                const subtotalCell = input.closest('tr').querySelector('.item-subtotal');
                if (isNaN(qty) || qty < 0) { qty = 0; }
                else if (qty > maxQty) { qty = maxQty; input.value = maxQty; }

                /* Per-row display: show discounted unit × qty (informational) */
                subtotalCell.textContent = formatPeso(qty * discUnit);

                /* Accumulate original gross for returned portion */
                returnedGross += origPrice * soldQty * (qty / soldQty); /* = origPrice × qty */
                totalQty += qty;
            });

            /* Proportional total — mirrors PHP backend formula */
            const totalVal = (saleGross > 0)
                ? Math.round(returnedGross / saleGross * saleNet * 100) / 100
                : Math.round(returnedGross * 100) / 100;

            estimatedTotal.textContent = formatPeso(totalVal);
            submitReturnBtn.disabled = !(totalQty > 0 && reasonTextarea.value.trim().length > 0);
        }
        returnQtyInputs.forEach(i => i.addEventListener('input', checkValidity));
        reasonTextarea.addEventListener('input', checkValidity);
        checkValidity();
    }
});
</script>

<!-- Confirm Modal -->
<div class="modal fade" id="returnConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content">
            <div class="modal-body" style="text-align:center; padding:28px 24px 20px;">
                <div class="confirm-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <p style="font-size:1rem; font-weight:700; color:#111827; margin-bottom:6px;">Confirm Return</p>
                <p style="font-size:.83rem; color:#6b7280; margin:0;">Process return for the selected items? Stock levels will be updated.</p>
            </div>
            <div class="modal-footer" style="justify-content:center; gap:10px;">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-process" id="confirmReturnBtn">
                    <i class="bi bi-arrow-return-left"></i> Proceed
                </button>
            </div>
        </div>
    </div>
</div>
<script>
function showReturnConfirmModal() {
    const m = document.getElementById('returnConfirmModal');
    if (m) {
        bootstrap.Modal.getOrCreateInstance(m).show();
    }
}
document.getElementById('confirmReturnBtn')?.addEventListener('click', function () {
    document.getElementById('returnProcessForm').submit();
});
</script>
</body>
</html>