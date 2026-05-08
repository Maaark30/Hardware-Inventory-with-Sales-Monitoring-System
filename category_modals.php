<?php
// ========================= CATEGORY MODALS =========================
?>

<style>
/* ── Shared modal styles ───────────────────── */
.modal-premium .modal-content {
    border: 0;
    border-radius: 18px;
    overflow: hidden;
    font-family: 'Inter', Arial, sans-serif;
}
.modal-premium .modal-header {
    padding: 20px 24px 14px;
    border-bottom: 0;
}
.modal-premium .modal-header.cat-header {
    background: linear-gradient(135deg, #1458ec 0%, #2563eb 100%);
}
.modal-premium .modal-header.sub-header {
    background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
}
.modal-premium .modal-header .modal-title {
    color: #fff;
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 9px;
}
.modal-premium .modal-header .modal-title i {
    font-size: 1.15rem;
}
.modal-premium .btn-close-custom {
    background: rgba(255,255,255,0.2);
    border: 0;
    border-radius: 8px;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.18s;
}
.modal-premium .btn-close-custom:hover { background: rgba(255,255,255,0.35); }
.modal-premium .modal-body { padding: 20px 24px; background: #fff; }
.modal-premium .form-label {
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6b7280;
    margin-bottom: 6px;
}
.modal-premium .form-control,
.modal-premium .form-select {
    border-radius: 10px;
    border: 1.5px solid #e5e7eb;
    font-size: 0.91rem;
    padding: 9px 12px;
    transition: border-color 0.18s, box-shadow 0.18s;
}
.modal-premium .form-control:focus,
.modal-premium .form-select:focus {
    border-color: #1458ec;
    box-shadow: 0 0 0 3px rgba(20,88,236,0.1);
}
.modal-premium .current-image {
    width: 56px; height: 56px;
    border-radius: 10px;
    object-fit: cover;
    border: 2px solid #e8f0fe;
    margin-top: 8px;
}
.modal-premium .modal-footer {
    padding: 14px 24px 20px;
    border-top: 1px solid #f0f4f8;
    background: #fafbfc;
    gap: 10px;
}
.btn-modal-cancel {
    border-radius: 10px;
    padding: 9px 20px;
    font-weight: 600;
    font-size: 0.88rem;
    background: #f3f4f6;
    border: 0;
    color: #374151;
    transition: background 0.18s;
}
.btn-modal-cancel:hover { background: #e5e7eb; color: #111; }
.btn-modal-submit {
    border-radius: 10px;
    padding: 9px 22px;
    font-weight: 600;
    font-size: 0.88rem;
    background: linear-gradient(135deg, #1458ec, #2563eb);
    border: 0;
    color: #fff;
    transition: box-shadow 0.18s, transform 0.18s;
}
.btn-modal-submit:hover {
    box-shadow: 0 6px 16px rgba(20,88,236,0.35);
    transform: translateY(-1px);
    color: #fff;
}
</style>

<!-- ===================== ADD CATEGORY MODAL ===================== -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-premium" style="max-width:440px;">
    <div class="modal-content">
      <form action="categories.php" method="POST">
        <input type="hidden" name="add_category" value="1">
        <div class="modal-header cat-header d-flex justify-content-between align-items-center">
          <h5 class="modal-title" id="addCategoryModalLabel">
            <i class="bi bi-tag-fill"></i> Add Category
          </h5>
          <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="add_category_name" class="form-label">Category Name</label>
            <input type="text" class="form-control" name="category_name" id="add_category_name"
                   placeholder="e.g. Electronics" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">
            <i class="bi bi-x me-1"></i> Cancel
          </button>
          <button type="submit" class="btn-modal-submit">
            <i class="bi bi-plus-lg me-1"></i> Add Category
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== EDIT CATEGORY MODALS ===================== -->
<?php if (isset($categories) && mysqli_num_rows($categories) > 0): ?>
  <?php mysqli_data_seek($categories, 0); ?>
  <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
    <div class="modal fade" id="editCategoryModal<?= $cat['category_id'] ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-premium" style="max-width:440px;">
        <div class="modal-content">
          <form action="categories.php" method="POST">
            <input type="hidden" name="edit_category" value="1">
            <input type="hidden" name="id" value="<?= $cat['category_id'] ?>">
            <div class="modal-header cat-header d-flex justify-content-between align-items-center">
              <h5 class="modal-title">
                <i class="bi bi-pencil-fill"></i> Edit Category
              </h5>
              <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Category Name</label>
                <input type="text" class="form-control" name="category_name"
                       value="<?= htmlspecialchars($cat['category_name']) ?>" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">
                <i class="bi bi-x me-1"></i> Cancel
              </button>
              <button type="submit" class="btn-modal-submit">
                <i class="bi bi-check-lg me-1"></i> Update
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endwhile; ?>
<?php endif; ?>


<!-- ===================== ADD SUBCATEGORY MODAL ===================== -->
<div class="modal fade" id="addSubcategoryModal" tabindex="-1" aria-labelledby="addSubcategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-premium" style="max-width:480px;">
    <div class="modal-content">
      <form action="categories.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="add_subcategory" value="1">
        <div class="modal-header sub-header d-flex justify-content-between align-items-center">
          <h5 class="modal-title" id="addSubcategoryModalLabel">
            <i class="bi bi-diagram-3-fill"></i> Add Subcategory
          </h5>
          <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Subcategory Name</label>
            <input type="text" class="form-control" name="subcategory_name"
                   placeholder="e.g. Laptops" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Parent Category</label>
            <select class="form-select" name="category_id" required>
              <option value="" disabled selected>— Select Category —</option>
              <?php
              $catList = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
              while ($c = mysqli_fetch_assoc($catList)) {
                  echo "<option value='{$c['category_id']}'>" . htmlspecialchars($c['category_name']) . "</option>";
              }
              ?>
            </select>
          </div>
          <div class="mb-1">
            <label class="form-label">Subcategory Image <span style="color:#9ca3af;font-weight:400;text-transform:none;font-size:.8rem;">(optional, max 2 MB)</span></label>
            <input type="file" class="form-control" name="image_path" accept="image/*">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">
            <i class="bi bi-x me-1"></i> Cancel
          </button>
          <button type="submit" class="btn-modal-submit">
            <i class="bi bi-plus-lg me-1"></i> Add Subcategory
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== EDIT SUBCATEGORY MODALS ===================== -->
<?php if (isset($subcategories) && mysqli_num_rows($subcategories) > 0): ?>
  <?php mysqli_data_seek($subcategories, 0); ?>
  <?php while ($sub = mysqli_fetch_assoc($subcategories)): ?>
    <div class="modal fade" id="editSubcategoryModal<?= $sub['subcategory_id'] ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-premium" style="max-width:480px;">
        <div class="modal-content">
          <form action="categories.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="edit_subcategory" value="1">
            <input type="hidden" name="id" value="<?= $sub['subcategory_id'] ?>">
            <div class="modal-header sub-header d-flex justify-content-between align-items-center">
              <h5 class="modal-title">
                <i class="bi bi-pencil-fill"></i> Edit Subcategory
              </h5>
              <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Subcategory Name</label>
                <input type="text" class="form-control" name="subcategory_name"
                       value="<?= htmlspecialchars($sub['subcategory_name']) ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Parent Category</label>
                <select class="form-select" name="category_id" required>
                  <?php
                  $catList = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
                  while ($c = mysqli_fetch_assoc($catList)) {
                      $selected = ($c['category_id'] == $sub['category_id']) ? "selected" : "";
                      echo "<option value='{$c['category_id']}' $selected>" . htmlspecialchars($c['category_name']) . "</option>";
                  }
                  ?>
                </select>
              </div>
              <div class="mb-1">
                <label class="form-label">Replace Image <span style="color:#9ca3af;font-weight:400;text-transform:none;font-size:.8rem;">(leave blank to keep current)</span></label>
                <input type="file" class="form-control" name="image_path" accept="image/*">
                <?php if (!empty($sub['image_path'])): ?>
                  <div class="d-flex align-items-center gap-3 mt-3 p-2 rounded" style="background:#f8fafc; border: 1px solid #e2e8f0;">
                    <img src="<?= htmlspecialchars($sub['image_path']) ?>" class="current-image mt-0" alt="Current image">
                    <div class="flex-grow-1">
                        <div style="font-size:.8rem; color:#475569; font-weight:600;">Current image</div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="remove_image" id="rem_img_<?= $sub['subcategory_id'] ?>" value="1">
                            <label class="form-check-label" for="rem_img_<?= $sub['subcategory_id'] ?>" style="font-size:.78rem; color:#ef4444; font-weight:500; cursor:pointer;">
                                Remove this photo
                            </label>
                        </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">
                <i class="bi bi-x me-1"></i> Cancel
              </button>
              <button type="submit" class="btn-modal-submit">
                <i class="bi bi-check-lg me-1"></i> Update
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endwhile; ?>
<?php endif; ?>
