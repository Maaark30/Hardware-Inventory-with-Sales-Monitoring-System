<?php
// ==========================================================
// PRODUCT MODALS: Add New Item & Edit Existing Item
// ==========================================================
// Requires $categories_query, $subcategories_query, and $result from products.php
// ==========================================================

// ----------------------------------------------------------
// 1. Prepare Subcategory Data for JavaScript Dynamic Dropdowns
// ----------------------------------------------------------
$all_subcategories = [];

if (isset($subcategories_query)) {
    mysqli_data_seek($subcategories_query, 0);
    while ($subcat = mysqli_fetch_assoc($subcategories_query)) {
        $all_subcategories[$subcat['category_id']][] = [
            'id' => $subcat['subcategory_id'],
            'name' => $subcat['subcategory_name']
        ];
    }
    mysqli_data_seek($subcategories_query, 0);
}
$subcategories_json = json_encode($all_subcategories);

// ----------------------------------------------------------
// 2. Prepare Category Data for SKU Generation (JavaScript use)
// ----------------------------------------------------------
$all_categories = [];

if (isset($categories_query)) {
    mysqli_data_seek($categories_query, 0);
    while ($cat = mysqli_fetch_assoc($categories_query)) {
        $all_categories[$cat['category_id']] = $cat['category_name'];
    }
    mysqli_data_seek($categories_query, 0);
}
$categories_json = json_encode($all_categories);
?>

<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="products.php" method="POST" enctype="multipart/form-data"
                 onsubmit="return validateNewProduct();">
                <input type="hidden" name="add_product" value="1">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-box-seam me-2"></i> Add New Item
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control sku-input" id="product_name_new"
                                   name="product_name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select sku-input" id="category_id_new" name="category_id" required>
                                <option value="" disabled selected>-- Select Category --</option>
                                <?php mysqli_data_seek($categories_query, 0); ?>
                                <?php while ($cat = mysqli_fetch_assoc($categories_query)): ?>
                                    <option value="<?= $cat['category_id'] ?>">
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Brand</label>
                            <input type="text" class="form-control sku-input" id="brand_new"
                                   name="brand" placeholder="e.g., Boysen">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Subcategory</label>
                            <select class="form-select sku-input" id="subcategory_id_new" name="subcategory_id">
                                <option value="" selected>-- Select Subcategory --</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Variation</label>
                            <input type="text" class="form-control sku-input" id="variation_new"
                                   name="variation" placeholder="e.g., Cement 40kg, Roofshield">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" id="unit" name="unit"
                                   placeholder="e.g., Pcs, Kg, Box" required>
                        </div>
                        


                        <div class="col-md-6">
                            <label class="form-label">Selling Price (₱)</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" name="selling_price"
                                       value="0.00" min="0" step="0.01" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Threshold</label>
                            <input type="number" class="form-control" name="reorder_level" value="0" min="0">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">SKU (Auto-Generated)</label>
                            <input type="text" class="form-control bg-light fw-bold" id="sku_display_new"
                                   value="SKU-AUTO-GENERATED" readonly>
                            <input type="hidden" id="sku_submit_new" name="sku" value="">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
if (isset($result) && mysqli_num_rows($result) > 0):
    mysqli_data_seek($result, 0);
    while ($row = mysqli_fetch_assoc($result)): 
?>
<div class="modal fade" id="editProductModal<?= $row['product_id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="products.php" method="POST" enctype="multipart/form-data"
                 onsubmit="return showConfirm({title:'Update Product?', message:'Are you sure you want to update <?= addslashes($row['product_name']) ?>?', okText:'Update', callback:()=>this.submit()});">
                <input type="hidden" name="edit_product" value="1">
                <input type="hidden" name="product_id" value="<?= $row['product_id'] ?>">
                <input type="hidden" name="stock" value="<?= htmlspecialchars($row['stock']) ?>"> 
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-2"></i> Edit Product
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-control" name="product_name"
                                   value="<?= htmlspecialchars($row['product_name']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select category-select"
                                     name="category_id"
                                     id="category_id_<?= $row['product_id'] ?>"
                                     data-product-id="<?= $row['product_id'] ?>"
                                     data-initial-subcategory-id="<?= htmlspecialchars($row['subcategory_id'] ?? '') ?>"
                                     required>
                                <?php mysqli_data_seek($categories_query, 0); ?>
                                <?php while ($cat = mysqli_fetch_assoc($categories_query)): ?>
                                    <option value="<?= $cat['category_id'] ?>"
                                        <?= ($cat['category_id'] == $row['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Brand</label>
                            <input type="text" class="form-control" name="brand"
                                   value="<?= htmlspecialchars($row['brand'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Subcategory</label>
                            <select class="form-select subcategory-select"
                                     id="subcategory_id_<?= $row['product_id'] ?>"
                                     name="subcategory_id">
                                <option value="">-- Select Subcategory --</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Variation</label>
                            <input type="text" class="form-control" name="variation"
                                   value="<?= htmlspecialchars($row['variation'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" name="unit"
                                   value="<?= htmlspecialchars($row['unit'] ?? '') ?>" required>
                        </div>



                        <div class="col-md-6">
                            <label class="form-label">Selling Price (₱)</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" name="selling_price"
                                       value="<?= htmlspecialchars($row['selling_price'] ?? 0.00) ?>"
                                       min="0" step="0.01" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Current Stock</label>
                            <input type="text" class="form-control"
                                   value="<?= formatQty($row['stock']) ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Threshold</label>
                            <input type="number" class="form-control" name="reorder_level" 
                                   value="<?= htmlspecialchars($row['reorder_level'] ?? 0) ?>" min="0">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">SKU (Saved)</label>
                            <?php 
                                $sku_display = empty($row['sku']) ? 'SKU-SAVED (Not Set)' : $row['sku'];
                            ?>
                            <input type="text" class="form-control bg-light fw-bold"
                                   value="<?= htmlspecialchars($sku_display) ?>" readonly>
                            <input type="hidden" name="sku" value="<?= htmlspecialchars($row['sku'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                           <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars(trim($row['description'] ?? '')) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-danger me-auto"
                            onclick="showCustomAlert('Delete <?= addslashes($row['product_name']) ?> permanently?', () => window.location.href='products.php?delete_id=<?= $row['product_id'] ?>')">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php 
    endwhile;
    mysqli_data_seek($result, 0);
endif;
?>


<div class="modal fade" id="searchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-search me-2"></i> Search Products</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="text" id="modalSearchInput" class="form-control mb-3" placeholder="Search product, category, or SKU...">

                <div id="modalSearchResults" class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Subcategory</th>
                                <th>Price</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody id="searchResultsBody">
                            <tr><td colspan="6" class="text-center text-muted">Start typing to search...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ---------- Utility: Debounce ----------
const debounce = (func, delay) => {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), delay);
    };
};

// ---------- Dependent Dropdowns ----------
const ALL_SUBCATEGORIES = <?= $subcategories_json ?>;
function updateSubcategoryDropdown(categoryId, subcatId, selectedSubcatId = null) {
    const categorySelect = document.getElementById(categoryId);
    const subcategorySelect = document.getElementById(subcatId);
    if (!categorySelect || !subcategorySelect) return;

    subcategorySelect.innerHTML = '<option value="">-- Select Subcategory --</option>';
    const categoryValue = categorySelect.value;

    if (categoryValue && ALL_SUBCATEGORIES[categoryValue]) {
        ALL_SUBCATEGORIES[categoryValue].forEach(subcat => {
            const option = document.createElement('option');
            option.value = subcat.id;
            option.textContent = subcat.name;
            if (selectedSubcatId && subcat.id == selectedSubcatId) option.selected = true;
            subcategorySelect.appendChild(option);
        });
    }
}

// Add Modal Dependency
document.getElementById('category_id_new')?.addEventListener('change', () => {
    updateSubcategoryDropdown('category_id_new', 'subcategory_id_new');
    generateSKU();
});
document.getElementById('subcategory_id_new')?.addEventListener('change', generateSKU);

// Edit Modals Dependency
document.querySelectorAll('.category-select').forEach(select => {
    const productId = select.dataset.productId;
    const modal = document.getElementById('editProductModal' + productId);

    select.addEventListener('change', () => updateSubcategoryDropdown(select.id, 'subcategory_id_' + productId));
    modal?.addEventListener('shown.bs.modal', () => {
        const initialSubcat = select.dataset.initialSubcategoryId;
        updateSubcategoryDropdown(select.id, 'subcategory_id_' + productId, initialSubcat);
    });
});


// ---------- SKU Auto-Generation ----------
const ALL_CATEGORIES_JS = <?= $categories_json ?>;
function cleanAndAbbreviate(text, maxLength = 4) {
    if (!text) return '';
    let cleaned = text.replace(/[^a-zA-Z0-9\s-]/g, '').toUpperCase().trim();
    cleaned = cleaned.replace(/[\s-]+/g, '-');
    const words = cleaned.split('-');
    let abbr = words.map(p => p.charAt(0)).join('');
    if (abbr.length < maxLength) abbr = cleaned.substring(0, maxLength);
    return abbr.substring(0, maxLength);
}
function generateSKU() {
    const name = document.getElementById('product_name_new')?.value || '';
    const brand = document.getElementById('brand_new')?.value || '';
    const variation = document.getElementById('variation_new')?.value || '';
    const category = document.getElementById('category_id_new')?.value || '';
    const categoryName = ALL_CATEGORIES_JS[category] || '';

    const base = cleanAndAbbreviate(categoryName || name, 5);
    const brandPart = cleanAndAbbreviate(brand, 3);
    const varPart = cleanAndAbbreviate(variation, 3);
    const code = [base, brandPart, varPart].filter(Boolean).join('-');
    const sku = (code ? code : 'SKU-PENDING') + '-001';

    document.getElementById('sku_display_new').value = sku.toUpperCase();
    document.getElementById('sku_submit_new').value = sku.toUpperCase();
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sku-input').forEach(el => el.addEventListener('input', generateSKU));
    document.getElementById('addProductModal')?.addEventListener('shown.bs.modal', generateSKU);
});

// ---------- Live Search Modal ----------
const input = document.getElementById('modalSearchInput');
const tbody = document.getElementById('searchResultsBody');

const performSearch = (query) => {
    if (!query) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Start typing to search...</td></tr>';
        return;
    }
    fetch('search_products.php?q=' + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No results found.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            data.forEach(p => {
                const row = tbody.insertRow();
                row.insertCell().textContent = p.product_name;
                row.insertCell().textContent = p.category_name;
                row.insertCell().textContent = p.subcategory_name;
                row.insertCell().innerHTML = `₱${parseFloat(p.price).toFixed(2)}`;
                row.insertCell().innerHTML = `<span class="badge ${p.stock < 10 ? 'bg-danger' : 'bg-success'}">${p.stock}</span>`;
            });
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading search results.</td></tr>';
        });
};
input?.addEventListener('input', debounce(() => performSearch(input.value.trim()), 300));

// ---------- Product Details Modal ----------
document.addEventListener('DOMContentLoaded', function() {
    const productDetailsModal = document.getElementById('productDetailsModal');
    const productDetailsContent = document.getElementById('productDetailsContent');
    const productDetailsModalLabel = document.getElementById('productDetailsModalLabel');

    console.log('Modal elements found:', {
        modal: productDetailsModal,
        content: productDetailsContent,
        label: productDetailsModalLabel
    });

    if (productDetailsModal && productDetailsContent && productDetailsModalLabel) {
        productDetailsModal.addEventListener('show.bs.modal', function (event) {
            console.log('Product details modal opening...');
            const button = event.relatedTarget;
            console.log('Button:', button);
            const productId = button.getAttribute('data-product-id');
            const productName = button.getAttribute('data-product-name');
            const view = button.getAttribute('data-view') || 'details';
            console.log('Product ID:', productId, 'Product Name:', productName, 'View:', view);

            // Set modal title based on view
            let titleIcon = 'bi-eye';
            let titleText = 'Details';
            switch(view) {
                case 'stockin':
                    titleIcon = 'bi-box-arrow-in-down';
                    titleText = 'Stock In History';
                    break;
                case 'stockout':
                    titleIcon = 'bi-box-arrow-up';
                    titleText = 'Stock Out History';
                    break;
                case 'expired':
                    titleIcon = 'bi-exclamation-triangle';
                    titleText = 'Expired Batches';
                    break;
                default:
                    titleIcon = 'bi-eye';
                    titleText = 'Details & Batch History';
            }
            productDetailsModalLabel.innerHTML = `<i class="bi ${titleIcon} me-2"></i> ${productName} - ${titleText}`;

            // Show a test message first
            productDetailsContent.innerHTML = '<div class="alert alert-info">Loading ' + titleText.toLowerCase() + ' for ID: ' + productId + '...</div>';

            // Remove loading spinner - fetch data directly
            fetch(`fetch_product_details.php?product_id=${productId}&view=${view}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response ok:', response.ok);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(html => {
                    console.log('Received HTML length:', html.length);
                    productDetailsContent.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading product details:', error);
                    productDetailsContent.innerHTML = `
                        <div class="alert alert-danger mb-0">
                            <strong>Error loading product details:</strong><br>
                            ${error.message}<br>
                            <small>Please check the browser console for more details.</small>
                        </div>
                    `;
                });
        });

        productDetailsModal.addEventListener('hidden.bs.modal', function () {
            // Clear content when modal is closed
            productDetailsContent.innerHTML = '';
        });
    } else {
        console.error('Product details modal elements not found!');
    }
});
function validateNewProduct() {
    const form = document.querySelector('#addProductModal form');
    if (form.getAttribute('data-verified') === 'true') {
        form.removeAttribute('data-verified');
        return true; 
    }

    const name = document.getElementById('product_name_new').value.trim();
    const brand = document.getElementById('brand_new').value.trim();
    const variation = document.getElementById('variation_new').value.trim();

    if (name && brand && variation) {
        const url = `check_duplicate.php?name=${encodeURIComponent(name)}&brand=${encodeURIComponent(brand)}&variation=${encodeURIComponent(variation)}`;
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.exists) {
                    showAlert({
                        title: 'Duplicate Detected',
                        message: `Product "${name}" (${brand} - ${variation}) already exists in the system. Duplicates are not allowed.`,
                        icon: 'bi-exclamation-octagon-fill',
                        color: 'var(--red)'
                    });
                } else {
                    form.setAttribute('data-verified', 'true');
                    form.submit();
                }
            })
            .catch(err => {
                console.error('Check failed:', err);
                form.setAttribute('data-verified', 'true');
                form.submit();
            });
            
        return false;
    }
    
    return true;
}

// Fixed Category Dependency in product_modals.php (ensure it works for all modals)
document.addEventListener('DOMContentLoaded', () => {
    const mainCatSelect = document.getElementById('category_id_new');
    if (mainCatSelect) {
        mainCatSelect.addEventListener('change', () => {
            updateSubcategoryDropdown('category_id_new', 'subcategory_id_new');
            if (typeof generateSKU === 'function') generateSKU();
        });
    }
});
// -----------------------------------------------------------------------

</script>

<!-- Product Details Modal -->
<div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="productDetailsModalLabel">
                    <i class="bi bi-eye me-2"></i> Product Details & Batch History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body" id="productDetailsContent">
                <!-- Content will be loaded here without loading spinner -->
            </div>
        </div>
    </div>
</div>