// --- POS System Logic ---

// Element references
const posInput = document.getElementById('pos_scan_input');
const resultsList = document.getElementById('searchResults'); 
const itemStatus = document.getElementById('itemStatus');
const posCartTableBody = document.querySelector('#posCartTable tbody');
const posSubtotalDisplay = document.getElementById('posSubtotal');
const posGrandTotalDisplay = document.getElementById('posGrandTotal');
const inputGrandTotal = document.getElementById('inputGrandTotal');
const posCashGiven = document.getElementById('posCashGiven');
const posChangeDisplay = document.getElementById('posChange');
const completeSaleButton = document.getElementById('completeSaleButton'); 
const paymentAlertContainer = document.getElementById('paymentAlertContainer');
const cancelTransactionButton = document.getElementById('cancelTransactionButton'); 

let cart = [];
let searchTimeout = null;

const esc = (str) => {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
};

// 1. Live Search Logic
posInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    if (query.length < 2) {
        resultsList.style.display = 'none';
        return;
    }
    searchTimeout = setTimeout(() => {
        // NOTE: search_products.php MUST return 'brand' and 'variation'
        fetch('search_products.php?q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                resultsList.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(item => {
                        const li = document.createElement('li');
                        li.classList.add('list-group-item', 'list-group-item-action');
                        
                        // FIX: Use 'item.brand' and 'item.variation' from the JSON response, robustly
                        const brandDisplay = item.brand && item.brand !== 'N/A' ? item.brand + ' - ' : '';
                        const variationDisplay = item.variation && item.variation !== 'None' ? ' (' + item.variation + ')' : '';

                        li.textContent = `${brandDisplay}${item.product_name}${variationDisplay} (${item.unit || ''}) (₱${parseFloat(item.price).toFixed(2)}) - Stock: ${item.stock}`;

                        li.dataset.id = item.product_id;
                        li.dataset.name = item.product_name;
                        li.dataset.price = item.price;
                        li.dataset.stock = item.stock;
                        
                        // FIX: Save Brand and Variation names to dataset using the correct keys
                        li.dataset.brand = item.brand; 
                        li.dataset.variation = item.variation; 
                        li.dataset.unit = item.unit;

                        li.addEventListener('click', function() {
                            addToCart(this.dataset); 
                            posInput.value = '';
                            resultsList.style.display = 'none';
                            posInput.focus(); 
                        });
                        resultsList.appendChild(li);
                    });
                    resultsList.style.display = 'block';
                } else {
                    resultsList.style.display = 'none';
                }
            });
    }, 300);
});

posInput?.addEventListener('blur', function() {
    setTimeout(() => resultsList.style.display = 'none', 100); 
});
posInput?.addEventListener('focus', function() {
    if (resultsList?.innerHTML !== '' && posInput.value.trim().length >= 2) {
            resultsList.style.display = 'block';
    }
});

// 2. Keypad Input Logic (unchanged)
document.querySelectorAll('.pos-key-num').forEach(button => {
    button.addEventListener('click', function() {
        const value = this.dataset.val;
        posInput.value += value;
        posInput.dispatchEvent(new Event('input')); 
    });
});

document.getElementById('pos-key-clear')?.addEventListener('click', function() {
    posInput.value = '';
    itemStatus.style.display = 'none';
    resultsList.style.display = 'none'; 
});

const handleEntry = () => {
    const query = posInput.value.trim();
    posInput.value = '';
    itemStatus.style.display = 'none';
    resultsList.style.display = 'none';

    if (query === '') return;

    // Only performs exact match search (Barcode/ID)
    fetch('search_products.php?q=' + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            if (data.length === 1 && (data[0].barcode === query || data[0].product_id == query)) {
                // FIX: Passes the correct 'brand' and 'variation' keys to addToCart.
                addToCart({
                    id: data[0].product_id,
                    name: data[0].product_name,
                    price: parseFloat(data[0].price),
                    stock: parseInt(data[0].stock),
                    brand: data[0].brand_name, 
                    variation: data[0].variation_name, 
                    unit: data[0].unit 
                });
            } else {
                itemStatus.textContent = `Error: Barcode or exact Product ID '${query}' not found. Try typing the name and selecting from the dropdown.`;
                itemStatus.style.display = 'block';
            }
        })
        .catch(err => {
            console.error('Search error:', err);
            itemStatus.textContent = 'A search error occurred.';
            itemStatus.style.display = 'block';
        });
    
    posInput.focus();
};

document.getElementById('pos-key-enter')?.addEventListener('click', handleEntry);

posInput?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        handleEntry();
    }
});

// 3. Function to update Quantity
window.updateQuantity = function(index, newQtyStr) {
    const newQty = parseInt(newQtyStr);
    const item = cart[index];

    // 1. Basic Validation (Must be an integer >= 1)
    if (isNaN(newQty) || newQty < 1) {
        itemStatus.textContent = `Error: Quantity must be 1 or greater.`;
        itemStatus.style.display = 'block';
        renderCart(); 
        return;
    }
    
    // 2. Stock Validation (Must be <= stock)
    if (newQty > item.stock) {
        itemStatus.textContent = `Error: Quantity cannot exceed stock (${item.stock}).`;
        itemStatus.style.display = 'block';
        renderCart(); 
        return;
    }

    // 3. Update and Render
    item.quantity = newQty;
    itemStatus.style.display = 'none'; 
    renderCart(); 
}

// 4. Cart Management Functions 
function addToCart(product) {
    const existing = cart.find(p => p.id === product.id);

    if (existing) {
        if (existing.quantity < product.stock) {
            existing.quantity++;
        } else {
            itemStatus.textContent = `Not enough stock for ${product.name}!`;
            itemStatus.style.display = 'block';
        }
    } else {
        if (product.stock > 0) {
            cart.push({
                id: product.id,
                name: product.name,
                price: parseFloat(product.price),
                stock: parseInt(product.stock),
                quantity: 1,
                // FIX: Stores Brand and Variation names in the cart object using 'brand'/'variation' keys, robustly.
                brand: product.brand_name ?? 'N/A', 
                variation: product.variation_name ?? 'None', 
                unit: product.unit ?? '-'
            });
        } else {
            itemStatus.textContent = `${product.name} is out of stock!`;
            itemStatus.style.display = 'block';
        }
    }
    renderCart();
}

window.removeFromCart = function(index) {
    cart.splice(index, 1);
    renderCart();
}

function renderCart() {
    posCartTableBody.innerHTML = '';
    let subtotal = 0;
    const taxRate = 0.00; // Assuming 0 tax rate for now

    cart.forEach((p, index) => {
        const itemTotal = p.price * p.quantity;
        subtotal += itemTotal;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <strong>${esc(p.name)}</strong>
                <div class="text-muted small">
                    ${[p.brand, p.variation, p.unit].filter(x => x && x !== 'N/A' && x !== 'None').map(x => esc(x)).join(' · ')}
                </div>
                <input type="hidden" name="products[${index}][id]" value="${p.id}">
                <input type="hidden" name="products[${index}][quantity]" value="${p.quantity}">
            </td>
            <td class="text-center">
                <input type="number" 
                        value="${p.quantity}" 
                        min="1" 
                        max="${p.stock}"
                        class="form-control form-control-sm text-center mx-auto" 
                        style="width: 60px;"
                        onchange="updateQuantity(${index}, this.value)">
            </td>
            <td class="text-end d-flex justify-content-end align-items-center"> 
                <span class="me-2">₱${itemTotal.toFixed(2)}</span>
                <i class="fa fa-times-circle text-danger" style="cursor:pointer;" onclick="removeFromCart(${index})"></i>
            </td>
        `;
        posCartTableBody.appendChild(row);
    });

    const tax = subtotal * taxRate;
    const grandTotal = subtotal + tax;

    posSubtotalDisplay.textContent = `₱${subtotal.toFixed(2)}`;
    document.getElementById('posTax').textContent = `₱${tax.toFixed(2)}`;
    posGrandTotalDisplay.textContent = `₱${grandTotal.toFixed(2)}`;
    inputGrandTotal.value = grandTotal.toFixed(2);

    calculateChange();
}

// 5. Financial Calculations
function toggleCompleteSaleButton(canComplete) {
    if (completeSaleButton) {
        completeSaleButton.disabled = !canComplete;
        if (canComplete) {
            completeSaleButton.classList.remove('btn-warning'); 
            completeSaleButton.classList.add('btn-success');
        } else {
            completeSaleButton.classList.remove('btn-success');
            completeSaleButton.classList.add('btn-warning');
        }
    }
}

function calculateChange() {
    const cash = parseFloat(posCashGiven.value) || 0;
    const grandTotal = parseFloat(inputGrandTotal.value) || 0;
    const change = cash - grandTotal;
    let saleReady = false;

    if (paymentAlertContainer) paymentAlertContainer.innerHTML = '';

    if (change >= 0) {
        posChangeDisplay.textContent = `₱${change.toFixed(2)}`;
        posChangeDisplay.classList.remove('text-danger');
        posChangeDisplay.classList.add('text-primary');
        saleReady = true;
    } else {
        posChangeDisplay.textContent = `₱${(-change).toFixed(2)} Due`;
        posChangeDisplay.classList.add('text-danger');
        posChangeDisplay.classList.remove('text-primary');
        saleReady = false;
    }
    
    // Disable the button if the cart is empty OR cash is insufficient
    const cartHasItems = cart.length > 0;
    toggleCompleteSaleButton(saleReady && cartHasItems);
}

posCashGiven?.addEventListener('input', calculateChange);

// --- Sale Submission Interception (Blocking with Alert) ---
if (completeSaleButton) {
    const form = completeSaleButton.closest('form');

    if (form) {
        form.addEventListener('submit', function(e) {
            itemStatus.style.display = 'none';

            if (completeSaleButton.disabled) {
                e.preventDefault(); 
                
                const amountDue = posChangeDisplay.textContent;
                
                const alertMessage = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>🛑 PAYMENT REQUIRED!</strong> Cash tendered is insufficient. The amount due is ${amountDue}.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                
                if (paymentAlertContainer) {
                    paymentAlertContainer.innerHTML = alertMessage;
                }

                itemStatus.textContent = "Cannot complete sale: Cash is less than the Grand Total.";
                itemStatus.style.display = 'block';
                itemStatus.classList.remove('alert-success');
                itemStatus.classList.add('alert-danger');
                
                posCashGiven.focus(); 
            }
        });
    }
}

// NEW FUNCTION: Handles clearing the POS state only when explicitly cancelled or after a successful sale
function resetPOS() {
    cart = [];
    renderCart(); 
    posInput.value = '';
    posCashGiven.value = '';
    itemStatus.style.display = 'none';
    if (paymentAlertContainer) paymentAlertContainer.innerHTML = '';
    toggleCompleteSaleButton(false);
}


// ----------------------------------------------------------------------------------
// Modal Control Logic
// ----------------------------------------------------------------------------------

document.getElementById('addSaleModal')?.addEventListener('shown.bs.modal', () => {
    posInput.focus();
});

cancelTransactionButton?.addEventListener('click', function() {
    resetPOS(); 
    const modalElement = document.getElementById('addSaleModal');
    if (modalElement) {
        // Use Bootstrap's method to hide the modal properly
        const modalInstance = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
        modalInstance.hide();
    }
});


// --- PIN Confirmation Logic ---
const pinModalElement = document.getElementById('confirmPinModal');
const pinModal = pinModalElement ? new bootstrap.Modal(pinModalElement) : null; 

const pinInput = document.getElementById('confirmPinInput');
const pinErrorMsg = document.getElementById('pinErrorMsg');

document.getElementById('completeSaleButton')?.addEventListener('click', function() {
    if (this.disabled || !pinModal) {
        return; 
    }

    const grandTotal = parseFloat(document.getElementById('inputGrandTotal').value || 0);
    const cash = parseFloat(document.getElementById('posCashGiven').value || 0);

    if (grandTotal <= 0 || cash < grandTotal) {
        return; 
    }

    pinInput.value = '';
    pinErrorMsg.style.display = 'none';
    pinModal.show();
});

document.getElementById('confirmPinBtn')?.addEventListener('click', function() {
    const pin = pinInput.value.trim();

    if (pin.length !== 4) {
        pinErrorMsg.textContent = 'PIN must be 4 digits.';
        pinErrorMsg.style.display = 'block';
        return;
    }

    fetch('verify_pin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'pin=' + encodeURIComponent(pin)
    })
    .then(res => res.text())
    .then(response => {
        if (response.trim() === 'success') {
            pinModal.hide();
            document.getElementById('saleForm').submit();
        } else {
            pinErrorMsg.textContent = 'Invalid PIN. Try again.';
            pinErrorMsg.style.display = 'block';
        }
    })
    .catch(() => {
        pinErrorMsg.textContent = 'Error verifying PIN.';
        pinErrorMsg.style.display = 'block';
    });
});