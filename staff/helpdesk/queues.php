<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

if (!isLoggedIn() || isSupplier()) {
    header('Location: /staff/auth/login.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-inbox"></i> Helpdesk Queues</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#queueModal">
                <i class="bi bi-plus-circle"></i> Create Queue
            </button>
        </div>

        <!-- Queues Grid -->
        <div class="row" id="queuesGrid">
            <!-- Queues will be loaded here -->
        </div>
    </div>
</div>

<!-- Queue Modal (Create/Edit) -->
<div class="modal fade" id="queueModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="queueModalTitle">Create Queue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="queueForm">
                <input type="hidden" id="queueId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Queue Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="queueName" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Slug <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="queueSlug" required>
                                <small class="text-muted">URL-friendly identifier (e.g., first-line-support)</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="queueDescription" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="queueEmail" placeholder="support@company.com">
                                <small class="text-muted">For incoming ticket emails</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" class="form-control" id="queueSortOrder" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" id="queueColor" value="#3498db">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Icon</label>
                                <select class="form-select" id="queueIcon">
                                    <option value="inbox">Inbox</option>
                                    <option value="headset">Headset</option>
                                    <option value="tools">Tools</option>
                                    <option value="gear">Gear</option>
                                    <option value="question-circle">Question</option>
                                    <option value="bug">Bug</option>
                                    <option value="lightning">Lightning</option>
                                    <option value="shield">Shield</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <h6>SLA Settings</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Response (hours)</label>
                                <input type="number" class="form-control" id="queueSlaFirstResponse" placeholder="e.g., 2">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Resolution (hours)</label>
                                <input type="number" class="form-control" id="queueSlaResolution" placeholder="e.g., 24">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Queue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Staff Assignment Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Queue Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="staffQueueId">

                <!-- Add Staff Form -->
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Add Staff Members</strong>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Select Staff</label>
                                <select class="form-select" id="staffSelect" multiple size="6">
                                    <!-- Staff loaded here -->
                                </select>
                                <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Permissions</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="permCanView" checked>
                                    <label class="form-check-label" for="permCanView">
                                        Can view tickets in this queue
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="permCanRespond" checked>
                                    <label class="form-check-label" for="permCanRespond">
                                        Can respond to tickets
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="permCanAssign" checked>
                                    <label class="form-check-label" for="permCanAssign">
                                        Can assign tickets to others
                                    </label>
                                </div>
                                <button type="button" class="btn btn-primary mt-3" id="addStaffBtn">
                                    <i class="bi bi-plus"></i> Add Selected Staff
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Staff Table -->
                <div class="card">
                    <div class="card-header">
                        <strong>Current Staff Assignments</strong>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Staff Member</th>
                                        <th>Can View</th>
                                        <th>Can Respond</th>
                                        <th>Can Assign</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="queueStaffTable">
                                    <!-- Staff assignments loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let allStaff = [];
let currentQueueId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadQueues();
    loadAllStaff();

    // Auto-generate slug from name
    document.getElementById('queueName').addEventListener('input', function(e) {
        const slug = e.target.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        document.getElementById('queueSlug').value = slug;
    });

    // Form submission
    document.getElementById('queueForm').addEventListener('submit', handleQueueSubmit);
    document.getElementById('addStaffBtn').addEventListener('click', handleAddStaff);

    // Reset form on modal close
    document.getElementById('queueModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('queueForm').reset();
        document.getElementById('queueId').value = '';
        document.getElementById('queueModalTitle').textContent = 'Create Queue';
    });
});

async function loadQueues() {
    try {
        const response = await fetch('/staff/api/queues.php?action=list');
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        renderQueues(result.data);
    } catch (error) {
        alert('Error loading queues: ' + error.message);
    }
}

function renderQueues(queues) {
    const grid = document.getElementById('queuesGrid');

    if (queues.length === 0) {
        grid.innerHTML = '<div class="col-12"><div class="alert alert-info">No queues found. Create your first queue to get started.</div></div>';
        return;
    }

    grid.innerHTML = queues.map(queue => `
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header" style="background-color: ${queue.color}; color: white;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-${queue.icon}"></i>
                            ${escapeHtml(queue.name)}
                        </h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="editQueue(${queue.id}); return false;">
                                    <i class="bi bi-pencil"></i> Edit
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="manageStaff(${queue.id}); return false;">
                                    <i class="bi bi-people"></i> Manage Staff
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteQueue(${queue.id}); return false;">
                                    <i class="bi bi-trash"></i> Delete
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    ${queue.description ? `<p class="text-muted">${escapeHtml(queue.description)}</p>` : ''}

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="border rounded p-2 text-center">
                                <small class="text-muted d-block">Open Tickets</small>
                                <strong class="h4 text-primary">${queue.open_tickets || 0}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 text-center">
                                <small class="text-muted d-block">Staff</small>
                                <strong class="h4 text-success">${queue.staff_count || 0}</strong>
                            </div>
                        </div>
                    </div>

                    ${queue.email ? `
                        <div class="mb-2">
                            <i class="bi bi-envelope"></i>
                            <small>${escapeHtml(queue.email)}</small>
                        </div>
                    ` : ''}

                    ${queue.sla_first_response_hours || queue.sla_resolution_hours ? `
                        <div class="mb-2">
                            <i class="bi bi-clock"></i>
                            <small>SLA:
                                ${queue.sla_first_response_hours ? `${queue.sla_first_response_hours}h response` : ''}
                                ${queue.sla_first_response_hours && queue.sla_resolution_hours ? ', ' : ''}
                                ${queue.sla_resolution_hours ? `${queue.sla_resolution_hours}h resolution` : ''}
                            </small>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>
    `).join('');
}

async function loadAllStaff() {
    try {
        const response = await fetch('/staff/api/helpdesk-meta.php?action=staff_list');
        const result = await response.json();

        if (result.success) {
            allStaff = result.data;
        }
    } catch (error) {
        console.error('Error loading staff:', error);
    }
}

async function editQueue(queueId) {
    try {
        const response = await fetch(`/staff/api/queues.php?action=view&id=${queueId}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        const queue = result.data;

        // Populate form
        document.getElementById('queueId').value = queue.id;
        document.getElementById('queueName').value = queue.name;
        document.getElementById('queueSlug').value = queue.slug;
        document.getElementById('queueDescription').value = queue.description || '';
        document.getElementById('queueEmail').value = queue.email || '';
        document.getElementById('queueSortOrder').value = queue.sort_order || 0;
        document.getElementById('queueColor').value = queue.color || '#3498db';
        document.getElementById('queueIcon').value = queue.icon || 'inbox';
        document.getElementById('queueSlaFirstResponse').value = queue.sla_first_response_hours || '';
        document.getElementById('queueSlaResolution').value = queue.sla_resolution_hours || '';

        document.getElementById('queueModalTitle').textContent = 'Edit Queue';

        const modal = new bootstrap.Modal(document.getElementById('queueModal'));
        modal.show();
    } catch (error) {
        alert('Error loading queue: ' + error.message);
    }
}

async function handleQueueSubmit(e) {
    e.preventDefault();

    const queueId = document.getElementById('queueId').value;
    const action = queueId ? 'update' : 'create';

    const data = {
        name: document.getElementById('queueName').value,
        slug: document.getElementById('queueSlug').value,
        description: document.getElementById('queueDescription').value,
        email: document.getElementById('queueEmail').value,
        sort_order: document.getElementById('queueSortOrder').value,
        color: document.getElementById('queueColor').value,
        icon: document.getElementById('queueIcon').value,
        sla_first_response_hours: document.getElementById('queueSlaFirstResponse').value || null,
        sla_resolution_hours: document.getElementById('queueSlaResolution').value || null
    };

    if (queueId) {
        data.id = queueId;
    }

    try {
        const response = await fetch(`/staff/api/queues.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('queueModal')).hide();
            loadQueues();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error saving queue: ' + error.message);
    }
}

async function deleteQueue(queueId) {
    if (!confirm('Are you sure you want to delete this queue? This will not delete tickets, but they will become unassigned.')) {
        return;
    }

    try {
        const response = await fetch('/staff/api/queues.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: queueId })
        });

        const result = await response.json();

        if (result.success) {
            loadQueues();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error deleting queue: ' + error.message);
    }
}

async function manageStaff(queueId) {
    currentQueueId = queueId;
    document.getElementById('staffQueueId').value = queueId;

    // Populate staff select
    const staffSelect = document.getElementById('staffSelect');
    staffSelect.innerHTML = allStaff.map(staff =>
        `<option value="${staff.id}">${escapeHtml(staff.name)}</option>`
    ).join('');

    // Load current staff assignments
    await loadQueueStaff(queueId);

    const modal = new bootstrap.Modal(document.getElementById('staffModal'));
    modal.show();
}

async function loadQueueStaff(queueId) {
    try {
        const response = await fetch(`/staff/api/queues.php?action=staff&queue_id=${queueId}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        renderQueueStaff(result.data);
    } catch (error) {
        console.error('Error loading queue staff:', error);
    }
}

function renderQueueStaff(staff) {
    const table = document.getElementById('queueStaffTable');

    if (staff.length === 0) {
        table.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No staff assigned to this queue</td></tr>';
        return;
    }

    table.innerHTML = staff.map(s => `
        <tr>
            <td>${escapeHtml(s.name)}</td>
            <td>${s.can_view ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>'}</td>
            <td>${s.can_respond ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>'}</td>
            <td>${s.can_assign ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>'}</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="removeStaff(${s.user_id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

async function handleAddStaff() {
    const selectedOptions = document.getElementById('staffSelect').selectedOptions;
    const userIds = Array.from(selectedOptions).map(opt => opt.value);

    if (userIds.length === 0) {
        alert('Please select at least one staff member');
        return;
    }

    const data = {
        queue_id: currentQueueId,
        user_ids: userIds,
        can_view: document.getElementById('permCanView').checked,
        can_respond: document.getElementById('permCanRespond').checked,
        can_assign: document.getElementById('permCanAssign').checked
    };

    try {
        const response = await fetch('/staff/api/queues.php?action=assign_staff', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            document.getElementById('staffSelect').selectedIndex = -1;
            await loadQueueStaff(currentQueueId);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error adding staff: ' + error.message);
    }
}

async function removeStaff(userId) {
    if (!confirm('Remove this staff member from the queue?')) {
        return;
    }

    try {
        const response = await fetch('/staff/api/queues.php?action=remove_staff', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                queue_id: currentQueueId,
                user_id: userId
            })
        });

        const result = await response.json();

        if (result.success) {
            await loadQueueStaff(currentQueueId);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error removing staff: ' + error.message);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
