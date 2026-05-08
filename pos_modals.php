<div class="modal fade" id="addSaleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form action="handle_sale.php" method="POST" id="saleForm">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="fa fa-cash-register me-2"></i> Point of Sale</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body p-4">
          <div class="row">
            <!-- Left side -->
            <div class="col-lg-6 mb-4 mb-lg-0">
              <h6 class="text-kjb-blue mb-3">Item Entry (SKU or Name)</h6>

              <!-- SKU Search -->
              <div class="input-group mb-3 position-relative">
                <span class="input-group-text"><i class="fa fa-barcode text-kjb-blue"></i></span>
                <input type="text" class="form-control form-control-lg" id="pos_scan_input"
                       placeholder="Scan or Enter SKU / Product Name" autofocus>
                <ul id="searchResults" class="list-group position-absolute w-100"
                    style="z-index: 1000; max-height: 200px; overflow-y: auto; top: 100%; display: none;"></ul>
              </div>

              <div id="itemStatus" class="alert alert-danger p-2 mb-3" style="display:none;"></div>

              <!-- Numeric Pad -->
              <div id="numericPad" class="mb-3">
                <div class="d-grid gap-2 mb-2" style="grid-template-columns: repeat(3, 1fr);">
                  <button type="button" class="btn btn-lg btn-light border border-secondary pos-key-num" data-val="7">7</button>
                  <button type="button" class="btn btn-lg btn-light border border-secondary pos-key-num" data-val="8">8</button>
                  <button type="button" class="btn btn-lg btn-light border border-secondary pos-key-num" data-val="9">9</button>
                  <button type="button" class="btn btn-lg btn-light border border-secondary pos-key-num" data-val="4">4</button>
                  <button type="button" class="btn btn-lg btn-light border border-secondary pos-key-num" data-val="5">5</button>
                  <button type="button" class="btn btn-lg btn-light border border-secondary pos-key-num" data-val="6">6</button>
                  <button type="button" class="btn btn-lg btn-light border border-secondary pos-key-num" data-val="1">1</button>
                  <button type="button" class="btn btn-lg btn-light border border-secondary pos-key-num" data-val="2">2</button>
                  <button type="button" class="btn btn-lg btn-light border border-secondary pos-key-num" data-val="3">3</button>
                </div>
                <div class="d-grid gap-2" style="grid-template-columns: 2fr 1fr;">
                  <button type="button" class="btn btn-lg btn-light border border-secondary pos-key-num" data-val="0">0</button>
                  <button type="button" class="btn btn-lg btn-light border border-secondary pos-key-num" data-val=".">.</button>
                </div>
              </div>

              <div class="d-grid gap-2" style="grid-template-columns: repeat(2, 1fr);">
                <button type="button" class="btn btn-lg btn-warning" id="pos-key-enter">
                  <i class="fa fa-plus-circle me-1"></i> Add Item
                </button>
                <button type="button" class="btn btn-lg btn-danger" id="pos-key-clear">
                  <i class="fa fa-eraser me-1"></i> Clear
                </button>
              </div>
            </div>

            <!-- Right side -->
            <div class="col-lg-6">
              <h6 class="text-secondary mb-3">Transaction Details</h6>

              <div class="table-responsive mb-3" style="max-height: 250px; overflow-y: auto;">
                <table class="table table-sm table-striped align-middle" id="posCartTable">
                  <thead class="table-dark sticky-top">
                    <tr>
                      <th>Item Description</th>
                      <th style="width: 100px;">Qty</th>
                      <th class="text-end">Total</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="p-3 border border-kjb-blue rounded mb-3 bg-light">
                <div class="d-flex justify-content-between mb-2">
                  <span>Subtotal:</span>
                  <span id="posSubtotal">₱0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span></span>
                  <span id="posTax">₱0.00</span>
                </div>
                <div class="d-flex justify-content-between fs-5 fw-bold text-success border-top pt-2">
                  <span>TOTAL:</span>
                  <span id="posGrandTotal">₱0.00</span>
                  <input type="hidden" name="total_amount" id="inputGrandTotal">
                </div>
                <hr class="my-2">

                <div id="paymentAlertContainer"></div>
                <div class="mb-2">
                  <label for="posCashGiven" class="form-label fw-bold small text-dark">Cash Given (₱)</label>
                  <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                         id="posCashGiven" name="cash_given" placeholder="0.00" required>
                </div>

                <div class="d-flex justify-content-between fs-5 fw-bold">
                  <span>CHANGE:</span>
                  <span id="posChange" class="text-kjb-blue">₱0.00</span>
                </div>
              </div>

              <div class="d-grid gap-2">
                <button type="button" id="completeSaleButton" class="btn btn-warning btn-lg">
                  <i class="fa fa-check-circle me-2"></i> Complete Sale
                </button>
                <button type="button" class="btn btn-dark btn-lg" data-bs-dismiss="modal">
                  <i class="fa fa-undo me-2"></i> Cancel / Exit
                </button>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Staff PIN Confirmation -->
<div class="modal fade" id="confirmPinModal" tabindex="-1" aria-labelledby="confirmPinLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header bg-primary text-white rounded-top-4">
        <h5 class="modal-title"><i class="fa fa-lock me-2"></i> Enter Staff PIN</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <input type="password" id="confirmPinInput" class="form-control form-control-lg text-center mx-auto border-kjb-blue"
               maxlength="4" style="max-width: 150px; font-size: 1.5rem;" placeholder="••••">
        <div id="pinErrorMsg" class="text-danger mt-2" style="display:none;">Invalid PIN. Try again.</div>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success px-4" id="confirmPinBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>
