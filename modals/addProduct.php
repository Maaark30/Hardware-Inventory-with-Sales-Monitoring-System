<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="addProductModalLabel">
          <i class="fa fa-plus-circle"></i> Add Product
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Form -->
      <form id="saveProducts" method="POST" enctype="multipart/form-data" action="saveProduct.php">
        <div class="modal-body">
          <div id="errorMessage" class="alert alert-warning d-none"></div>

          <!-- Product Name -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="product_name">Product Name</label>
            <input type="text" id="product_name" name="product_name" class="form-control border-dark" placeholder="Enter product name" required>
            <div class="error-message text-danger"></div>
          </div>

          <!-- Product Image -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="product_image">Product Image (Optional)</label>
            <input type="file" id="product_image" name="product_image" class="form-control border-dark" accept="image/*">
            <div class="error-message text-danger"></div>
          </div>

          <!-- Barcode (auto-generated) -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="barcode">Barcode</label>
            <input type="text" id="barcode" name="barcode" class="form-control border-dark" readonly>
            <div class="error-message text-danger"></div>
          </div>

          <!-- Category Dropdown -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="category_id">Category</label>
            <select id="category_id" name="category_id" class="form-control border-dark" required>
              <option selected disabled>Select Category</option>
              <?php
                require 'dbcon.php';
                $categories = mysqli_query($con, "SELECT category_id, category_name FROM categories");
                while($category = mysqli_fetch_assoc($categories)) {
                  echo "<option value='{$category['category_id']}'>{$category['category_name']}</option>";
                }
              ?>
            </select>
            <div class="error-message text-danger"></div>
          </div>

          <!-- Price -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="price">Price</label>
            <input type="number" id="price" name="price" class="form-control border-dark" placeholder="Enter price" step="0.01" required>
            <div class="error-message text-danger"></div>
          </div>

          <!-- Stock -->
          <div class="mb-3">
            <label class="fw-bold mb-2" for="stock">Stock</label>
            <input type="number" id="stock" name="stock" class="form-control border-dark" placeholder="Enter stock quantity" required>
            <div class="error-message text-danger"></div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">
            <i class="fa fa-times"></i> Close
          </button>
          <button type="submit" class="btn btn-dark">
            <i class="fa fa-save"></i> Save Product
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- JavaScript to auto-generate barcode -->
<script>
  // Function to generate a unique barcode
  function generateBarcode() {
      const timestamp = Date.now(); 
      const randomNum = Math.floor(100 + Math.random() * 900); // 3 random digits
      return "BC" + timestamp + randomNum; 
  }

  // Auto-generate barcode when modal opens
  document.addEventListener("DOMContentLoaded", function () {
      const addProductModal = document.getElementById("addProductModal");
      addProductModal.addEventListener("show.bs.modal", function () {
          document.getElementById("barcode").value = generateBarcode();
      });
  });
</script>
