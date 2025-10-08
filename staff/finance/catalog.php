<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'Catalog Items';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="bi bi-box-seam"></i> Catalog Items
        </h1>
        <button type="button" class="btn btn-primary" onclick="openItemModal('create')">
            <i class="bi bi-plus-circle"></i> New Catalog Item
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Item Type</label>
                    <select class="form-select" id="filterItemType" onchange="loadItems()">
                        <option value="">All Types</option>
                        <option value="goods">Goods</option>
                        <option value="service">Service</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Inventory Item</label>
                    <select class="form-select" id="filterInventory" onchange="loadItems()">
                        <option value="">All Items</option>
                        <option value="1">Inventory Items</option>
                        <option value="0">Non-Inventory</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus" onchange="loadItems()">
                        <option value="">All Status</option>
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="SKU, name, description..." onkeyup="loadItems()">
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>UoM</th>
                            <th>Unit Price</th>
                            <th>Inventory</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <tr>
                            <td colspan="8" class="text-center">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                Loading catalog items...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalTitle">New Catalog Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Nav Tabs -->
                <ul class="nav nav-tabs mb-3" id="itemTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#basicInfo">
                            <i class="bi bi-info-circle"></i> Basic Info
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pricing">
                            <i class="bi bi-currency-dollar"></i> Pricing
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#accounting">
                            <i class="bi bi-calculator"></i> Accounting
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#vendors">
                            <i class="bi bi-people"></i> Vendors
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#inventory">
                            <i class="bi bi-box"></i> Inventory
                        </button>
                    </li>
                </ul>

                <form id="itemForm">
                    <input type="hidden" id="itemId" name="id">

                    <!-- Basic Info Tab -->
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="basicInfo" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">SKU / Item Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="itemSku" name="item_sku" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Item Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="itemName" name="item_name" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Item Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="itemType" name="item_type" required>
                                        <option value="goods">Goods</option>
                                        <option value="service">Service</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Unit of Measure <span class="text-danger">*</span></label>
                                    <select class="form-select" id="uom" name="uom" required>
                                        <option value="EA">Each (EA)</option>
                                        <option value="PCS">Pieces (PCS)</option>
                                        <option value="BOX">Box (BOX)</option>
                                        <option value="KG">Kilogram (KG)</option>
                                        <option value="LB">Pound (LB)</option>
                                        <option value="HR">Hour (HR)</option>
                                        <option value="DAY">Day (DAY)</option>
                                        <option value="MTH">Month (MTH)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Category</label>
                                    <input type="text" class="form-control" id="category" name="category">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing Tab -->
                        <div class="tab-pane fade" id="pricing" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Standard Cost</label>
                                    <input type="number" class="form-control" id="standardCost" name="standard_cost" step="0.01" min="0">
                                    <small class="text-muted">Average purchase price</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">List Price</label>
                                    <input type="number" class="form-control" id="listPrice" name="list_price" step="0.01" min="0">
                                    <small class="text-muted">Standard selling price</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Currency</label>
                                    <select class="form-select" id="currency" name="currency">
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                        <option value="GBP">GBP</option>
                                    </select>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Vendor-specific pricing</strong> can be configured in the Vendors tab below.
                            </div>
                        </div>

                        <!-- Accounting Tab -->
                        <div class="tab-pane fade" id="accounting" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Expense GL Account</label>
                                    <input type="text" class="form-control" id="expenseGl" name="expense_gl_account" placeholder="e.g., 5000">
                                    <small class="text-muted">For non-inventory purchases</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Inventory Asset GL</label>
                                    <input type="text" class="form-control" id="inventoryGl" name="inventory_gl_account" placeholder="e.g., 1200">
                                    <small class="text-muted">For inventory items</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">COGS GL Account</label>
                                    <input type="text" class="form-control" id="cogsGl" name="cogs_gl_account" placeholder="e.g., 5100">
                                    <small class="text-muted">Cost of goods sold</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tax Code</label>
                                    <input type="text" class="form-control" id="taxCode" name="tax_code" placeholder="e.g., VAT-20">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="taxable" name="is_taxable" value="1">
                                        <label class="form-check-label" for="taxable">
                                            This item is taxable
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vendors Tab -->
                        <div class="tab-pane fade" id="vendors" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Preferred Vendor</label>
                                    <input type="number" class="form-control" id="preferredVendor" name="preferred_vendor_id" placeholder="Vendor ID">
                                    <small class="text-muted">Primary supplier for this item</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Vendor SKU</label>
                                    <input type="text" class="form-control" id="vendorSku" name="vendor_sku" placeholder="Vendor's part number">
                                </div>
                            </div>

                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Coming Soon:</strong> Multi-vendor price lists with tier pricing will be managed here.
                            </div>
                        </div>

                        <!-- Inventory Tab -->
                        <div class="tab-pane fade" id="inventory" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="isInventory" name="is_inventory_item" value="1">
                                        <label class="form-check-label" for="isInventory">
                                            <strong>This is an inventory item</strong> (track stock levels)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div id="inventoryFields" style="display: none;">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Reorder Point</label>
                                        <input type="number" class="form-control" id="reorderPoint" name="reorder_point" step="0.01" min="0">
                                        <small class="text-muted">Alert when stock falls below</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Reorder Quantity</label>
                                        <input type="number" class="form-control" id="reorderQty" name="reorder_qty" step="0.01" min="0">
                                        <small class="text-muted">Standard order quantity</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Lead Time (days)</label>
                                        <input type="number" class="form-control" id="leadTime" name="lead_time_days" min="0">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="trackSerial" name="track_serial_numbers" value="1">
                                            <label class="form-check-label" for="trackSerial">
                                                Track Serial Numbers
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="trackBatch" name="track_batch_numbers" value="1">
                                            <label class="form-check-label" for="trackBatch">
                                                Track Batch/Lot Numbers
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="trackExpiry" name="track_expiry" value="1">
                                            <label class="form-check-label" for="trackExpiry">
                                                Track Expiry Dates
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveItem()">
                    <i class="bi bi-save"></i> Save Item
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentFilters = {
    item_type: '',
    is_inventory_item: '',
    status: 'active',
    search: ''
};

// Load items on page load
document.addEventListener('DOMContentLoaded', function() {
    loadItems();

    // Show/hide inventory fields based on checkbox
    document.getElementById('isInventory').addEventListener('change', function() {
        document.getElementById('inventoryFields').style.display = this.checked ? 'block' : 'none';
    });
});

function loadItems() {
    currentFilters.item_type = document.getElementById('filterItemType').value;
    currentFilters.is_inventory_item = document.getElementById('filterInventory').value;
    currentFilters.status = document.getElementById('filterStatus').value;
    currentFilters.search = document.getElementById('filterSearch').value;

    const params = new URLSearchParams();
    if (currentFilters.item_type) params.append('item_type', currentFilters.item_type);
    if (currentFilters.is_inventory_item) params.append('is_inventory_item', currentFilters.is_inventory_item);
    if (currentFilters.status) params.append('status', currentFilters.status);
    if (currentFilters.search) params.append('search', currentFilters.search);

    fetch(`/staff/api/finance/catalog.php?action=list&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderItems(data.data);
            } else {
                showError('Failed to load items: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading items:', error);
            showError('Failed to load items');
        });
}

function renderItems(items) {
    const tbody = document.getElementById('itemsTableBody');

    if (items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted">
                    <i class="bi bi-inbox"></i> No catalog items found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = items.map(item => `
        <tr>
            <td><strong>${escapeHtml(item.item_sku)}</strong></td>
            <td>${escapeHtml(item.item_name)}</td>
            <td><span class="badge bg-secondary">${item.item_type}</span></td>
            <td>${item.uom}</td>
            <td>$${parseFloat(item.list_price || 0).toFixed(2)}</td>
            <td>
                ${item.is_inventory_item == 1 ?
                    '<span class="badge bg-info">Inventory</span>' :
                    '<span class="badge bg-light text-dark">Non-Inv</span>'}
            </td>
            <td>
                <span class="badge bg-${item.status === 'active' ? 'success' : 'secondary'}">
                    ${item.status}
                </span>
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="viewItem(${item.id})">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="editItem(${item.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteItem(${item.id}, '${escapeHtml(item.item_sku)}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function openItemModal(mode, itemId = null) {
    const modal = new bootstrap.Modal(document.getElementById('itemModal'));
    const form = document.getElementById('itemForm');
    form.reset();

    document.getElementById('itemModalTitle').textContent = mode === 'create' ? 'New Catalog Item' : 'Edit Catalog Item';
    document.getElementById('inventoryFields').style.display = 'none';

    if (mode === 'edit' && itemId) {
        loadItemData(itemId);
    }

    modal.show();
}

function loadItemData(itemId) {
    fetch(`/staff/api/finance/catalog.php?action=view&id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = data.data;

                document.getElementById('itemId').value = item.id;
                document.getElementById('itemSku').value = item.item_sku;
                document.getElementById('itemName').value = item.item_name;
                document.getElementById('description').value = item.description || '';
                document.getElementById('itemType').value = item.item_type;
                document.getElementById('uom').value = item.uom;
                document.getElementById('category').value = item.category || '';
                document.getElementById('status').value = item.status;

                // Pricing
                document.getElementById('standardCost').value = item.standard_cost || '';
                document.getElementById('listPrice').value = item.list_price || '';
                document.getElementById('currency').value = item.currency || 'USD';

                // Accounting
                document.getElementById('expenseGl').value = item.expense_gl_account || '';
                document.getElementById('inventoryGl').value = item.inventory_gl_account || '';
                document.getElementById('cogsGl').value = item.cogs_gl_account || '';
                document.getElementById('taxCode').value = item.tax_code || '';
                document.getElementById('taxable').checked = item.is_taxable == 1;

                // Vendors
                document.getElementById('preferredVendor').value = item.preferred_vendor_id || '';
                document.getElementById('vendorSku').value = item.vendor_sku || '';

                // Inventory
                document.getElementById('isInventory').checked = item.is_inventory_item == 1;
                if (item.is_inventory_item == 1) {
                    document.getElementById('inventoryFields').style.display = 'block';
                    document.getElementById('reorderPoint').value = item.reorder_point || '';
                    document.getElementById('reorderQty').value = item.reorder_qty || '';
                    document.getElementById('leadTime').value = item.lead_time_days || '';
                    document.getElementById('trackSerial').checked = item.track_serial_numbers == 1;
                    document.getElementById('trackBatch').checked = item.track_batch_numbers == 1;
                    document.getElementById('trackExpiry').checked = item.track_expiry == 1;
                }

            } else {
                showError('Failed to load item: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading item:', error);
            showError('Failed to load item data');
        });
}

function saveItem() {
    const form = document.getElementById('itemForm');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const itemData = {};

    for (let [key, value] of formData.entries()) {
        itemData[key] = value;
    }

    // Handle checkboxes
    itemData.is_taxable = document.getElementById('taxable').checked ? 1 : 0;
    itemData.is_inventory_item = document.getElementById('isInventory').checked ? 1 : 0;
    itemData.track_serial_numbers = document.getElementById('trackSerial').checked ? 1 : 0;
    itemData.track_batch_numbers = document.getElementById('trackBatch').checked ? 1 : 0;
    itemData.track_expiry = document.getElementById('trackExpiry').checked ? 1 : 0;

    const action = itemData.id ? 'update' : 'create';

    fetch(`/staff/api/finance/catalog.php?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(itemData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
            loadItems();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error saving item:', error);
        showError('Failed to save item');
    });
}

function viewItem(itemId) {
    openItemModal('edit', itemId);
    // Make form read-only
    document.querySelectorAll('#itemForm input, #itemForm select, #itemForm textarea').forEach(el => {
        el.disabled = true;
    });
    document.querySelector('#itemModal .modal-footer .btn-primary').style.display = 'none';
}

function editItem(itemId) {
    openItemModal('edit', itemId);
}

function deleteItem(itemId, itemSku) {
    if (!confirm(`Are you sure you want to delete item "${itemSku}"?\n\nThis will deactivate the item but preserve it in the audit trail.`)) {
        return;
    }

    fetch(`/staff/api/finance/catalog.php?action=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: itemId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            loadItems();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting item:', error);
        showError('Failed to delete item');
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
