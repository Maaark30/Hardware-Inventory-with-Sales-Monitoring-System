<?php
include 'project.php';
session_start();

// Check authentication - allow AJAX calls
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username']) && !isset($_SESSION['role'])) {
    exit('<div class="alert alert-danger">Session expired. Please refresh the page and try again.</div>');
}

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$view = isset($_GET['view']) ? $_GET['view'] : 'details';

if ($product_id <= 0) {
    exit('<div class="alert alert-danger">Invalid product ID.</div>');
}

// Get product basic information
$product_sql = "SELECT p.*, c.category_name, s.subcategory_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.category_id
                LEFT JOIN subcategories s ON p.subcategory_id = s.subcategory_id
                WHERE p.product_id = ?";
$product_stmt = $conn->prepare($product_sql);

if (!$product_stmt) {
    exit('<div class="alert alert-danger">Failed to prepare product query.</div>');
}

$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();
$product = $product_result->fetch_assoc();
$product_stmt->close();

if (!$product) {
    exit('<div class="alert alert-warning">Product not found.</div>');
}

// Get stock-in history with batch information
$stock_in_sql = "SELECT
                    sh.id AS stock_history_id,
                    sh.quantity,
                    sh.expiry_date,
                    sh.created_at,
                    sh.supplier_price,
                    sh.total_cost,
                    sib.batch_id,
                    sib.reference_no,
                    sib.stock_in_date,
                    s.supplier_name
                 FROM stock_history sh
                 LEFT JOIN stock_in_batches sib ON sh.batch_id = sib.batch_id
                 LEFT JOIN suppliers s ON sib.supplier_id = s.supplier_id
                 WHERE sh.product_id = ? AND sh.quantity > 0
                 ORDER BY sh.created_at DESC";
$stock_in_stmt = $conn->prepare($stock_in_sql);

if (!$stock_in_stmt) {
    exit('<div class="alert alert-danger">Failed to prepare stock-in history query.</div>');
}

$stock_in_stmt->bind_param("i", $product_id);
$stock_in_stmt->execute();
$stock_in_result = $stock_in_stmt->get_result();

$stock_in_history = [];
while ($row = $stock_in_result->fetch_assoc()) {
    $stock_in_history[] = $row;
}
$stock_in_stmt->close();

// Get stock-out history
$stock_out_sql = "SELECT
                     so.quantity,
                     so.reason,
                     so.created_at,
                     so.supplier_price,
                     so.total_cost
                  FROM stock_out so
                  WHERE so.product_id = ?
                  ORDER BY so.created_at DESC";
$stock_out_stmt = $conn->prepare($stock_out_sql);

if (!$stock_out_stmt) {
    exit('<div class="alert alert-danger">Failed to prepare stock-out history query.</div>');
}

$stock_out_stmt->bind_param("i", $product_id);
$stock_out_stmt->execute();
$stock_out_result = $stock_out_stmt->get_result();

$stock_out_history = [];
while ($row = $stock_out_result->fetch_assoc()) {
    $stock_out_history[] = $row;
}
$stock_out_stmt->close();

// Get expired batches for this product
$expired_sql = "SELECT
                   sh.id AS stock_history_id,
                   sh.quantity,
                   sh.expiry_date,
                   sh.created_at,
                   sib.batch_id,
                   sib.reference_no
                FROM stock_history sh
                LEFT JOIN stock_in_batches sib ON sh.batch_id = sib.batch_id
                WHERE sh.product_id = ?
                  AND sh.expiry_date IS NOT NULL
                  AND sh.expiry_date < CURDATE()
                  AND sh.quantity > 0
                ORDER BY sh.expiry_date DESC";
$expired_stmt = $conn->prepare($expired_sql);

if (!$expired_stmt) {
    exit('<div class="alert alert-danger">Failed to prepare expired batches query.</div>');
}

$expired_stmt->bind_param("i", $product_id);
$expired_stmt->execute();
$expired_result = $expired_stmt->get_result();

$expired_batches = [];
while ($row = $expired_result->fetch_assoc()) {
    $expired_batches[] = $row;
}
$expired_stmt->close();
?>

<div class="container-fluid p-0">
    <?php if ($view === 'details'): ?>
    <!-- Product Information -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card border-0 bg-light h-100">
                <div class="card-body">
                    <h6 class="fw-bold text-primary mb-3">Product Information</h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($product['product_name']) ?></p>
                            <p class="mb-1"><strong>Brand:</strong> <?= htmlspecialchars($product['brand'] ?? '') ?></p>
                            <p class="mb-1"><strong>Variation:</strong> <?= htmlspecialchars($product['variation'] ?? '') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Category:</strong> <?= htmlspecialchars($product['category_name'] ?? '') ?></p>
                            <p class="mb-1"><strong>Subcategory:</strong> <?= htmlspecialchars($product['subcategory_name'] ?? '') ?></p>
                            <p class="mb-1"><strong>Unit:</strong> <?= htmlspecialchars($product['unit'] ?? '') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>SKU:</strong> <?= htmlspecialchars($product['sku'] ?? '') ?></p>
                            <p class="mb-1"><strong>Current Stock:</strong>
                                <span class="badge bg-<?= $product['stock'] <= ($product['reorder_level'] ?? 0) ? 'danger' : 'success' ?>">
                                    <?= formatQty($product['stock']) ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">

                            <p class="mb-1"><strong>Selling Price:</strong> ₱<?= number_format((float)$product['selling_price'], 2) ?></p>
                        </div>
                    </div>
                    <?php if (!empty($product['description'])): ?>
                        <p class="mb-0 mt-2"><strong>Description:</strong> <?= htmlspecialchars($product['description']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 bg-primary text-white h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <h6 class="mb-2 opacity-75">Stock Summary</h6>
                    <h1 class="fw-bold mb-1">
                        <?= formatQty($product['stock']) ?>
                    </h1>
                    <p class="mb-1 fw-medium text-white-50 small">Current Stock</p>
                    <div class="mt-2 pt-2 border-top border-white-default">
                        <small>Threshold: 
                            <?= formatQty($product['reorder_level'] ?? 0) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Stock Batches -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom-0 pt-4 px-4 pb-0">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-clock-history me-2 text-primary"></i>Recent Stock Batches
                </h6>
                <span class="badge bg-primary-subtle text-primary px-3 py-2 fw-semibold" style="font-size: 10px;">
                    SUPPLY RECORDS
                </span>
            </div>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="table-responsive rounded-3 border overflow-hidden">
                <table class="table table-hover mb-0 align-middle" style="font-size: 13px;">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">Supplier Source</th>
                            <th class="text-center py-3">Quantity</th>
                            <th class="text-center py-3">Supplier Price</th>
                            <th class="text-center py-3">Total Cost</th>
                            <th class="text-center py-3">Ref No.</th>
                            <th class="text-end pe-4 py-3">Date Stocked</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php if (!empty($stock_in_history)): ?>
                            <?php 
                            // Only show top 5 in the details view
                            foreach (array_slice($stock_in_history, 0, 5) as $item): 
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($item['supplier_name'] ?? 'N/A') ?></div>
                                        <div class="text-muted x-small" style="font-size: 11px;">Batch #<?= (int)$item['batch_id'] ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary-subtle text-secondary px-3">
                                            <?= formatQty($item['quantity']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center fw-bold text-primary">₱<?= number_format((float)$item['supplier_price'], 2) ?></td>
                                    <td class="text-center text-muted">₱<?= number_format((float)($item['total_cost'] ?? ((float)$item['supplier_price'] * (int)$item['quantity'])), 2) ?></td>
                                    <td class="text-center">
                                        <span class="font-monospace text-secondary opacity-75"><?= htmlspecialchars($item['reference_no'] ?? 'N/A') ?></span>
                                    </td>
                                    <td class="text-end pe-4 text-muted">
                                        <div class="fw-medium text-dark"><?= date('M d, Y', strtotime($item['created_at'])) ?></div>
                                        <div class="x-small text-muted" style="font-size: 11px;"><?= date('h:i A', strtotime($item['created_at'])) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox-fill fs-2 d-block mb-2 opacity-25"></i>
                                    No stock-in records found for this product.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($stock_in_history) > 5): ?>
                <div class="text-center mt-3">
                    <p class="text-muted mb-0" style="font-size: 12px;">
                        Showing the 5 most recent batches. To see more, switch to the <strong>Stock In History</strong> view.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'stockin'): ?>
    <!-- Stock-In History -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-success text-white">
            <strong><i class="bi bi-box-arrow-in-down me-2"></i>Stock-In History (Batches)</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Batch #</th>
                            <th>Reference No.</th>
                            <th>Supplier</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-center">Supplier Price</th>
                            <th class="text-center">Total Cost</th>
                            <th class="text-center">Expiry Date</th>
                            <th class="text-center">Stocked In</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($stock_in_history)): ?>
                            <?php foreach ($stock_in_history as $item): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-success">Batch #<?= (int)$item['batch_id'] ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($item['reference_no'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($item['supplier_name'] ?? 'N/A') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary">
                                            <?= formatQty($item['quantity']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">₱<?= number_format((float)$item['supplier_price'], 2) ?></td>
                                    <td class="text-center">₱<?= number_format((float)($item['total_cost'] ?? ((float)$item['supplier_price'] * (int)$item['quantity'])), 2) ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($item['expiry_date'])): ?>
                                            <span class="badge bg-<?= strtotime($item['expiry_date']) < time() ? 'danger' : 'warning' ?>">
                                                <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?= date('F j, Y g:ia', strtotime($item['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No stock-in history found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'stockout'): ?>
    <!-- Stock-Out History -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-danger text-white">
            <strong><i class="bi bi-box-arrow-up me-2"></i>Stock-Out History</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">Quantity</th>
                            <th>Reason</th>
                            <th class="text-center">Cost</th>
                            <th class="text-center">Stocked Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($stock_out_history)): ?>
                            <?php foreach ($stock_out_history as $item): ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="badge bg-danger">
                                            <?= formatQty($item['quantity']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($item['reason'] ?? 'N/A') ?></td>
                                    <td class="text-center">₱<?= number_format((float)($item['total_cost'] ?? ($item['supplier_price'] * $item['quantity'])), 2) ?></td>
                                    <td class="text-center">
                                        <?= date('F j, Y g:ia', strtotime($item['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No stock-out history found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'expired'): ?>
    <!-- Expired Batches -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-warning text-dark">
            <strong><i class="bi bi-exclamation-triangle me-2"></i>Expired Batches</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Batch #</th>
                            <th>Reference No.</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-center">Expiry Date</th>
                            <th class="text-center">Stocked In</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($expired_batches)): ?>
                            <?php foreach ($expired_batches as $item): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-danger">Batch #<?= (int)$item['batch_id'] ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($item['reference_no'] ?? 'N/A') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">
                                            <?= formatQty($item['quantity']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">
                                            <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?= date('F j, Y g:ia', strtotime($item['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No expired batches found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>