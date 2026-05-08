<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #000000; color: white;">
                <h5 class="modal-title" id="editProductModalLabel">
                    <i class="fa fa-edit"></i> Edit Product
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="color: white;"></button>
            </div>
            <form id="updateProducts">
                <div class="modal-body">
                    <div id="errorMessage" class="alert alert-warning d-none"></div>
                    <input type="hidden" name="product_id" id="product_id">
                    <div class="mb-3">
                        <label class="fw-bold mb-2" for="edit_product_name">Product Name</label>
                        <input type="text" name="product_name" id="edit_product_name" class="form-control" placeholder="Enter product name" style="border-color: #000000;">
                        <div class="error-message text-danger"></div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold mb-2" for="edit_category_id">Category</label>
                        <select name="category_id" id="edit_category_id" class="form-control" style="border-color: #000000;">
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

                    <div class="mb-3">
                        <label class="fw-bold mb-2" for="edit_price">Price</label>
                        <input type="number" name="price" id="edit_price" class="form-control" placeholder="Enter price" step="0.01" style="border-color: #000000;">
                        <div class="error-message text-danger"></div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold mb-2" for="edit_stock">Stock</label>
                        <input type="number" name="stock" id="edit_stock" class="form-control" placeholder="Enter stock quantity" style="border-color: #000000;">
                        <div class="error-message text-danger"></div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: white; color: #000000; border-color: #000000;">
                        <i class="fa fa-times"></i> Close
                    </button>
                    <button type="submit" class="btn" style="background-color: #000000; color: white;">
                        <i class="fa fa-save"></i> Update Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
