<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';
require_once __DIR__ . '/../../includes/core/finance-audit.php';

if (!isLoggedIn() || isSupplier()) {
    header('Location: /staff/auth/login.php');
    exit;
}

$pageTitle = 'Vendor Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-building"></i> Vendor Management</h2>
                <p class="text-muted mb-0">Manage suppliers and vendor relationships</p>
            </div>
            <button class="btn btn-primary" onclick="openCreateVendor()">
                <i class="bi bi-plus-circle"></i> New Vendor
            </button>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" id="searchVendor" placeholder="Search vendors...">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="filterStatus">
                            <option value="">All Statuses</option>
                            <option value="active" selected>Active</option>
                            <option value="pending">Pending</option>
                            <option value="suspended">Suspended</option>
                            <option value="blocked">Blocked</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="filterRisk">
                            <option value="">All Risk Levels</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="filterCategory">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vendors Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Vendor Code</th>
                                <th>Vendor Name</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Risk Level</th>
                                <th>Payment Terms</th>
                                <th>Total Spend</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="vendorsTable">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="spinner-border text-primary"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Vendor Detail Modal -->
<div class="modal fade" id="vendorModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="vendorModalTitle">Vendor Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Nav Tabs -->
                <ul class="nav nav-tabs mb-3" id="vendorTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                            <i class="bi bi-info-circle"></i> Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button">
                            <i class="bi bi-people"></i> Contacts
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button">
                            <i class="bi bi-file-earmark-text"></i> Documents
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button">
                            <i class="bi bi-clock-history"></i> Audit History
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="performance-tab" data-bs-toggle="tab" data-bs-target="#performance" type="button">
                            <i class="bi bi-graph-up"></i> Performance
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="vendorTabContent">
                    <!-- Information Tab -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <form id="vendorForm">
                            <input type="hidden" id="vendorId">

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="border-bottom pb-2 mb-3">Basic Information</h6>

                                    <div class="mb-3">
                                        <label class="form-label">Vendor Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="vendorCode" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="vendorName" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Legal Name</label>
                                        <input type="text" class="form-control" id="legalName">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select class="form-select" id="categoryId">
                                            <option value="">Select Category</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Website</label>
                                        <input type="url" class="form-control" id="website">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="border-bottom pb-2 mb-3">Primary Contact</h6>

                                    <div class="mb-3">
                                        <label class="form-label">Contact Name</label>
                                        <input type="text" class="form-control" id="primaryContactName">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Contact Email</label>
                                        <input type="email" class="form-control" id="primaryContactEmail">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Contact Phone</label>
                                        <input type="tel" class="form-control" id="primaryContactPhone">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <h6 class="border-bottom pb-2 mb-3">Address</h6>

                                    <div class="mb-3">
                                        <label class="form-label">Address Line 1</label>
                                        <input type="text" class="form-control" id="addressLine1">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Address Line 2</label>
                                        <input type="text" class="form-control" id="addressLine2">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">City</label>
                                            <input type="text" class="form-control" id="city">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">State/Province</label>
                                            <input type="text" class="form-control" id="stateProvince">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Postal Code</label>
                                            <input type="text" class="form-control" id="postalCode">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Country</label>
                                            <select class="form-select" id="country">
                                                <option value="US">United States</option>
                                                <option value="GB">United Kingdom</option>
                                                <option value="CA">Canada</option>
                                                <option value="AU">Australia</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="border-bottom pb-2 mb-3">Tax & Payment</h6>

                                    <div class="mb-3">
                                        <label class="form-label">Tax ID Type</label>
                                        <select class="form-select" id="taxIdType">
                                            <option value="">Select Type</option>
                                            <option value="EIN">EIN (US)</option>
                                            <option value="SSN">SSN (US)</option>
                                            <option value="VAT">VAT Number (EU)</option>
                                            <option value="NIF">NIF (Portugal)</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Tax ID Number</label>
                                        <input type="text" class="form-control" id="taxId">
                                        <small class="text-muted">Encrypted at rest</small>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="taxExempt">
                                        <label class="form-check-label" for="taxExempt">Tax Exempt</label>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is1099Vendor">
                                        <label class="form-check-label" for="is1099Vendor">1099 Vendor</label>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Payment Terms</label>
                                        <select class="form-select" id="paymentTerms">
                                            <option value="NET30">Net 30</option>
                                            <option value="NET60">Net 60</option>
                                            <option value="NET90">Net 90</option>
                                            <option value="COD">COD</option>
                                            <option value="PREPAID">Prepaid</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Payment Method</label>
                                        <select class="form-select" id="paymentMethod">
                                            <option value="CHECK">Check</option>
                                            <option value="ACH">ACH</option>
                                            <option value="WIRE">Wire Transfer</option>
                                            <option value="CARD">Credit Card</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Credit Limit</label>
                                        <input type="number" step="0.01" class="form-control" id="creditLimit" value="0.00">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <h6 class="border-bottom pb-2 mb-3">Risk Assessment</h6>

                                    <div class="mb-3">
                                        <label class="form-label">Risk Level</label>
                                        <select class="form-select" id="riskLevel">
                                            <option value="low">Low</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="high">High</option>
                                            <option value="critical">Critical</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Risk Score (0-100)</label>
                                        <input type="number" min="0" max="100" class="form-control" id="riskScore" value="50">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Risk Notes</label>
                                        <textarea class="form-control" id="riskNotes" rows="3"></textarea>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="border-bottom pb-2 mb-3">Status & Compliance</h6>

                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" id="status">
                                            <option value="pending">Pending</option>
                                            <option value="active">Active</option>
                                            <option value="suspended">Suspended</option>
                                            <option value="blocked">Blocked</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Onboarding Status</label>
                                        <select class="form-select" id="onboardingStatus">
                                            <option value="new">New</option>
                                            <option value="documents_pending">Documents Pending</option>
                                            <option value="review">Under Review</option>
                                            <option value="approved">Approved</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Contacts Tab -->
                    <div class="tab-pane fade" id="contacts" role="tabpanel">
                        <button class="btn btn-sm btn-primary mb-3" onclick="addContact()">
                            <i class="bi bi-plus"></i> Add Contact
                        </button>
                        <div id="contactsList"></div>
                    </div>

                    <!-- Documents Tab -->
                    <div class="tab-pane fade" id="documents" role="tabpanel">
                        <button class="btn btn-sm btn-primary mb-3" onclick="uploadDocument()">
                            <i class="bi bi-upload"></i> Upload Document
                        </button>
                        <div id="documentsList"></div>
                    </div>

                    <!-- Audit History Tab -->
                    <div class="tab-pane fade" id="history" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <strong>Complete Audit Trail</strong> - Every change is logged with who, what, when, and why.
                        </div>
                        <div id="auditHistory"></div>
                    </div>

                    <!-- Performance Tab -->
                    <div class="tab-pane fade" id="performance" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Total Orders</h6>
                                        <h3 id="perfTotalOrders">-</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Total Spend</h6>
                                        <h3 id="perfTotalSpend">-</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>On-Time Delivery</h6>
                                        <h3 id="perfOnTime">-</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveVendor()">
                    <i class="bi bi-save"></i> Save Vendor
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let vendors = [];
let currentVendor = null;

document.addEventListener('DOMContentLoaded', function() {
    loadVendors();
    loadCategories();
});

async function loadVendors() {
    try {
        const status = document.getElementById('filterStatus').value;
        const risk = document.getElementById('filterRisk').value;
        const search = document.getElementById('searchVendor').value;

        const params = new URLSearchParams();
        if (status) params.append('status', status);
        if (risk) params.append('risk_level', risk);
        if (search) params.append('search', search);

        const response = await fetch(`/staff/api/finance/vendors.php?action=list&${params}`);
        const result = await response.json();

        if (result.success) {
            vendors = result.data;
            renderVendors();
        }
    } catch (error) {
        console.error('Error loading vendors:', error);
    }
}

function renderVendors() {
    const tbody = document.getElementById('vendorsTable');

    if (vendors.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No vendors found</td></tr>';
        return;
    }

    tbody.innerHTML = vendors.map(vendor => `
        <tr>
            <td><strong>${escapeHtml(vendor.vendor_code)}</strong></td>
            <td>${escapeHtml(vendor.vendor_name)}</td>
            <td>
                ${vendor.primary_contact_email ? escapeHtml(vendor.primary_contact_email) : '-'}<br>
                <small class="text-muted">${vendor.primary_contact_phone || ''}</small>
            </td>
            <td><span class="badge bg-${getStatusColor(vendor.status)}">${vendor.status}</span></td>
            <td><span class="badge bg-${getRiskColor(vendor.risk_level)}">${vendor.risk_level}</span></td>
            <td>${vendor.payment_terms}</td>
            <td>$${parseFloat(vendor.total_spend || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewVendor(${vendor.id})">
                    <i class="bi bi-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'active': 'success',
        'suspended': 'danger',
        'blocked': 'dark',
        'inactive': 'secondary'
    };
    return colors[status] || 'secondary';
}

function getRiskColor(risk) {
    const colors = {
        'low': 'success',
        'medium': 'warning',
        'high': 'danger',
        'critical': 'dark'
    };
    return colors[risk] || 'secondary';
}

async function viewVendor(vendorId) {
    try {
        const response = await fetch(`/staff/api/finance/vendors.php?action=view&id=${vendorId}`);
        const result = await response.json();

        if (result.success) {
            currentVendor = result.data;
            populateVendorForm(currentVendor);
            loadAuditHistory(vendorId);

            const modal = new bootstrap.Modal(document.getElementById('vendorModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading vendor:', error);
        alert('Error loading vendor details');
    }
}

function populateVendorForm(vendor) {
    document.getElementById('vendorModalTitle').textContent = `Vendor: ${vendor.vendor_name}`;
    document.getElementById('vendorId').value = vendor.id;
    document.getElementById('vendorCode').value = vendor.vendor_code;
    document.getElementById('vendorName').value = vendor.vendor_name;
    document.getElementById('legalName').value = vendor.legal_name || '';
    document.getElementById('categoryId').value = vendor.category_id || '';
    document.getElementById('website').value = vendor.website || '';

    document.getElementById('primaryContactName').value = vendor.primary_contact_name || '';
    document.getElementById('primaryContactEmail').value = vendor.primary_contact_email || '';
    document.getElementById('primaryContactPhone').value = vendor.primary_contact_phone || '';

    document.getElementById('addressLine1').value = vendor.address_line1 || '';
    document.getElementById('addressLine2').value = vendor.address_line2 || '';
    document.getElementById('city').value = vendor.city || '';
    document.getElementById('stateProvince').value = vendor.state_province || '';
    document.getElementById('postalCode').value = vendor.postal_code || '';
    document.getElementById('country').value = vendor.country || 'US';

    document.getElementById('taxIdType').value = vendor.tax_id_type || '';
    document.getElementById('taxId').value = vendor.tax_id || '';
    document.getElementById('taxExempt').checked = vendor.tax_exempt == 1;
    document.getElementById('is1099Vendor').checked = vendor.is_1099_vendor == 1;
    document.getElementById('paymentTerms').value = vendor.payment_terms || 'NET30';
    document.getElementById('paymentMethod').value = vendor.payment_method || 'CHECK';
    document.getElementById('creditLimit').value = vendor.credit_limit || 0;

    document.getElementById('riskLevel').value = vendor.risk_level || 'medium';
    document.getElementById('riskScore').value = vendor.risk_score || 50;
    document.getElementById('riskNotes').value = vendor.risk_notes || '';

    document.getElementById('status').value = vendor.status || 'pending';
    document.getElementById('onboardingStatus').value = vendor.onboarding_status || 'new';
    document.getElementById('notes').value = vendor.notes || '';

    // Performance stats
    document.getElementById('perfTotalOrders').textContent = vendor.total_orders || 0;
    document.getElementById('perfTotalSpend').textContent = '$' + parseFloat(vendor.total_spend || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('perfOnTime').textContent = (vendor.on_time_delivery_rate || 0) + '%';
}

async function loadAuditHistory(vendorId) {
    try {
        const response = await fetch(`/staff/api/finance/vendors.php?action=audit_history&id=${vendorId}`);
        const result = await response.json();

        if (result.success) {
            renderAuditHistory(result.data);
        }
    } catch (error) {
        console.error('Error loading audit history:', error);
    }
}

function renderAuditHistory(history) {
    const container = document.getElementById('auditHistory');

    if (!history || history.length === 0) {
        container.innerHTML = '<p class="text-muted">No audit history available</p>';
        return;
    }

    container.innerHTML = `
        <div class="timeline">
            ${history.map(entry => `
                <div class="card mb-2">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <strong>${entry.user_name || 'System'}</strong>
                            <small class="text-muted">${new Date(entry.created_at).toLocaleString()}</small>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-info">${entry.action}</span>
                            ${entry.description}
                        </div>
                        ${entry.field_name ? `
                            <div class="mt-2 small">
                                <strong>${entry.field_name}:</strong>
                                <span class="text-danger">${entry.old_value || '(empty)'}</span>
                                →
                                <span class="text-success">${entry.new_value || '(empty)'}</span>
                            </div>
                        ` : ''}
                        <div class="mt-1 small text-muted">
                            IP: ${entry.ip_address || 'N/A'}
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function openCreateVendor() {
    currentVendor = null;
    document.getElementById('vendorForm').reset();
    document.getElementById('vendorId').value = '';
    document.getElementById('vendorModalTitle').textContent = 'Create New Vendor';

    const modal = new bootstrap.Modal(document.getElementById('vendorModal'));
    modal.show();
}

async function saveVendor() {
    const form = document.getElementById('vendorForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const vendorId = document.getElementById('vendorId').value;
    const action = vendorId ? 'update' : 'create';

    const data = {
        id: vendorId || undefined,
        vendor_code: document.getElementById('vendorCode').value,
        vendor_name: document.getElementById('vendorName').value,
        legal_name: document.getElementById('legalName').value,
        category_id: document.getElementById('categoryId').value || null,
        website: document.getElementById('website').value,

        primary_contact_name: document.getElementById('primaryContactName').value,
        primary_contact_email: document.getElementById('primaryContactEmail').value,
        primary_contact_phone: document.getElementById('primaryContactPhone').value,

        address_line1: document.getElementById('addressLine1').value,
        address_line2: document.getElementById('addressLine2').value,
        city: document.getElementById('city').value,
        state_province: document.getElementById('stateProvince').value,
        postal_code: document.getElementById('postalCode').value,
        country: document.getElementById('country').value,

        tax_id_type: document.getElementById('taxIdType').value,
        tax_id: document.getElementById('taxId').value,
        tax_exempt: document.getElementById('taxExempt').checked ? 1 : 0,
        is_1099_vendor: document.getElementById('is1099Vendor').checked ? 1 : 0,
        payment_terms: document.getElementById('paymentTerms').value,
        payment_method: document.getElementById('paymentMethod').value,
        credit_limit: document.getElementById('creditLimit').value,

        risk_level: document.getElementById('riskLevel').value,
        risk_score: document.getElementById('riskScore').value,
        risk_notes: document.getElementById('riskNotes').value,

        status: document.getElementById('status').value,
        onboarding_status: document.getElementById('onboardingStatus').value,
        notes: document.getElementById('notes').value
    };

    try {
        const response = await fetch(`/staff/api/finance/vendors.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            alert('Vendor saved successfully');
            bootstrap.Modal.getInstance(document.getElementById('vendorModal')).hide();
            loadVendors();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving vendor:', error);
        alert('Error saving vendor');
    }
}

async function loadCategories() {
    try {
        const response = await fetch('/staff/api/finance/vendors.php?action=categories');
        const result = await response.json();

        if (result.success) {
            const selects = ['categoryId', 'filterCategory'];
            selects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    result.data.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat.id;
                        option.textContent = cat.name;
                        select.appendChild(option);
                    });
                }
            });
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

// Search filter
let searchTimeout;
document.getElementById('searchVendor').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadVendors, 500);
});

document.getElementById('filterStatus').addEventListener('change', loadVendors);
document.getElementById('filterRisk').addEventListener('change', loadVendors);
document.getElementById('filterCategory').addEventListener('change', loadVendors);

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
