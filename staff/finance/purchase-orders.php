<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'Purchase Orders';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="bi bi-cart-check"></i> Purchase Orders
        </h1>
        <button type="button" class="btn btn-primary" onclick="openPOModal('create')">
            <i class="bi bi-plus-circle"></i> New Purchase Order
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus" onchange="loadPOs()">
                        <option value="">All Status</option>
                        <option value="draft" selected>Draft</option>
                        <option value="approved">Approved</option>
                        <option value="sent">Sent to Vendor</option>
                        <option value="partially_received">Partially Received</option>
                        <option value="fully_received">Fully Received</option>
                        <option value="closed">Closed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Vendor</label>
                    <input type="number" class="form-control" id="filterVendor" placeholder="Vendor ID" onchange="loadPOs()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" id="filterDateFrom" onchange="loadPOs()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="PO number, description..." onkeyup="loadPOs()">
                </div>
            </div>
        </div>
    </div>

    <!-- POs Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Date</th>
                            <th>Vendor</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Received</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="posTableBody">
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                Loading purchase orders...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- PO Modal -->
<div class="modal fade" id="poModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="poModalTitle">New Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="poForm">
                    <input type="hidden" id="poId" name="id">

                    <!-- Header Info -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Vendor <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="vendorId" name="vendor_id" required placeholder="Vendor ID">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PO Date</label>
                            <input type="date" class="form-control" id="poDate" name="po_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expected Delivery</label>
                            <input type="date" class="form-control" id="expectedDate" name="expected_delivery_date">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Payment Terms</label>
                            <select class="form-select" id="paymentTerms" name="payment_terms">
                                <option value="NET30">Net 30</option>
                                <option value="NET60">Net 60</option>
                                <option value="NET90">Net 90</option>
                                <option value="COD">Cash on Delivery</option>
                                <option value="PREPAID">Prepaid</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Shipping Method</label>
                            <select class="form-select" id="shippingMethod" name="shipping_method">
                                <option value="STANDARD">Standard Shipping</option>
                                <option value="EXPRESS">Express</option>
                                <option value="OVERNIGHT">Overnight</option>
                                <option value="PICKUP">Pickup</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Buyer</label>
                            <input type="number" class="form-control" id="buyerUser" name="buyer_user_id" placeholder="User ID">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>

                    <!-- Line Items Section -->
                    <h6 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-list-ul"></i> Line Items
                    </h6>

                    <div id="lineItems">
                        <!-- Line items will be added here dynamically -->
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addLineItem()">
                        <i class="bi bi-plus-circle"></i> Add Line Item
                    </button>

                    <!-- Total -->
                    <div class="row">
                        <div class="col-md-8"></div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <strong>Subtotal:</strong>
                                        <span id="subtotal">$0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <strong>Tax:</strong>
                                        <span id="tax">$0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <strong>Shipping:</strong>
                                        <input type="number" class="form-control form-control-sm" id="shippingCost" name="shipping_cost" value="0" step="0.01" onchange="calculateTotals()">
                                    </div>
                                    <div class="d-flex justify-content-between border-top pt-2">
                                        <strong>Total:</strong>
                                        <strong id="total">$0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" onclick="savePO('draft')">
                    <i class="bi bi-save"></i> Save as Draft
                </button>
                <button type="button" class="btn btn-primary" onclick="savePO('approved')">
                    <i class="bi bi-check-circle"></i> Approve & Send to Vendor
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View PO Modal -->
<div class="modal fade" id="viewPOModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalTitle">Purchase Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="poDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printPOBtn" onclick="printPO()">
                    <i class="bi bi-printer"></i> Print PO
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentFilters = {
    status: 'draft',
    vendor_id: '',
    date_from: '',
    search: ''
};

let lineItemCount = 0;

document.addEventListener('DOMContentLoaded', function() {
    loadPOs();
});

function loadPOs() {
    currentFilters.status = document.getElementById('filterStatus').value;
    currentFilters.vendor_id = document.getElementById('filterVendor').value;
    currentFilters.date_from = document.getElementById('filterDateFrom').value;
    currentFilters.search = document.getElementById('filterSearch').value;

    const params = new URLSearchParams();
    if (currentFilters.status) params.append('status', currentFilters.status);
    if (currentFilters.vendor_id) params.append('vendor_id', currentFilters.vendor_id);
    if (currentFilters.date_from) params.append('date_from', currentFilters.date_from);
    if (currentFilters.search) params.append('search', currentFilters.search);

    fetch(`/staff/api/finance/purchase-orders.php?action=list&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderPOs(data.data);
            } else {
                showError('Failed to load purchase orders: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading POs:', error);
            showError('Failed to load purchase orders');
        });
}

function renderPOs(pos) {
    const tbody = document.getElementById('posTableBody');

    if (pos.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted">
                    <i class="bi bi-inbox"></i> No purchase orders found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = pos.map(po => {
        const statusColors = {
            'draft': 'secondary',
            'approved': 'success',
            'sent': 'info',
            'partially_received': 'warning',
            'fully_received': 'primary',
            'closed': 'dark',
            'cancelled': 'danger'
        };

        const receivedPercent = po.total_amount > 0 ? ((po.received_amount / po.total_amount) * 100).toFixed(0) : 0;

        return `
            <tr>
                <td><strong>${escapeHtml(po.po_number || 'DRAFT')}</strong></td>
                <td>${new Date(po.po_date).toLocaleDateString()}</td>
                <td>${escapeHtml(po.vendor_name || 'Vendor #' + po.vendor_id)}</td>
                <td><strong>$${parseFloat(po.total_amount || 0).toFixed(2)}</strong></td>
                <td>
                    <span class="badge bg-${statusColors[po.status]}">
                        ${formatStatus(po.status)}
                    </span>
                </td>
                <td>
                    <div class="progress" style="width: 100px;">
                        <div class="progress-bar" role="progressbar" style="width: ${receivedPercent}%">${receivedPercent}%</div>
                    </div>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewPO(${po.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                        ${po.status === 'draft' ? `
                            <button class="btn btn-outline-secondary" onclick="editPO(${po.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function formatStatus(status) {
    return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

function openPOModal(mode, poId = null) {
    const modal = new bootstrap.Modal(document.getElementById('poModal'));
    const form = document.getElementById('poForm');
    form.reset();

    document.getElementById('poModalTitle').textContent = mode === 'create' ? 'New Purchase Order' : 'Edit Purchase Order';
    document.getElementById('lineItems').innerHTML = '';
    lineItemCount = 0;

    if (mode === 'edit' && poId) {
        loadPOData(poId);
    } else {
        addLineItem();
    }

    modal.show();
}

function loadPOData(poId) {
    fetch(`/staff/api/finance/purchase-orders.php?action=view&id=${poId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const po = data.data;

                document.getElementById('poId').value = po.id;
                document.getElementById('vendorId').value = po.vendor_id;
                document.getElementById('poDate').value = po.po_date;
                document.getElementById('expectedDate').value = po.expected_delivery_date || '';
                document.getElementById('paymentTerms').value = po.payment_terms || 'NET30';
                document.getElementById('shippingMethod').value = po.shipping_method || 'STANDARD';
                document.getElementById('buyerUser').value = po.buyer_user_id || '';
                document.getElementById('notes').value = po.notes || '';
                document.getElementById('shippingCost').value = po.shipping_cost || 0;

                if (po.line_items && po.line_items.length > 0) {
                    po.line_items.forEach(item => {
                        addLineItem(item);
                    });
                } else {
                    addLineItem();
                }

                calculateTotals();

            } else {
                showError('Failed to load PO: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading PO:', error);
            showError('Failed to load PO data');
        });
}

function addLineItem(data = null) {
    lineItemCount++;

    const container = document.getElementById('lineItems');
    const itemDiv = document.createElement('div');
    itemDiv.className = 'card mb-3 line-item';
    itemDiv.id = `line-${lineItemCount}`;

    itemDiv.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Item #${lineItemCount}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLineItem('line-${lineItemCount}')">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Item / Description <span class="text-danger">*</span></label>
                    <input type="text" class="form-control item-description" value="${data?.item_description || ''}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity <span class="text-danger">*</span></label>
                    <input type="number" class="form-control item-qty" value="${data?.quantity || 1}" min="1" step="0.01" required onchange="calculateTotals()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unit Price <span class="text-danger">*</span></label>
                    <input type="number" class="form-control item-price" value="${data?.unit_price || ''}" min="0" step="0.01" required onchange="calculateTotals()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Line Total</label>
                    <input type="text" class="form-control line-total" readonly value="$0.00">
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-3">
                    <label class="form-label">Catalog Item ID</label>
                    <input type="number" class="form-control catalog-item" value="${data?.catalog_item_id || ''}" placeholder="Optional">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tax Rate (%)</label>
                    <input type="number" class="form-control tax-rate" value="${data?.tax_rate || 0}" min="0" step="0.01" onchange="calculateTotals()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Requisition Line ID</label>
                    <input type="number" class="form-control req-line" value="${data?.requisition_line_id || ''}" placeholder="Optional">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Expected Date</label>
                    <input type="date" class="form-control expected-date" value="${data?.expected_delivery_date || ''}">
                </div>
            </div>
        </div>
    `;

    container.appendChild(itemDiv);
    calculateTotals();
}

function removeLineItem(lineId) {
    document.getElementById(lineId).remove();
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    let totalTax = 0;

    document.querySelectorAll('.line-item').forEach(lineDiv => {
        const qty = parseFloat(lineDiv.querySelector('.item-qty').value) || 0;
        const price = parseFloat(lineDiv.querySelector('.item-price').value) || 0;
        const taxRate = parseFloat(lineDiv.querySelector('.tax-rate').value) || 0;

        const lineTotal = qty * price;
        const lineTax = lineTotal * (taxRate / 100);

        lineDiv.querySelector('.line-total').value = `$${lineTotal.toFixed(2)}`;

        subtotal += lineTotal;
        totalTax += lineTax;
    });

    const shippingCost = parseFloat(document.getElementById('shippingCost')?.value || 0);
    const total = subtotal + totalTax + shippingCost;

    document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById('tax').textContent = `$${totalTax.toFixed(2)}`;
    document.getElementById('total').textContent = `$${total.toFixed(2)}`;
}

function savePO(mode) {
    const form = document.getElementById('poForm');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const lineItems = [];
    document.querySelectorAll('.line-item').forEach(lineDiv => {
        const description = lineDiv.querySelector('.item-description').value;
        if (!description) return;

        lineItems.push({
            catalog_item_id: lineDiv.querySelector('.catalog-item').value || null,
            item_description: description,
            quantity: parseFloat(lineDiv.querySelector('.item-qty').value),
            unit_price: parseFloat(lineDiv.querySelector('.item-price').value),
            tax_rate: parseFloat(lineDiv.querySelector('.tax-rate').value) || 0,
            requisition_line_id: lineDiv.querySelector('.req-line').value || null,
            expected_delivery_date: lineDiv.querySelector('.expected-date').value || null
        });
    });

    if (lineItems.length === 0) {
        showError('Please add at least one line item');
        return;
    }

    const poData = {
        id: document.getElementById('poId').value || undefined,
        vendor_id: document.getElementById('vendorId').value,
        po_date: document.getElementById('poDate').value,
        expected_delivery_date: document.getElementById('expectedDate').value || null,
        payment_terms: document.getElementById('paymentTerms').value,
        shipping_method: document.getElementById('shippingMethod').value,
        shipping_cost: parseFloat(document.getElementById('shippingCost').value) || 0,
        buyer_user_id: document.getElementById('buyerUser').value || null,
        notes: document.getElementById('notes').value || null,
        line_items: lineItems,
        approve: (mode === 'approved')
    };

    const action = poData.id ? 'update' : 'create';

    fetch(`/staff/api/finance/purchase-orders.php?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(poData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            bootstrap.Modal.getInstance(document.getElementById('poModal')).hide();
            loadPOs();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error saving PO:', error);
        showError('Failed to save PO');
    });
}

function viewPO(poId) {
    fetch(`/staff/api/finance/purchase-orders.php?action=view&id=${poId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderPODetails(data.data);
                const modal = new bootstrap.Modal(document.getElementById('viewPOModal'));
                modal.show();
            } else {
                showError('Failed to load PO: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading PO:', error);
            showError('Failed to load PO');
        });
}

function renderPODetails(po) {
    const container = document.getElementById('poDetails');

    let html = `
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>${escapeHtml(po.po_number || 'DRAFT')}</h5>
                <p class="text-muted">Vendor: ${escapeHtml(po.vendor_name || 'Vendor #' + po.vendor_id)}</p>
            </div>
            <div class="col-md-6 text-end">
                <span class="badge bg-${getStatusColor(po.status)} fs-6">${formatStatus(po.status)}</span>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <strong>PO Date:</strong> ${new Date(po.po_date).toLocaleDateString()}<br>
                <strong>Expected Delivery:</strong> ${po.expected_delivery_date ? new Date(po.expected_delivery_date).toLocaleDateString() : 'N/A'}<br>
                <strong>Payment Terms:</strong> ${po.payment_terms}
            </div>
            <div class="col-md-6">
                <strong>Shipping Method:</strong> ${po.shipping_method}<br>
                <strong>Buyer:</strong> ${escapeHtml(po.buyer_name || 'N/A')}<br>
                <strong>Status:</strong> ${formatStatus(po.status)}
            </div>
        </div>

        ${po.notes ? `
            <div class="alert alert-info">
                <strong>Notes:</strong><br>
                ${escapeHtml(po.notes)}
            </div>
        ` : ''}

        <h6 class="border-bottom pb-2">Line Items</h6>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Tax</th>
                    <th>Received</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                ${po.line_items.map(item => `
                    <tr>
                        <td>${escapeHtml(item.item_description)}</td>
                        <td>${item.quantity}</td>
                        <td>$${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td>${item.tax_rate}%</td>
                        <td>${item.received_quantity || 0} / ${item.quantity}</td>
                        <td class="text-end">$${parseFloat(item.line_total).toFixed(2)}</td>
                    </tr>
                `).join('')}
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="text-end">Subtotal:</th>
                    <th class="text-end">$${parseFloat(po.subtotal_amount).toFixed(2)}</th>
                </tr>
                <tr>
                    <th colspan="5" class="text-end">Tax:</th>
                    <th class="text-end">$${parseFloat(po.tax_amount).toFixed(2)}</th>
                </tr>
                <tr>
                    <th colspan="5" class="text-end">Shipping:</th>
                    <th class="text-end">$${parseFloat(po.shipping_cost || 0).toFixed(2)}</th>
                </tr>
                <tr>
                    <th colspan="5" class="text-end">Total:</th>
                    <th class="text-end">$${parseFloat(po.total_amount).toFixed(2)}</th>
                </tr>
            </tfoot>
        </table>
    `;

    container.innerHTML = html;
}

function getStatusColor(status) {
    const colors = {
        'draft': 'secondary',
        'approved': 'success',
        'sent': 'info',
        'partially_received': 'warning',
        'fully_received': 'primary',
        'closed': 'dark',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

function editPO(poId) {
    openPOModal('edit', poId);
}

function printPO() {
    window.print();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showSuccess(message) {
    alert('Success: ' + message);
}

function showError(message) {
    alert('Error: ' + message);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
