<div class="modal fade" id="addVariantModal" tabindex="-1" aria-labelledby="addVariantModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header" style="background-color: #000; color: #fff;">
        <h5 class="modal-title" id="addVariantModalLabel">
          <i class="fa fa-plus-circle"></i> Add Variant
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="color: #fff;"></button>
      </div>

      <form id="saveVariant">
        <div class="modal-body">
          <div id="errorMessage" class="alert alert-warning d-none"></div>

          <!-- Hidden product_id -->
          <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

          <!-- Variant Name -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="variant_name">Variant Name</label>
            <input type="text" name="variant_name" class="form-control" placeholder="Enter variant (e.g. 1-inch, 2-inch)" style="border-color: #000;">
            <div class="error-message text-danger"></div>
          </div>

          <!-- Price -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="price">Price</label>
            <input type="number" name="price" class="form-control" placeholder="Enter price" step="0.01" style="border-color: #000;">
            <div class="error-message text-danger"></div>
          </div>

          <!-- Stock -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="stock">Stock</label>
            <input type="number" name="stock" class="form-control" placeholder="Enter stock quantity" style="border-color: #000;">
            <div class="error-message text-danger"></div>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #fff; color: #000; border-color: #000;">
            <i class="fa fa-times"></i> Close
          </button>
          <button type="submit" class="btn" style="background-color: #000; color: #fff;">
            <i class="fa fa-save"></i> Save Variant
          </button>
        </div>
      </form>

    </div>
  </div>
</div>
