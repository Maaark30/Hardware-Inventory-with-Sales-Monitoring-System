<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #000000; color: white;">
                <h5 class="modal-title" id="editCategoryModalLabel">
                    <i class="fa fa-edit"></i> Edit Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="color: white;">
                </button>
            </div>
            <form id="updateCategories">
                <div class="modal-body">
                    <div id="errorMessage" class="alert alert-warning d-none"></div>
                    <input type="hidden" name="category_id" id="category_id">
                    <div class="mb-3">
                        <label class="fw-bold mb-2" for="category_name">Category Name</label>
                        <input type="text" name="category_name" id="category_name" class="form-control" placeholder="Enter category name" style="border-color: #000000;">
                        <div class="error-message text-danger"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: white; color: #000000; border-color: #000000;">
                        <i class="fa fa-times"></i> Close
                    </button>
                    <button type="submit" class="btn" style="background-color: #000000; color: white;">
                        <i class="fa fa-save"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
