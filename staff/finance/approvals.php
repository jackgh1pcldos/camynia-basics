<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'Approval Rules';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="bi bi-diagram-3"></i> Approval Rules
        </h1>
        <button type="button" class="btn btn-primary" onclick="openRuleModal('create')">
            <i class="bi bi-plus-circle"></i> New Approval Rule
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Document Type</label>
                    <select class="form-select" id="filterDocType" onchange="loadRules()">
                        <option value="">All Types</option>
                        <option value="requisition">Requisitions</option>
                        <option value="purchase_order">Purchase Orders</option>
                        <option value="expense_report">Expense Reports</option>
                        <option value="invoice">Invoices</option>
                        <option value="payment">Payments</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus" onchange="loadRules()">
                        <option value="">All Status</option>
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="Search by rule name..." onkeyup="loadRules()">
                </div>
            </div>
        </div>
    </div>

    <!-- Rules Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Rule Name</th>
                            <th>Document Type</th>
                            <th>Priority</th>
                            <th>Conditions</th>
                            <th>Approval Levels</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rulesTableBody">
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                Loading approval rules...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Rule Modal -->
<div class="modal fade" id="ruleModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ruleModalTitle">New Approval Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="ruleForm">
                    <input type="hidden" id="ruleId" name="id">

                    <!-- Basic Info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Rule Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ruleName" name="rule_name" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Document Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="docType" name="document_type" required>
                                <option value="">Select...</option>
                                <option value="requisition">Requisition</option>
                                <option value="purchase_order">Purchase Order</option>
                                <option value="expense_report">Expense Report</option>
                                <option value="invoice">Invoice</option>
                                <option value="payment">Payment</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Priority</label>
                            <input type="number" class="form-control" id="priority" name="priority" value="100" min="1">
                            <small class="text-muted">Lower = evaluated first</small>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Escalation Hours</label>
                            <input type="number" class="form-control" id="escalationHours" name="escalation_hours" value="24" min="1">
                            <small class="text-muted">Auto-escalate if no response</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Notify Requester</label>
                            <select class="form-select" id="notifyRequester" name="notify_requester">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>

                    <!-- Conditions Section -->
                    <h6 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-funnel"></i> Conditions
                        <small class="text-muted">(When should this rule apply?)</small>
                    </h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Amount Range</label>
                            <div class="input-group">
                                <span class="input-group-text">Min</span>
                                <input type="number" class="form-control" id="amountMin" placeholder="0.00" step="0.01">
                                <span class="input-group-text">Max</span>
                                <input type="number" class="form-control" id="amountMax" placeholder="Unlimited" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categories (comma-separated)</label>
                            <input type="text" class="form-control" id="categories" placeholder="e.g., IT Equipment, Office Supplies">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Departments (comma-separated)</label>
                            <input type="text" class="form-control" id="departments" placeholder="e.g., IT, Finance, HR">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vendor Risk Levels</label>
                            <select class="form-select" id="vendorRisk" multiple size="3">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                            <small class="text-muted">Hold Ctrl to select multiple</small>
                        </div>
                    </div>

                    <!-- Approval Chain Section -->
                    <h6 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-diagram-3"></i> Approval Chain
                        <small class="text-muted">(Who needs to approve?)</small>
                    </h6>

                    <div id="approvalChain">
                        <!-- Approval levels will be added here dynamically -->
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addApprovalLevel()">
                        <i class="bi bi-plus-circle"></i> Add Approval Level
                    </button>

                    <div class="form-text mb-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Tip:</strong> Approvals will be requested in order (Level 1 first, then Level 2, etc.).
                        Set amount limits to define the maximum amount each approver can approve without escalating.
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveRule()">
                    <i class="bi bi-save"></i> Save Rule
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentFilters = {
    document_type: '',
    status: 'active',
    search: ''
};

let approvalLevelCount = 0;

// Load rules on page load
document.addEventListener('DOMContentLoaded', function() {
    loadRules();
});

function loadRules() {
    currentFilters.document_type = document.getElementById('filterDocType').value;
    currentFilters.status = document.getElementById('filterStatus').value;
    currentFilters.search = document.getElementById('filterSearch').value;

    const params = new URLSearchParams();
    if (currentFilters.document_type) params.append('document_type', currentFilters.document_type);
    if (currentFilters.status) params.append('status', currentFilters.status);
    if (currentFilters.search) params.append('search', currentFilters.search);

    fetch(`/staff/api/finance/approval-rules.php?action=list&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderRules(data.data);
            } else {
                showError('Failed to load rules: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading rules:', error);
            showError('Failed to load rules');
        });
}

function renderRules(rules) {
    const tbody = document.getElementById('rulesTableBody');

    if (rules.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted">
                    <i class="bi bi-inbox"></i> No approval rules found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = rules.map(rule => {
        const conditions = JSON.parse(rule.conditions || '{}');
        const chain = JSON.parse(rule.approval_chain || '[]');

        let conditionsSummary = [];
        if (conditions.amount_min || conditions.amount_max) {
            conditionsSummary.push(`Amount: $${conditions.amount_min || '0'} - $${conditions.amount_max || '∞'}`);
        }
        if (conditions.categories) {
            conditionsSummary.push(`Categories: ${conditions.categories.join(', ')}`);
        }

        return `
            <tr>
                <td><strong>${escapeHtml(rule.rule_name)}</strong></td>
                <td>
                    <span class="badge bg-secondary">${formatDocType(rule.document_type)}</span>
                </td>
                <td><span class="badge bg-light text-dark">${rule.priority}</span></td>
                <td>
                    <small>${conditionsSummary.length > 0 ? conditionsSummary.join('<br>') : '<em>No conditions</em>'}</small>
                </td>
                <td>
                    <span class="badge bg-info">${chain.length} level(s)</span>
                </td>
                <td>
                    <span class="badge bg-${rule.status === 'active' ? 'success' : 'secondary'}">
                        ${rule.status}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewRule(${rule.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="editRule(${rule.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteRule(${rule.id}, '${escapeHtml(rule.rule_name)}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function formatDocType(type) {
    const types = {
        'requisition': 'Requisition',
        'purchase_order': 'Purchase Order',
        'expense_report': 'Expense Report',
        'invoice': 'Invoice',
        'payment': 'Payment'
    };
    return types[type] || type;
}

function openRuleModal(mode, ruleId = null) {
    const modal = new bootstrap.Modal(document.getElementById('ruleModal'));
    const form = document.getElementById('ruleForm');
    form.reset();

    document.getElementById('ruleModalTitle').textContent = mode === 'create' ? 'New Approval Rule' : 'Edit Approval Rule';
    document.getElementById('approvalChain').innerHTML = '';
    approvalLevelCount = 0;

    if (mode === 'edit' && ruleId) {
        loadRuleData(ruleId);
    } else {
        // Add one default approval level for new rules
        addApprovalLevel();
    }

    modal.show();
}

function loadRuleData(ruleId) {
    fetch(`/staff/api/finance/approval-rules.php?action=view&id=${ruleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const rule = data.data;

                document.getElementById('ruleId').value = rule.id;
                document.getElementById('ruleName').value = rule.rule_name;
                document.getElementById('docType').value = rule.document_type;
                document.getElementById('priority').value = rule.priority;
                document.getElementById('status').value = rule.status;
                document.getElementById('escalationHours').value = rule.escalation_hours || 24;
                document.getElementById('notifyRequester').value = rule.notify_requester;

                // Load conditions
                const conditions = JSON.parse(rule.conditions || '{}');
                if (conditions.amount_min) document.getElementById('amountMin').value = conditions.amount_min;
                if (conditions.amount_max) document.getElementById('amountMax').value = conditions.amount_max;
                if (conditions.categories) document.getElementById('categories').value = conditions.categories.join(', ');
                if (conditions.departments) document.getElementById('departments').value = conditions.departments.join(', ');
                if (conditions.vendor_risk_levels) {
                    const riskSelect = document.getElementById('vendorRisk');
                    for (let option of riskSelect.options) {
                        option.selected = conditions.vendor_risk_levels.includes(option.value);
                    }
                }

                // Load approval chain
                const chain = JSON.parse(rule.approval_chain || '[]');
                chain.forEach(level => {
                    addApprovalLevel(level);
                });

            } else {
                showError('Failed to load rule: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading rule:', error);
            showError('Failed to load rule data');
        });
}

function addApprovalLevel(data = null) {
    approvalLevelCount++;
    const level = data ? data.level : approvalLevelCount;

    const container = document.getElementById('approvalChain');
    const levelDiv = document.createElement('div');
    levelDiv.className = 'card mb-3';
    levelDiv.id = `level-${approvalLevelCount}`;

    levelDiv.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">
                    <span class="badge bg-primary">Level ${level}</span>
                </h6>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeApprovalLevel('level-${approvalLevelCount}')">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Approver User ID <span class="text-danger">*</span></label>
                    <input type="number" class="form-control approver-user" value="${data?.approver_user_id || ''}" required>
                    <small class="text-muted">User ID from users table</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Amount Limit</label>
                    <input type="number" class="form-control amount-limit" value="${data?.amount_limit || ''}" step="0.01" placeholder="Unlimited">
                    <small class="text-muted">Max amount this approver can approve</small>
                </div>
            </div>
            <input type="hidden" class="approval-level" value="${level}">
        </div>
    `;

    container.appendChild(levelDiv);
}

function removeApprovalLevel(levelId) {
    document.getElementById(levelId).remove();
}

function saveRule() {
    const form = document.getElementById('ruleForm');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Build conditions object
    const conditions = {};
    const amountMin = document.getElementById('amountMin').value;
    const amountMax = document.getElementById('amountMax').value;
    if (amountMin) conditions.amount_min = parseFloat(amountMin);
    if (amountMax) conditions.amount_max = parseFloat(amountMax);

    const categories = document.getElementById('categories').value;
    if (categories) conditions.categories = categories.split(',').map(c => c.trim());

    const departments = document.getElementById('departments').value;
    if (departments) conditions.departments = departments.split(',').map(d => d.trim());

    const vendorRisk = Array.from(document.getElementById('vendorRisk').selectedOptions).map(o => o.value);
    if (vendorRisk.length > 0) conditions.vendor_risk_levels = vendorRisk;

    // Build approval chain
    const approvalChain = [];
    const levels = document.querySelectorAll('#approvalChain .card');
    levels.forEach(levelCard => {
        const level = parseInt(levelCard.querySelector('.approval-level').value);
        const userId = levelCard.querySelector('.approver-user').value;
        const amountLimit = levelCard.querySelector('.amount-limit').value;

        if (userId) {
            approvalChain.push({
                level: level,
                approver_user_id: parseInt(userId),
                amount_limit: amountLimit ? parseFloat(amountLimit) : null
            });
        }
    });

    if (approvalChain.length === 0) {
        showError('Please add at least one approval level');
        return;
    }

    const ruleData = {
        id: document.getElementById('ruleId').value || undefined,
        rule_name: document.getElementById('ruleName').value,
        document_type: document.getElementById('docType').value,
        priority: parseInt(document.getElementById('priority').value),
        status: document.getElementById('status').value,
        escalation_hours: parseInt(document.getElementById('escalationHours').value),
        notify_requester: parseInt(document.getElementById('notifyRequester').value),
        conditions: conditions,
        approval_chain: approvalChain
    };

    const action = ruleData.id ? 'update' : 'create';

    fetch(`/staff/api/finance/approval-rules.php?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(ruleData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            bootstrap.Modal.getInstance(document.getElementById('ruleModal')).hide();
            loadRules();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error saving rule:', error);
        showError('Failed to save rule');
    });
}

function viewRule(ruleId) {
    openRuleModal('edit', ruleId);
    // Make form read-only
    document.querySelectorAll('#ruleForm input, #ruleForm select').forEach(el => {
        el.disabled = true;
    });
    document.querySelector('#ruleModal .modal-footer .btn-primary').style.display = 'none';
}

function editRule(ruleId) {
    openRuleModal('edit', ruleId);
}

function deleteRule(ruleId, ruleName) {
    if (!confirm(`Are you sure you want to delete the rule "${ruleName}"?\n\nThis will deactivate the rule but preserve it in the audit trail.`)) {
        return;
    }

    fetch(`/staff/api/finance/approval-rules.php?action=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: ruleId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            loadRules();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting rule:', error);
        showError('Failed to delete rule');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showSuccess(message) {
    // Implement toast notification
    alert('Success: ' + message);
}

function showError(message) {
    // Implement toast notification
    alert('Error: ' + message);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
