<?php
include 'project.php';
session_start();

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['username'];

// Fetch products for Add Product modal (stock > 0)
$products = [];
$sql = "SELECT product_id, product_name, selling_price, stock, unit, brand, variation
        FROM products
        WHERE stock > 0
        ORDER BY product_name ASC";
$res = $conn->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
} else {
    $_SESSION['error'] = "Error fetching products: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="icon" type="image/x-icon" href="favicon.ico">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Add Sale — K&J B Hardware</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/admin1.css">

  <style>
    :root {
            --bg:           #eef1f8;
            --surface:      #ffffff;
            --surface-2:    #f7f9fc;
            --border:       #e2e8f0;
            --border-light: #edf2f7;
            --ink:          #0f172a;
            --ink-2:        #334155;
            --muted:        #64748b;
            --faint:        #94a3b8;
            --blue:         #2563eb;
            --blue-dk:      #1d4ed8;
            --blue-lt:      #eff6ff;
            --blue-mid:     #dbeafe;
            --green:        #059669;
            --green-lt:     #ecfdf5;
            --amber:        #d97706;
            --amber-lt:     #fffbeb;
            --red:          #dc2626;
            --red-lt:       #fef2f2;
            --violet:       #7c3aed;
            --violet-lt:    #f5f3ff;
            --r:            12px;
            --r-sm:         8px;
            --r-lg:         18px;
            --card-radius:  16px;
            --card-shadow:  0 2px 16px rgba(0,0,0,0.07);
            --sh-xs:        0 1px 3px rgba(0,0,0,.05);
            --sh-sm:        0 2px 8px rgba(0,0,0,.06);
            --sh:           0 4px 20px rgba(0,0,0,.08);
            --sh-lg:        0 8px 32px rgba(0,0,0,.1);
            --font:         'Plus Jakarta Sans', sans-serif;
            --mono:         'JetBrains Mono', monospace;
        }
        *,*::before,*::after{box-sizing:border-box;}
        body { font-family:var(--font); background:var(--bg); color:var(--ink); font-size:14px; }
        .content { background:var(--bg); min-height:100vh; }
        .main-wrap { padding:28px 28px 64px; }
        .dropdown-toggle::after { display:none; }


    /* ── Dashboard header ── */
    .dash-header {
      background: linear-gradient(135deg, #0f2557 0%, #1a58ec 60%, #2563eb 100%);
      border-radius: 0 0 28px 28px;
      padding: 28px 32px 32px;
      margin-bottom: 32px;
      position: relative;
      overflow: hidden;
    }
    .dash-header::before {
      content: '';
      position: absolute;
      width: 320px; height: 320px;
      background: rgba(255,255,255,0.05);
      border-radius: 50%;
      top: -100px; right: -60px;
      pointer-events: none;
    }
    .dash-header::after {
      content: '';
      position: absolute;
      width: 180px; height: 180px;
      background: rgba(255,255,255,0.04);
      border-radius: 50%;
      bottom: -60px; left: 30%;
      pointer-events: none;
    }
    .dash-header h1 { font-size: 1.6rem; font-weight: 800; color: #fff; margin: 0; }
    .dash-header .sub { font-size: 13px; color: rgba(255,255,255,0.65); margin-top: 4px; }
    .dash-header .date-pill {
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 20px;
      padding: 6px 14px;
      font-size: 12.5px;
      color: rgba(255,255,255,0.9);
      font-weight: 500;
    }

    /* ── POS layout ── */
    .pos-container { width: 100%; margin: auto; padding: 0 24px 24px; }

    .cart-summary {
      background: #fff;
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      display: flex;
      flex-direction: column;
      padding: 1.5rem;
      min-height: calc(100vh - 180px);
      border: 1px solid rgba(0,0,0,0.04);
    }

    #cartItemsContainer {
      flex-grow: 1;
      overflow-y: auto;
      margin-bottom: 1rem;
      padding-right: 6px;
    }

    .grand-total-box {
      border: 3px solid var(--blue);
      border-radius: 10px;
      padding: 12px 16px;
      background: rgba(26,88,236,0.06);
    }

    .discount-input {
      border: 1px solid #ffc107;
      background: #fff3cd;
    }

    #cartItemsContainer table {
      width: 100% !important;
      table-layout: auto;
    }

    #cartItemsContainer table th:first-child,
    #cartItemsContainer table td:first-child {
      width: auto;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* ── Modals ── */
    .modal-content {
      border: none;
      border-radius: 24px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
      overflow: hidden;
    }
    .modal-header {
      padding: 24px 32px;
      border-bottom: 1px solid #f1f5f9;
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      color: #fff;
    }
    .modal-header .modal-title { font-size: 1.1rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; }
    .modal-header .btn-close { filter: invert(1) grayscale(1); opacity: 0.8; }
    .modal-body { padding: 24px 32px; background: #fff; }
    .modal-footer { padding: 18px 32px; border-top: 1px solid #f1f5f9; background: #f8fafc; }

    @media print {
      .no-print { display: none !important; }
      body { background: #fff; }
    }
  </style>
</head>

<body>
<div class="d-flex">

  <!-- SIDEBAR -->
  <div class="sidebar flex-column p-0" id="sidebar">
    <div class="sidebar-logo text-center">
      <img src="images/logo.png" alt="Inventory Logo">
      <h5 class="mt-2 text-white">Staff Panel</h5>
    </div>
    <hr class="text-white">
    <ul class="nav flex-column">
      <li class="sidebar-title">Main</li>
      <li class="nav-item mb-2"><a class="nav-link" href="staff_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
      <li class="nav-item mb-2"><a class="nav-link active" href="staff_add_sale.php"><i class="bi bi-cart-plus me-2"></i> Add Sale</a></li>

      <li class="sidebar-title">Operations</li>
      <li class="nav-item mb-2"><a class="nav-link" href="staff_products.php"><i class="bi bi-box-seam me-2"></i> Product Management</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="purchased_history.php"><i class="bi bi-cart-check me-2"></i> Sales</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="staff_returns.php"><i class="bi bi-arrow-return-left me-2"></i> Returns</a></li>
      <li class="nav-item mb-2"><a class="nav-link" href="staff_product_history.php"><i class="bi bi-clock-history me-2"></i> Product History</a></li>

      <li class="sidebar-title">Others</li>
      <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
    </ul>
  </div>

  <!-- CONTENT -->
  <div class="content flex-grow-1">

    <!-- Page Header -->
    <div class="dash-header">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h1><i class="bi bi-cart-plus me-2"></i>Add New Sale</h1>
          <div class="sub">Create a new invoice and record a sale transaction</div>
        </div>
        <div class="date-pill">
          <i class="bi bi-calendar3 me-2"></i><?= date('l, F j, Y') ?>
        </div>
      </div>
    </div>

    <div class="container-fluid pos-container">

      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
          <?= htmlspecialchars($_SESSION['error']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php unset($_SESSION['error']); endif; ?>

      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
          <?= htmlspecialchars($_SESSION['success']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php unset($_SESSION['success']); endif; ?>

      <div class="row g-4">
        <div class="col-12">
          <div class="cart-summary">

            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
              <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-cart-fill me-2"></i> Invoice Cart
              </h5>

              <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger no-print fw-bold d-flex align-items-center gap-1" id="clearInvoiceBtn">
                  <i class="bi bi-trash"></i> <span class="d-none d-sm-inline">Clear Invoice</span>
                </button>
                <button type="button" class="btn btn-sm btn-primary no-print fw-bold d-flex align-items-center gap-1"
                        data-bs-toggle="modal" data-bs-target="#addProductModal">
                  <i class="bi bi-plus-circle"></i> Add Sale Invoice 
                </button>
              </div>
            </div>

            <div id="cartItemsContainer">
              <table class="table table-sm table-borderless">
                <thead>
                  <tr>
                    <th class="text-muted border-bottom" style="width: auto;">Item</th>
                    <th class="text-center text-muted border-bottom" style="width: 90px;">Quantity</th>
                    <th class="text-end text-muted border-bottom" style="width: 120px;">Total</th>
                    <th class="text-center text-muted border-bottom" style="width: 40px;"></th>
                  </tr>
                </thead>
                <tbody id="cartTableBody">
                  <tr id="emptyCartRow">
                    <td colspan="4" class="text-center text-muted py-5">
                      <i class="bi bi-cart-plus-fill fs-3 d-block mb-2 text-primary"></i>
                      Click <b>Add Product</b> to start.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="pt-3 border-top">

              <div class="accordion mb-3" id="customerInfoAccordion">
                <div class="accordion-item border-0">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed fw-bold small" type="button"
                            data-bs-toggle="collapse" data-bs-target="#collapseCustomerInfo">
                      <i class="bi bi-person-fill me-2"></i> Customer Details (Click to Edit)
                    </button>
                  </h2>
                  <div id="collapseCustomerInfo" class="accordion-collapse collapse" data-bs-parent="#customerInfoAccordion">
                    <div class="accordion-body p-3">
                      <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">
                          Customer Name <span style="color:var(--muted); font-weight:400; font-size:.7rem;">(optional)</span>
                        </label>
                        <input type="text" class="form-control form-control-sm" id="customerNameInput"
                               placeholder="Walk-in Customer">
                      </div>
                      <div class="row g-2">
                        <div class="col-md-6">
                          <label class="form-label small fw-bold text-muted">Address (Optional)</label>
                          <input type="text" class="form-control form-control-sm" id="customerAddressInput" placeholder="Address...">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label small fw-bold text-muted">Contact No. (Optional)</label>
                          <input type="text" class="form-control form-control-sm" id="customerContactInput" placeholder="Contact #...">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-between fw-bold fs-5 mb-2">
                <span class="text-muted">SUBTOTAL:</span>
                <span id="cartSubtotal" class="text-dark">₱0.00</span>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small fw-bold">DISCOUNT (₱)</label>
                <input type="number" step="0.01" min="0"
                       class="form-control form-control-lg text-end discount-input"
                       id="cashDiscountInput" placeholder="0.00">
              </div>

              <div class="d-flex justify-content-between align-items-center grand-total-box mb-3">
                <span class="text-dark fw-bold fs-5">GRAND TOTAL:</span>
                <span id="cartGrandTotal" class="text-primary fs-4">₱0.00</span>
              </div>

              <button class="btn btn-primary w-100 py-3 fw-bold fs-5 no-print"
                      id="generateInvoiceBtn"
                      data-bs-toggle="modal"
                      data-bs-target="#invoiceModal"
                      disabled>
                <i class="bi bi-receipt me-2"></i> Generate Invoice
              </button>

              <div class="mt-2 small text-muted">
                Payment is handled physically; this page generates an invoice for printing.
              </div>

            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

</div>

<!-- ADD PRODUCT MODAL -->
<div class="modal fade" id="addProductModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">

        <label class="form-label small fw-bold text-muted">Search Product (Name, Brand, or Variation)</label>
        <input type="text" class="form-control mb-2" id="productSearchModal"
               placeholder="Type product name, brand, or variation...">

        <div class="list-group mb-3" id="productResults" style="max-height: 280px; overflow:auto;"></div>

        <div class="border rounded p-3 bg-light mb-3">
          <div class="fw-bold" id="selectedProductName">No product selected</div>
          <div class="small text-muted" id="selectedProductMeta"></div>
          <div class="small text-muted" id="selectedStockNote"></div>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small fw-bold text-muted">Quantity</label>
            <input type="number" class="form-control" id="productQty" min="0.001" step="any" value="1" disabled>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold text-muted">Amount (₱)</label>
            <input type="number" class="form-control" id="productAmount" min="0.01" step="0.01" placeholder="0.00" disabled>
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button type="button" class="btn btn-primary w-100" id="addToCartBtn" disabled>
              <i class="bi bi-cart-plus me-1"></i>Add to Cart
            </button>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

<!-- INVOICE MODAL -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header no-print">
        <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Invoice Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="invoiceForm" action="process_pos_staff.php" method="POST">
        <div class="modal-body p-4" id="invoicePrintArea">

          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <h4 class="mb-1 fw-bold">INVOICE</h4>
              <div class="small text-muted">Prepared by: <?= htmlspecialchars($current_user) ?></div>
              <div class="small text-muted">Date: <span id="invoiceDate"></span></div>
            </div>
            <div class="text-end">
              <div class="small text-muted">Customer:</div>
              <div class="fw-bold" id="invoiceCustomerName">Walk-in Customer</div>
              <div class="small text-muted" id="invoiceCustomerContact"></div>
              <div class="small text-muted" id="invoiceCustomerAddress"></div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Item</th>
                  <th class="text-center" style="width:80px;">Qty</th>
                  <th class="text-end" style="width:140px;">Unit Price</th>
                  <th class="text-end" style="width:140px;">Line Total</th>
                </tr>
              </thead>
              <tbody id="invoiceTableBody"></tbody>
            </table>
          </div>

          <hr>

          <div class="row">
            <div class="col-6"></div>
            <div class="col-6">
              <div class="d-flex justify-content-between">
                <span class="text-muted fw-bold">Subtotal</span>
                <span id="invoiceSubtotal">₱0.00</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-muted fw-bold">Discount</span>
                <span id="invoiceDiscount">₱0.00</span>
              </div>
              <div class="d-flex justify-content-between fs-5 border-top pt-2 mt-2">
                <span class="fw-bold">Grand Total</span>
                <span class="fw-bold text-primary" id="invoiceGrandTotal">₱0.00</span>
              </div>
            </div>
          </div>

          <div class="mt-3 small text-muted">
            Note: Payment is handled physically. This invoice is for computation and customer reference.
          </div>

          <input type="hidden" name="cart_data" id="invoiceCartDataInput">
          <input type="hidden" name="discount_amount" id="invoiceDiscountAmountInput">
          <input type="hidden" name="customer_name" id="invoiceCustomerNameInput">
          <input type="hidden" name="customer_contact" id="invoiceCustomerContactInput">
          <input type="hidden" name="customer_address" id="invoiceCustomerAddressInput">
          <input type="hidden" name="total_amount" id="invoiceTotalAmountInput">

          <input type="hidden" name="payment_type" value="PHYSICAL_CASH">
          <input type="hidden" name="cash_given" value="0">
          <input type="hidden" name="change_amount_display" value="₱0.00">

        </div>

        <div class="modal-footer d-flex justify-content-between no-print">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" id="printInvoiceBtn">
              <i class="bi bi-printer me-1"></i> Print
            </button>
            <button type="submit" class="btn btn-success fw-bold">
              <i class="bi bi-check-circle me-1"></i> Confirm & Save
            </button>
          </div>
        </div>

      </form>
    </div>
  </div>
</div>

<script>
  const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  let cart = {};
  let grandTotal = 0;
  const STAFF_PERSISTENCE_KEY = 'staff_add_sale_persistence';

  /**
   * Premium modal-based replacement for the browser's confirm()
   */
  function showConfirm({title = 'Are you sure?', message, okText = 'OK', icon = 'bi-exclamation-triangle-fill', callback}) {
      const id = 'cConfirm_' + Date.now();
      const html = `
          <div class="modal fade" id="${id}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
              <div class="modal-dialog modal-dialog-centered modal-sm">
                  <div class="modal-content border-0 shadow-lg text-start" style="border-radius:16px; overflow:hidden;">
                      <div class="modal-body p-4 text-center">
                          <div class="mb-3 d-inline-flex align-items-center justify-content-center" 
                               style="width:54px; height:54px; border-radius:50%; background:var(--red-lt); color:var(--red); font-size:1.5rem;">
                              <i class="bi ${icon}"></i>
                          </div>
                          <h6 class="fw-bold mb-2 text-dark">${title}</h6>
                          <p class="mb-0 text-muted" style="font-size: .8rem; line-height:1.5;">${message}</p>
                      </div>
                      <div class="modal-footer border-0 p-3 pt-0 d-flex gap-2 mt-0">
                          <button type="button" class="btn btn-light fw-bold flex-grow-1" data-bs-dismiss="modal" 
                                  style="border-radius:10px; font-size:.8rem; border:1px solid var(--border);">Cancel</button>
                          <button type="button" class="btn btn-danger fw-bold flex-grow-1 border-0" id="ok_${id}" 
                                  style="border-radius:10px; font-size:.8rem; background:var(--red); box-shadow:0 4px 12px rgba(220,38,38,.25);">
                                  ${okText}
                          </button>
                      </div>
                  </div>
              </div>
          </div>`;
      
      document.body.insertAdjacentHTML('beforeend', html);
      const modalEl = document.getElementById(id);
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
      
      document.getElementById('ok_' + id).addEventListener('click', () => {
          callback();
          modal.hide();
      });
      
      modalEl.addEventListener('hidden.bs.modal', () => modalEl.remove());
      return false;
  }

  function saveInvoiceState() {
      const state = {
          cart: cart,
          discount: cashDiscountInput.value,
          customer: {
              name: customerNameInputEl.value,
              contact: customerContactInputEl.value,
              address: customerAddressInputEl.value
          }
      };
      localStorage.setItem(STAFF_PERSISTENCE_KEY, JSON.stringify(state));
  }

  function loadInvoiceState() {
      const saved = localStorage.getItem(STAFF_PERSISTENCE_KEY);
      if (!saved) return;

      try {
          const state = JSON.parse(saved);
          cart = state.cart || {};
          
          if (state.discount) cashDiscountInput.value = state.discount;
          if (state.customer) {
              customerNameInputEl.value = state.customer.name || 'Walk-in Customer';
              customerContactInputEl.value = state.customer.contact || '';
              customerAddressInputEl.value = state.customer.address || '';
          }

          updateCustomerPreview();
          renderCart();
      } catch (e) {
          console.error('Failed to load staff invoice state:', e);
      }
  }

  function resetInvoice() {
      if (Object.keys(cart).length === 0 && (!customerNameInputEl.value || customerNameInputEl.value === 'Walk-in Customer')) return;
      
      showConfirm({
          title: 'Clear Invoice?',
          message: 'Are you sure you want to clear the entire invoice? This action cannot be undone.',
          okText: 'Yes, Clear All',
          icon: 'bi-trash-fill',
          callback: () => {
              cart = {};
              cashDiscountInput.value = '';
              customerNameInputEl.value = 'Walk-in Customer';
              customerContactInputEl.value = '';
              customerAddressInputEl.value = '';
              localStorage.removeItem(STAFF_PERSISTENCE_KEY);
              
              updateCustomerPreview();
              renderCart();
          }
      });
  }

  function updateCustomerPreview() {
      invoiceCustomerName.textContent = customerNameInputEl.value.trim() || "Walk-in Customer";
  }

  const cartBody = document.getElementById('cartTableBody');
  const emptyCartRow = document.getElementById('emptyCartRow');
  const cartSubtotalEl = document.getElementById('cartSubtotal');
  const cashDiscountInput = document.getElementById('cashDiscountInput');
  const cartGrandTotalEl = document.getElementById('cartGrandTotal');
  const generateInvoiceBtn = document.getElementById('generateInvoiceBtn');

  const customerNameInputEl = document.getElementById('customerNameInput');
  const customerContactInputEl = document.getElementById('customerContactInput');
  const customerAddressInputEl = document.getElementById('customerAddressInput');

  const invoiceModal = document.getElementById('invoiceModal');
  const invoiceTableBody = document.getElementById('invoiceTableBody');
  const invoiceSubtotalEl = document.getElementById('invoiceSubtotal');
  const invoiceDiscountEl = document.getElementById('invoiceDiscount');
  const invoiceGrandTotalEl = document.getElementById('invoiceGrandTotal');
  const invoiceDate = document.getElementById('invoiceDate');

  const invoiceCustomerName = document.getElementById('invoiceCustomerName');
  const invoiceCustomerContact = document.getElementById('invoiceCustomerContact');
  const invoiceCustomerAddress = document.getElementById('invoiceCustomerAddress');

  const invoiceCartDataInput = document.getElementById('invoiceCartDataInput');
  const invoiceDiscountAmountInput = document.getElementById('invoiceDiscountAmountInput');
  const invoiceCustomerNameInput = document.getElementById('invoiceCustomerNameInput');
  const invoiceCustomerContactInput = document.getElementById('invoiceCustomerContactInput');
  const invoiceCustomerAddressInput = document.getElementById('invoiceCustomerAddressInput');
  const invoiceTotalAmountInput = document.getElementById('invoiceTotalAmountInput');

  const printInvoiceBtn = document.getElementById('printInvoiceBtn');

  const productSearchModal = document.getElementById('productSearchModal');
  const productResults = document.getElementById('productResults');
  const selectedProductName = document.getElementById('selectedProductName');
  const selectedProductMeta = document.getElementById('selectedProductMeta');
  const selectedStockNote = document.getElementById('selectedStockNote');
  const productQty = document.getElementById('productQty');
  const productAmount = document.getElementById('productAmount');
  const addToCartBtn = document.getElementById('addToCartBtn');

  let selectedProduct = null;

  const formatCurrency = (value) => {
    const v = parseFloat(value || 0);
    return '₱' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  };

  function safeText(t) {
    return (t ?? '').toString();
  }

  function calculateCartTotals() {
    let subtotal = 0;
    for (const id in cart) {
      subtotal += cart[id].product_data.default_price * cart[id].quantity;
    }

    let discount = parseFloat(cashDiscountInput.value) || 0;
    if (discount < 0) discount = 0;
    if (discount > subtotal) discount = subtotal;

    grandTotal = subtotal - discount;
    if (grandTotal < 0) grandTotal = 0;

    cartSubtotalEl.innerText = formatCurrency(subtotal);
    cartGrandTotalEl.innerText = formatCurrency(grandTotal);

    generateInvoiceBtn.disabled = Object.keys(cart).length === 0;
    
    saveInvoiceState();
  }

  function renderCart() {
    cartBody.innerHTML = '';
    let hasItems = false;

    for (const id in cart) {
      hasItems = true;
      const item = cart[id];
      const lineTotal = item.product_data.default_price * item.quantity;

      const brand = safeText(item.product_data.brand);
      const variation = safeText(item.product_data.variation);
      const metaParts = [];
      if (brand) metaParts.push('Brand: ' + brand);
      if (variation) metaParts.push('Variation: ' + variation);

      const row = document.createElement('tr');
      row.classList.add('cart-item-row');
      row.innerHTML = `
        <td>
          <div class="fw-bold text-dark">${item.product_data.product_name}</div>
          <small class="text-muted">
            ${formatCurrency(item.product_data.default_price)} / ${item.product_data.unit || 'unit'}
            ${metaParts.length ? ' • ' + metaParts.join(' • ') : ''}
          </small>
        </td>

        <td class="text-center">
          <input type="number"
                 class="form-control form-control-sm text-center cart-qty-input bg-light"
                 value="${item.quantity}"
                 min="0.001"
                 step="any"
                 max="${item.product_data.stock}"
                 data-id="${id}"
                 style="width: 90px; margin:auto;">
        </td>

        <td class="text-end fw-bold text-primary">${formatCurrency(lineTotal)}</td>

        <td class="text-center">
          <button type="button" class="btn btn-sm btn-outline-danger border-0 remove-item-btn" data-id="${id}">
            <i class="bi bi-x-lg"></i>
          </button>
        </td>
      `;
      cartBody.appendChild(row);
    }

    emptyCartRow.style.display = hasItems ? 'none' : '';
    attachCartListeners();
    calculateCartTotals();
  }

  function attachCartListeners() {
    document.querySelectorAll('.cart-qty-input').forEach(input => {
      input.oninput = (e) => {
        const id = e.target.dataset.id;
        const maxStock = parseFloat(e.target.max);
        let qty = parseFloat(e.target.value);

        if (isNaN(qty) || qty < 0.001) qty = 0.001;
        if (qty > maxStock) qty = maxStock;

        // Don't force value on every keystroke to allow typing decimals like "0."
        cart[id].quantity = qty;
        calculateCartTotals();
      };
      // On blur, clean up the display value
      input.onblur = (e) => {
          e.target.value = cart[e.target.dataset.id].quantity;
          renderCart();
      };
    });

    document.querySelectorAll('.remove-item-btn').forEach(btn => {
      btn.onclick = (e) => {
        const id = e.currentTarget.dataset.id;
        delete cart[id];
        renderCart();
      };
    });
  }

  function renderResults(list) {
    productResults.innerHTML = '';

    if (!list.length) {
      productResults.innerHTML = `<div class="text-muted small p-2">No matching products.</div>`;
      return;
    }

    list.slice(0, 40).forEach(p => {
      const name = safeText(p.product_name);
      const unit = safeText(p.unit);
      const brand = safeText(p.brand);
      const variation = safeText(p.variation);
      const price = formatCurrency(p.selling_price);
      const stock = parseFloat(p.stock || 0);

      const metaParts = [];
      if (brand) metaParts.push('Brand: ' + brand);
      if (variation) metaParts.push('Variation: ' + variation);
      if (unit) metaParts.push('Unit: ' + unit);

      const stockBadge = stock === 0
        ? `<span class="badge bg-danger">0</span>`
        : stock <= 5
          ? `<span class="badge bg-warning text-dark">${stock}</span>`
          : `<span class="badge bg-success">${stock}</span>`;

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start';
      btn.innerHTML = `
        <div class="me-2">
          <div class="fw-semibold">${name}</div>
          <div class="small text-muted">
            ${metaParts.length ? metaParts.join(' • ') + ' • ' : ''}Price: ${price}
          </div>
        </div>
        <div class="text-end">
          <div class="small text-muted">Stock</div>
          ${stockBadge}
        </div>
      `;
      btn.addEventListener('click', () => selectProduct(p));
      productResults.appendChild(btn);
    });
  }

  function selectProduct(p) {
    selectedProduct = p;

    const name = safeText(p.product_name);
    const unit = safeText(p.unit);
    const brand = safeText(p.brand);
    const variation = safeText(p.variation);
    const price = formatCurrency(p.selling_price);
    const stock = parseFloat(p.stock || 0);

    const metaParts = [];
    if (brand) metaParts.push('Brand: ' + brand);
    if (variation) metaParts.push('Variation: ' + variation);
    if (unit) metaParts.push('Unit: ' + unit);

    selectedProductName.textContent = name;
    selectedProductMeta.textContent = `${metaParts.length ? metaParts.join(' • ') + ' • ' : ''}Price: ${price}`;
    selectedStockNote.textContent = `Available stock: ${stock}`;

    productQty.disabled = false;
    productAmount.disabled = false;
    addToCartBtn.disabled = false;

    productQty.value = 1;
    productAmount.value = parseFloat(p.selling_price).toFixed(2);
    productQty.max = stock;

    if (stock <= 0) {
      addToCartBtn.disabled = true;
      selectedStockNote.textContent = `Out of stock`;
    }
  }

  function filterProducts(term) {
    const t = term.toLowerCase().trim();
    if (!t) return PRODUCTS;

    return PRODUCTS.filter(p => {
      const name = safeText(p.product_name).toLowerCase();
      const brand = safeText(p.brand).toLowerCase();
      const variation = safeText(p.variation).toLowerCase();
      return name.includes(t) || brand.includes(t) || variation.includes(t);
    });
  }

  productSearchModal.addEventListener('input', function () {
    renderResults(filterProducts(this.value));

    selectedProduct = null;
    selectedProductName.textContent = 'No product selected';
    selectedProductMeta.textContent = '';
    selectedStockNote.textContent = '';
    productQty.value = 1;
    productQty.disabled = true;
    addToCartBtn.disabled = true;
  });

  document.getElementById('addProductModal').addEventListener('shown.bs.modal', function () {
    productSearchModal.value = '';
    selectedProduct = null;

    selectedProductName.textContent = 'No product selected';
    selectedProductMeta.textContent = '';
    selectedStockNote.textContent = '';
    productQty.value = 1;
    productAmount.value = '';
    productQty.disabled = true;
    productAmount.disabled = true;
    addToCartBtn.disabled = true;

    renderResults(PRODUCTS);
    productSearchModal.focus();
  });

  // Sync Qty -> Amount
  productQty.addEventListener('input', function() {
    if (!selectedProduct) return;
    const price = parseFloat(selectedProduct.selling_price || 0);
    const qty = parseFloat(this.value) || 0;
    productAmount.value = (qty * price).toFixed(2);
  });

  // Sync Amount -> Qty
  productAmount.addEventListener('input', function() {
    if (!selectedProduct) return;
    const price = parseFloat(selectedProduct.selling_price || 0);
    const amt = parseFloat(this.value) || 0;
    if (price > 0) {
      productQty.value = (amt / price).toFixed(4);
    }
  });

  addToCartBtn.addEventListener('click', function () {
    if (!selectedProduct) {
      alert("Please select a product from the results.");
      return;
    }

    const product_id = parseInt(selectedProduct.product_id, 10);
    const name = safeText(selectedProduct.product_name);
    const price = parseFloat(selectedProduct.selling_price || 0);
    const stock = parseFloat(selectedProduct.stock || 0);
    const unit = safeText(selectedProduct.unit);
    const brand = safeText(selectedProduct.brand);
    const variation = safeText(selectedProduct.variation);

    let qty = parseFloat(productQty.value);
    if (isNaN(qty) || qty <= 0) {
        alert("Please enter a valid quantity.");
        return;
    }
    if (qty > stock) qty = stock;

    if (stock <= 0) {
      alert("This product is out of stock.");
      return;
    }

    if (cart[product_id]) {
      const newQty = cart[product_id].quantity + qty;
      cart[product_id].quantity = (newQty > stock) ? stock : newQty;
    } else {
      cart[product_id] = {
        product_data: {
          product_id: product_id,
          product_name: name,
          default_price: price,
          stock: stock,
          unit: unit,
          brand: brand,
          variation: variation
        },
        quantity: qty
      };
    }

    renderCart();

    const modal = bootstrap.Modal.getInstance(document.getElementById('addProductModal'));
    modal.hide();
  });

  invoiceModal.addEventListener('show.bs.modal', function () {
    invoiceDate.textContent = new Date().toLocaleString();

    const cname = customerNameInputEl.value.trim() || "Walk-in Customer";
    const ccontact = customerContactInputEl.value.trim();
    const caddress = customerAddressInputEl.value.trim();

    invoiceCustomerName.textContent = cname;
    invoiceCustomerContact.textContent = ccontact ? ("Contact: " + ccontact) : "";
    invoiceCustomerAddress.textContent = caddress ? ("Address: " + caddress) : "";

    invoiceCustomerNameInput.value = cname;
    invoiceCustomerContactInput.value = ccontact;
    invoiceCustomerAddressInput.value = caddress;

    invoiceTableBody.innerHTML = "";
    let subtotal = 0;

    const itemsForDB = [];

    for (const id in cart) {
      const item = cart[id];
      const unitPrice = parseFloat(item.product_data.default_price);
      const qty = parseFloat(item.quantity);
      const lineTotal = unitPrice * qty;
      subtotal += lineTotal;

      const brand = safeText(item.product_data.brand);
      const variation = safeText(item.product_data.variation);
      const metaParts = [];
      if (brand) metaParts.push('Brand: ' + brand);
      if (variation) metaParts.push('Variation: ' + variation);

      invoiceTableBody.innerHTML += `
        <tr>
          <td>
            ${item.product_data.product_name}
            ${metaParts.length ? `<div class="small text-muted">${metaParts.join(' • ')}</div>` : ``}
          </td>
          <td class="text-center">${qty.toFixed(4).replace(/\.?0+$/, '')}</td>
          <td class="text-end">${formatCurrency(unitPrice)}</td>
          <td class="text-end">${formatCurrency(lineTotal)}</td>
        </tr>
      `;

      itemsForDB.push({
        product_id: item.product_data.product_id,
        quantity: qty,
        total_price: lineTotal.toFixed(2)
      });
    }

    let discount = parseFloat(cashDiscountInput.value) || 0;
    if (discount < 0) discount = 0;
    if (discount > subtotal) discount = subtotal;

    const grand = subtotal - discount;
    invoiceSubtotalEl.textContent = formatCurrency(subtotal);
    invoiceDiscountEl.textContent = formatCurrency(discount);
    invoiceGrandTotalEl.textContent = formatCurrency(grand);

    invoiceCartDataInput.value = JSON.stringify(itemsForDB);
    invoiceDiscountAmountInput.value = discount.toFixed(2);
    invoiceTotalAmountInput.value = grand.toFixed(2);
  });

  printInvoiceBtn.addEventListener('click', function () {
    const content = document.getElementById('invoicePrintArea').innerHTML;
    const w = window.open('', '', 'width=900,height=650');
    w.document.write(`
      <html>
      <head>
        <title>Invoice Print</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>body{padding:20px;} table{font-size:14px;}</style>
      </head>
      <body>${content}</body>
      </html>
    `);
    w.document.close();
    w.focus();
    w.print();
    w.close();
  });

  cashDiscountInput.addEventListener('input', calculateCartTotals);
  
  customerNameInputEl.addEventListener('input', function() {
      updateCustomerPreview();
      calculateCartTotals();
      saveInvoiceState();
  });
  
  customerContactInputEl.addEventListener('input', saveInvoiceState);
  customerAddressInputEl.addEventListener('input', saveInvoiceState);

  document.getElementById('clearInvoiceBtn')?.addEventListener('click', resetInvoice);
  
  document.getElementById('invoiceForm')?.addEventListener('submit', function() {
      localStorage.removeItem(STAFF_PERSISTENCE_KEY);
  });

  loadInvoiceState();
</script>

</body>
</html>