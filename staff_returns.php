<?php
include 'project.php';
session_start();

/* ============================================================
   STAFF RETURNS PAGE
   REBUILT TO MATCH ADMIN DASHBOARD STYLE
   ============================================================ */

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['username'];
$dashboard_link = 'staff_dashboard.php';

/* ============================================================
   HELPERS
   ============================================================ */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return '₱' . number_format((float)$value, 2);
}

/* ============================================================
   PAGE STATE
   ============================================================ */
$sale_group_id_searched = null;
$sale_group = null;
$sale_items = [];
$search_error = '';

/* ============================================================
   PROCESS RETURN
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    $original_sale_id     = (int)($_POST['original_sale_group_id'] ?? 0);
    $return_reason        = trim($_POST['return_reason'] ?? '');
    $items_to_return_data = $_POST['items_to_return'] ?? [];
    $total_value_returned = 0.00;
    $total_returned_gross = 0.00; 

    foreach ($items_to_return_data as $product_id => $quantity) {
        $qty_float = (float)$quantity;
        if ($qty_float > 0) $items_to_return[(int)$product_id] = $qty_float;
    }

    if ($original_sale_id <= 0 || empty($items_to_return) || $return_reason === '') {
        $_SESSION['error'] = "Return failed: Missing sale ID, items to return, or reason.";
        header("Location: staff_returns.php?search_sale_id=" . $original_sale_id);
        exit();
    }

    $conn->begin_transaction();
    try {
        $stmt_return = $conn->prepare("INSERT INTO returns (original_sale_group_id, return_reason, processed_by, created_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt_return) throw new Exception("Prepare failed (returns): " . $conn->error);
        $stmt_return->bind_param("iss", $original_sale_id, $return_reason, $current_user);
        if (!$stmt_return->execute()) throw new Exception("Failed to create return record.");
        $return_id = $conn->insert_id;
        $stmt_return->close();

        /* Fetch sale-level discount for prorated refund calculation */
        $stmt_disc_info = $conn->prepare("
            SELECT sg.discount_amount, COALESCE(SUM(s2.total_price), 0) AS gross_total
            FROM sale_groups sg
            LEFT JOIN sales s2 ON s2.sale_group_id = sg.sale_group_id
            WHERE sg.sale_group_id = ?
            GROUP BY sg.sale_group_id, sg.discount_amount LIMIT 1");
        $stmt_disc_info->bind_param("i", $original_sale_id);
        $stmt_disc_info->execute();
        $disc_info = $stmt_disc_info->get_result()->fetch_assoc();
        $stmt_disc_info->close();
        $sale_discount_amount = (float)($disc_info['discount_amount'] ?? 0);
        $sale_gross_total     = (float)($disc_info['gross_total']     ?? 0);

        $stmt_get_sale_item = $conn->prepare("
            SELECT s.quantity AS sold_qty, s.total_price, COALESCE(SUM(ri.quantity), 0) AS already_returned_qty
            FROM sales s
            LEFT JOIN returns r ON r.original_sale_group_id = s.sale_group_id
            LEFT JOIN return_items ri ON ri.return_id = r.return_id AND ri.product_id = s.product_id
            WHERE s.sale_group_id = ? AND s.product_id = ?
            GROUP BY s.sale_id, s.quantity, s.total_price LIMIT 1");

        $stmt_return_item   = $conn->prepare("INSERT INTO return_items (return_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt_update_stock  = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");

        foreach ($items_to_return as $product_id => $quantity_to_return) {
            $stmt_get_sale_item->bind_param("ii", $original_sale_id, $product_id);
            $stmt_get_sale_item->execute();
            $sid = $stmt_get_sale_item->get_result()->fetch_assoc();
            
            $sold_qty = (float)$sid['sold_qty'];
            $item_line_total = (float)$sid['total_price'];
            $total_returned_gross += $item_line_total * ($quantity_to_return / max(0.001, $sold_qty));

            $stmt_return_item->bind_param("iid", $return_id, $product_id, $quantity_to_return);
            $stmt_return_item->execute();

            $stmt_update_stock->bind_param("di", $quantity_to_return, $product_id);
            $stmt_update_stock->execute();
        }

        $stmt_get_sale_item->close();
        $stmt_return_item->close();
        $stmt_update_stock->close();

        $sale_net_paid = max(0.0, $sale_gross_total - $sale_discount_amount);
        $total_value_returned = ($sale_gross_total > 0)
            ? round($total_returned_gross / $sale_gross_total * $sale_net_paid, 2)
            : round($total_returned_gross, 2);

        $conn->commit();

        $_SESSION['success'] = "Return processed successfully for Sale ID #{$original_sale_id}. Financial value returned: " . money($total_value_returned) . ".";
        header("Location: staff_returns.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Return failed: " . $e->getMessage();
        header("Location: staff_returns.php?search_sale_id=" . $original_sale_id);
        exit();
    }
}

/* ============================================================
   SEARCH SALE
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_sale_id'])) {
    $sale_group_id_searched = (int)($_GET['search_sale_id'] ?? 0);
    if ($sale_group_id_searched > 0) {
        $stmt_group = $conn->prepare("
            SELECT sg.sale_group_id, sg.created_at, COALESCE(u.full_name, sg.created_by) AS created_by, sp.total_amount
            FROM sale_groups sg
            LEFT JOIN sale_payments sp ON sg.sale_group_id = sp.sale_group_id
            LEFT JOIN users u ON sg.created_by = u.username
            WHERE sg.sale_group_id = ? LIMIT 1");
        $stmt_group->bind_param("i", $sale_group_id_searched);
        $stmt_group->execute();
        $sale_group = $stmt_group->get_result()->fetch_assoc();
        $stmt_group->close();

        if ($sale_group) {
            $stmt_items = $conn->prepare("
                SELECT s.sale_id, s.product_id, p.product_name, p.brand, p.variation, p.unit,
                    s.quantity AS sold_qty, s.total_price,
                    (s.total_price / NULLIF(s.quantity, 0)) AS unit_price,
                    sg.discount_amount,
                    (SELECT COALESCE(SUM(s2.total_price), 0) FROM sales s2 WHERE s2.sale_group_id = s.sale_group_id) AS group_gross_total,
                    COALESCE(SUM(ri.quantity), 0) AS already_returned_qty
                FROM sales s
                JOIN products p ON s.product_id = p.product_id
                JOIN sale_groups sg ON sg.sale_group_id = s.sale_group_id
                LEFT JOIN returns r ON r.original_sale_group_id = s.sale_group_id
                LEFT JOIN return_items ri ON ri.return_id = r.return_id AND ri.product_id = s.product_id
                WHERE s.sale_group_id = ?
                GROUP BY s.sale_id, s.product_id, p.product_name, p.brand, p.variation, p.unit, s.quantity, s.total_price, sg.discount_amount
                ORDER BY p.product_name ASC");
            $stmt_items->bind_param("i", $sale_group_id_searched);
            $stmt_items->execute();
            $res = $stmt_items->get_result();
            while ($row = $res->fetch_assoc()) {
                $row['remaining_returnable_qty'] = max(0.0, (float)$row['sold_qty'] - (float)$row['already_returned_qty']);
                $g_line   = (float)$row['total_price'];
                $g_gross  = (float)$row['group_gross_total'];
                $g_disc   = (float)$row['discount_amount'];
                $g_ratio  = ($g_gross > 0) ? ($g_line / $g_gross) : 0;
                $g_discounted_line = max(0.0, $g_line - ($g_disc * $g_ratio));
                $row['discounted_unit_price'] = $g_discounted_line / max(0.001, (float)$row['sold_qty']);
                $sale_items[] = $row;
            }
            $stmt_items->close();
            if (empty($sale_items)) { $search_error = "No items found for this sale."; $sale_group = null; }
        } else {
            $search_error = "Sale ID #{$sale_group_id_searched} not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Process Returns — Staff</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/admin1.css">
  <style>
    :root {
      --bg:           #eef1f8;
      --surface:      #ffffff;
      --surface-2:    #f7f9fc;
      --border:       #e2e8f0;
      --ink:          #0f172a;
      --ink-2:        #334155;
      --muted:        #64748b;
      --faint:        #94a3b8;
      --blue:         #2563eb;
      --blue-lt:      #eff6ff;
      --blue-mid:     #dbeafe;
      --green:        #059669;
      --amber:        #d97706;
      --red:          #dc2626;
      --violet:       #7c3aed;
      --card-radius:  16px;
      --card-shadow:  0 2px 16px rgba(0,0,0,0.07);
      --font:         'Plus Jakarta Sans', sans-serif;
      
      /* Returns-specific UI */
      --blue-dk:      #1d4ed8;
      --green-lt:     #ecfdf5;
      --amber-lt:     #fffbeb;
      --red-lt:       #fef2f2;
      --r:            12px;
      --r-sm:         8px;
      --r-lg:         18px;
      --sh-xs:        0 1px 3px rgba(0,0,0,.05);
      --sh-sm:        0 2px 8px rgba(0,0,0,.06);
      --sh:           0 4px 20px rgba(0,0,0,.08);
      --sh-lg:        0 8px 32px rgba(0,0,0,.1);
      --mono:         'JetBrains Mono', monospace;
    }
    body { font-family:var(--font); background:var(--bg); color:var(--ink); font-size:14px; }
    .content { background:var(--bg); min-height:100vh; }

    .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; flex-wrap: wrap; }
    .page-header-icon {
      width: 44px; height: 44px; border-radius: 12px;
      background: #fee2e2; color: #ef4444;
      display: grid; place-items: center; font-size: 1.2rem; flex-shrink: 0;
    }
    .page-header-text h1 { font-size: 1.35rem; font-weight: 700; color: #111827; margin: 0 0 2px; }
    .page-header-text p  { font-size: .82rem; color: #6b7280; margin: 0; }
    .panel { background: #fff; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-bottom: 20px; overflow: hidden; }
    .panel-header { padding: 16px 20px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
    .panel-title { font-size:.95rem; font-weight:700; color:#111827; margin:0; }
    .panel-subtitle { font-size:.78rem; color:#9ca3af; margin:2px 0 0; }
    .panel-body { padding: 20px; }
    .search-form-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
    .form-group { flex: 1; min-width: 200px; }
    .field-label { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; margin-bottom: 6px; }
    .form-input-styled {
      width: 100%; padding: 10px 14px; border-radius: 10px;
      border: 1.5px solid #e5e7eb; font-size: .88rem; font-weight: 500;
      background: #f9fafb; transition: all .15s; outline: none;
    }
    .form-input-styled:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 4px rgba(37,99,235,.1); }
    .btn-find {
      background: #2563eb; color: #fff; border: none; border-radius: 10px;
      padding: 10px 20px; font-size: .88rem; font-weight: 600; cursor: pointer;
      transition: all .15s; display: inline-flex; align-items: center; gap: 8px;
    }
    .btn-find:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,.2); }
    .rtable { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .rtable thead th {
      font-size: .68rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
      color: #6b7280; background: #f9fafb; padding: 12px 14px; border-bottom: 1px solid #f3f4f6;
    }
    .rtable tbody td { padding: 14px; border-bottom: 1px solid #f9fafb; vertical-align: middle; }
    .rtable tbody tr:last-child td { border-bottom: none; }
    .product-name { font-weight: 700; color: #111827; }
    .product-meta { font-size: .75rem; color: #9ca3af; margin-top: 1px; }
    .qty-badge-sold { display: inline-block; padding: 2px 8px; background: #dbeafe; color: #2563eb; border-radius: 6px; font-weight: 700; font-size: .75rem; }
    .qty-badge-ret  { display: inline-block; padding: 2px 8px; background: #fee2e2; color: #ef4444; border-radius: 6px; font-weight: 700; font-size: .75rem; }
    .qty-badge-avail{ display: inline-block; padding: 2px 8px; background: #dcfce7; color: #166534; border-radius: 6px; font-weight: 700; font-size: .75rem; }
    .return-qty-input {
      width: 70px; padding: 6px 10px; border-radius: 8px; border: 1.5px solid #e5e7eb;
      text-align: center; font-weight: 700; color: #2563eb; outline: none; transition: all .15s;
    }
    .return-qty-input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
    .sale-meta-bar {
      background: #f9fafb; border-bottom: 1px solid #f3f4f6; padding: 12px 20px;
      display: flex; gap: 24px; flex-wrap: wrap; font-size: .8rem; color: #4b5563;
    }
    .form-actions { padding: 20px; border-top: 1px solid #f3f4f6; display: flex; justify-content: flex-end; gap: 12px; }
    .btn-cancel { padding: 9px 18px; border-radius: 10px; border: 1.5px solid #e5e7eb; background: #fff; color: #4b5563; font-weight: 600; text-decoration: none; font-size: .82rem; }
    .btn-process { padding: 9px 22px; border-radius: 10px; border: none; background: #ef4444; color: #fff; font-weight: 600; cursor: pointer; transition: all .15s; font-size: .82rem; }
    .btn-process:hover:not(:disabled) { background: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239,68,68,.2); }
    .btn-process:disabled { opacity: .4; cursor: not-allowed; }
    .reason-textarea { width: 100%; padding: 12px; border-radius: 10px; border: 1.5px solid #e5e7eb; font-size: .88rem; outline: none; background: #f9fafb; transition: all .15s; }
    .reason-textarea:focus { border-color: #2563eb; background: #fff; }
    /* Premium Alerts (Toast-style) */
    .alert-custom {
      padding: 16px 20px; border-radius: 14px;
      display: flex; align-items: center; gap: 14px;
      font-size: .88rem; font-weight: 600;
      border: 1px solid transparent; box-shadow: 0 8px 24px rgba(0,0,0,.12);
      position: fixed; top: 24px; right: 24px; z-index: 9999;
      min-width: 320px; max-width: 450px;
      animation: slideInRight 0.4s ease-out;
    }
    @keyframes slideInRight {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    .alert-custom-success {
      background: #f0fdf4; color: #166534; border-color: #bbf7d0;
    }
    .alert-custom-error {
      background: #fef2f2; color: #991b1b; border-color: #fecaca;
    }
    .alert-custom i { font-size: 1.1rem; }
    .btn-alert-close {
      background: none; border: none; color: #9ca3af; font-size: 1.4rem;
      position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
      line-height: 1; cursor: pointer; transition: color .15s;
    }
    .btn-alert-close:hover { color: #4b5563; }
  </style>
</head>
<body>
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
      <li class="nav-item mb-2"><a class="nav-link" href="staff_products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="purchased_history.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
      <li class="nav-item mb-2"><a class="nav-link active" href="staff_returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="staff_product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>

      <li class="sidebar-title">Others</li>
      <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
    </ul>
  </div>

  <!-- CONTENT -->
  <div class="content flex-grow-1">
    <div class="p-4">
      <div class="container-fluid">

        <div class="page-header">
            <div class="page-header-icon"><i class="bi bi-arrow-return-left"></i></div>
            <div class="page-header-text">
                <h1>Customer Returns</h1>
                <p>Process a refund and return items to inventory stock.</p>
            </div>
        </div>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert-custom alert-custom-success">
                <i class="bi bi-check-circle-fill"></i> 
                <span><?= e($_SESSION['success']) ?></span>
                <button type="button" class="btn-alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert-custom alert-custom-error">
                <i class="bi bi-exclamation-triangle-fill"></i> 
                <span><?= e($_SESSION['error']) ?></span>
                <button type="button" class="btn-alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Search Panel -->
        <div class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-title">Find Sale Record</p>
                    <p class="panel-subtitle">Search by Receipt # to see returnable items</p>
                </div>
            </div>
            <div class="panel-body">
                <form action="staff_returns.php" method="GET">
                    <div class="search-form-row">
                        <div class="form-group" style="max-width:300px;">
                            <label class="field-label">Receipt / Sale ID</label>
                            <input type="number" class="form-input-styled" name="search_sale_id" 
                                   value="<?= e($sale_group_id_searched ?? '') ?>" placeholder="e.g. 42" required>
                        </div>
                        <button type="submit" class="btn-find">
                            <i class="bi bi-search"></i> Find Sale
                        </button>
                    </div>
                </form>
                <?php if ($search_error): ?>
                    <div class="text-danger mt-3 small"><i class="bi bi-exclamation-circle me-1"></i> <?= e($search_error) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($sale_group && !empty($sale_items)): ?>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-title">Sale #<?= (int)$sale_group['sale_group_id'] ?></p>
                    <p class="panel-subtitle">Select quantities to return to stock</p>
                </div>
            </div>
            <div class="sale-meta-bar">
                <span><strong>Date:</strong> <?= date("M d, Y h:i A", strtotime($sale_group['created_at'])) ?></span>
                <span><strong>Paid:</strong> <?= money((float)$sale_group['total_amount']) ?></span>
                <span><strong>Items:</strong> <?= count($sale_items) ?></span>
            </div>
            
            <div style="background: #eff6ff; padding: 10px 20px; border-bottom: 1px solid #dbeafe; font-size: 0.78rem; color: #1e40af; display: flex; align-items: center; gap: 8px;">
                <i class="bi bi-info-circle-fill"></i>
                <span><strong>Proportional Refund Active:</strong> Refund is calculated based on the <em>net price paid</em> after any discounts. This ensures the correct portion of the discount is reversed.</span>
            </div>
            <form action="staff_returns.php" method="POST" id="returnProcessForm">
                <input type="hidden" name="process_return" value="1">
                <input type="hidden" name="original_sale_group_id" value="<?= (int)$sale_group['sale_group_id'] ?>">
                
                <div style="overflow-x:auto;">
                    <table class="rtable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th style="text-align:center;">Sold</th>
                                <th style="text-align:center;">Returned</th>
                                <th style="text-align:center;">Available</th>
                                <th style="text-align:right;">Orig. Price</th>
                                <th style="text-align:right; color:#059669;">Net Price</th>
                                <th style="text-align:center; background:#f0f9ff;">Qty to Return</th>
                                <th style="text-align:right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sale_items as $item):
                                $rq = (float)$item['remaining_returnable_qty'];
                                $already = (float)$item['already_returned_qty'];
                                $sold = (float)$item['sold_qty'];
                                
                                // Meta cleanup
                                $meta_parts = array_filter([$item['brand'], $item['variation']], function($v) {
                                    return !empty($v) && !in_array(strtolower(trim($v)), ['n/a', 'none', '-']);
                                });
                                $meta_text = implode(' &middot; ', $meta_parts);
                            ?>
                            <tr>
                                <td>
                                    <div class="product-name"><?= e($item['product_name']) ?></div>
                                    <?php if ($meta_text): ?>
                                    <div class="product-meta"><?= $meta_text ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;"><span class="qty-badge-sold"><?= $sold ?> <?= e($item['unit'] ?? '') ?></span></td>
                                <td style="text-align:center;"><span class="qty-badge-ret"><?= $already ?> <?= e($item['unit'] ?? '') ?></span></td>
                                <td style="text-align:center;"><span class="qty-badge-avail"><?= $rq ?> <?= e($item['unit'] ?? '') ?></span></td>
                                <td style="text-align:right; text-decoration:line-through; color:#9ca3af;"><?= money((float)$item['unit_price']) ?></td>
                                <td style="text-align:right; color:#059669; font-weight:700;"><?= money((float)$item['discounted_unit_price']) ?></td>
                                <td style="text-align:center; background:#f0f9ff;">
                                    <?php if ($rq > 0): ?>
                                        <input type="number" class="return-qty-input" name="items_to_return[<?= (int)$item['product_id'] ?>]"
                                               value="0" min="0" max="<?= $rq ?>" step="any"
                                               data-unit-price="<?= (float)$item['discounted_unit_price'] ?>">
                                    <?php else: ?>
                                        <span class="text-muted small fw-bold">Returned</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right; font-weight:700;" class="row-subtotal">₱0.00</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="7" style="text-align:right; font-weight:700; color:#4b5563;">Total Estimated Refund</td>
                                <td style="text-align:right; font-size:1.1rem; font-weight:800; color:#dc2626;" id="estimatedRefundTotal">₱0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="padding:20px;">
                    <label class="field-label">Reason for Return</label>
                    <textarea class="reason-textarea" name="return_reason" rows="3" required 
                              placeholder="Why is this being returned?"></textarea>
                </div>

                <div class="form-actions">
                    <a href="staff_returns.php" class="btn-cancel">Cancel</a>
                    <button type="button" class="btn-process" id="submitBtn" disabled onclick="showReturnConfirmModal()">
                        <i class="bi bi-arrow-return-left me-1"></i> Process Return
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {


    const returnForm = document.getElementById('returnProcessForm');
    if (returnForm) {
        const qtyInputs = returnForm.querySelectorAll('.return-qty-input');
        const reasonText = returnForm.querySelector('textarea[name="return_reason"]');
        const submitBtn = document.getElementById('submitBtn');
        const totalDisp = document.getElementById('estimatedRefundTotal');

        function updateTotals() {
            let total = 0;
            let anyQty = false;
            qtyInputs.forEach(input => {
                const qty = parseInt(input.value) || 0;
                const price = parseFloat(input.dataset.unitPrice) || 0;
                const sub = qty * price;
                total += sub;
                if (qty > 0) anyQty = true;
                input.closest('tr').querySelector('.row-subtotal').textContent = '₱' + sub.toLocaleString(undefined, {minimumFractionDigits:2});
            });
            totalDisp.textContent = '₱' + total.toLocaleString(undefined, {minimumFractionDigits:2});
            submitBtn.disabled = !(anyQty && reasonText.value.trim().length > 0);
        }

        qtyInputs.forEach(i => i.addEventListener('input', updateTotals));
        reasonText.addEventListener('input', updateTotals);
    }

    const confirmBtn = document.getElementById('confirmReturnBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            document.getElementById('returnProcessForm').submit();
        });
    }
});

function showReturnConfirmModal() {
    const m = document.getElementById('returnConfirmModal');
    if (m) {
        const modalInstance = bootstrap.Modal.getOrCreateInstance(m);
        modalInstance.show();
    }
}
</script>

<!-- Confirm Modal -->
<div class="modal fade" id="returnConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content">
            <div class="modal-body" style="text-align:center; padding:28px 24px 20px;">
                <div style="width:54px; height:54px; background:#fef2f2; color:#dc2626; border-radius:50%; display:grid; place-items:center; font-size:1.5rem; margin:0 auto 16px;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <p style="font-size:1rem; font-weight:700; color:#111827; margin-bottom:6px;">Confirm Return</p>
                <p style="font-size:.83rem; color:#6b7280; margin:0;">Process return for the selected items? Stock levels will be updated.</p>
            </div>
            <div class="modal-footer" style="justify-content:center; gap:10px; border:none; padding-bottom:24px;">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-process" id="confirmReturnBtn">
                    <i class="bi bi-arrow-return-left me-1"></i> Proceed
                </button>
            </div>
        </div>
    </div>
</div>
</body>
</html>