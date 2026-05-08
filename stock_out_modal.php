<!-- 🆕 Stock Out Modal -->
<div class="modal fade" id="stockOutModal" tabindex="-1" aria-labelledby="stockOutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="stockOutModalLabel"><i class="bi bi-box-arrow-up me-2"></i>Stock Out Item</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="stockOutForm" action="process_stock_out.php" method="POST" onsubmit="return showConfirm({title:'Confirm Stock Out?', message:'Are you sure you want to record this stock out? This will deduct the selected quantity from the official inventory permanently.', okText:'Confirm Stock Out', okClass:'green', icon:'bi-box-arrow-up', callback:()=>this.submit()});">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="product_id_out" class="form-label">Select Product</label>
              <select name="product_id" id="product_id_out" class="form-select" required>
                <option value="">-- Choose Product --</option>
                <?php
                mysqli_data_seek($result, 0);
                while ($p = mysqli_fetch_assoc($result)): ?>
                  <option value="<?= $p['product_id'] ?>">
                    <?= htmlspecialchars($p['product_name']) ?> 
                    (Stock: <?= formatQty($p['stock']) ?>)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="quantity_out" class="form-label">Quantity</label>
              <input type="number" name="quantity" id="quantity_out" class="form-control" min="1" required>
            </div>

            <div class="col-12">
              <label for="reason_out" class="form-label">Reason / Remarks</label>
              <textarea name="reason" id="reason_out" class="form-control" placeholder="e.g., Sold, Damaged, Transferred"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-save me-1"></i> Save Stock Out
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
