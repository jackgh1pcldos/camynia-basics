<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'Tickets';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Support Tickets</h1>
            <p class="text-muted mb-0">Manage customer support requests</p>
        </div>
        <div>
            <button class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="bi bi-funnel me-1"></i>Filters
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                <i class="bi bi-plus-circle me-2"></i>New Ticket
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4" id="statsCards">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-inbox"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stat-value" id="statOpen">-</div>
                            <div class="stat-label">Open Tickets</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stat-value" id="statUnassigned">-</div>
                            <div class="stat-label">Unassigned</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-person-check"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stat-value" id="statMy">-</div>
                            <div class="stat-label">Assigned to Me</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stat-value" id="statResolvedToday">-</div>
                            <div class="stat-label">Resolved Today</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchBox" placeholder="Ticket #, subject, contact...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="" selected>All Statuses</option>
                        <option value="new">New</option>
                        <option value="open">Open</option>
                        <option value="pending">Pending</option>
                        <option value="on-hold">On Hold</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Queue</label>
                    <select class="form-select" id="filterQueue">
                        <option value="">All Queues</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Priority</label>
                    <select class="form-select" id="filterPriority">
                        <option value="">All Priorities</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Assigned To</label>
                    <select class="form-select" id="filterAssigned">
                        <option value="">All</option>
                        <option value="unassigned">Unassigned</option>
                        <option value="me">Assigned to Me</option>
                    </select>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <button class="btn btn-sm btn-secondary" id="applyFilters">
                        <i class="bi bi-funnel me-1"></i>Apply Filters
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="resetFilters">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </button>
                    <button class="btn btn-sm btn-outline-primary" id="saveFilterBtn">
                        <i class="bi bi-save me-1"></i>Save as Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Saved Filters -->
    <div class="mb-3" id="savedFiltersContainer" style="display: none;">
        <div class="d-flex gap-2 flex-wrap" id="savedFilters"></div>
    </div>

    <!-- Tickets Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 30px;">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>Ticket #</th>
                            <th>Subject</th>
                            <th>Contact</th>
                            <th>Queue</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ticketsTable">
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav aria-label="Page navigation" id="paginationContainer" class="mt-3"></nav>
        </div>
    </div>
</div>

<!-- Create Ticket Modal -->
<div class="modal fade" id="createTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createTicketForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Name *</label>
                            <input type="text" class="form-control" id="newContactName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Email *</label>
                            <input type="email" class="form-control" id="newContactEmail" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Phone</label>
                            <input type="tel" class="form-control" id="newContactPhone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Queue</label>
                            <select class="form-select" id="newQueue">
                                <option value="">Select Queue</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" id="newPriority"></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assign To</label>
                            <select class="form-select" id="newAssignTo">
                                <option value="">Unassigned</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <input type="text" class="form-control" id="newSubject" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea class="form-control" id="newDescription" rows="6" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveTicket">Create Ticket</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let currentFilters = {
    status: '',
    queue_id: '',
    priority_id: '',
    assigned_to: '',
    q: ''
};
let queues = [];
let priorities = [];
let staff = [];

// Load initial data
loadQueues();
loadPriorities();
loadStaff();
loadStats();
loadSavedFilters();
loadTickets();

function loadQueues() {
    fetch('/staff/api/queues.php?action=my_queues')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                queues = data.data;
                const queueSelects = ['filterQueue', 'newQueue'];
                queueSelects.forEach(selectId => {
                    const select = document.getElementById(selectId);
                    queues.forEach(queue => {
                        const option = document.createElement('option');
                        option.value = queue.id;
                        option.textContent = queue.name;
                        select.appendChild(option);
                    });
                });
            }
        });
}

function loadPriorities() {
    fetch('/staff/api/helpdesk-meta.php?action=priorities')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                priorities = data.data;
                const prioritySelects = ['filterPriority', 'newPriority'];
                prioritySelects.forEach(selectId => {
                    const select = document.getElementById(selectId);
                    priorities.forEach(priority => {
                        const option = document.createElement('option');
                        option.value = priority.id;
                        option.textContent = priority.name;
                        if (priority.is_default) option.selected = true;
                        select.appendChild(option);
                    });
                });
            }
        });
}

function loadStaff() {
    fetch('/staff/api/helpdesk-meta.php?action=staff_list')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                staff = data.data;
                const select = document.getElementById('newAssignTo');
                staff.forEach(member => {
                    const option = document.createElement('option');
                    option.value = member.id;
                    option.textContent = `${member.name} (${member.active_tickets} active)`;
                    select.appendChild(option);
                });
            }
        });
}

function loadStats() {
    fetch('/staff/api/helpdesk-meta.php?action=stats')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const stats = data.data;
                document.getElementById('statOpen').textContent = stats.open_tickets || 0;
                document.getElementById('statUnassigned').textContent = stats.unassigned_tickets || 0;
                document.getElementById('statMy').textContent = stats.my_tickets || 0;
                document.getElementById('statResolvedToday').textContent = stats.resolved_today || 0;
            }
        });
}

function loadSavedFilters() {
    fetch('/staff/api/filters.php?action=list')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                document.getElementById('savedFiltersContainer').style.display = 'block';
                renderSavedFilters(data.data);
            }
        });
}

function renderSavedFilters(filters) {
    const container = document.getElementById('savedFilters');
    container.innerHTML = filters.map(filter => `
        <button class="btn btn-sm btn-outline-secondary" onclick="applySavedFilter(${filter.id})">
            ${filter.is_default ? '<i class="bi bi-star-fill me-1"></i>' : ''}
            ${escapeHtml(filter.name)}
        </button>
    `).join('');
}

function applySavedFilter(filterId) {
    // Apply saved filter logic
    console.log('Apply filter:', filterId);
}

function loadTickets(page = 1) {
    currentPage = page;

    const params = new URLSearchParams({
        action: 'list',
        page: page,
        ...currentFilters
    });

    fetch(`/staff/api/tickets.php?${params}`)
        .then(res => {
            console.log('Response status:', res.status);
            return res.text();
        })
        .then(text => {
            console.log('Response text:', text);
            const data = JSON.parse(text);
            console.log('Parsed data:', data);
            if (data.success) {
                console.log('Tickets count:', data.data.length);
                renderTickets(data.data);
                renderPagination(data.pagination);
            } else {
                alert('Error loading tickets: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            const tbody = document.getElementById('ticketsTable');
            tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Error loading tickets: ${err.message}</td></tr>`;
        });
}

function renderTickets(tickets) {
    const tbody = document.getElementById('ticketsTable');

    console.log('renderTickets called with', tickets.length, 'tickets');

    if (!tickets || tickets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No tickets found</td></tr>';
        return;
    }

    try {
        tbody.innerHTML = tickets.map(ticket => {
        const priorityColors = {
            1: 'success',
            2: 'warning',
            3: 'danger',
            4: 'danger',
            5: 'dark'
        };

        const statusColors = {
            'new': 'primary',
            'open': 'info',
            'pending': 'warning',
            'on-hold': 'secondary',
            'resolved': 'success',
            'closed': 'dark',
            'cancelled': 'secondary'
        };

        return `
            <tr>
                <td><input type="checkbox" class="ticket-checkbox" value="${ticket.id}"></td>
                <td><a href="ticket.php?id=${ticket.id}" class="text-decoration-none"><strong>${escapeHtml(ticket.ticket_number)}</strong></a></td>
                <td>
                    <a href="ticket.php?id=${ticket.id}" class="text-decoration-none text-dark">
                        ${escapeHtml(ticket.subject)}
                    </a>
                    ${ticket.tags && ticket.tags.length > 0 ? '<br>' + ticket.tags.map(tag => `<span class="badge" style="background-color: ${tag.color}">${escapeHtml(tag.name)}</span>`).join(' ') : ''}
                </td>
                <td>
                    ${escapeHtml(ticket.contact_name)}<br>
                    <small class="text-muted">${escapeHtml(ticket.contact_email)}</small>
                </td>
                <td>
                    ${ticket.queue_name ? `<span class="badge" style="background-color: ${ticket.queue_color}">${escapeHtml(ticket.queue_name)}</span>` : '<span class="text-muted">None</span>'}
                </td>
                <td>
                    ${ticket.priority_name ? `<span class="badge bg-${priorityColors[ticket.priority_level] || 'secondary'}">${escapeHtml(ticket.priority_name)}</span>` : ''}
                </td>
                <td><span class="badge bg-${statusColors[ticket.status] || 'secondary'}">${escapeHtml(ticket.status)}</span></td>
                <td>${ticket.assigned_to_name ? escapeHtml(ticket.assigned_to_name) : '<span class="text-muted">Unassigned</span>'}</td>
                <td><small>${formatDateTime(ticket.created_at)}</small></td>
                <td>
                    <a href="ticket.php?id=${ticket.id}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
        `;
        }).join('');
        console.log('Tickets rendered successfully');
    } catch (error) {
        console.error('Error rendering tickets:', error);
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Error rendering tickets: ${error.message}</td></tr>`;
    }
}

function renderPagination(pagination) {
    const container = document.getElementById('paginationContainer');

    if (pagination.totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '<ul class="pagination justify-content-center mb-0">';

    html += `
        <li class="page-item ${pagination.page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadTickets(${pagination.page - 1}); return false;">Previous</a>
        </li>
    `;

    for (let i = 1; i <= pagination.totalPages; i++) {
        if (i === 1 || i === pagination.totalPages || (i >= pagination.page - 2 && i <= pagination.page + 2)) {
            html += `
                <li class="page-item ${i === pagination.page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadTickets(${i}); return false;">${i}</a>
                </li>
            `;
        } else if (i === pagination.page - 3 || i === pagination.page + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    html += `
        <li class="page-item ${pagination.page === pagination.totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadTickets(${pagination.page + 1}); return false;">Next</a>
        </li>
    `;

    html += '</ul>';
    container.innerHTML = html;
}

// Apply filters
document.getElementById('applyFilters').addEventListener('click', function() {
    currentFilters.status = document.getElementById('filterStatus').value;
    currentFilters.queue_id = document.getElementById('filterQueue').value;
    currentFilters.priority_id = document.getElementById('filterPriority').value;
    currentFilters.assigned_to = document.getElementById('filterAssigned').value;
    currentFilters.q = document.getElementById('searchBox').value;
    loadTickets(1);
});

// Reset filters
document.getElementById('resetFilters').addEventListener('click', function() {
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterQueue').value = '';
    document.getElementById('filterPriority').value = '';
    document.getElementById('filterAssigned').value = '';
    document.getElementById('searchBox').value = '';
    currentFilters = { status: '', queue_id: '', priority_id: '', assigned_to: '', q: '' };
    loadTickets(1);
});

// Search with debounce
let searchTimeout;
document.getElementById('searchBox').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentFilters.q = this.value;
        loadTickets(1);
    }, 500);
});

// Create ticket
document.getElementById('saveTicket').addEventListener('click', function() {
    const form = document.getElementById('createTicketForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const data = {
        contact_name: document.getElementById('newContactName').value,
        contact_email: document.getElementById('newContactEmail').value,
        contact_phone: document.getElementById('newContactPhone').value,
        queue_id: document.getElementById('newQueue').value || null,
        priority_id: document.getElementById('newPriority').value || null,
        assigned_to: document.getElementById('newAssignTo').value || null,
        subject: document.getElementById('newSubject').value,
        description: document.getElementById('newDescription').value
    };

    fetch('/staff/api/tickets.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('createTicketModal')).hide();
            form.reset();
            loadTickets(currentPage);
            loadStats();
            alert('Ticket created successfully: ' + data.ticket_number);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error creating ticket');
    });
});

// Utility functions
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function formatDateTime(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now - date;
    const hours = diff / 1000 / 60 / 60;

    if (hours < 24) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } else {
        return date.toLocaleDateString();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
