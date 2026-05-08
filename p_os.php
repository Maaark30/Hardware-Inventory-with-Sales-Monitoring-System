<?php
include 'project.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
$current_user = $_SESSION['username'];

$products = [];
$sql = "SELECT product_id, product_name, selling_price, stock, unit, brand, variation   
        FROM products
        WHERE stock > 0
        ORDER BY product_name ASC";
$res = $conn->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
} else {
    $_SESSION['error'] = "Error fetching products: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Invoice Generator</title>

 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin1.css">
  <style>
    :root {
      --bg: #f0f2f7;
      --surface: #ffffff;
      --surface-2: #f8f9fc;
      --border: #e4e8f0;
      --border-subtle: #eef0f6;
      --accent: #3b5bdb;
      --accent-light: #eef2ff;
      --accent-hover: #2f4ac9;
      --success: #12b886;
      --success-light: #e6fcf5;
      --warning: #f59f00;
      --warning-light: #fff9db;
      --danger: #fa5252;
      --danger-light: #fff5f5;
      --text-primary: #1a1d2e;
      --text-secondary: #6b7280;
      --text-muted: #9ca3af;
      --radius: 14px;
      --radius-sm: 8px;
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
      gap: 14px;
      margin-bottom: 28px;
    }
    .page-header-icon {
      width: 48px; height: 48px;
      background: var(--accent);
      border-radius: var(--radius-sm);
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 22px;
      flex-shrink: 0;
      box-shadow: 0 4px 12px rgba(59,91,219,.35);
    }
    .page-header h4 {
      margin: 0;
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--text-primary);
      letter-spacing: -.3px;
    }
    .page-header p {
      margin: 2px 0 0;
      font-size: .82rem;
      color: var(--text-muted);
    }

    /* ── Alerts ── */
    .alert {
      border: none;
      border-radius: var(--radius-sm);
      font-size: .875rem;
      font-weight: 500;
    }

    /* ── Card ── */
    .pos-card {
      background: var(--surface);
      border-radius: var(--radius);
      border: 1px solid var(--border);
      box-shadow: var(--shadow-sm);
      display: flex;
      flex-direction: column;
      padding: 0;
      min-height: calc(100vh - 120px);
      overflow: hidden;
    }

    .pos-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 24px 18px;
      border-bottom: 1px solid var(--border-subtle);
    }
    .pos-card-header h5 {
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-primary);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .pos-card-header h5 i {
      color: var(--accent);
      font-size: 1.1rem;
    }

    /* ── Add product button ── */
    .btn-add-product {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 8px 16px;
      font-size: .82rem;
      font-weight: 600;
      font-family: var(--font);
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: var(--radius-sm);
      cursor: pointer;
      transition: background .15s, box-shadow .15s, transform .1s;
      box-shadow: 0 2px 8px rgba(59,91,219,.3);
    }
    .btn-add-product:hover {
      background: var(--accent-hover);
      box-shadow: 0 4px 12px rgba(59,91,219,.4);
      transform: translateY(-1px);
    }
    .btn-add-product:active { transform: translateY(0); }

    /* ── Cart Table ── */
    #cartItemsContainer {
      flex-grow: 1;
      overflow-y: auto;
      padding: 12px 24px 4px;
    }

    #cartItemsContainer::-webkit-scrollbar { width: 4px; }
    #cartItemsContainer::-webkit-scrollbar-track { background: transparent; }
    #cartItemsContainer::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

    .cart-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }
    .cart-table thead th {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--text-muted);
      padding: 8px 10px 10px;
      border-bottom: 1px solid var(--border-subtle);
      background: transparent;
    }
    .cart-table tbody td {
      padding: 12px 10px;
      border-bottom: 1px solid var(--border-subtle);
      vertical-align: middle;
    }
    .cart-table tbody tr:last-child td { border-bottom: none; }
    .cart-table tbody tr {
      transition: background .12s;
    }
    .cart-table tbody tr:hover { background: var(--surface-2); }

    .cart-item-name {
      font-size: .9rem;
      font-weight: 600;
      color: var(--text-primary);
      line-height: 1.3;
    }
    .cart-item-meta {
      font-size: .75rem;
      color: var(--text-muted);
      margin-top: 2px;
    }

    .cart-qty-input {
      width: 76px;
      padding: 6px 8px;
      font-size: .875rem;
      font-family: var(--font-mono);
      font-weight: 500;
      text-align: center;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--surface-2);
      color: var(--text-primary);
      transition: border-color .15s, box-shadow .15s;
      outline: none;
      -moz-appearance: textfield;
    }
    .cart-qty-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(59,91,219,.12);
      background: #fff;
    }
    .cart-qty-input::-webkit-inner-spin-button,
    .cart-qty-input::-webkit-outer-spin-button { -webkit-appearance: none; }

    .cart-line-total {
      font-family: var(--font-mono);
      font-size: .9rem;
      font-weight: 600;
      color: var(--text-primary);
    }

    .btn-remove-item {
      width: 32px; height: 32px;
      border-radius: var(--radius-sm);
      border: none;
      background: transparent;
      color: var(--text-muted);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: background .12s, color .12s;
      font-size: .9rem;
    }
    .btn-remove-item:hover {
      background: var(--danger-light);
      color: var(--danger);
    }

    /* ── Empty cart state ── */
    .empty-cart-cell {
      padding: 56px 20px !important;
      text-align: center;
      color: var(--text-muted);
    }
    .empty-cart-icon {
      width: 64px; height: 64px;
      background: var(--accent-light);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 26px;
      color: var(--accent);
      margin: 0 auto 14px;
    }
    .empty-cart-label {
      font-size: .92rem;
      font-weight: 600;
      color: var(--text-secondary);
      margin-bottom: 4px;
    }
    .empty-cart-sub {
      font-size: .8rem;
      color: var(--text-muted);
    }

    /* ── Bottom panel ── */
    .cart-footer {
      padding: 20px 24px 24px;
      border-top: 1px solid var(--border-subtle);
    }

    /* ── Customer accordion ── */
    .customer-accordion {
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      margin-bottom: 20px;
      overflow: hidden;
    }
    .customer-accordion-btn {
      width: 100%;
      background: var(--surface-2);
      border: none;
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-family: var(--font);
      font-size: .83rem;
      font-weight: 600;
      color: var(--text-secondary);
      cursor: pointer;
      transition: background .12s;
      text-align: left;
    }
    .customer-accordion-btn:hover { background: var(--border-subtle); }
    .customer-accordion-btn i { color: var(--accent); font-size: 1rem; }
    .customer-accordion-btn .chevron {
      margin-left: auto;
      transition: transform .2s;
    }
    .customer-accordion-btn.open .chevron { transform: rotate(180deg); }
    .customer-accordion-body {
      display: none;
      padding: 16px;
      background: #fff;
      border-top: 1px solid var(--border-subtle);
    }
    .customer-accordion-body.open { display: block; }

    /* ── Form inputs ── */
    .pos-label {
      display: block;
      font-size: .74rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--text-muted);
      margin-bottom: 6px;
    }
    .pos-input {
      width: 100%;
      padding: 9px 12px;
      font-family: var(--font);
      font-size: .875rem;
      font-weight: 500;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--surface-2);
      color: var(--text-primary);
      transition: border-color .15s, box-shadow .15s;
      outline: none;
    }
    .pos-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(59,91,219,.12);
      background: #fff;
    }
    .pos-input::placeholder { color: var(--text-muted); font-weight: 400; }

    /* ── Totals ── */
    .totals-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }
    .totals-label {
      font-size: .82rem;
      color: var(--text-muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
    }
    .totals-value {
      font-family: var(--font-mono);
      font-size: .9rem;
      font-weight: 600;
      color: var(--text-secondary);
    }

    .discount-row {
      margin-bottom: 14px;
    }
    .discount-input {
      width: 100%;
      padding: 9px 12px;
      font-family: var(--font-mono);
      font-size: .9rem;
      font-weight: 500;
      border: 1.5px solid var(--warning);
      border-radius: var(--radius-sm);
      background: var(--warning-light);
      color: var(--text-primary);
      outline: none;
      transition: border-color .15s, box-shadow .15s;
      text-align: right;
    }
    .discount-input:focus {
      border-color: var(--warning);
      box-shadow: 0 0 0 3px rgba(245,159,0,.15);
    }

    .grand-total-box {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: var(--accent);
      border-radius: var(--radius-sm);
      padding: 16px 20px;
      margin-bottom: 16px;
      box-shadow: 0 4px 14px rgba(59,91,219,.3);
    }
    .grand-total-label {
      font-size: .78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: rgba(255,255,255,.7);
    }
    .grand-total-value {
      font-family: var(--font-mono);
      font-size: 1.5rem;
      font-weight: 700;
      color: #fff;
      letter-spacing: -.5px;
    }

    /* ── Generate button ── */
    .btn-generate {
      width: 100%;
      padding: 14px;
      font-family: var(--font);
      font-size: 1rem;
      font-weight: 700;
      border: none;
      border-radius: var(--radius-sm);
      cursor: pointer;
      background: var(--success);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: background .15s, box-shadow .15s, transform .1s, opacity .2s;
      box-shadow: 0 4px 14px rgba(18,184,134,.35);
      letter-spacing: -.2px;
    }
    .btn-generate:hover:not(:disabled) {
      background: #0fa574;
      box-shadow: 0 6px 18px rgba(18,184,134,.45);
      transform: translateY(-1px);
    }
    .btn-generate:active:not(:disabled) { transform: translateY(0); }
    .btn-generate:disabled {
      opacity: .45;
      cursor: not-allowed;
      box-shadow: none;
    }

    .payment-note {
      text-align: center;
      font-size: .75rem;
      color: var(--text-muted);
      margin-top: 10px;
    }
    .payment-note i { margin-right: 4px; }

    /* ── ADD PRODUCT MODAL ── */
    .modal-content {
      border: none;
      border-radius: var(--radius);
      box-shadow: var(--shadow-lg);
      font-family: var(--font);
    }
    .modal-header {
      border-bottom: 1px solid var(--border-subtle);
      padding: 20px 24px;
    }
    .modal-header .modal-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-primary);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .modal-header .modal-title i { color: var(--accent); }
    .btn-close { opacity: .5; }
    .modal-body { padding: 20px 24px; }
    .modal-footer { border-top: 1px solid var(--border-subtle); padding: 16px 24px; }

    .search-box {
      position: relative;
      margin-bottom: 14px;
    }
    .search-box i {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 1rem;
      pointer-events: none;
    }
    .search-box input {
      padding-left: 38px;
    }

    /* ── Product list ── */
    #productResults {
      max-height: 280px;
      overflow-y: auto;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      margin-bottom: 14px;
    }
    #productResults::-webkit-scrollbar { width: 4px; }
    #productResults::-webkit-scrollbar-track { background: transparent; }
    #productResults::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

    .product-result-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 11px 14px;
      border: none;
      border-bottom: 1px solid var(--border-subtle);
      background: #fff;
      width: 100%;
      text-align: left;
      cursor: pointer;
      transition: background .1s;
      font-family: var(--font);
    }
    .product-result-item:last-child { border-bottom: none; }
    .product-result-item:hover { background: var(--accent-light); }
    .product-result-item.selected { background: var(--accent-light); }
    .product-result-name { font-size: .875rem; font-weight: 600; color: var(--text-primary); }
    .product-result-meta { font-size: .73rem; color: var(--text-muted); margin-top: 2px; }

    .stock-badge {
      display: inline-flex;
      align-items: center;
      padding: 3px 8px;
      border-radius: 20px;
      font-family: var(--font-mono);
      font-size: .72rem;
      font-weight: 600;
    }
    .stock-badge.good { background: var(--success-light); color: var(--success); }
    .stock-badge.low { background: var(--warning-light); color: var(--warning); }
    .stock-badge.out { background: var(--danger-light); color: var(--danger); }

    /* ── Selected product preview ── */
    .selected-product-box {
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 14px 16px;
      background: var(--surface-2);
      margin-bottom: 16px;
      transition: border-color .2s;
    }
    .selected-product-box.has-product {
      border-color: var(--accent);
      background: var(--accent-light);
    }
    .selected-product-box-name {
      font-size: .9rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 3px;
    }
    .selected-product-box-meta {
      font-size: .78rem;
      color: var(--text-secondary);
    }
    .selected-product-box-stock {
      font-size: .75rem;
      margin-top: 6px;
      font-weight: 600;
    }

    /* ── INVOICE MODAL ── */
    .invoice-header-bar {
      background: linear-gradient(135deg, var(--accent) 0%, #5c7cfa 100%);
      padding: 20px 24px;
      border-radius: var(--radius) var(--radius) 0 0;
    }
    .invoice-header-bar .modal-title {
      color: #fff;
      font-size: 1rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .invoice-header-bar .btn-close-white { filter: brightness(10); opacity: .8; }

    #invoicePrintArea {
      padding: 28px 28px 16px;
    }
    .invoice-meta-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 24px;
    }
    .invoice-title-block h3 {
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--text-primary);
      margin: 0 0 4px;
      letter-spacing: -1px;
    }
    .invoice-title-block .sub {
      font-size: .78rem;
      color: var(--text-muted);
      line-height: 1.6;
    }
    .invoice-customer-block {
      text-align: right;
    }
    .invoice-customer-block .label {
      font-size: .7rem;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--text-muted);
      font-weight: 700;
      margin-bottom: 4px;
    }
    .invoice-customer-block .name {
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-primary);
    }
    .invoice-customer-block .detail {
      font-size: .78rem;
      color: var(--text-secondary);
    }

    .invoice-table {
      width: 100%;
      border-collapse: collapse;
      font-size: .875rem;
    }
    .invoice-table thead th {
      padding: 8px 10px;
      font-size: .7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--text-muted);
      border-bottom: 2px solid var(--border);
      background: transparent;
    }
    .invoice-table tbody td {
      padding: 10px;
      border-bottom: 1px solid var(--border-subtle);
      color: var(--text-primary);
      vertical-align: middle;
    }
    .invoice-table tbody tr:last-child td { border-bottom: none; }

    .invoice-totals {
      margin-top: 16px;
      border-top: 2px solid var(--border);
      padding-top: 14px;
    }
    .invoice-totals-row {
      display: flex;
      justify-content: space-between;
      font-size: .83rem;
      margin-bottom: 6px;
      color: var(--text-secondary);
      font-weight: 500;
    }
    .invoice-totals-row span:last-child { font-family: var(--font-mono); }
    .invoice-grand-row {
      display: flex;
      justify-content: space-between;
      margin-top: 10px;
      padding: 12px 16px;
      background: var(--accent);
      border-radius: var(--radius-sm);
    }
    .invoice-grand-row span {
      font-size: 1.05rem;
      font-weight: 800;
      color: #fff;
      font-family: var(--font-mono);
    }
    .invoice-grand-row span:first-child {
      font-family: var(--font);
      letter-spacing: -.2px;
    }

    .invoice-note {
      font-size: .73rem;
      color: var(--text-muted);
      margin-top: 20px;
      padding-top: 14px;
      border-top: 1px dashed var(--border);
    }

    /* ── Modal footer buttons ── */
    .btn-modal-secondary {
      padding: 9px 18px;
      font-family: var(--font);
      font-size: .875rem;
      font-weight: 600;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: #fff;
      color: var(--text-secondary);
      cursor: pointer;
      transition: background .12s, border-color .12s;
    }
    .btn-modal-secondary:hover { background: var(--surface-2); border-color: var(--text-muted); }

    .btn-modal-print {
      padding: 9px 18px;
      font-family: var(--font);
      font-size: .875rem;
      font-weight: 600;
      border: 1.5px solid var(--accent);
      border-radius: var(--radius-sm);
      background: transparent;
      color: var(--accent);
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: background .12s;
    }
    .btn-modal-print:hover { background: var(--accent-light); }

    .btn-modal-confirm {
      padding: 9px 20px;
      font-family: var(--font);
      font-size: .875rem;
      font-weight: 700;
      border: none;
      border-radius: var(--radius-sm);
      background: var(--success);
      color: #fff;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 7px;
      transition: background .12s, box-shadow .12s;
      box-shadow: 0 2px 8px rgba(18,184,134,.3);
    }
    .btn-modal-confirm:hover { background: #0fa574; box-shadow: 0 4px 12px rgba(18,184,134,.4); }

    @media print {
      .no-print { display: none !important; }
      body { background: #fff; }
    }
  </style>
</head>

<body>
<div class="d-flex">

  <!-- ===================== SIDEBAR (KEPT) ===================== -->
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
      <li class="nav-item mb-2"><a class="nav-link active" href="p_os.php"><i class="bi bi-receipt me-2"></i> Invoice</a></li>
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

  <!-- ===================== CONTENT ===================== -->
  <div class="content flex-grow-1">
    <div class="container-fluid" style="padding: 28px 28px 28px;">

      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
          <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php unset($_SESSION['error']); endif; ?>

      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
          <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php unset($_SESSION['success']); endif; ?>

      <!-- Page header -->
      <div class="page-header no-print">
        <div class="page-header-icon">
          <i class="bi bi-receipt"></i>
        </div>
        <div>
          <h4>Invoice Generator</h4>
          <p>Create and print sales invoices for customers</p>
        </div>
      </div>

      <!-- Main card -->
      <div class="pos-card no-print">

        <div class="pos-card-header">
          <h5><i class="bi bi-cart3"></i> Active Invoice Items</h5>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-danger btn-sm fw-bold d-flex align-items-center gap-1" id="clearInvoiceBtn" onclick="resetInvoice()" style="border-radius: var(--radius-sm); font-size: .82rem;">
              <i class="bi bi-trash"></i> <span class="d-none d-sm-inline">Clear Invoice</span>
            </button>
            <button type="button" class="btn-add-product" data-bs-toggle="modal" data-bs-target="#addProductModal">
              <i class="bi bi-plus-lg"></i> Add Sale Invoice
            </button>
          </div>
        </div>

        <!-- Cart items -->
        <div id="cartItemsContainer">
          <table class="cart-table">
            <thead>
              <tr>
                <th>Item</th>
                <th style="width:100px; text-align:center;">Qty</th>
                <th style="width:130px; text-align:right;">Total</th>
                <th style="width:44px;"></th>
              </tr>
            </thead>
            <tbody id="cartTableBody">
              <tr id="emptyCartRow">
                <td colspan="4" class="empty-cart-cell">
                  <div class="empty-cart-icon"><i class="bi bi-cart-plus"></i></div>
                  <div class="empty-cart-label">Your cart is empty</div>
                  <div class="empty-cart-sub">Click <strong>Add Product</strong> to get started</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer -->
        <div class="cart-footer">

          <!-- Customer accordion -->
          <div class="customer-accordion no-print">
            <button class="customer-accordion-btn" id="customerAccordionBtn" type="button">
              <i class="bi bi-person-circle"></i>
              Customer Details
              <span class="text-muted" style="font-size:.75rem; font-weight:400; margin-left:4px;" id="customerNamePreview">Walk-in Customer</span>
              <i class="bi bi-chevron-down chevron" style="font-size:.7rem; color: var(--text-muted);"></i>
            </button>
            <div class="customer-accordion-body" id="customerAccordionBody">
              <div class="mb-3">
                <label class="pos-label">Customer Name <span style="color:var(--text-muted); font-weight:400; text-transform:none; font-size:.7rem;">(optional)</span></label>
                <input type="text" class="pos-input" id="customerNameInput" placeholder="Walk-in Customer">
              </div>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="pos-label">Address <span style="color:var(--text-muted); font-weight:400; text-transform:none; font-size:.7rem;">(optional)</span></label>
                  <input type="text" class="pos-input" id="customerAddressInput" placeholder="e.g. Cagayan de Oro">
                </div>
                <div class="col-md-6">
                  <label class="pos-label">Contact No. <span style="color:var(--text-muted); font-weight:400; text-transform:none; font-size:.7rem;">(optional)</span></label>
                  <input type="text" class="pos-input" id="customerContactInput" placeholder="e.g. 09xxxxxxxxx">
                </div>
              </div>
            </div>
          </div>

          <!-- Totals -->
          <div class="totals-row">
            <span class="totals-label">Subtotal</span>
            <span class="totals-value" id="cartSubtotal">₱0.00</span>
          </div>

          <div class="discount-row">
            <label class="pos-label" style="margin-bottom:6px;">
              <i class="bi bi-tag me-1" style="color:var(--warning);"></i>Discount (₱)
            </label>
            <input type="number" step="0.01" min="0"
                   class="discount-input"
                   id="cashDiscountInput" placeholder="0.00">
          </div>

          <div class="grand-total-box">
            <span class="grand-total-label">Grand Total</span>
            <span class="grand-total-value" id="cartGrandTotal">₱0.00</span>
          </div>

          <button class="btn-generate no-print"
                  id="generateInvoiceBtn"
                  data-bs-toggle="modal"
                  data-bs-target="#invoiceModal"
                  disabled>
            <i class="bi bi-receipt"></i> Generate Invoice
          </button>

          <p class="payment-note no-print">
            <i class="bi bi-info-circle"></i>Payment is collected physically — this generates a printable invoice.
          </p>

        </div>
      </div>

    </div>
  </div>

</div>

<!-- ===================== ADD PRODUCT MODAL ===================== -->
<div class="modal fade" id="addProductModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <span class="modal-title"><i class="bi bi-search"></i> Search & Add Product</span>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <label class="pos-label">Search Product (Name, Brand or Variation)</label>
        <div class="search-box">
          <i class="bi bi-search"></i>
          <input type="text" class="pos-input" id="productSearchModal"
                 placeholder="Start typing to search…">
        </div>

        <div id="productResults"></div>

        <div class="selected-product-box" id="selectedProductBox">
          <div class="selected-product-box-name" id="selectedProductName">No product selected</div>
          <div class="selected-product-box-meta" id="selectedProductMeta"></div>
          <div class="selected-product-box-stock" id="selectedStockNote"></div>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="pos-label">Quantity</label>
            <input type="number" class="pos-input" id="productQty" min="0.001" step="any" value="1" disabled>
          </div>
          <div class="col-md-4">
            <label class="pos-label">Amount (₱)</label>
            <input type="number" class="pos-input" id="productAmount" min="0.01" step="0.01" placeholder="0.00" disabled>
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button type="button" class="btn-generate" id="addToCartBtn" disabled style="padding: 10px 20px; font-size:.875rem;">
              <i class="bi bi-cart-plus"></i>Add to Cart
            </button>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

<!-- ===================== INVOICE MODAL ===================== -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="invoice-header-bar no-print d-flex justify-content-between align-items-center">
        <span class="modal-title"><i class="bi bi-receipt"></i> Invoice Preview</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <form id="invoiceForm" action="process_pos.php" method="POST">
        <div class="modal-body p-0" id="invoicePrintArea">

          <div id="invoicePrintInner" style="padding: 28px 28px 16px;">
            <div class="invoice-meta-row">
              <div class="invoice-title-block">
                <h3>INVOICE</h3>
                <div class="sub">
                  Prepared by: <strong><?= htmlspecialchars($current_user) ?></strong><br>
                  Date: <span id="invoiceDate"></span>
                </div>
              </div>
              <div class="invoice-customer-block">
                <div class="label">Bill To</div>
                <div class="name" id="invoiceCustomerName">Walk-in Customer</div>
                <div class="detail" id="invoiceCustomerContact"></div>
                <div class="detail" id="invoiceCustomerAddress"></div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="invoice-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th style="width:70px; text-align:center;">Qty</th>
                    <th style="width:130px; text-align:right;">Unit Price</th>
                    <th style="width:130px; text-align:right;">Total</th>
                  </tr>
                </thead>
                <tbody id="invoiceTableBody"></tbody>
              </table>
            </div>

            <div class="row">
              <div class="col-6"></div>
              <div class="col-6">
                <div class="invoice-totals">
                  <div class="invoice-totals-row">
                    <span>Subtotal</span>
                    <span id="invoiceSubtotal">₱0.00</span>
                  </div>
                  <div class="invoice-totals-row">
                    <span>Discount</span>
                    <span id="invoiceDiscount">₱0.00</span>
                  </div>
                  <div class="invoice-grand-row">
                    <span>Grand Total</span>
                    <span id="invoiceGrandTotal">₱0.00</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="invoice-note">
              <i class="bi bi-info-circle me-1"></i>
              Payment is collected physically. This invoice is for computation and customer reference only.
            </div>

            <!-- Hidden fields -->
            <input type="hidden" name="cart_data" id="invoiceCartDataInput">
            <input type="hidden" name="discount_amount" id="invoiceDiscountAmountInput">
            <input type="hidden" name="customer_name" id="invoiceCustomerNameInput">
            <input type="hidden" name="customer_contact" id="invoiceCustomerContactInput">
            <input type="hidden" name="customer_address" id="invoiceCustomerAddressInput">
            <input type="hidden" name="total_amount" id="invoiceTotalAmountInput">
            <input type="hidden" name="payment_type" value="PHYSICAL_CASH">
            <input type="hidden" name="cash_given" value="0">
            <input type="hidden" name="change_amount_display" value="₱0.00">
          </div>
        </div>

        <div class="modal-footer d-flex justify-content-between no-print">
          <button type="button" class="btn-modal-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn-modal-confirm">
            <i class="bi bi-check-circle"></i> Confirm & Save
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const PRODUCTS = <?= json_encode($products) ?>;

  let cart = {};
  let grandTotal = 0;
  const INVOICE_STORAGE_KEY = 'p_os_invoice_persistence';

  /**
   * Saves the current invoice state to localStorage
   */
  function saveInvoiceState() {
      const state = {
          cart: cart,
          discount: cashDiscountInput.value,
          customer: {
              name: customerNameInputEl.value,
              contact: customerContactInputEl.value,
              address: customerAddressInputEl.value
          }
      };
      localStorage.setItem(INVOICE_STORAGE_KEY, JSON.stringify(state));
  }

  /**
   * Loads the saved invoice state from localStorage
   */
  function loadInvoiceState() {
      const saved = localStorage.getItem(INVOICE_STORAGE_KEY);
      if (!saved) return;

      try {
          const state = JSON.parse(saved);
          cart = state.cart || {};
          
          if (state.discount !== undefined && state.discount !== null && state.discount !== '') {
              cashDiscountInput.value = state.discount;
          }
          if (state.customer) {
              customerNameInputEl.value = state.customer.name || '';
              customerContactInputEl.value = state.customer.contact || '';
              customerAddressInputEl.value = state.customer.address || '';
          }

          // Force updates
          updateCustomerPreview();
          renderCart();
      } catch (e) {
          console.error('Failed to load invoice state:', e);
      }
  }

  /**
   * Resets the entire invoice form and clears persistence
   */
  window.resetInvoice = function() {
      const hasCart = typeof cart !== 'undefined' && Object.keys(cart).length > 0;
      const hasName = customerNameInputEl && customerNameInputEl.value.trim() !== '';
      const hasContact = customerContactInputEl && customerContactInputEl.value.trim() !== '';
      const hasAddress = customerAddressInputEl && customerAddressInputEl.value.trim() !== '';
      const hasDiscount = cashDiscountInput && cashDiscountInput.value !== '' && cashDiscountInput.value != 0;
      
      if (!hasCart && !hasName && !hasContact && !hasAddress && !hasDiscount) return;

      const doReset = () => {
          cart = {};
          if (cashDiscountInput) cashDiscountInput.value = '';
          if (customerNameInputEl) customerNameInputEl.value = '';
          if (customerContactInputEl) customerContactInputEl.value = '';
          if (customerAddressInputEl) customerAddressInputEl.value = '';
          
          localStorage.removeItem(INVOICE_STORAGE_KEY);
          
          if (typeof updateCustomerPreview === 'function') updateCustomerPreview();
          if (typeof renderCart === 'function') renderCart();
          if (typeof calculateCartTotals === 'function') calculateCartTotals();
      };

      if (typeof showConfirm === 'function') {
          showConfirm({
              title: 'Clear Invoice?',
              message: 'Are you sure you want to clear the entire invoice? This action cannot be undone.',
              okText: 'Yes, Clear All',
              icon: 'bi-trash-fill',
              callback: doReset
          });
      } else if (confirm('Are you sure you want to clear the entire invoice?')) {
          doReset();
      }
  };

  function updateCustomerPreview() {
      customerNamePreview.textContent = customerNameInputEl.value.trim() || 'Walk-in Customer';
  }

  const cartBody = document.getElementById('cartTableBody');
  const emptyCartRow = document.getElementById('emptyCartRow');
  const cartSubtotalEl = document.getElementById('cartSubtotal');
  const cartGrandTotalEl = document.getElementById('cartGrandTotal');
  const cashDiscountInput = document.getElementById('cashDiscountInput');
  const generateInvoiceBtn = document.getElementById('generateInvoiceBtn');
  const customerNameInputEl = document.getElementById('customerNameInput');
  const customerContactInputEl = document.getElementById('customerContactInput');
  const customerAddressInputEl = document.getElementById('customerAddressInput');

  const invoiceModal = document.getElementById('invoiceModal');
  const invoiceDate = document.getElementById('invoiceDate');
  const invoiceCustomerName = document.getElementById('invoiceCustomerName');
  const invoiceCustomerContact = document.getElementById('invoiceCustomerContact');
  const invoiceCustomerAddress = document.getElementById('invoiceCustomerAddress');
  const invoiceTableBody = document.getElementById('invoiceTableBody');
  const invoiceSubtotalEl = document.getElementById('invoiceSubtotal');
  const invoiceDiscountEl = document.getElementById('invoiceDiscount');
  const invoiceGrandTotalEl = document.getElementById('invoiceGrandTotal');

  const invoiceCartDataInput = document.getElementById('invoiceCartDataInput');
  const invoiceDiscountAmountInput = document.getElementById('invoiceDiscountAmountInput');
  const invoiceCustomerNameInput = document.getElementById('invoiceCustomerNameInput');
  const invoiceCustomerContactInput = document.getElementById('invoiceCustomerContactInput');
  const invoiceCustomerAddressInput = document.getElementById('invoiceCustomerAddressInput');
  const invoiceTotalAmountInput = document.getElementById('invoiceTotalAmountInput');

  const printInvoiceBtn = document.getElementById('printInvoiceBtn');

  // Customer accordion
  const accordionBtn = document.getElementById('customerAccordionBtn');
  const accordionBody = document.getElementById('customerAccordionBody');
  const customerNamePreview = document.getElementById('customerNamePreview');

  accordionBtn.addEventListener('click', () => {
    accordionBtn.classList.toggle('open');
    accordionBody.classList.toggle('open');
  });

  customerNameInputEl.addEventListener('input', function () {
    updateCustomerPreview();
    calculateCartTotals();
    saveInvoiceState();
  });
  
  customerContactInputEl.addEventListener('input', saveInvoiceState);
  customerAddressInputEl.addEventListener('input', saveInvoiceState);

  const formatCurrency = (value) => {
    const v = parseFloat(value || 0);
    return '₱' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  };

  function safeText(t) {
    return (t ?? '').toString();
  }

  function calculateCartTotals() {
    let subtotal = 0;
    for (const id in cart) {
      subtotal += cart[id].product_data.default_price * cart[id].quantity;
    }

    let discount = parseFloat(cashDiscountInput.value) || 0;
    if (discount < 0) discount = 0;
    if (discount > subtotal) discount = subtotal;

    grandTotal = Math.max(subtotal - discount, 0);

    cartSubtotalEl.innerText = formatCurrency(subtotal);
    cartGrandTotalEl.innerText = formatCurrency(grandTotal);

    generateInvoiceBtn.disabled = Object.keys(cart).length === 0;
    
    saveInvoiceState(); // Persist totals and discount changes
  }

  function renderCart() {
    cartBody.innerHTML = '';
    let hasItems = false;

    for (const id in cart) {
      hasItems = true;
      const item = cart[id];
      const lineTotal = item.product_data.default_price * item.quantity;

      const brand = safeText(item.product_data.brand);
      const variation = safeText(item.product_data.variation);
      const metaParts = [];
      if (brand) metaParts.push(brand);
      if (variation) metaParts.push(variation);

      const row = document.createElement('tr');
      row.innerHTML = `
        <td>
          <div class="cart-item-name">${item.product_data.product_name}</div>
          <div class="cart-item-meta">
            ${formatCurrency(item.product_data.default_price)} / ${item.product_data.unit || 'unit'}
            ${metaParts.length ? ' · ' + metaParts.join(' · ') : ''}
          </div>
        </td>
        <td style="text-align:center;">
          <input type="number"
                 class="cart-qty-input"
                 value="${item.quantity}"
                 min="0.001"
                 step="any"
                 max="${item.product_data.stock}"
                 data-id="${id}">
        </td>
        <td style="text-align:right;">
          <span class="cart-line-total">${formatCurrency(lineTotal)}</span>
        </td>
        <td style="text-align:center;">
          <button type="button" class="btn-remove-item remove-item-btn" data-id="${id}">
            <i class="bi bi-x-lg"></i>
          </button>
        </td>
      `;
      cartBody.appendChild(row);
    }

    emptyCartRow.style.display = hasItems ? 'none' : '';
    attachCartListeners();
    calculateCartTotals();
  }

  function attachCartListeners() {
    document.querySelectorAll('.cart-qty-input').forEach(input => {
      input.oninput = (e) => {
        const id = e.target.dataset.id;
        const maxStock = parseFloat(e.target.max);
        let qty = parseFloat(e.target.value);
        if (isNaN(qty) || qty < 0.001) qty = 0.001;
        if (qty > maxStock) qty = maxStock;
        
        cart[id].quantity = qty;
        calculateCartTotals();
      };
      input.onblur = (e) => {
          e.target.value = cart[e.target.dataset.id].quantity;
          renderCart();
      };
    });

    document.querySelectorAll('.remove-item-btn').forEach(btn => {
      btn.onclick = (e) => {
        const id = e.currentTarget.dataset.id;
        delete cart[id];
        renderCart();
      };
    });
  }

  // ── ADD PRODUCT MODAL ──
  const productSearchModal = document.getElementById('productSearchModal');
  const productResults = document.getElementById('productResults');
  const selectedProductBox = document.getElementById('selectedProductBox');
  const selectedProductName = document.getElementById('selectedProductName');
  const selectedProductMeta = document.getElementById('selectedProductMeta');
  const selectedStockNote = document.getElementById('selectedStockNote');
  const productQty = document.getElementById('productQty');
  const addToCartBtn = document.getElementById('addToCartBtn');

  let selectedProduct = null;

  function renderResults(list) {
    productResults.innerHTML = '';

    if (!list.length) {
      productResults.innerHTML = `<div style="padding:16px; text-align:center; color:var(--text-muted); font-size:.83rem;">No matching products found.</div>`;
      return;
    }

    list.slice(0, 40).forEach(p => {
      const name = safeText(p.product_name);
      const unit = safeText(p.unit);
      const brand = safeText(p.brand);
      const variation = safeText(p.variation);
      const price = formatCurrency(p.selling_price);
      const stock = parseFloat(p.stock || 0);

      const metaParts = [];
      if (brand) metaParts.push(brand);
      if (variation) metaParts.push(variation);
      if (unit) metaParts.push(unit);

      let stockBadgeClass = 'good';
      if (stock === 0) stockBadgeClass = 'out';
      else if (stock <= 5) stockBadgeClass = 'low';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'product-result-item';
      btn.innerHTML = `
        <div>
          <div class="product-result-name">${name}</div>
          <div class="product-result-meta">${metaParts.length ? metaParts.join(' · ') + ' · ' : ''}${price}</div>
        </div>
        <div style="text-align:right; flex-shrink:0; margin-left:12px;">
          <div style="font-size:.68rem; color:var(--text-muted); margin-bottom:3px;">Stock</div>
          <span class="stock-badge ${stockBadgeClass}">${stock}</span>
        </div>
      `;
      btn.addEventListener('click', () => selectProduct(p));
      productResults.appendChild(btn);
    });
  }

  function selectProduct(p) {
    selectedProduct = p;

    const name = safeText(p.product_name);
    const unit = safeText(p.unit);
    const brand = safeText(p.brand);
    const variation = safeText(p.variation);
    const price = formatCurrency(p.selling_price);
    const stock = parseFloat(p.stock || 0);

    const metaParts = [];
    if (brand) metaParts.push(brand);
    if (variation) metaParts.push(variation);
    if (unit) metaParts.push(unit);

    selectedProductName.textContent = name;
    selectedProductMeta.textContent = `${metaParts.length ? metaParts.join(' · ') + ' · ' : ''}Price: ${price}`;
    selectedStockNote.textContent = stock <= 0 ? '⚠ Out of stock' : `✓ ${stock} units available`;
    selectedStockNote.style.color = stock <= 0 ? 'var(--danger)' : 'var(--success)';

    selectedProductBox.classList.add('has-product');

    productQty.disabled = false;
    productAmount.disabled = false;
    addToCartBtn.disabled = false;
    productQty.value = 1;
    productAmount.value = parseFloat(p.selling_price).toFixed(2);
    productQty.max = stock;

    if (stock <= 0) {
      addToCartBtn.disabled = true;
    }
  }

  function filterProducts(term) {
    const t = term.toLowerCase().trim();
    if (!t) return PRODUCTS;
    return PRODUCTS.filter(p => {
      const name = safeText(p.product_name).toLowerCase();
      const brand = safeText(p.brand).toLowerCase();
      const variation = safeText(p.variation).toLowerCase();
      return name.includes(t) || brand.includes(t) || variation.includes(t);
    });
  }

  productSearchModal.addEventListener('input', function () {
    renderResults(filterProducts(this.value));
    selectedProduct = null;
    selectedProductName.textContent = 'No product selected';
    selectedProductMeta.textContent = '';
    selectedStockNote.textContent = '';
    selectedProductBox.classList.remove('has-product');
    productQty.value = 1;
    productAmount.value = '';
    productQty.disabled = true;
    productAmount.disabled = true;
    addToCartBtn.disabled = true;
  });

  document.getElementById('addProductModal').addEventListener('shown.bs.modal', function () {
    productSearchModal.value = '';
    selectedProduct = null;
    selectedProductName.textContent = 'No product selected';
    selectedProductMeta.textContent = '';
    selectedStockNote.textContent = '';
    selectedProductBox.classList.remove('has-product');
    productQty.value = 1;
    productAmount.value = '';
    productQty.disabled = true;
    productAmount.disabled = true;
    addToCartBtn.disabled = true;
    renderResults(PRODUCTS);
    productSearchModal.focus();
  });

  const productAmount = document.getElementById('productAmount');

  // Sync Qty -> Amount
  productQty.addEventListener('input', function() {
    if (!selectedProduct) return;
    const price = parseFloat(selectedProduct.selling_price || 0);
    const qty = parseFloat(this.value) || 0;
    productAmount.value = (qty * price).toFixed(2);
  });

  // Sync Amount -> Qty
  productAmount.addEventListener('input', function() {
    if (!selectedProduct) return;
    const price = parseFloat(selectedProduct.selling_price || 0);
    const amt = parseFloat(this.value) || 0;
    if (price > 0) {
      productQty.value = (amt / price).toFixed(4);
    }
  });

  addToCartBtn.addEventListener('click', function () {
    if (!selectedProduct) {
      alert("Please select a product from the results.");
      return;
    }

    const product_id = parseInt(selectedProduct.product_id, 10);
    const name = safeText(selectedProduct.product_name);
    const price = parseFloat(selectedProduct.selling_price || 0);
    const stock = parseFloat(selectedProduct.stock || 0);
    const unit = safeText(selectedProduct.unit);
    const brand = safeText(selectedProduct.brand);
    const variation = safeText(selectedProduct.variation);

    let qty = parseFloat(productQty.value);
    if (isNaN(qty) || qty <= 0) {
        alert("Please enter a valid quantity.");
        return;
    }
    if (qty > stock) qty = stock;

    if (stock <= 0) {
      alert("This product is out of stock.");
      return;
    }

    if (cart[product_id]) {
      const newQty = cart[product_id].quantity + qty;
      cart[product_id].quantity = (newQty > stock) ? stock : newQty;
    } else {
      cart[product_id] = {
        product_data: {
          product_id, product_name: name, default_price: price,
          stock, unit, brand, variation
        },
        quantity: qty
      };
    }

    renderCart();
    bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
  });

  // ── INVOICE MODAL BUILD ──
  invoiceModal.addEventListener('show.bs.modal', function () {
    invoiceDate.textContent = new Date().toLocaleString();
    invoiceTableBody.innerHTML = '';

    const cname = customerNameInputEl.value.trim() || "Walk-in Customer";
    const ccontact = customerContactInputEl.value.trim();
    const caddress = customerAddressInputEl.value.trim();

    invoiceCustomerName.textContent = cname;
    invoiceCustomerContact.textContent = ccontact ? ("📞 " + ccontact) : "";
    invoiceCustomerAddress.textContent = caddress ? ("📍 " + caddress) : "";

    invoiceCustomerNameInput.value = cname;
    invoiceCustomerContactInput.value = ccontact;
    invoiceCustomerAddressInput.value = caddress;

    let subtotal = 0;
    const itemsForDB = [];

    for (const id in cart) {
      const item = cart[id];
      const unitPrice = parseFloat(item.product_data.default_price);
      const qty = parseFloat(item.quantity);
      const lineTotal = unitPrice * qty;
      subtotal += lineTotal;

      const brand = safeText(item.product_data.brand);
      const variation = safeText(item.product_data.variation);
      const metaParts = [];
      if (brand) metaParts.push(brand);
      if (variation) metaParts.push(variation);

      invoiceTableBody.innerHTML += `
        <tr>
          <td>
            <strong>${item.product_data.product_name}</strong>
            ${metaParts.length ? `<div style="font-size:.73rem; color:var(--text-muted); margin-top:2px;">${metaParts.join(' · ')}</div>` : ''}
          </td>
          <td style="text-align:center;">${qty.toFixed(4).replace(/\.?0+$/, '')}</td>
          <td style="text-align:right; font-family:var(--font-mono);">${formatCurrency(unitPrice)}</td>
          <td style="text-align:right; font-family:var(--font-mono); font-weight:600;">${formatCurrency(lineTotal)}</td>
        </tr>
      `;

      itemsForDB.push({
        product_id: item.product_data.product_id,
        quantity: qty,
        total_price: lineTotal.toFixed(2)
      });
    }

    let discount = parseFloat(cashDiscountInput.value) || 0;
    if (discount < 0) discount = 0;
    if (discount > subtotal) discount = subtotal;

    const grand = Math.max(subtotal - discount, 0);

    invoiceSubtotalEl.textContent = formatCurrency(subtotal);
    invoiceDiscountEl.textContent = formatCurrency(discount);
    invoiceGrandTotalEl.textContent = formatCurrency(grand);

    invoiceCartDataInput.value = JSON.stringify(itemsForDB);
    invoiceDiscountAmountInput.value = discount.toFixed(2);
    invoiceTotalAmountInput.value = grand.toFixed(2);
  });

  printInvoiceBtn.addEventListener('click', function () {
    const content = document.getElementById('invoicePrintArea').innerHTML;
    const w = window.open('', '', 'width=900,height=650');
    w.document.write(`
      <html>
      <head>
        <title>Invoice Print</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
        <style>
          body { padding: 28px; font-family: 'DM Sans', sans-serif; }
          table { font-size: 13px; width: 100%; border-collapse: collapse; }
          th { padding: 6px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #888; border-bottom: 2px solid #e4e8f0; }
          td { padding: 9px 8px; border-bottom: 1px solid #f0f2f7; }
          .no-print { display: none !important; }
          .invoice-grand-row { background: #3b5bdb; border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; }
          .invoice-grand-row span { color: #fff; font-weight: 800; font-size: 1.05rem; font-family: 'DM Mono', monospace; }
          .invoice-grand-row span:first-child { font-family: 'DM Sans', sans-serif; }
        </style>
      </head>
      <body>${content}</body>
      </html>
    `);
    w.document.close();
    w.focus();
    w.print();
    w.close();
  });

  cashDiscountInput.addEventListener('input', calculateCartTotals);

  /**
   * Premium modal-based replacement for the browser's confirm()
   */
  function showConfirm({title = 'Are you sure?', message, okText = 'OK', okClass = 'btn-confirm-ok', icon = 'bi-exclamation-triangle-fill', callback}) {
      const id = 'cConfirm_' + Date.now();
      const html = `
          <div class="modal fade" id="${id}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
              <div class="modal-dialog modal-dialog-centered modal-sm">
                  <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">
                      <div class="modal-body p-4 text-center">
                          <div class="mb-3 d-inline-flex align-items-center justify-content-center" 
                               style="width:54px; height:54px; border-radius:50%; background:var(--danger-light); color:var(--danger); font-size:1.5rem;">
                              <i class="bi ${icon}"></i>
                          </div>
                          <h6 class="fw-bold mb-2" style="color:var(--text-primary);">${title}</h6>
                          <p class="mb-0 text-muted" style="font-size: .8rem; line-height:1.5;">${message}</p>
                      </div>
                      <div class="modal-footer border-0 p-3 pt-0 d-flex gap-2">
                          <button type="button" class="btn btn-light fw-bold flex-grow-1" data-bs-dismiss="modal" 
                                  style="border-radius:10px; font-size:.8rem; border:1px solid var(--border);">Cancel</button>
                          <button type="button" class="btn btn-danger fw-bold flex-grow-1" id="ok_${id}" 
                                  style="border-radius:10px; font-size:.8rem; box-shadow:0 4px 12px rgba(250,82,82,.25);">${okText}</button>
                      </div>
                  </div>
              </div>
          </div>`;
      
      document.body.insertAdjacentHTML('beforeend', html);
      const modalEl = document.getElementById(id);
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
      
      document.getElementById('ok_' + id).addEventListener('click', () => {
          callback();
          modal.hide();
      });
      
      modalEl.addEventListener('hidden.bs.modal', () => modalEl.remove());
      return false;
  }

  // Event listener for clearInvoiceBtn is now handled via onclick in HTML

  
  document.getElementById('invoiceForm')?.addEventListener('submit', function() {
      // Clear persistence and cache on successful submission (form will reload)
      localStorage.removeItem(INVOICE_STORAGE_KEY);
  });

  // INITIALIZE
  loadInvoiceState();
  
  // Save on exit as a fallback
  window.addEventListener('beforeunload', saveInvoiceState);
</script>

</body>
</html>