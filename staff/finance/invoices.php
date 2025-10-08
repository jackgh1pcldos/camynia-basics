<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'AP Invoices';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="bi bi-file-earmark-text"></i> AP Invoices
        </h1>
        <button type="button" class="btn btn-primary" onclick="openInvoiceModal('create')">
            <i class="bi bi-plus-circle"></i> New Invoice
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus" onchange="loadInvoices()">
                        <option value="">All Status</option>
                        <option value="draft" selected>Draft</option>
                        <option value="pending_match">Pending Match</option>
                        <option value="matched">Matched</option>
                        <option value="variance">Variance</option>
                        <option value="approved">Approved</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Match Status</label>
                    <select class="form-select" id="filterMatch" onchange="loadInvoices()">
                        <option value="">All</option>
                        <option value="matched">Matched</option>
                        <option value="variance">Variance</option>
                        <option value="exception">Exception</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Vendor</label>
                    <input type="number" class="form-control" id="filterVendor" placeholder="Vendor ID" onchange="loadInvoices()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" id="filterDateFrom" onchange="loadInvoices()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Due Date From</label>
                    <input type="date" class="form-control" id="filterDueFrom" onchange="loadInvoices()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="Invoice #..." onkeyup="loadInvoices()">
                </div>
            </div>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th>Vendor</th>
                            <th>PO #</th>
                            <th>Amount</th>
                            <th>Match Status</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="invoicesTableBody">
                        <tr>
                            <td colspan="9" class="text-center">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                Loading invoices...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalTitle">New Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="invoiceForm">
                    <input type="hidden" id="invoiceId" name="id">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Vendor <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="vendorId" name="vendor_id" required placeholder="Vendor ID">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vendor Invoice # <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="vendorInvoiceNumber" name="vendor_invoice_number" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purchase Order</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="poId" name="po_id" placeholder="PO ID (optional)">
                                <button type="button" class="btn btn-outline-secondary" onclick="loadPOForInvoice()">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Invoice Date</label>
                            <input type="date" class="form-control" id="invoiceDate" name="invoice_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="dueDate" name="due_date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Payment Terms</label>
                            <select class="form-select" id="paymentTerms" name="payment_terms">
                                <option value="NET30">Net 30</option>
                                <option value="NET60">Net 60</option>
                                <option value="NET90">Net 90</option>
                                <option value="COD">Cash on Delivery</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Currency</label>
                            <select class="form-select" id="currency" name="currency">
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="GBP">GBP</option>
                            </select>
                        </div>
                    </div>

                    <div id="poInfo" class="alert alert-info" style="display: none;">
                        <strong>PO Details:</strong> <span id="poDetails"></span>
                    </div>

                    <h6 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-list-ul"></i> Line Items
                    </h6>

                    <div id="lineItems"></div>

                    <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addLineItem()">
                        <i class="bi bi-plus-circle"></i> Add Line Item
                    </button>

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
                                    <div class="d-flex justify-content-between border-top pt-2">
                                        <strong>Total:</strong>
                                        <strong id="total">$0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="matchResult" class="mt-3" style="display: none;"></div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" onclick="saveInvoice('draft')">
                    <i class="bi bi-save"></i> Save as Draft
                </button>
                <button type="button" class="btn btn-primary" onclick="saveInvoice('submit')">
                    <i class="bi bi-check-circle"></i> Submit for Matching
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentFilters = {
    status: 'draft',
    match_status: '',
    vendor_id: '',
    date_from: '',
    due_from: '',
    search: ''
};

let lineItemCount = 0;
let currentPO = null;

document.addEventListener('DOMContentLoaded', function() {
    loadInvoices();
});

function loadInvoices() {
    currentFilters.status = document.getElementById('filterStatus').value;
    currentFilters.match_status = document.getElementById('filterMatch').value;
    currentFilters.vendor_id = document.getElementById('filterVendor').value;
    currentFilters.date_from = document.getElementById('filterDateFrom').value;
    currentFilters.due_from = document.getElementById('filterDueFrom').value;
    currentFilters.search = document.getElementById('filterSearch').value;

    const params = new URLSearchParams();
    if (currentFilters.status) params.append('status', currentFilters.status);
    if (currentFilters.match_status) params.append('match_status', currentFilters.match_status);
    if (currentFilters.vendor_id) params.append('vendor_id', currentFilters.vendor_id);
    if (currentFilters.date_from) params.append('date_from', currentFilters.date_from);
    if (currentFilters.due_from) params.append('due_from', currentFilters.due_from);
    if (currentFilters.search) params.append('search', currentFilters.search);

    fetch(`/staff/api/finance/ap-invoices.php?action=list&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderInvoices(data.data);
            } else {
                showError('Failed to load invoices: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading invoices:', error);
            showError('Failed to load invoices');
        });
}

function renderInvoices(invoices) {
    const tbody = document.getElementById('invoicesTableBody');

    if (invoices.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center text-muted">
                    <i class="bi bi-inbox"></i> No invoices found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = invoices.map(inv => {
        const statusColors = {
            'draft': 'secondary',
            'pending_match': 'warning',
            'matched': 'success',
            'variance': 'danger',
            'approved': 'info',
            'paid': 'primary'
        };

        const matchColors = {
            'pending': 'secondary',
            'matched': 'success',
            'variance': 'warning',
            'exception': 'danger',
            'manual_override': 'info'
        };

        return `
            <tr>
                <td><strong>${escapeHtml(inv.vendor_invoice_number)}</strong></td>
                <td>${new Date(inv.invoice_date).toLocaleDateString()}</td>
                <td>${inv.due_date ? new Date(inv.due_date).toLocaleDateString() : 'N/A'}</td>
                <td>${escapeHtml(inv.vendor_name || 'Vendor #' + inv.vendor_id)}</td>
                <td>${escapeHtml(inv.po_number || '-')}</td>
                <td><strong>$${parseFloat(inv.total_amount || 0).toFixed(2)}</strong></td>
                <td>
                    ${inv.match_status ? `<span class="badge bg-${matchColors[inv.match_status]}">${formatStatus(inv.match_status)}</span>` : '-'}
                </td>
                <td>
                    <span class="badge bg-${statusColors[inv.status]}">
                        ${formatStatus(inv.status)}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewInvoice(${inv.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function formatStatus(status) {
    return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

function openInvoiceModal(mode, invId = null) {
    const modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
    const form = document.getElementById('invoiceForm');
    form.reset();

    document.getElementById('invoiceModalTitle').textContent = mode === 'create' ? 'New Invoice' : 'Edit Invoice';
    document.getElementById('lineItems').innerHTML = '';
    document.getElementById('poInfo').style.display = 'none';
    document.getElementById('matchResult').style.display = 'none';
    lineItemCount = 0;
    currentPO = null;

    addLineItem();
    modal.show();
}

function loadPOForInvoice() {
    const poId = document.getElementById('poId').value;

    if (!poId) {
        return;
    }

    fetch(`/staff/api/finance/purchase-orders.php?action=view&id=${poId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentPO = data.data;
                displayPOForInvoice(currentPO);
            } else {
                showError('Failed to load PO: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading PO:', error);
            showError('Failed to load PO');
        });
}

function displayPOForInvoice(po) {
    document.getElementById('poInfo').style.display = 'block';
    document.getElementById('poDetails').textContent = `${po.po_number} - ${po.vendor_name} - Total: $${parseFloat(po.total_amount).toFixed(2)}`;

    document.getElementById('vendorId').value = po.vendor_id;
    document.getElementById('paymentTerms').value = po.payment_terms;

    document.getElementById('lineItems').innerHTML = '';
    lineItemCount = 0;

    po.line_items.forEach(item => {
        addLineItem({
            po_line_id: item.id,
            item_description: item.item_description,
            quantity: item.quantity,
            unit_price: item.unit_price,
            tax_rate: item.tax_rate
        });
    });

    calculateTotals();
}

function addLineItem(data = null) {
    lineItemCount++;

    const container = document.getElementById('lineItems');
    const itemDiv = document.createElement('div');
    itemDiv.className = 'card mb-2 line-item';
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
                <div class="col-md-5">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm item-description" value="${data?.item_description || ''}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" class="form-control form-control-sm item-qty" value="${data?.quantity || 1}" min="0" step="0.01" onchange="calculateTotals()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unit Price</label>
                    <input type="number" class="form-control form-control-sm item-price" value="${data?.unit_price || ''}" min="0" step="0.01" onchange="calculateTotals()">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Tax %</label>
                    <input type="number" class="form-control form-control-sm tax-rate" value="${data?.tax_rate || 0}" min="0" step="0.01" onchange="calculateTotals()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Line Total</label>
                    <input type="text" class="form-control form-control-sm line-total" readonly value="$0.00">
                </div>
            </div>
            <input type="hidden" class="po-line-id" value="${data?.po_line_id || ''}">
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

    const total = subtotal + totalTax;

    document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById('tax').textContent = `$${totalTax.toFixed(2)}`;
    document.getElementById('total').textContent = `$${total.toFixed(2)}`;
}

function saveInvoice(mode) {
    const form = document.getElementById('invoiceForm');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const lineItems = [];
    document.querySelectorAll('.line-item').forEach(lineDiv => {
        const description = lineDiv.querySelector('.item-description').value;
        if (!description) return;

        lineItems.push({
            po_line_id: lineDiv.querySelector('.po-line-id').value || null,
            item_description: description,
            quantity: parseFloat(lineDiv.querySelector('.item-qty').value),
            unit_price: parseFloat(lineDiv.querySelector('.item-price').value),
            tax_rate: parseFloat(lineDiv.querySelector('.tax-rate').value) || 0
        });
    });

    if (lineItems.length === 0) {
        showError('Please add at least one line item');
        return;
    }

    const invoiceData = {
        vendor_id: document.getElementById('vendorId').value,
        vendor_invoice_number: document.getElementById('vendorInvoiceNumber').value,
        po_id: document.getElementById('poId').value || null,
        invoice_date: document.getElementById('invoiceDate').value,
        due_date: document.getElementById('dueDate').value || null,
        payment_terms: document.getElementById('paymentTerms').value,
        currency: document.getElementById('currency').value,
        line_items: lineItems,
        submit: (mode === 'submit')
    };

    fetch(`/staff/api/finance/ap-invoices.php?action=create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(invoiceData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            if (data.match_result) {
                displayMatchResult(data.match_result);
            } else {
                bootstrap.Modal.getInstance(document.getElementById('invoiceModal')).hide();
                loadInvoices();
            }
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error saving invoice:', error);
        showError('Failed to save invoice');
    });
}

function displayMatchResult(result) {
    const container = document.getElementById('matchResult');
    container.style.display = 'block';

    let alertClass = 'info';
    if (result.status === 'matched') alertClass = 'success';
    if (result.status === 'variance' || result.status === 'exception') alertClass = 'warning';

    container.innerHTML = `
        <div class="alert alert-${alertClass}">
            <h6>3-Way Match Result: ${formatStatus(result.status)}</h6>
            ${result.message ? `<p>${result.message}</p>` : ''}
            ${result.price_variance ? `<p>Price Variance: $${result.price_variance.toFixed(2)}</p>` : ''}
            ${result.quantity_variance ? `<p>Quantity Variance: ${result.quantity_variance}</p>` : ''}
        </div>
    `;
}

function viewInvoice(invId) {
    alert('View invoice #' + invId + ' - Full view implementation would be here');
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
