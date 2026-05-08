// NOTE: This script relies on the 'debounce' function being defined in the main product_modals.php script block.

if (typeof window.showConfirm !== 'function') {
    window.showConfirm = showConfirm = function({title = 'Are you sure?', message, okText = 'OK', okClass = 'btn-confirm-ok', icon = 'bi-exclamation-triangle-fill', callback}) {
        const id = 'cConfirm_' + Date.now();
        const html = `
            <div class="modal fade" id="${id}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content confirm-modal">
                        <div class="confirm-modal-body">
                            <div class="confirm-icon" style="${okClass.includes('red') ? '' : 'background:var(--blue-mid); color:var(--blue);'}">
                                <i class="bi ${icon}"></i>
                            </div>
                            <div style="font-weight:800; font-size:.95rem; margin-bottom:8px; color:var(--ink);">${title}</div>
                            <div class="confirm-message" style="font-size:.78rem; color:var(--muted);">${message}</div>
                        </div>
                        <div class="confirm-modal-footer">
                            <button type="button" class="btn-confirm-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn-confirm-ok ${okClass}" id="ok_${id}">${okText}</button>
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
    };
}

if (typeof window.showAlert !== 'function') {
    window.showAlert = showAlert = function({title = 'Notice', message, icon = 'bi-info-circle-fill', color = 'var(--blue)'}) {
        const id = 'cAlert_' + Date.now();
        const html = `
            <div class="modal fade" id="${id}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content confirm-modal">
                        <div class="confirm-modal-body">
                            <div class="confirm-icon" style="background:${color}15; color:${color};">
                                <i class="bi ${icon}"></i>
                            </div>
                            <div style="font-weight:800; font-size:.95rem; margin-bottom:8px; color:var(--ink);">${title}</div>
                            <div class="confirm-message" style="font-size:.78rem; color:var(--muted);">${message}</div>
                        </div>
                        <div class="confirm-modal-footer">
                            <button type="button" class="btn-confirm-ok" style="background:${color}; border-color:${color}; width:100%;" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
        const modalEl = document.getElementById(id);
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        modalEl.addEventListener('hidden.bs.modal', () => modalEl.remove());
        return false;
    };
}

const esc = (str) => {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
};

// Object to store products: {productId: {id, name, sku, unit, current_stock, brand, variation, quantity, supplier_price, expiry_date}}
let stockInItems = {};
const STORAGE_KEY = 'stock_in_state_persistence';

/**
 * Saves the current modal state to localStorage
 */
function saveStockInState() {
    const state = {
        items: stockInItems,
        supplier_id: document.getElementById('supplierSelect')?.value || '',
        reference_no: document.getElementById('referenceNo')?.value || ''
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

/**
 * Loads the saved modal state from localStorage
 */
function loadStockInState() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (!saved) return;

    try {
        const state = JSON.parse(saved);
        stockInItems = state.items || {};
        
        // Populate inputs if they exist
        const supplierSelect = document.getElementById('supplierSelect');
        const referenceNo = document.getElementById('referenceNo');
        
        if (supplierSelect && state.supplier_id) {
            supplierSelect.value = state.supplier_id;
            // Trigger contact no update
            supplierSelect.dispatchEvent(new Event('change'));
        }
        
        if (referenceNo && state.reference_no) {
            referenceNo.value = state.reference_no;
        }

        renderStockInItems();
    } catch (e) {
        console.error('Failed to load stock-in state:', e);
    }
}

const stockInItemsBody = document.getElementById('stockInItemsBody');
const stockInSearchInput = document.getElementById('stockInSearchInput');
const stockInSearchResults = document.getElementById('stockInSearchResults'); 
const confirmStockInBtn = document.getElementById('confirmStockInBtn');
const clearStockInItemsBtn = document.getElementById('clearStockInItemsBtn');
const stockInTotalCountSpan = document.getElementById('stockInTotalCount');

/**
 * Renders the list of products in the modal table.
 */
function renderStockInItems() {
    let html = '';
    let totalItems = 0;
    
    const productIds = Object.keys(stockInItems);

    if (productIds.length === 0) {
        stockInItemsBody.innerHTML = '<tr id="emptyStockInMessage"><td colspan="4" class="text-center text-muted py-4">Scan a product or use the search bar above to add items.</td></tr>';
        confirmStockInBtn.disabled = true;
        stockInTotalCountSpan.textContent = '0';
        return;
    }

    productIds.forEach(id => {
        const item = stockInItems[id];
        totalItems += item.quantity;

        html += `
            <tr data-product-id="${id}">
                <td style="width: 45%;">
                    <div class="fw-bold">${esc(item.name)}</div>
                    <div class="small text-muted">
                        ${[item.brand, item.variation, item.unit].filter(x => x && x !== 'N/A' && x !== 'None').map(x => esc(x)).join(' · ')}
                    </div>
                    <div class="small text-info">sku: ${esc(item.sku)} | Current Stock: ${Number(item.current_stock).toString()}</div>
                </td>

                <td style="width: 15%;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">₱</span>
                        <input type="number" class="form-control stock-in-price-input" 
                               data-id="${id}" value="${item.supplier_price || '0.00'}" step="0.01" min="0">
                    </div>
                </td>

                <td style="width: 15%;">
                    <input type="date" class="form-control form-control-sm stock-in-expiry-input" 
                           data-id="${id}" value="${item.expiry_date || ''}" title="Leave blank if not applicable">
                </td>

                <td style="width: 15%;" class="text-center">
                    <div class="input-group input-group-sm">
                        <button type="button" class="btn btn-outline-secondary stock-in-qty-btn" data-id="${id}" data-action="decrease">-</button>
                        <input type="number" class="form-control text-center stock-in-qty-input" data-id="${id}" value="${item.quantity}" min="1">
                        <button type="button" class="btn btn-outline-secondary stock-in-qty-btn" data-id="${id}" data-action="increase">+</button>
                    </div>
                </td>
                <td style="width: 10%;">
                    <button type="button" class="btn btn-sm btn-danger remove-stock-in-item" data-id="${id}" title="Remove Item">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    stockInItemsBody.innerHTML = html;
    confirmStockInBtn.disabled = totalItems === 0;
    stockInTotalCountSpan.textContent = totalItems;
    saveStockInState(); // Persist changes whenever we re-render
}

/**
 * Adds or increments a product in the stockInItems object.
 */
async function addOrIncrementProduct(product) {
    const id = product.product_id;
    const supplierSelect = document.getElementById('supplierSelect');
    const supplierId = supplierSelect ? supplierSelect.value : null;

    if (stockInItems[id]) {
        stockInItems[id].quantity += 1;
        renderStockInItems();
    } else {
        stockInItems[id] = {
            id: id,
            name: product.product_name,
            sku: product.sku,
            unit: product.unit,
            current_stock: product.stock,
            brand: product.brand, 
            variation: product.variation, 
            quantity: 1,
            supplier_price: product.supplier_price || 0.00,
            expiry_date: '' // Initialize empty expiry date
        };
        
        renderStockInItems();

        // If a supplier is already selected, try to fetch the most recent price from that supplier
        if (supplierId) {
            try {
                const response = await fetch(`fetch_last_prices.php?supplier_id=${supplierId}&product_ids=${id}`);
                const prices = await response.json();
                
                if (prices[id] !== undefined) {
                    stockInItems[id].supplier_price = prices[id];
                    
                    // Re-render only if the price was actually updated (to avoid flickering)
                    const priceInput = document.querySelector(`.stock-in-price-input[data-id="${id}"]`);
                    if (priceInput) {
                        priceInput.value = prices[id];
                        priceInput.classList.add('bg-success-subtle');
                        setTimeout(() => priceInput.classList.remove('bg-success-subtle'), 1000);
                    }
                }
            } catch (error) {
                console.error('Error fetching supplier-specific price:', error);
            }
        }
    }
}

/**
 * Fetches product data for an exact query (sku/ID) and adds it to the list.
 */
async function searchAndAddProductByEnter(query) {
    query = query.trim();
    if (!query) return;

    try {
        const response = await fetch('search_products.php?q=' + encodeURIComponent(query));
        const data = await response.json();

        if (data.length === 1) {
            const product = data[0];
            addOrIncrementProduct({
                product_id: product.product_id,
                product_name: product.product_name,
                sku: product.sku,
                unit: product.unit,
                stock: product.stock,
                brand: product.brand || product.brand_name, 
                variation: product.variation || product.variation_name,
                supplier_price: product.supplier_price
            }); 
            stockInSearchInput.value = ''; 
        } else {
            alert('Product not found or multiple matches. Please select from the list or check the sku.');
        }

    } catch (error) {
        console.error('Error fetching product for stock-in:', error);
        alert('An error occurred while fetching product data.');
    }
}


/**
 * Performs a live search and populates the dropdown.
 */
const performStockInLiveSearch = (query) => {
    if (query.length < 2) {
        stockInSearchResults.style.display = 'none';
        return;
    }

    fetch('search_products.php?q=' + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            stockInSearchResults.innerHTML = '';
            if (data.length > 0) {
                data.forEach(item => {
                    const li = document.createElement('li');
                    li.classList.add('list-group-item', 'list-group-item-action', 'cursor-pointer');
                    
                    const brandValue = item.brand || item.brand_name || 'N/A';
                    const variationValue = item.variation || item.variation_name || 'N/A';
                    
                    const nameDiv = document.createElement('div');
                    nameDiv.classList.add('fw-bold');
                    nameDiv.textContent = item.product_name; 
                    li.appendChild(nameDiv);

                    const detailsDiv = document.createElement('div');
                    detailsDiv.classList.add('small', 'text-muted');
                    const metaParts = [brandValue, variationValue, item.unit].filter(x => x && x !== 'N/A' && x !== 'None' && x !== 'Unbranded');
                    const stockDisplay = item.stock !== undefined ? ` (Stock: ${Number(item.stock).toString()})` : '';
                    detailsDiv.textContent = metaParts.join(' · ') + stockDisplay;
                    li.appendChild(detailsDiv);

                    li.dataset.id = item.product_id;
                    li.dataset.name = item.product_name;
                    li.dataset.sku = item.sku;
                    li.dataset.unit = item.unit; 
                    li.dataset.currentStock = item.stock;
                    li.dataset.brand = brandValue; 
                    li.dataset.variation = variationValue; 
                    li.dataset.supplierPrice = item.supplier_price || 0.00;

                    li.addEventListener('click', function() {
                        addOrIncrementProduct({
                            product_id: this.dataset.id,
                            product_name: this.dataset.name,
                            sku: this.dataset.sku,
                            unit: this.dataset.unit,
                            stock: this.dataset.currentStock,
                            brand: this.dataset.brand, 
                            variation: this.dataset.variation,
                            supplier_price: this.dataset.supplierPrice
                        });
                        stockInSearchInput.value = '';
                        stockInSearchResults.style.display = 'none';
                        stockInSearchInput.focus(); 
                    });
                    stockInSearchResults.appendChild(li);
                });
                stockInSearchResults.style.display = 'block';
            } else {
                stockInSearchResults.innerHTML = '<li class="list-group-item text-muted small">No products found.</li>';
                stockInSearchResults.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error fetching stock-in search results:', error);
            stockInSearchResults.innerHTML = '<li class="list-group-item text-danger small">Error loading search results.</li>';
            stockInSearchResults.style.display = 'block';
        });
};

const debouncedStockInSearch = debounce(performStockInLiveSearch, 300);

// --- Event Listeners ---

stockInSearchInput?.addEventListener('input', function() {
    debouncedStockInSearch(this.value.trim());
});

stockInSearchInput?.addEventListener('keyup', (e) => {
    if (e.key === 'Enter') {
        stockInSearchResults.style.display = 'none'; 
        searchAndAddProductByEnter(stockInSearchInput.value.trim()); 
    }
});

stockInSearchInput?.addEventListener('blur', function() {
    setTimeout(() => stockInSearchResults.style.display = 'none', 150); 
});

stockInSearchInput?.addEventListener('focus', function() {
    if (stockInSearchResults.innerHTML !== '' && stockInSearchInput.value.trim().length >= 2) {
        stockInSearchResults.style.display = 'block';
    }
});

document.getElementById('stockInManualAddBtn')?.addEventListener('click', () => {
    stockInSearchResults.style.display = 'none'; 
    searchAndAddProductByEnter(stockInSearchInput.value.trim()); 
});

// --- Delegation for Dynamic Table Elements ---
stockInItemsBody?.addEventListener('click', (e) => {
    const target = e.target;
    const itemElement = target.closest('[data-id]');
    const id = itemElement?.getAttribute('data-id');

    if (!id || !stockInItems[id]) return;

    if (target.closest('.remove-stock-in-item')) {
        delete stockInItems[id];
        renderStockInItems();
    } else if (target.closest('.stock-in-qty-btn')) {
        const action = target.closest('[data-action]').getAttribute('data-action');
        let currentQty = stockInItems[id].quantity;

        if (action === 'increase') {
            stockInItems[id].quantity = currentQty + 1;
        } else if (action === 'decrease' && currentQty > 1) {
            stockInItems[id].quantity = currentQty - 1;
        }
        renderStockInItems();
    }
});

// --- ✅ UPDATED: INPUT CHANGE LISTENER (Qty & Expiry Alert) ---
stockInItemsBody?.addEventListener('input', (e) => {
    const target = e.target;
    
    // Quantity Change
    if (target.classList.contains('stock-in-qty-input')) {
        const id = target.getAttribute('data-id');
        let newQty = parseInt(target.value) || 1; 
        if (newQty < 1) newQty = 1;
        stockInItems[id].quantity = newQty;
        target.value = newQty; 
        renderStockInItems();
    } 
    
    // Supplier Price Change
    else if (target.classList.contains('stock-in-price-input')) {
        const id = target.getAttribute('data-id');
        let newPrice = parseFloat(target.value) || 0.00;
        if (newPrice < 0) newPrice = 0.00;
        stockInItems[id].supplier_price = newPrice;
        // No need to re-render for every keystroke unless we add totals
    }
    
    // ✅ Expiry Date Change with "Almost Expired" Check
    else if (target.classList.contains('stock-in-expiry-input')) {
        const id = target.getAttribute('data-id');
        const selectedDate = target.value;
        
        // Save the date (even if empty/optional)
        stockInItems[id].expiry_date = selectedDate;

        if (selectedDate) {
            const dateObj = new Date(selectedDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Midnight

            // Calculate 30 days from now for "Near Expiry"
            const warningDate = new Date();
            warningDate.setHours(0, 0, 0, 0);
            warningDate.setDate(today.getDate() + 30); 

            if (dateObj < today) {
                // 1. Already Expired
                showCustomNotice("⚠️ WARNING: You have selected a date that is already expired!");
            } 
            else if (dateObj <= warningDate) {
                // 2. Almost Expired (Within 30 days)
                showCustomNotice("⚠️ NOTICE: This item expires soon (within 30 days).");
            }
        }
    }
});

clearStockInItemsBtn?.addEventListener('click', () => {
    showCustomConfirm('Are you sure you want to remove ALL items from the stock-in list?', () => {
        stockInItems = {};
        if(document.getElementById('supplierSelect')) document.getElementById('supplierSelect').value = ''; 
        if(document.getElementById('contactNo')) document.getElementById('contactNo').value = '';
        if(document.getElementById('referenceNo')) document.getElementById('referenceNo').value = '';
        localStorage.removeItem(STORAGE_KEY);
        renderStockInItems();
    });
});

// Form Submission
const stockInFormEl = document.getElementById('stockInForm');
stockInFormEl?.addEventListener('submit', (e) => {
    if (Object.keys(stockInItems).length === 0) {
        showAlert({title:'Empty List', message:'Please add at least one item to stock in.'});
        e.preventDefault();
        return false;
    }

    const dataToSend = Object.values(stockInItems).map(item => ({
        product_id: item.id,
        quantity: item.quantity,
        expiry_date: item.expiry_date,
        supplier_price: item.supplier_price
    }));

    document.getElementById('stock_in_data_json').value = JSON.stringify(dataToSend);

    if (!stockInFormEl.dataset.confirmed) {
        e.preventDefault();
        showConfirm({
            title: 'Confirm Stock In',
            message: 'Are you sure you want to process this stock-in transaction?',
            okText: 'Confirm',
            icon: 'bi-box-arrow-in-down',
            callback: () => {
                stockInFormEl.dataset.confirmed = 'true';
                localStorage.removeItem(STORAGE_KEY); // Clear persisted state on successful submission
                stockInFormEl.submit();
            }
        });
    } else {
        delete stockInFormEl.dataset.confirmed;
    }
});

document.getElementById('stockInModal')?.addEventListener('hidden.bs.modal', function() {
    // We no longer clear stockInItems here to allow persistence
    stockInSearchInput.value = '';
    stockInSearchResults.style.display = 'none'; 
});

document.getElementById('stockInModal')?.addEventListener('shown.bs.modal', function() {
    stockInSearchInput.focus();
});

/**
 * Fetches the latest prices for all items currently in the list based on the selected supplier.
 */
async function updatePricesForSelectedSupplier() {
    const supplierId = document.getElementById('supplierSelect')?.value;
    const productIds = Object.keys(stockInItems);

    if (!supplierId || productIds.length === 0) return;

    // Visual feedback: dim the price fields while fetching
    const priceInputs = document.querySelectorAll('.stock-in-price-input');
    priceInputs.forEach(input => {
        input.classList.add('opacity-50');
        input.disabled = true;
    });

    try {
        const response = await fetch(`fetch_last_prices.php?supplier_id=${supplierId}&product_ids=${productIds.join(',')}`);
        const prices = await response.json();

        let updatedAny = false;
        productIds.forEach(id => {
            if (prices[id] !== undefined) {
                stockInItems[id].supplier_price = prices[id];
                updatedAny = true;
            }
        });

        if (updatedAny) {
            renderStockInItems();
            // Brief highlight for updated fields
            document.querySelectorAll('.stock-in-price-input').forEach(input => {
                input.classList.add('bg-success-subtle');
                setTimeout(() => input.classList.remove('bg-success-subtle'), 1000);
            });
        }
    } catch (error) {
        console.error('Error updating supplier prices:', error);
    } finally {
        priceInputs.forEach(input => {
            input.classList.remove('opacity-50');
            input.disabled = false;
        });
    }
}

// Add Global Listener for Supplier Selection and state saving
document.addEventListener('DOMContentLoaded', () => {
    // Load persisted state immediately
    loadStockInState();

    const supplierSelect = document.getElementById('supplierSelect');
    if (supplierSelect) {
        supplierSelect.addEventListener('change', () => {
            updatePricesForSelectedSupplier(); // Fetch prices if supplier changes
            saveStockInState(); // Save supplier_id change
        });
        console.log('Supplier price auto-fill & persistence listener attached.');
    }

    const referenceNo = document.getElementById('referenceNo');
    if (referenceNo) {
        referenceNo.addEventListener('input', saveStockInState);
    }
});

// Alias for locally used functions to use global ones
function showCustomNotice(msg) { showAlert({message: msg}); }
function showCustomConfirm(msg, cb) { showConfirm({message: msg, callback: cb}); }
