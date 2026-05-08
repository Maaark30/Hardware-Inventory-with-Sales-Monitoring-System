<div class="modal fade" id="deleteVariantModal" tabindex="-1" aria-labelledby="deleteVariantModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header" style="background-color: #000; color: #fff;">
        <h5 class="modal-title" id="deleteVariantModalLabel">
          <i class="fa fa-trash"></i> Delete Variant
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="color: #fff;"></button>
      </div>

      <!-- Body -->
      <div class="modal-body text-center">
        <p>Are you sure you want to delete this variant?</p>
      </div>

      <!-- Footer -->
      <div class="modal-footer d-flex justify-content-center">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #fff; color: #000; border-color: #000;">
          <i class="fa fa-times"></i> Cancel
        </button>
        <button type="button" id="confirmDeleteVariantBtn" class="btn btn-danger">
          <i class="fa fa-trash"></i> Delete
        </button>
      </div>

    </div>
  </div>
</div>
