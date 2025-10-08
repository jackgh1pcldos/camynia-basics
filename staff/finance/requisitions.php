<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'Purchase Requisitions';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="bi bi-file-earmark-text"></i> Purchase Requisitions
        </h1>
        <button type="button" class="btn btn-primary" onclick="openRequisitionModal('create')">
            <i class="bi bi-plus-circle"></i> New Requisition
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus" onchange="loadRequisitions()">
                        <option value="">All Status</option>
                        <option value="draft" selected>Draft</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="converted_to_po">Converted to PO</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Requester</label>
                    <select class="form-select" id="filterRequester" onchange="loadRequisitions()">
                        <option value="">All Requesters</option>
                        <option value="me">My Requisitions</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" id="filterDateFrom" onchange="loadRequisitions()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="PR number, description..." onkeyup="loadRequisitions()">
                </div>
            </div>
        </div>
    </div>

    <!-- Requisitions Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>PR Number</th>
                            <th>Date</th>
                            <th>Requester</th>
                            <th>Description</th>
                            <th>Total Amount</th>
                            <th>Budget Status</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="requisitionsTableBody">
                        <tr>
                            <td colspan="8" class="text-center">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                Loading requisitions...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Requisition Modal -->
<div class="modal fade" id="requisitionModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requisitionModalTitle">New Requisition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="requisitionForm">
                    <input type="hidden" id="requisitionId" name="id">

                    <!-- Header Info -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="description" name="description" required placeholder="Brief description of what you're requesting">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Required By Date</label>
                            <input type="date" class="form-control" id="requiredDate" name="required_by_date">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Cost Center</label>
                            <input type="number" class="form-control" id="costCenter" name="cost_center_id" placeholder="Cost Center ID">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Project (Optional)</label>
                            <input type="text" class="form-control" id="project" name="project_code" placeholder="Project code">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department" placeholder="e.g., IT, Finance">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Justification</label>
                            <textarea class="form-control" id="justification" name="justification" rows="3" placeholder="Why is this purchase necessary?"></textarea>
                        </div>
                    </div>

                    <!-- Line Items Section -->
                    <h6 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-list-ul"></i> Line Items
                    </h6>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Add items to your requisition. You can select from the catalog or enter custom items.
                    </div>

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
                                    <div class="d-flex justify-content-between border-top pt-2">
                                        <strong>Total:</strong>
                                        <strong id="total">$0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Budget Check Result -->
                    <div id="budgetCheckResult" class="mt-3" style="display: none;"></div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" onclick="saveRequisition('draft')">
                    <i class="bi bi-save"></i> Save as Draft
                </button>
                <button type="button" class="btn btn-primary" onclick="saveRequisition('submit')">
                    <i class="bi bi-check-circle"></i> Submit for Approval
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Requisition Modal (includes approval chain) -->
<div class="modal fade" id="viewRequisitionModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalTitle">Requisition Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="requisitionDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentFilters = {
    status: 'draft',
    requester: '',
    date_from: '',
    search: ''
};

let lineItemCount = 0;

// Load requisitions on page load
document.addEventListener('DOMContentLoaded', function() {
    loadRequisitions();
});

function loadRequisitions() {
    currentFilters.status = document.getElementById('filterStatus').value;
    currentFilters.requester = document.getElementById('filterRequester').value;
    currentFilters.date_from = document.getElementById('filterDateFrom').value;
    currentFilters.search = document.getElementById('filterSearch').value;

    const params = new URLSearchParams();
    if (currentFilters.status) params.append('status', currentFilters.status);
    if (currentFilters.requester === 'me') params.append('my_requisitions', '1');
    if (currentFilters.date_from) params.append('date_from', currentFilters.date_from);
    if (currentFilters.search) params.append('search', currentFilters.search);

    fetch(`/staff/api/finance/requisitions.php?action=list&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderRequisitions(data.data);
            } else {
                showError('Failed to load requisitions: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading requisitions:', error);
            showError('Failed to load requisitions');
        });
}

function renderRequisitions(requisitions) {
    const tbody = document.getElementById('requisitionsTableBody');

    if (requisitions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted">
                    <i class="bi bi-inbox"></i> No requisitions found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = requisitions.map(req => {
        const statusColors = {
            'draft': 'secondary',
            'pending_approval': 'warning',
            'approved': 'success',
            'rejected': 'danger',
            'converted_to_po': 'info',
            'cancelled': 'dark'
        };

        const budgetColors = {
            'within_budget': 'success',
            'budget_warning': 'warning',
            'over_budget': 'danger',
            'no_budget': 'secondary'
        };

        return `
            <tr>
                <td><strong>${escapeHtml(req.pr_number || 'DRAFT')}</strong></td>
                <td>${new Date(req.requisition_date).toLocaleDateString()}</td>
                <td>${escapeHtml(req.requester_name || 'Unknown')}</td>
                <td>${escapeHtml(req.description)}</td>
                <td><strong>$${parseFloat(req.total_amount || 0).toFixed(2)}</strong></td>
                <td>
                    <span class="badge bg-${budgetColors[req.budget_check_status] || 'secondary'}">
                        ${formatBudgetStatus(req.budget_check_status)}
                    </span>
                </td>
                <td>
                    <span class="badge bg-${statusColors[req.status]}">
                        ${formatStatus(req.status)}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewRequisition(${req.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                        ${req.status === 'draft' ? `
                            <button class="btn btn-outline-secondary" onclick="editRequisition(${req.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteRequisition(${req.id}, '${escapeHtml(req.pr_number || 'DRAFT')}')">
                                <i class="bi bi-trash"></i>
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

function formatBudgetStatus(status) {
    const labels = {
        'within_budget': 'Within Budget',
        'budget_warning': 'Warning',
        'over_budget': 'Over Budget',
        'no_budget': 'No Budget'
    };
    return labels[status] || status;
}

function openRequisitionModal(mode, reqId = null) {
    const modal = new bootstrap.Modal(document.getElementById('requisitionModal'));
    const form = document.getElementById('requisitionForm');
    form.reset();

    document.getElementById('requisitionModalTitle').textContent = mode === 'create' ? 'New Requisition' : 'Edit Requisition';
    document.getElementById('lineItems').innerHTML = '';
    document.getElementById('budgetCheckResult').style.display = 'none';
    lineItemCount = 0;

    if (mode === 'edit' && reqId) {
        loadRequisitionData(reqId);
    } else {
        // Add one default line item for new requisitions
        addLineItem();
    }

    modal.show();
}

function loadRequisitionData(reqId) {
    fetch(`/staff/api/finance/requisitions.php?action=view&id=${reqId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const req = data.data;

                document.getElementById('requisitionId').value = req.id;
                document.getElementById('description').value = req.description;
                document.getElementById('requiredDate').value = req.required_by_date || '';
                document.getElementById('costCenter').value = req.cost_center_id || '';
                document.getElementById('project').value = req.project_code || '';
                document.getElementById('department').value = req.department || '';
                document.getElementById('justification').value = req.justification || '';

                // Load line items
                if (req.line_items && req.line_items.length > 0) {
                    req.line_items.forEach(item => {
                        addLineItem(item);
                    });
                } else {
                    addLineItem();
                }

                calculateTotals();

            } else {
                showError('Failed to load requisition: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading requisition:', error);
            showError('Failed to load requisition data');
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
                    <input type="text" class="form-control item-description" value="${data?.item_description || ''}" required placeholder="What are you requesting?">
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
                    <label class="form-label">GL Account</label>
                    <input type="text" class="form-control gl-account" value="${data?.gl_account_code || ''}" placeholder="e.g., 5000">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cost Center</label>
                    <input type="number" class="form-control item-cost-center" value="${data?.cost_center_id || ''}" placeholder="Cost Center ID">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tax Rate (%)</label>
                    <input type="number" class="form-control tax-rate" value="${data?.tax_rate || 0}" min="0" step="0.01" onchange="calculateTotals()">
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

    const total = subtotal + totalTax;

    document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById('tax').textContent = `$${totalTax.toFixed(2)}`;
    document.getElementById('total').textContent = `$${total.toFixed(2)}`;
}

function saveRequisition(mode) {
    const form = document.getElementById('requisitionForm');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Collect line items
    const lineItems = [];
    document.querySelectorAll('.line-item').forEach(lineDiv => {
        const description = lineDiv.querySelector('.item-description').value;
        if (!description) return; // Skip empty lines

        lineItems.push({
            catalog_item_id: lineDiv.querySelector('.catalog-item').value || null,
            item_description: description,
            quantity: parseFloat(lineDiv.querySelector('.item-qty').value),
            unit_price: parseFloat(lineDiv.querySelector('.item-price').value),
            tax_rate: parseFloat(lineDiv.querySelector('.tax-rate').value) || 0,
            gl_account_code: lineDiv.querySelector('.gl-account').value || null,
            cost_center_id: lineDiv.querySelector('.item-cost-center').value || null
        });
    });

    if (lineItems.length === 0) {
        showError('Please add at least one line item');
        return;
    }

    const reqData = {
        id: document.getElementById('requisitionId').value || undefined,
        description: document.getElementById('description').value,
        required_by_date: document.getElementById('requiredDate').value || null,
        cost_center_id: document.getElementById('costCenter').value || null,
        project_code: document.getElementById('project').value || null,
        department: document.getElementById('department').value || null,
        justification: document.getElementById('justification').value || null,
        line_items: lineItems
    };

    const action = reqData.id ? 'update' : 'create';
    const submitForApproval = (mode === 'submit');

    fetch(`/staff/api/finance/requisitions.php?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(reqData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (submitForApproval && data.id) {
                // Submit for approval
                submitRequisition(data.id);
            } else {
                showSuccess(data.message);
                bootstrap.Modal.getInstance(document.getElementById('requisitionModal')).hide();
                loadRequisitions();
            }
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error saving requisition:', error);
        showError('Failed to save requisition');
    });
}

function submitRequisition(reqId) {
    fetch(`/staff/api/finance/requisitions.php?action=submit`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: reqId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            bootstrap.Modal.getInstance(document.getElementById('requisitionModal')).hide();
            loadRequisitions();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error submitting requisition:', error);
        showError('Failed to submit requisition');
    });
}

function viewRequisition(reqId) {
    fetch(`/staff/api/finance/requisitions.php?action=view&id=${reqId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderRequisitionDetails(data.data);
                const modal = new bootstrap.Modal(document.getElementById('viewRequisitionModal'));
                modal.show();
            } else {
                showError('Failed to load requisition: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading requisition:', error);
            showError('Failed to load requisition');
        });
}

function renderRequisitionDetails(req) {
    const container = document.getElementById('requisitionDetails');

    let html = `
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>${escapeHtml(req.pr_number || 'DRAFT')}</h5>
                <p class="text-muted">${escapeHtml(req.description)}</p>
            </div>
            <div class="col-md-6 text-end">
                <span class="badge bg-${getStatusColor(req.status)} fs-6">${formatStatus(req.status)}</span>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <strong>Requester:</strong> ${escapeHtml(req.requester_name || 'Unknown')}<br>
                <strong>Date:</strong> ${new Date(req.requisition_date).toLocaleDateString()}<br>
                <strong>Required By:</strong> ${req.required_by_date ? new Date(req.required_by_date).toLocaleDateString() : 'N/A'}
            </div>
            <div class="col-md-6">
                <strong>Department:</strong> ${escapeHtml(req.department || 'N/A')}<br>
                <strong>Cost Center:</strong> ${req.cost_center_id || 'N/A'}<br>
                <strong>Project:</strong> ${escapeHtml(req.project_code || 'N/A')}
            </div>
        </div>

        ${req.justification ? `
            <div class="alert alert-info">
                <strong>Justification:</strong><br>
                ${escapeHtml(req.justification)}
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
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                ${req.line_items.map(item => `
                    <tr>
                        <td>${escapeHtml(item.item_description)}</td>
                        <td>${item.quantity}</td>
                        <td>$${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td>${item.tax_rate}%</td>
                        <td class="text-end">$${parseFloat(item.line_total).toFixed(2)}</td>
                    </tr>
                `).join('')}
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-end">Total:</th>
                    <th class="text-end">$${parseFloat(req.total_amount).toFixed(2)}</th>
                </tr>
            </tfoot>
        </table>
    `;

    container.innerHTML = html;
}

function getStatusColor(status) {
    const colors = {
        'draft': 'secondary',
        'pending_approval': 'warning',
        'approved': 'success',
        'rejected': 'danger',
        'converted_to_po': 'info',
        'cancelled': 'dark'
    };
    return colors[status] || 'secondary';
}

function editRequisition(reqId) {
    openRequisitionModal('edit', reqId);
}

function deleteRequisition(reqId, prNumber) {
    if (!confirm(`Are you sure you want to delete requisition "${prNumber}"?\n\nThis will cancel the requisition.`)) {
        return;
    }

    fetch(`/staff/api/finance/requisitions.php?action=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: reqId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            loadRequisitions();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting requisition:', error);
        showError('Failed to delete requisition');
    });
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
