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
            <h2><i class="bi bi-flag"></i> Ticket Priorities</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#priorityModal" onclick="openCreateModal()">
                <i class="bi bi-plus-circle"></i> Create Priority
            </button>
        </div>

        <!-- Priorities Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Level</th>
                                <th>Color</th>
                                <th>SLA (Minutes)</th>
                                <th>Default</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="prioritiesTable">
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Priority Modal (Create/Edit) -->
<div class="modal fade" id="priorityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="priorityModalTitle">Create Priority</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="priorityForm">
                <input type="hidden" id="priorityId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="priorityName" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Level <span class="text-danger">*</span></label>
                        <select class="form-select" id="priorityLevel" required>
                            <option value="1">1 - Low</option>
                            <option value="2">2 - Normal</option>
                            <option value="3">3 - High</option>
                            <option value="4">4 - Urgent</option>
                            <option value="5">5 - Critical</option>
                        </select>
                        <small class="text-muted">Lower numbers = lower priority</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" class="form-control form-control-color" id="priorityColor" value="#6c757d">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SLA Minutes</label>
                        <input type="number" class="form-control" id="prioritySla" placeholder="e.g., 240 for 4 hours">
                        <small class="text-muted">Response time target in minutes (optional)</small>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="priorityDefault">
                        <label class="form-check-label" for="priorityDefault">
                            Set as default priority for new tickets
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Priority</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let priorities = [];

// Load priorities on page load
document.addEventListener('DOMContentLoaded', function() {
    loadPriorities();
});

async function loadPriorities() {
    try {
        const response = await fetch('/staff/api/helpdesk-meta.php?action=priorities');
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to load priorities');
        }

        priorities = result.data;
        renderPriorities();
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('prioritiesTable').innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-danger py-4">
                    Error loading priorities: ${error.message}
                </td>
            </tr>
        `;
    }
}

function renderPriorities() {
    const tbody = document.getElementById('prioritiesTable');

    if (priorities.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted py-4">
                    No priorities found. Create your first priority to get started.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = priorities.map(priority => `
        <tr>
            <td><strong>${escapeHtml(priority.name)}</strong></td>
            <td>
                <span class="badge bg-secondary">${priority.level}</span>
            </td>
            <td>
                <span class="badge" style="background-color: ${priority.color}">
                    ${priority.color}
                </span>
            </td>
            <td>${priority.sla_minutes ? priority.sla_minutes + ' min' : '<span class="text-muted">None</span>'}</td>
            <td>
                ${priority.is_default ? '<span class="badge bg-success"><i class="bi bi-check"></i> Default</span>' : ''}
            </td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editPriority(${priority.id})">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deletePriority(${priority.id}, '${escapeHtml(priority.name)}')">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function openCreateModal() {
    document.getElementById('priorityModalTitle').textContent = 'Create Priority';
    document.getElementById('priorityForm').reset();
    document.getElementById('priorityId').value = '';
    document.getElementById('priorityColor').value = '#6c757d';
}

function editPriority(priorityId) {
    const priority = priorities.find(p => p.id == priorityId);
    if (!priority) return;

    document.getElementById('priorityModalTitle').textContent = 'Edit Priority';
    document.getElementById('priorityId').value = priority.id;
    document.getElementById('priorityName').value = priority.name;
    document.getElementById('priorityLevel').value = priority.level;
    document.getElementById('priorityColor').value = priority.color;
    document.getElementById('prioritySla').value = priority.sla_minutes || '';
    document.getElementById('priorityDefault').checked = priority.is_default == 1;

    const modal = new bootstrap.Modal(document.getElementById('priorityModal'));
    modal.show();
}

document.getElementById('priorityForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const priorityId = document.getElementById('priorityId').value;
    const action = priorityId ? 'update_priority' : 'create_priority';

    const data = {
        name: document.getElementById('priorityName').value,
        level: parseInt(document.getElementById('priorityLevel').value),
        color: document.getElementById('priorityColor').value,
        sla_minutes: document.getElementById('prioritySla').value || null,
        is_default: document.getElementById('priorityDefault').checked ? 1 : 0
    };

    if (priorityId) {
        data.id = priorityId;
    }

    try {
        const response = await fetch(`/staff/api/helpdesk-meta.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to save priority');
        }

        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('priorityModal'));
        modal.hide();

        // Reload priorities
        await loadPriorities();

        // Show success message
        alert(result.message);
    } catch (error) {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    }
});

async function deletePriority(priorityId, priorityName) {
    if (!confirm(`Are you sure you want to delete the priority "${priorityName}"?\n\nThis action cannot be undone.`)) {
        return;
    }

    try {
        const response = await fetch('/staff/api/helpdesk-meta.php?action=delete_priority', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: priorityId })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to delete priority');
        }

        // Reload priorities
        await loadPriorities();

        // Show success message
        alert(result.message);
    } catch (error) {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
