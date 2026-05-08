<div class="modal fade" id="addSaleModal" tabindex="-1" aria-labelledby="addSaleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <!-- Modal Header with Black Background -->
      <div class="modal-header" style="background-color: #000000; color: white;">
        <h5 class="modal-title" id="addSaleModalLabel">
          <i class="fa fa-plus-circle"></i> Add Sale
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="color: white;"></button>
      </div>

      <form id="saveSales">
        <div class="modal-body">
          <div id="errorMessage" class="alert alert-warning d-none"></div>

          <!-- Product Selection -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="product_id">Product</label>
            <select name="product_id" id="product_id" class="form-control" style="border-color: #000000;">
              <option selected disabled>Select Product</option>
              <?php
                require 'dbcon.php';
                $products = mysqli_query($con, "SELECT product_id, product_name FROM products");
                while($product = mysqli_fetch_assoc($products)) {
                  echo "<option value='{$product['product_id']}'>{$product['product_name']}</option>";
                }
              ?>
            </select>
            <div class="error-message text-danger"></div>
          </div>

          <!-- Variant Selection -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="variant_id">Variant</label>
            <select name="variant_id" id="variant_id" class="form-control" style="border-color: #000000;" disabled>
              <option selected disabled>Select a product first</option>
            </select>
            <div class="error-message text-danger"></div>
          </div>

          <!-- Quantity -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" class="form-control" placeholder="Enter quantity" min="1" style="border-color: #000000;">
            <div class="error-message text-danger"></div>
          </div>

          <!-- Sale Price -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="sale_price">Sale Price</label>
            <input type="number" id="sale_price" name="sale_price" class="form-control" placeholder="Enter sale price" min="0" step="0.01" style="border-color: #000000;">
            <div class="error-message text-danger"></div>
          </div>
        </div>

        <!-- Modal Footer Buttons -->
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: white; color: #000000; border-color: #000000;">
            <i class="fa fa-times"></i> Close
          </button>
          <button type="submit" class="btn" style="background-color: #000000; color: white;">
            <i class="fa fa-save"></i> Save Sale
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
