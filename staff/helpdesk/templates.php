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
            <h2><i class="bi bi-chat-left-text"></i> Response Templates</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal">
                <i class="bi bi-plus-circle"></i> Create Template
            </button>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="searchTemplate" placeholder="Search templates...">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterQueue">
                            <option value="">All Queues</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterScope">
                            <option value="">All Templates</option>
                            <option value="my">My Templates</option>
                            <option value="global">Global Templates</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" onclick="loadTemplates()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Templates Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Subject</th>
                                <th>Queue</th>
                                <th>Scope</th>
                                <th>Created By</th>
                                <th>Use Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="templatesTable">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="spinner-border" role="status">
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

<!-- Template Modal (Create/Edit) -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalTitle">Create Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="templateForm">
                <input type="hidden" id="templateId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Template Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="templateName" required>
                                <small class="text-muted">Internal name for this template</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Subject Line</label>
                                <input type="text" class="form-control" id="templateSubject">
                                <small class="text-muted">Optional - default reply subject if used</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Queue</label>
                                <select class="form-select" id="templateQueue">
                                    <option value="">All Queues (Global)</option>
                                </select>
                                <small class="text-muted">Leave blank for all queues</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Visibility</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="templateIsGlobal">
                                    <label class="form-check-label" for="templateIsGlobal">
                                        Make this template available to all staff
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Template Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="templateContent" rows="10" required></textarea>
                    </div>

                    <div class="alert alert-info">
                        <strong><i class="bi bi-info-circle"></i> Available Variables:</strong><br>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <code>{{ticket.ticket_number}}</code> - Ticket number<br>
                                <code>{{ticket.subject}}</code> - Ticket subject<br>
                                <code>{{ticket.contact_name}}</code> - Customer name<br>
                                <code>{{ticket.contact_email}}</code> - Customer email<br>
                            </div>
                            <div class="col-md-6">
                                <code>{{queue.name}}</code> - Queue name<br>
                                <code>{{staff.name}}</code> - Your name<br>
                                <code>{{ticket.resolution_notes}}</code> - Resolution notes<br>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">HTML Content (Optional)</label>
                        <textarea class="form-control" id="templateContentHtml" rows="5"></textarea>
                        <small class="text-muted">Rich HTML version for email notifications</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Template Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Name:</strong> <span id="previewName"></span>
                </div>
                <div class="mb-3">
                    <strong>Subject:</strong> <span id="previewSubject"></span>
                </div>
                <div class="mb-3">
                    <strong>Queue:</strong> <span id="previewQueue"></span>
                </div>
                <div class="mb-3">
                    <strong>Scope:</strong> <span id="previewScope"></span>
                </div>
                <hr>
                <div class="mb-3">
                    <strong>Content:</strong>
                    <div class="border rounded p-3 mt-2 bg-light">
                        <pre id="previewContent" style="white-space: pre-wrap; margin: 0;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let searchTimeout = null;

document.addEventListener('DOMContentLoaded', function() {
    loadTemplates();
    loadQueues();

    // Search with debounce
    document.getElementById('searchTemplate').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadTemplates, 500);
    });

    // Filter changes
    document.getElementById('filterQueue').addEventListener('change', loadTemplates);
    document.getElementById('filterScope').addEventListener('change', loadTemplates);

    // Form submission
    document.getElementById('templateForm').addEventListener('submit', handleTemplateSubmit);

    // Reset form on modal close
    document.getElementById('templateModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('templateForm').reset();
        document.getElementById('templateId').value = '';
        document.getElementById('templateModalTitle').textContent = 'Create Template';
    });
});

async function loadQueues() {
    try {
        const response = await fetch('/staff/api/queues.php?action=list');
        const result = await response.json();

        if (result.success) {
            const filterSelect = document.getElementById('filterQueue');
            const templateSelect = document.getElementById('templateQueue');

            const options = result.data.map(q =>
                `<option value="${q.id}">${escapeHtml(q.name)}</option>`
            ).join('');

            filterSelect.innerHTML = '<option value="">All Queues</option>' + options;
            templateSelect.innerHTML = '<option value="">All Queues (Global)</option>' + options;
        }
    } catch (error) {
        console.error('Error loading queues:', error);
    }
}

async function loadTemplates() {
    const search = document.getElementById('searchTemplate').value;
    const queueId = document.getElementById('filterQueue').value;
    const scope = document.getElementById('filterScope').value;

    let url = '/staff/api/templates.php?action=list';
    const params = [];

    if (queueId) params.push(`queue_id=${queueId}`);

    if (params.length > 0) {
        url += '&' + params.join('&');
    }

    try {
        const response = await fetch(url);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        let templates = result.data;

        // Client-side filtering for search and scope
        if (search) {
            const searchLower = search.toLowerCase();
            templates = templates.filter(t =>
                t.name.toLowerCase().includes(searchLower) ||
                (t.subject && t.subject.toLowerCase().includes(searchLower)) ||
                (t.content && t.content.toLowerCase().includes(searchLower))
            );
        }

        if (scope === 'my') {
            templates = templates.filter(t => !t.is_global);
        } else if (scope === 'global') {
            templates = templates.filter(t => t.is_global);
        }

        renderTemplates(templates);
    } catch (error) {
        document.getElementById('templatesTable').innerHTML =
            `<tr><td colspan="7" class="text-center text-danger">Error: ${error.message}</td></tr>`;
    }
}

function renderTemplates(templates) {
    const table = document.getElementById('templatesTable');

    if (templates.length === 0) {
        table.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No templates found</td></tr>';
        return;
    }

    table.innerHTML = templates.map(template => `
        <tr>
            <td>
                <strong>${escapeHtml(template.name)}</strong>
            </td>
            <td>${template.subject ? escapeHtml(template.subject) : '<span class="text-muted">N/A</span>'}</td>
            <td>${template.queue_name ? `<span class="badge bg-secondary">${escapeHtml(template.queue_name)}</span>` : '<span class="text-muted">All</span>'}</td>
            <td>${template.is_global ? '<span class="badge bg-success">Global</span>' : '<span class="badge bg-info">Personal</span>'}</td>
            <td>${escapeHtml(template.created_by_name || 'N/A')}</td>
            <td><span class="badge bg-secondary">${template.use_count || 0}</span></td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="previewTemplate(${template.id})" title="Preview">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="editTemplate(${template.id})" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteTemplate(${template.id})" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function editTemplate(templateId) {
    try {
        const response = await fetch(`/staff/api/templates.php?action=view&id=${templateId}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        const template = result.data;

        // Populate form
        document.getElementById('templateId').value = template.id;
        document.getElementById('templateName').value = template.name;
        document.getElementById('templateSubject').value = template.subject || '';
        document.getElementById('templateQueue').value = template.queue_id || '';
        document.getElementById('templateIsGlobal').checked = template.is_global == 1;
        document.getElementById('templateContent').value = template.content;
        document.getElementById('templateContentHtml').value = template.content_html || '';

        document.getElementById('templateModalTitle').textContent = 'Edit Template';

        const modal = new bootstrap.Modal(document.getElementById('templateModal'));
        modal.show();
    } catch (error) {
        alert('Error loading template: ' + error.message);
    }
}

async function previewTemplate(templateId) {
    try {
        const response = await fetch(`/staff/api/templates.php?action=view&id=${templateId}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        const template = result.data;

        document.getElementById('previewName').textContent = template.name;
        document.getElementById('previewSubject').textContent = template.subject || 'N/A';
        document.getElementById('previewQueue').textContent = template.queue_name || 'All Queues';
        document.getElementById('previewScope').innerHTML = template.is_global
            ? '<span class="badge bg-success">Global</span>'
            : '<span class="badge bg-info">Personal</span>';
        document.getElementById('previewContent').textContent = template.content;

        const modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
    } catch (error) {
        alert('Error loading template: ' + error.message);
    }
}

async function handleTemplateSubmit(e) {
    e.preventDefault();

    const templateId = document.getElementById('templateId').value;
    const action = templateId ? 'update' : 'create';

    const data = {
        name: document.getElementById('templateName').value,
        subject: document.getElementById('templateSubject').value || null,
        queue_id: document.getElementById('templateQueue').value || null,
        is_global: document.getElementById('templateIsGlobal').checked ? 1 : 0,
        content: document.getElementById('templateContent').value,
        content_html: document.getElementById('templateContentHtml').value || null
    };

    if (templateId) {
        data.id = templateId;
    }

    try {
        const response = await fetch(`/staff/api/templates.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();
            loadTemplates();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error saving template: ' + error.message);
    }
}

async function deleteTemplate(templateId) {
    if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch('/staff/api/templates.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: templateId })
        });

        const result = await response.json();

        if (result.success) {
            loadTemplates();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error deleting template: ' + error.message);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
