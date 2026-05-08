<div class="modal fade" id="stockInModal" tabindex="-1" aria-labelledby="stockInModalLabel" aria-hidden="true">
<div class="modal-dialog modal-xl">
<div class="modal-content">

<form action="staff_products.php" method="POST" id="stockInForm">

<input type="hidden" name="stock_in_submit" value="1">
<input type="hidden" name="stock_in_data_json" id="stock_in_data_json">

<div class="modal-header bg-primary text-white">
<h5 class="modal-title" id="stockInModalLabel">
<i class="bi bi-box-arrow-in-down me-2"></i> Item Stock-In
</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<div class="row g-2 mb-3 align-items-center position-relative">

<div class="col-md-9 position-relative">
<input type="text" class="form-control" id="stockInSearchInput"
placeholder="Search SKU or search item name..." autofocus autocomplete="off">

<ul id="stockInSearchResults"
class="list-group position-absolute w-100 shadow-sm"
style="z-index:1056;max-height:220px;overflow-y:auto;display:none;">
</ul>
</div>

<div class="col-md-3">
<button type="button" class="btn btn-primary w-100" id="stockInManualAddBtn">
<i class="bi bi-search me-1"></i> Search
</button>
</div>

</div>

<p class="text-muted small">
Type and hit 'Search' or simply scan a SKU. Quantity starts at 1.
</p>

<h6 class="mt-4">ITEMS TO STOCK IN:</h6>

<div class="table-responsive" style="max-height:350px;overflow-y:auto;">
<table class="table table-striped align-middle">

<thead>
<tr>
<th style="width:45%">Product Details</th>
<th style="width:15%" class="text-muted small">Supplier Price (₱)</th>
<th style="width:15%" class="text-muted small">Expiry (Optional)</th>
<th style="width:15%" class="text-center">Quantity</th>
<th style="width:10%">Remove</th>
</tr>
</thead>

<tbody id="stockInItemsBody">
<tr id="emptyStockInMessage">
<td colspan="4" class="text-center text-muted py-4">
Scan a product or use the search bar above to add items.
</td>
</tr>
</tbody>

</table>
</div>

<h6 class="mt-3 pt-3 border-top">Supplier Details (for Record Keeping)</h6>

<div class="row g-3">

<div class="col-md-4">
<label for="supplierSelect" class="form-label mb-1 small">
Select Supplier
</label>

<select id="supplierSelect" name="supplier_id"
class="form-select form-select-sm" required>

<option value="">-- Choose Supplier --</option>

<?php
if (isset($conn)) {

$suppliers = $conn->query("
SELECT supplier_id,supplier_name,contact_no
FROM suppliers
ORDER BY supplier_name ASC
");

while($s = $suppliers->fetch_assoc()):
?>

<option value="<?= $s['supplier_id'] ?>"
data-contact-no="<?= htmlspecialchars($s['contact_no'] ?? '') ?>">

<?= htmlspecialchars($s['supplier_name']) ?>

</option>

<?php endwhile; } ?>

</select>
</div>

<div class="col-md-4">
<label for="contactNo" class="form-label mb-1 small">
Contact No.
</label>

<input type="text"
class="form-control form-control-sm"
id="contactNo"
name="contact_no_display"
readonly
placeholder="Auto-filled">
</div>

<div class="col-md-4">
<label for="referenceNo" class="form-label mb-1 small">
Invoice/Reference No.
</label>

<input type="text"
class="form-control form-control-sm"
id="referenceNo"
name="reference_no"
placeholder="e.g., PO-12345">
</div>

</div>
</div>

<div class="modal-footer d-flex justify-content-between">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <div class="d-flex">
        <button type="button" class="btn btn-danger me-2" id="clearStockInItemsBtn">
            <i class="bi bi-trash me-1"></i> Clear Items (<span id="stockInTotalCount">0</span>)
        </button>
        <button type="submit" class="btn btn-success" id="confirmStockInBtn" disabled>
            <i class="bi bi-check-circle me-1"></i> Confirm Stock In
        </button>
    </div>
</div>

</form>
</div>
</div>
</div>

<script src="stock_in_script.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function(){

const supplierSelect = document.getElementById('supplierSelect');
const contactInput = document.getElementById('contactNo');

if(supplierSelect){

supplierSelect.addEventListener('change',function(){

const selectedOption = this.options[this.selectedIndex];
const contactNo = selectedOption.dataset.contactNo || '';

contactInput.value = contactNo;

});

}

});
</script>