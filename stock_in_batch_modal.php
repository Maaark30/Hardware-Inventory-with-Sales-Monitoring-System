<?php
include 'project.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===================== FETCH BATCH DETAILS AJAX =====================
if (isset($_GET['fetch']) && $_GET['fetch'] == 1) {
    if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
        exit('<div class="alert alert-danger">Unauthorized access.</div>');
    }

    $batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

    if ($batch_id <= 0) {
        exit('<div class="alert alert-danger">Invalid batch ID.</div>');
    }

    // Batch header
    $batch_sql = "SELECT b.*, s.supplier_name, u.full_name AS staff_name
                  FROM stock_in_batches b
                  LEFT JOIN suppliers s ON b.supplier_id = s.supplier_id
                  LEFT JOIN users u ON b.stocked_by = u.username
                  WHERE b.batch_id = ?";
    $batch_stmt = $conn->prepare($batch_sql);

    if (!$batch_stmt) {
        exit('<div class="alert alert-danger">Failed to prepare batch query.</div>');
    }

    $batch_stmt->bind_param("i", $batch_id);
    $batch_stmt->execute();
    $batch_result = $batch_stmt->get_result();
    $batch = $batch_result->fetch_assoc();
    $batch_stmt->close();

    if (!$batch) {
        exit('<div class="alert alert-warning">Batch not found.</div>');
    }

    // Batch items
    $items_sql = "SELECT 
                    sh.product_id,
                    sh.quantity,
                    sh.expiry_date,
                    sh.created_at,
                    sh.supplier_price,
                    p.product_name,
                    p.brand,
                    p.variation,
                    p.unit,
                    p.selling_price
                  FROM stock_history sh
                  LEFT JOIN products p ON sh.product_id = p.product_id
                  WHERE sh.batch_id = ?
                  ORDER BY p.product_name ASC, sh.created_at ASC";

    $items_stmt = $conn->prepare($items_sql);

    if (!$items_stmt) {
        exit('<div class="alert alert-danger">Failed to prepare items query: ' . htmlspecialchars($conn->error) . '</div>');
    }

    $items_stmt->bind_param("i", $batch_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();

    $items_data = [];
    $total_items = 0;
    $total_quantity = 0;
    $grand_total_supplier_price = 0;

    while ($item = $items_result->fetch_assoc()) {
        $items_data[] = $item;
        $total_items++;
        $iq = (float)$item['quantity'];
        $total_quantity += $iq;
        $grand_total_supplier_price += ((float)($item['supplier_price'] ?? 0) * $iq);
    }
    $items_stmt->close();
    ?>

    <div class="container-fluid p-0">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body">
                        <h6 class="fw-bold text-primary mb-3">Batch Information</h6>
                        <p class="mb-1"><strong>Batch ID:</strong> #<?= (int)$batch['batch_id'] ?></p>
                        <p class="mb-1"><strong>Reference No:</strong> <?= htmlspecialchars($batch['reference_no']) ?></p>
                        <p class="mb-1"><strong>Supplier:</strong> <?= htmlspecialchars($batch['supplier_name'] ?? 'N/A') ?></p>
                        <p class="mb-1"><strong>Stocked By:</strong> <?= htmlspecialchars($batch['staff_name'] ?: $batch['stocked_by']) ?></p>
                        <p class="mb-0"><strong>Date & Time:</strong> <?= date('F j, Y g:ia', strtotime($batch['stock_in_date'])) ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="card border-0 bg-primary text-white h-50">
                    <div class="card-body text-center">
                        <h6 class="mb-0">Distinct Entries</h6>
                        <h2 class="fw-bold mb-0"><?= $total_items ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="card border-0 bg-success text-white h-50">
                    <div class="card-body text-center">
                        <h6 class="mb-0">Total Quantity</h6>
                        <h2 class="fw-bold mb-0">
                            <?php 
                            echo ($total_quantity == (int)$total_quantity) ? (int)$total_quantity : rtrim(rtrim(number_format($total_quantity, 4), '0'), '.');
                            ?>
                        </h2>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="card border-0 bg-warning text-white h-50">
                    <div class="card-body text-center">
                        <h6 class="mb-0">Total Cost</h6>
                        <h6 class="fw-bold mb-0">₱<?= number_format($grand_total_supplier_price, 2) ?></h6>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 12px;">
        <div class="card-header border-0 py-3 px-4" style="background: #334155; color: #fff;">
            <h6 class="mb-0 fw-bold">Items in this Batch</h6>
        </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Brand</th>
                                <th>Variation</th>
                                <th>Unit</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-center">Supplier Price</th>
                                <th class="text-center">Subtotal</th>
                                <th class="text-center">Expiry Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($items_data)): ?>
                                <?php foreach ($items_data as $index => $item): ?>
                                    <?php
                                        $supplier_price = (float)($item['supplier_price'] ?? 0);
                                        $iq = (float)$item['quantity'];
                                        $subtotal = $supplier_price * $iq;
                                    ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($item['product_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($item['brand'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($item['variation'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($item['unit'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <?php 
                                            echo ($iq == (int)$iq) ? (int)$iq : rtrim(rtrim(number_format($iq, 4), '0'), '.');
                                            ?>
                                        </td>
                                        <td class="text-center">₱<?= number_format($supplier_price, 2) ?></td>
                                        <td class="text-center">₱<?= number_format($subtotal, 2) ?></td>
                                        <td class="text-center">
                                            <?= !empty($item['expiry_date']) ? htmlspecialchars($item['expiry_date']) : '-' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted">No items found in this batch.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php
    exit();
}
?>

<!-- Batch Details Modal -->
<div class="modal fade" id="batchDetailsModal" tabindex="-1" aria-labelledby="batchDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="batchDetailsModalLabel">
                    <i class="bi bi-box-seam me-2"></i> Batch Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div id="batchDetailsContent" class="modal-body-content">
                    <div class="loading-box">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div>Loading batch details...</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>