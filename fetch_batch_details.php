<?php
include 'project.php';
session_start();

if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo "<div class='alert alert-danger'>Unauthorized.</div>";
    exit;
}

$type = trim($_GET['type'] ?? '');
$batch_id = intval($_GET['batch_id'] ?? 0);

if ($batch_id <= 0 || !in_array($type, ['stock_in', 'stock_out'], true)) {
    echo "<div class='alert alert-danger'>Invalid request parameters.</div>";
    exit;
}

if ($type === 'stock_in') {
    $sql = "
        SELECT
            sh.id AS stock_history_id,
            p.product_name,
            p.brand,
            p.variation,
            p.unit,
            sh.quantity,
            sh.expiry_date,
            sh.supplier_price,
            sh.total_cost,
            sh.item_desc,
            sh.created_at,
            sib.reference_no,
            sib.supplier_id,
            sib.stocked_by,
            sib.stock_in_date,
            s.supplier_name,
            u.full_name AS staff_name
        FROM stock_history sh
        LEFT JOIN stock_in_batches sib ON sh.batch_id = sib.batch_id
        LEFT JOIN products p ON sh.product_id = p.product_id
        LEFT JOIN suppliers s ON sib.supplier_id = s.supplier_id
        LEFT JOIN users u ON sib.stocked_by = u.username
        WHERE sh.batch_id = ?
        ORDER BY sh.created_at ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $header_title = "Stock In Batch Details";
} else {
    $sql = "
        SELECT
            so.id AS stock_out_id,
            p.product_name,
            p.brand,
            p.variation,
            p.unit,
            so.quantity,
            so.reason,
            so.supplier_price,
            so.total_cost,
            so.created_at,
            so.stocked_by,
            u.full_name AS staff_name
        FROM stock_out so
        LEFT JOIN products p ON so.product_id = p.product_id
        LEFT JOIN users u ON so.stocked_by = u.username
        WHERE so.id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $header_title = "Stock Out Details";
}

$items = [];
while ($row = $result->fetch_assoc()) { $items[] = $row; }
$stmt->close();

if (empty($items)) {
    echo "<div class='alert alert-warning'>No items found for this batch.</div>";
    exit;
}

$first = $items[0];
$operation = ($type === 'stock_in') ? 'STOCK IN' : 'STOCK OUT';
$op_color = ($type === 'stock_in') ? '#3b82f6' : '#ef4444';
$op_bg = ($type === 'stock_in') ? '#eff6ff' : '#fef2f2';

$created_at = $first['created_at'] ?? '';
$staff = $first['staff_name'] ?? $first['stocked_by'] ?? '-';
$note = ($type === 'stock_in') ? ($first['reference_no'] ?? '-') : ($first['reason'] ?? '-');

$totalQty = 0;
$totalAmount = 0;
foreach ($items as $item) {
    $totalQty += (float)($item['quantity'] ?? 0);
    $totalAmount += (float)($item['total_cost'] ?? 0);
}

$formatted_date = $created_at ? date('M d, Y', strtotime($created_at)) : '-';
$formatted_time = $created_at ? date('h:i A', strtotime($created_at)) : '';
?>

<div class="container-fluid p-0">
    <!-- SUMMARY CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="p-4 border-0 h-100 shadow-sm" style="background: #f1f5f9; border-radius: 12px;">
                <h5 class="fw-bold text-primary mb-3" style="font-size: 1.1rem;">Batch Information</h5>
                <div class="d-flex flex-column gap-2" style="font-size: 0.95rem;">
                    <div><strong>Batch ID:</strong> #<?= $batch_id ?></div>
                    <div><strong><?= ($type === 'stock_in' ? 'Reference No:' : 'Reason:') ?></strong> <?= htmlspecialchars($note) ?></div>
                    <?php if($type === 'stock_in'): ?>
                        <div><strong>Supplier:</strong> <?= htmlspecialchars($first['supplier_name'] ?? 'N/A') ?></div>
                    <?php endif; ?>
                    <div><strong>Stocked By:</strong> <?= htmlspecialchars($staff) ?></div>
                    <div><strong>Date & Time:</strong> <?= $formatted_date ?> <?= $formatted_time ?></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="p-3 text-center h-50 d-flex flex-column justify-content-center shadow-sm" style="background: #3b82f6; color: #fff; border-radius: 12px;">
                <div class="small fw-bold text-uppercase opacity-75 mb-1">Distinct Entries</div>
                <div class="display-6 fw-bold"><?= count($items) ?></div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="p-3 text-center h-50 d-flex flex-column justify-content-center shadow-sm" style="background: #10b981; color: #fff; border-radius: 12px;">
                <div class="small fw-bold text-uppercase opacity-75 mb-1">Total Quantity</div>
                <div class="display-6 fw-bold">
                    <?php 
                    echo ($totalQty == (int)$totalQty) ? (int)$totalQty : rtrim(rtrim(number_format($totalQty, 4), '0'), '.');
                    ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="p-3 text-center h-50 d-flex flex-column justify-content-center shadow-sm" style="background: #f59e0b; color: #fff; border-radius: 12px;">
                <div class="small fw-bold text-uppercase opacity-75 mb-1">Total Cost</div>
                <div class="h4 fw-bold mb-0">₱<?= number_format($totalAmount, 2) ?></div>
            </div>
        </div>
    </div>

    <!-- ITEMS TABLE -->
    <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 12px;">
        <div class="card-header border-0 py-3 px-4" style="background: #334155; color: #fff;">
            <h6 class="mb-0 fw-bold">Items in this Batch</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem; border-collapse: collapse;">
                    <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <tr>
                            <th class="ps-4 text-center" style="width: 50px;">#</th>
                            <th>Product</th>
                            <th>Brand</th>
                            <th>Variation</th>
                            <th>Unit</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-center">Supplier Price</th>
                            <th class="text-center">Subtotal</th>
                            <th class="text-center pe-4">Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $idx = 1;
                        foreach ($items as $item): 
                            $supPrice = (float)($item['supplier_price'] ?? 0);
                            $totCost = (float)($item['total_cost'] ?? 0);
                            $exp = $item['expiry_date'] ?? '-';
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td class="ps-4 text-center text-muted"><?= $idx++ ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($item['product_name'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($item['brand'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['variation'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($item['unit'] ?? '-') ?></td>
                            <td class="text-center fw-bold">
                                <?php 
                                $iq = (float)$item['quantity'];
                                echo ($iq == (int)$iq) ? (int)$iq : rtrim(rtrim(number_format($iq, 4), '0'), '.');
                                ?>
                            </td>
                            <td class="text-center">₱<?= number_format($supPrice, 2) ?></td>
                            <td class="text-center fw-bold text-dark">₱<?= number_format($totCost, 2) ?></td>
                            <td class="text-center pe-4 text-muted small"><?= $exp ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
