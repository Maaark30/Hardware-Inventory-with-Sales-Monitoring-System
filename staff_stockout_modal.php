<div class="modal fade" id="stockOutModal" tabindex="-1" aria-labelledby="stockOutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="staff_products.php" method="POST" id="stockOutForm">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="stockOutModalLabel">
                        <i class="bi bi-box-arrow-up me-2"></i> Item Stock-Out
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning mb-0">
                        Stock-out modal file is loaded successfully, but the stock-out backend process is not yet connected in <strong>staff_products.php</strong>.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>