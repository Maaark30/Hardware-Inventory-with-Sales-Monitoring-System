<div class="modal fade" id="editVariantModal" tabindex="-1" aria-labelledby="editVariantModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header" style="background-color: #000; color: #fff;">
        <h5 class="modal-title" id="editVariantModalLabel">
          <i class="fa fa-edit"></i> Edit Variant
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="color: #fff;"></button>
      </div>

      <form id="updateVariant">
        <div class="modal-body">
          <div id="errorMessage" class="alert alert-warning d-none"></div>

          <!-- Hidden IDs -->
          <input type="hidden" name="variant_id" id="edit_variant_id">
          <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

          <!-- Variant Name -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="edit_variant_name">Variant Name</label>
            <input type="text" name="variant_name" id="edit_variant_name" class="form-control" placeholder="Enter variant name" style="border-color: #000;">
            <div class="error-message text-danger"></div>
          </div>

          <!-- Price -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="edit_price">Price</label>
            <input type="number" name="price" id="edit_price" class="form-control" step="0.01" style="border-color: #000;">
            <div class="error-message text-danger"></div>
          </div>

          <!-- Stock -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="edit_stock">Stock</label>
            <input type="number" name="stock" id="edit_stock" class="form-control" style="border-color: #000;">
            <div class="error-message text-danger"></div>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #fff; color: #000; border-color: #000;">
            <i class="fa fa-times"></i> Close
          </button>
          <button type="submit" class="btn" style="background-color: #000; color: #fff;">
            <i class="fa fa-save"></i> Update Variant
          </button>
        </div>
      </form>

    </div>
  </div>
</div>
