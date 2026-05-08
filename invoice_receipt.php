<?php
session_start();
date_default_timezone_set('Asia/Manila');
include 'project.php';

// --- Authentication ---
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// --- Get Sale Group ID ---
$sale_group_id = isset($_GET['sale_group_id']) ? intval($_GET['sale_group_id']) : 0;

if ($sale_group_id <= 0) {
    $_SESSION['error'] = "Invalid Sale ID provided for invoice.";
    header("Location: p_os.php"); 
    exit();
}

// --- Fetch Sale Details ---
$group_sql = "SELECT 
                sg.*, 
                sp.payment_type, 
                sp.total_amount,
                u.full_name AS processed_by
              FROM sale_groups sg
              JOIN sale_payments sp ON sg.sale_group_id = sp.sale_group_id
              LEFT JOIN users u ON sg.user_id = u.id
              WHERE sg.sale_group_id = ?";
$stmt_group = $conn->prepare($group_sql);

if (!$stmt_group) {
    die("Error preparing sale group query: " . $conn->error);
}

$stmt_group->bind_param("i", $sale_group_id);
$stmt_group->execute();
$group_result = $stmt_group->get_result();
$sale_group = $group_result->fetch_assoc();
$stmt_group->close();

// Fetch individual items
$items_sql = "SELECT 
                s.quantity, 
                s.total_price, 
                p.product_name, p.brand, p.variation, p.unit,
                (s.total_price / NULLIF(s.quantity, 0)) AS unit_price
              FROM sales s
              JOIN products p ON s.product_id = p.product_id
              WHERE s.sale_group_id = ?";
$stmt_items = $conn->prepare($items_sql);

if (!$stmt_items) {
    die("Error preparing items query: " . $conn->error);
}

$stmt_items->bind_param("i", $sale_group_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

$sale_items = [];
while ($row = $items_result->fetch_assoc()) {
    $sale_items[] = $row;
}
$stmt_items->close();
$conn->close();

if (!$sale_group || empty($sale_items)) {
    $_SESSION['error'] = "Could not find sale details.";
    header("Location: sales.php"); 
    exit();
}

// --- Computations ---
$subtotal = 0;
foreach ($sale_items as $item) {
    $subtotal += (float)$item['total_price'];
}

$discount_amount = (float)($sale_group['discount_amount'] ?? 0);
$total_amount = (float)($sale_group['total_amount'] ?? 0);
$payment_type = $sale_group['payment_type'] ?? 'INVOICE';

// VAT calculation removed as requested.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= htmlspecialchars($sale_group_id) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --invoice-width: 816px; /* 8.5 inches @ 96 DPI */
            --border-thick: 2px solid #000;
            --border-thin: 1px solid #000;
            --text-main: #000;
        }

        body {
            background-color: #f0f2f5;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            margin: 0;
            padding: 30px 0;
            -webkit-print-color-adjust: exact;
        }
        
        .invoice-container {
            width: var(--invoice-width);
            margin: 0 auto;
            background: #fff;
            padding: 30px 45px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            min-height: 940px; /* Reduced for 11-inch Letter paper */
            display: flex;
            flex-direction: column;
            border: var(--border-thin);
        }

        /* ── Header ── */
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .invoice-header h2 {
            font-size: 26px;
            font-weight: 800;
            margin: 0 0 5px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .invoice-header p {
            margin: 3px 0;
            font-size: 13px;
            line-height: 1.4;
        }

        .title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            border-top: var(--border-thin);
            border-bottom: var(--border-thin);
            padding: 8px 0;
        }
        .title-row h1 {
            font-size: 20px;
            font-weight: 800;
            margin: 0;
            text-transform: uppercase;
            flex-grow: 1;
            text-align: center;
            margin-left: 100px;
        }
        .invoice-no {
            font-size: 18px;
            font-weight: 700;
            width: 150px;
            text-align: right;
        }

        /* ── Customer Info Grid ── */
        .customer-info-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 50px;
            margin: 25px 0;
        }
        .info-field {
            display: flex;
            align-items: flex-end;
            margin-bottom: 15px; /* Increased to prevent overlap */
            min-height: 28px;    /* Consistent row height */
        }
        .info-label {
            font-weight: 700;
            font-size: 12px;
            white-space: nowrap;
            margin-right: 10px;
            text-transform: uppercase;
            color: #333;
            padding-bottom: 2px;
        }
        .info-value {
            border-bottom: 1px solid #000;
            flex-grow: 1;
            padding-bottom: 2px;
            font-size: 14px;
            font-weight: 500;
            min-height: 20px;
        }

        /* ── Table ── */
        .table-wrap {
            flex-grow: 1;
            border: var(--border-thick);
            margin-bottom: 0;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-table th {
            border-bottom: var(--border-thick);
            border-right: var(--border-thin);
            padding: 8px 5px;
            font-size: 13px;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
        }
        .invoice-table th:last-child { border-right: none; }
        
        .invoice-table td {
            border-right: var(--border-thin);
            padding: 10px 8px;
            font-size: 13px;
            vertical-align: top;
        }
        .invoice-table td:last-child { border-right: none; }

        /* Row heights */
        .invoice-table tr.item-row { min-height: 35px; }

        .desc-title {
            font-weight: 700;
            display: block;
            text-transform: uppercase;
        }
        .desc-sub {
            font-size: 11px;
            color: #444;
        }

        /* ── Totals ── */
        .summary-section {
            display: flex;
            border: var(--border-thick);
            border-top: none;
        }
        .summary-left {
            flex-grow: 1;
            padding: 15px;
            font-size: 12px;
            border-right: var(--border-thin);
        }
        .summary-right {
            width: 320px;
        }
        .total-line {
            display: flex;
            border-bottom: var(--border-thin);
        }
        .total-line:last-child { border-bottom: none; }
        .total-label {
            width: 180px;
            padding: 8px 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border-right: var(--border-thin);
            text-align: right;
            background: #fdfdfd;
        }
        .total-val {
            flex-grow: 1;
            padding: 8px 10px;
            text-align: right;
            font-size: 14px;
            font-family: 'IBM Plex Mono', monospace;
            font-weight: 700;
        }

        /* ── Footer ── */
        .invoice-footer {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .footer-legal {
            font-size: 11px;
            max-width: 450px;
        }
        .signature-area {
            text-align: center;
            width: 250px;
        }
        .sig-line {
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            min-height: 25px;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
        }
        .sig-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* ── Actions ── */
        .action-bar {
            width: var(--invoice-width);
            margin: 30px auto;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .btn-modern {
            padding: 12px 25px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 4px;
            border: 1.5px solid #000;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            background: #fff;
            color: #000;
            text-transform: uppercase;
        }
        .btn-modern-dark {
            background: #000;
            color: #fff;
        }
        .btn-modern:hover {
            opacity: 0.8;
            transform: translateY(-1px);
        }

        @media print {
            @page {
                size: letter;
                margin: 0;
            }
            body { background: #fff; padding: 0; }
            .invoice-container { 
                box-shadow: none; 
                margin: 0; 
                width: 100%; 
                border: none;
                padding: 12mm;
            }
            .action-bar, .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="invoice-container">
    
    <!-- Branding Header -->
    <div class="invoice-header">
        <div style="display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 10px;">
            <img src="images/logo.png" alt="Logo" style="height: 60px; width: auto;">
            <h2 style="margin:0;">K&J B HARDWARE & CONST. SUPPLIES</h2>
        </div>
        <p>Lomboy, Indahag, Cagayan De Oro City, Misamis Oriental</p>
    </div>

    <div class="title-row">
        <h1>Sales Invoice</h1>
        <div class="invoice-no">No. <?= str_pad((string)$sale_group_id, 4, '0', STR_PAD_LEFT) ?></div>
    </div>

    <!-- Customer Details -->
    <div class="customer-info-area">
        <div class="info-column">
            <div class="info-field">
                <span class="info-label">Sold To:</span>
                <div class="info-value"><?= strtoupper(htmlspecialchars($sale_group['customer_name'] ?: 'WALK-IN CUSTOMER')) ?></div>
            </div>
            <div class="info-field">
                <span class="info-label">Address:</span>
                <div class="info-value"><?= strtoupper(htmlspecialchars($sale_group['customer_address'] ?? '-')) ?></div>
            </div>
        </div>
        <div class="info-column">
            <div class="info-field">
                <span class="info-label">Date/Time:</span>
                <div class="info-value"><?= date("M d, Y | h:i A", strtotime($sale_group['created_at'])) ?></div>
            </div>
            <div class="info-field">
                <span class="info-label">Contact#:</span>
                <div class="info-value"><?= strtoupper(htmlspecialchars($sale_group['customer_contact'] ?? '-')) ?></div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="table-wrap">
        <table class="invoice-table">
            <thead>
                <tr>
                    <th width="8%">Qty</th>
                    <th width="10%">Unit</th>
                    <th width="52%">Articles</th>
                    <th width="12%">Unit Price</th>
                    <th width="18%">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rowCount = 0;
                foreach ($sale_items as $item): 
                    $rowCount++;
                    $unit = !empty($item['unit']) ? $item['unit'] : 'pc';
                    $meta = array_filter([$item['brand'] ?? '', $item['variation'] ?? '']);
                ?>
                <tr class="item-row">
                    <td style="text-align:center;">
                        <?php 
                        $q = (float)$item['quantity'];
                        echo ($q == (int)$q) ? (int)$q : rtrim(rtrim(number_format($q, 4), '0'), '.');
                        ?>
                    </td>
                    <td style="text-align:center;"><?= strtoupper($unit) ?></td>
                    <td>
                        <span class="desc-title"><?= htmlspecialchars($item['product_name']) ?></span>
                        <?php if (!empty($meta)): ?>
                            <span class="desc-sub"><?= implode(' | ', array_map('htmlspecialchars', $meta)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right; font-family: 'IBM Plex Mono';"><?= number_format((float)$item['unit_price'], 2) ?></td>
                    <td style="text-align:right; font-family: 'IBM Plex Mono';"><?= number_format((float)$item['total_price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                
                <!-- Fill empty rows (Optimized for 11" Letter size) -->
                <?php for($i = $rowCount; $i < 10; $i++): ?>
                    <tr class="item-row">
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary Area -->
    <div class="summary-section">
        <div class="summary-left">
            <p style="margin:0 0 10px 0; font-weight:700;">Received the above goods in good order and condition.</p>
            <div style="font-size: 10px; color: #666; font-style: italic;">
                Serial ranges and printer accreditation details would be listed here for official receipts.
            </div>
        </div>
        <div class="summary-right">
            <div class="total-line">
                <div class="total-label">Less: Discount</div>
                <div class="total-val"><?= number_format($discount_amount, 2) ?></div>
            </div>
            <div class="total-line" style="background: #fafafa;">
                <div class="total-label" style="font-size: 13px;">Total Amount Due</div>
                <div class="total-val" style="font-size: 18px;">₱<?= number_format($total_amount, 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Final Footer -->
    <div class="invoice-footer">
        <div class="footer-legal">
            <p style="margin:0;">* Exchange only within 7 days with this invoice.</p>
            <p style="margin:2px 0 0 0;">* This serves as your Official Sales Invoice.</p>
        </div>
        <div class="signature-area">
            <div class="sig-line"><?= strtoupper(htmlspecialchars($sale_group['processed_by'] ?? $sale_group['created_by'] ?? 'CASHIER')) ?></div>
            <span class="sig-label">Cashier / Authorized Representative</span>
        </div>
    </div>

</div>

<!-- Actions -->
<div class="action-bar no-print">
    <button onclick="window.print();" class="btn-modern btn-modern-dark">
        Print Invoice
    </button>
    <a href="sales.php" class="btn-modern">
        Back to Sales List
    </a>
</div>
</body>
</html>