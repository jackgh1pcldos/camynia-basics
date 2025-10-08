<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

if (!isLoggedIn() || isSupplier()) {
    header('Location: /staff/auth/login.php');
    exit;
}

$ticketId = $_GET['id'] ?? null;
if (!$ticketId) {
    header('Location: /staff/helpdesk/tickets.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Ticket Header -->
        <div class="card mb-4" id="ticketHeader">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="mb-1">
                            <i class="bi bi-ticket-perforated"></i>
                            <span id="ticketNumber"></span>
                        </h4>
                        <h5 class="text-muted" id="ticketSubject"></h5>
                    </div>
                    <div class="text-end">
                        <span id="ticketStatus" class="badge"></span>
                        <span id="ticketPriority" class="badge"></span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Contact</small>
                        <div id="contactInfo">
                            <strong id="contactName"></strong><br>
                            <span id="contactEmail" class="text-muted"></span><br>
                            <span id="contactPhone" class="text-muted"></span>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">Queue</small>
                        <span id="ticketQueue"></span>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">Assigned To</small>
                        <span id="assignedTo">Unassigned</span>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">Created</small>
                        <span id="createdAt"></span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Tags</small>
                        <div id="ticketTags"></div>
                    </div>
                </div>

                <div class="mt-3">
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTicketModal">
                        <i class="bi bi-pencil"></i> Edit Details
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#watchersModal">
                        <i class="bi bi-eye"></i> Watchers
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ccModal">
                        <i class="bi bi-envelope"></i> CC
                    </button>
                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#activityModal">
                        <i class="bi bi-clock-history"></i> Activity Log
                    </button>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Timeline Column -->
            <div class="col-lg-8">
                <!-- Original Message -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <strong>Original Request</strong>
                    </div>
                    <div class="card-body">
                        <div id="originalMessage"></div>
                        <div id="originalAttachments" class="mt-3"></div>
                    </div>
                </div>

                <!-- Replies & Notes Timeline -->
                <div id="timeline"></div>

                <!-- Reply Form -->
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" id="reply-tab" data-bs-toggle="tab" data-bs-target="#replyPane" type="button" role="tab">
                                    <i class="bi bi-reply"></i> Reply to Customer
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="note-tab" data-bs-toggle="tab" data-bs-target="#notePane" type="button" role="tab">
                                    <i class="bi bi-sticky"></i> Add Private Note
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Reply Tab -->
                            <div class="tab-pane fade show active" id="replyPane" role="tabpanel">
                                <form id="replyForm">
                                    <div class="mb-3">
                                        <label class="form-label">Template</label>
                                        <select class="form-select" id="replyTemplate">
                                            <option value="">-- Use Template --</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Message</label>
                                        <textarea class="form-control" id="replyMessage" rows="6" required></textarea>
                                        <small class="text-muted">Use @username to mention staff members</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Attachments</label>
                                        <input type="file" class="form-control" id="replyAttachments" multiple>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="resolveOnReply">
                                        <label class="form-check-label" for="resolveOnReply">
                                            Resolve ticket after sending reply
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send"></i> Send Reply
                                    </button>
                                </form>
                            </div>

                            <!-- Note Tab -->
                            <div class="tab-pane fade" id="notePane" role="tabpanel">
                                <form id="noteForm">
                                    <div class="mb-3">
                                        <label class="form-label">Private Note</label>
                                        <textarea class="form-control" id="noteMessage" rows="6" required></textarea>
                                        <small class="text-muted">Only visible to staff. Use @username to mention staff members</small>
                                    </div>
                                    <button type="submit" class="btn btn-secondary">
                                        <i class="bi bi-plus-circle"></i> Add Note
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Column -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Quick Actions</strong>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="quickStatus">
                                <option value="">-- Change Status --</option>
                                <option value="new">New</option>
                                <option value="open">Open</option>
                                <option value="pending">Pending</option>
                                <option value="on-hold">On Hold</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" id="quickPriority"></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign To</label>
                            <select class="form-select" id="quickAssign">
                                <option value="">-- Assign Staff --</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Queue</label>
                            <select class="form-select" id="quickQueue"></select>
                        </div>
                    </div>
                </div>

                <!-- Resolution Notes -->
                <div class="card mb-3" id="resolutionCard" style="display: none;">
                    <div class="card-header bg-success text-white">
                        <strong>Resolution Details</strong>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small class="text-muted">Resolved By</small><br>
                            <span id="resolvedBy"></span>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Resolved At</small><br>
                            <span id="resolvedAt"></span>
                        </div>
                        <div>
                            <small class="text-muted">Resolution Notes</small><br>
                            <div id="resolutionNotes"></div>
                        </div>
                    </div>
                </div>

                <!-- SLA Status -->
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>SLA Status</strong>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">First Response</small>
                            <div id="slaFirstResponse"></div>
                        </div>
                        <div>
                            <small class="text-muted d-block">Resolution</small>
                            <div id="slaResolution"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Ticket Modal -->
<div class="modal fade" id="editTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Ticket Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTicketForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" id="editSubject" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Name</label>
                        <input type="text" class="form-control" id="editContactName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Email</label>
                        <input type="email" class="form-control" id="editContactEmail" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Phone</label>
                        <input type="text" class="form-control" id="editContactPhone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <select class="form-select" id="editTags" multiple size="5"></select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Watchers Modal -->
<div class="modal fade" id="watchersModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Watchers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Add Watcher</label>
                    <select class="form-select" id="addWatcherSelect">
                        <option value="">-- Select Staff --</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-primary mt-2" id="addWatcherBtn">
                        <i class="bi bi-plus"></i> Add
                    </button>
                </div>
                <hr>
                <h6>Current Watchers</h6>
                <div id="watchersList"></div>
            </div>
        </div>
    </div>
</div>

<!-- CC Modal -->
<div class="modal fade" id="ccModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage CC Recipients</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Add CC Email</label>
                    <input type="email" class="form-control" id="addCcEmail" placeholder="email@example.com">
                    <input type="text" class="form-control mt-2" id="addCcName" placeholder="Name (optional)">
                    <button type="button" class="btn btn-sm btn-primary mt-2" id="addCcBtn">
                        <i class="bi bi-plus"></i> Add
                    </button>
                </div>
                <hr>
                <h6>Current CC Recipients</h6>
                <div id="ccList"></div>
            </div>
        </div>
    </div>
</div>

<!-- Activity Log Modal -->
<div class="modal fade" id="activityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Activity Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="activityLog"></div>
            </div>
        </div>
    </div>
</div>

<script>
let currentTicket = null;
const ticketId = <?= json_encode($ticketId) ?>;

document.addEventListener('DOMContentLoaded', function() {
    loadTicket();
    loadQueues();
    loadPriorities();
    loadStaff();
    loadTemplates();
    loadTags();

    // Quick action handlers
    document.getElementById('quickStatus').addEventListener('change', handleQuickStatusChange);
    document.getElementById('quickPriority').addEventListener('change', handleQuickPriorityChange);
    document.getElementById('quickAssign').addEventListener('change', handleQuickAssignChange);
    document.getElementById('quickQueue').addEventListener('change', handleQuickQueueChange);

    // Form handlers
    document.getElementById('replyForm').addEventListener('submit', handleReplySubmit);
    document.getElementById('noteForm').addEventListener('submit', handleNoteSubmit);
    document.getElementById('editTicketForm').addEventListener('submit', handleEditTicket);

    // Template selection
    document.getElementById('replyTemplate').addEventListener('change', handleTemplateSelect);

    // Watcher/CC handlers
    document.getElementById('addWatcherBtn').addEventListener('click', handleAddWatcher);
    document.getElementById('addCcBtn').addEventListener('click', handleAddCc);
});

async function loadTicket() {
    try {
        const response = await fetch(`/staff/api/tickets.php?action=view&id=${ticketId}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        currentTicket = result.data;
        renderTicket(currentTicket);
    } catch (error) {
        alert('Error loading ticket: ' + error.message);
        window.location.href = '/staff/helpdesk/tickets.php';
    }
}

function renderTicket(ticket) {
    // Header
    document.getElementById('ticketNumber').textContent = ticket.ticket_number;
    document.getElementById('ticketSubject').textContent = ticket.subject;

    // Status badge
    const statusColors = {
        'new': 'primary', 'open': 'info', 'pending': 'warning',
        'on-hold': 'secondary', 'resolved': 'success',
        'closed': 'dark', 'cancelled': 'danger'
    };
    const statusBadge = document.getElementById('ticketStatus');
    statusBadge.textContent = ticket.status.toUpperCase();
    statusBadge.className = `badge bg-${statusColors[ticket.status] || 'secondary'}`;

    // Priority badge
    const priorityColors = {
        'Low': 'success', 'Medium': 'warning',
        'High': 'danger', 'Critical': 'dark'
    };
    const priorityBadge = document.getElementById('ticketPriority');
    priorityBadge.textContent = ticket.priority_name || 'No Priority';
    priorityBadge.className = `badge bg-${priorityColors[ticket.priority_name] || 'secondary'}`;

    // Contact info
    document.getElementById('contactName').textContent = ticket.contact_name;
    document.getElementById('contactEmail').textContent = ticket.contact_email;
    document.getElementById('contactPhone').textContent = ticket.contact_phone || 'N/A';

    // Queue & Assignment
    document.getElementById('ticketQueue').innerHTML = ticket.queue_name
        ? `<span class="badge" style="background-color: ${ticket.queue_color}">${ticket.queue_name}</span>`
        : 'No Queue';

    document.getElementById('assignedTo').textContent = ticket.assigned_to_name || 'Unassigned';

    // Timestamps
    document.getElementById('createdAt').textContent = new Date(ticket.created_at).toLocaleString();

    // Tags
    renderTags(ticket.tags || []);

    // Original message
    document.getElementById('originalMessage').innerHTML = `<div class="mb-3">${escapeHtmlWithLineBreaks(ticket.description)}</div>`;

    // Render timeline
    renderTimeline(ticket.replies || []);

    // Resolution details
    if (ticket.status === 'resolved' || ticket.status === 'closed') {
        document.getElementById('resolutionCard').style.display = 'block';
        document.getElementById('resolvedBy').textContent = ticket.resolved_by_name || 'N/A';
        document.getElementById('resolvedAt').textContent = ticket.resolved_at ? new Date(ticket.resolved_at).toLocaleString() : 'N/A';
        document.getElementById('resolutionNotes').textContent = ticket.resolution_notes || 'No notes provided';
    }

    // SLA status
    renderSlaStatus(ticket);

    // Populate edit form
    document.getElementById('editSubject').value = ticket.subject;
    document.getElementById('editContactName').value = ticket.contact_name;
    document.getElementById('editContactEmail').value = ticket.contact_email;
    document.getElementById('editContactPhone').value = ticket.contact_phone || '';
}

function renderTags(tags) {
    const container = document.getElementById('ticketTags');
    if (tags.length === 0) {
        container.innerHTML = '<span class="text-muted">No tags</span>';
        return;
    }

    container.innerHTML = tags.map(tag =>
        `<span class="badge me-1" style="background-color: ${tag.color}">${escapeHtml(tag.name)}</span>`
    ).join('');
}

function renderTimeline(replies) {
    const timeline = document.getElementById('timeline');
    timeline.innerHTML = '';

    replies.forEach(reply => {
        const card = document.createElement('div');
        card.className = 'card mb-3';

        const headerClass = reply.is_note ? 'bg-warning' : 'bg-light';
        const icon = reply.is_note ? 'sticky' : 'reply';

        card.innerHTML = `
            <div class="card-header ${headerClass}">
                <div class="d-flex justify-content-between">
                    <strong>
                        <i class="bi bi-${icon}"></i>
                        ${reply.is_note ? 'Private Note' : 'Reply'} from ${escapeHtml(reply.staff_name)}
                    </strong>
                    <small>${new Date(reply.created_at).toLocaleString()}</small>
                </div>
            </div>
            <div class="card-body">
                ${escapeHtmlWithLineBreaks(reply.message)}
            </div>
        `;

        timeline.appendChild(card);
    });
}

function renderSlaStatus(ticket) {
    const firstResponse = document.getElementById('slaFirstResponse');
    const resolution = document.getElementById('slaResolution');

    if (ticket.first_response_sla_breached) {
        firstResponse.innerHTML = '<span class="badge bg-danger">Breached</span>';
    } else if (ticket.first_response_at) {
        firstResponse.innerHTML = '<span class="badge bg-success">Met</span>';
    } else {
        firstResponse.innerHTML = '<span class="badge bg-secondary">Pending</span>';
    }

    if (ticket.resolution_sla_breached) {
        resolution.innerHTML = '<span class="badge bg-danger">Breached</span>';
    } else if (ticket.resolved_at) {
        resolution.innerHTML = '<span class="badge bg-success">Met</span>';
    } else {
        resolution.innerHTML = '<span class="badge bg-secondary">Pending</span>';
    }
}

async function loadQueues() {
    try {
        const response = await fetch('/staff/api/queues.php?action=list');
        const result = await response.json();

        if (result.success) {
            const select = document.getElementById('quickQueue');
            select.innerHTML = '<option value="">-- Change Queue --</option>' +
                result.data.map(q => `<option value="${q.id}">${escapeHtml(q.name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading queues:', error);
    }
}

async function loadPriorities() {
    try {
        const response = await fetch('/staff/api/helpdesk-meta.php?action=priorities');
        const result = await response.json();

        if (result.success) {
            const select = document.getElementById('quickPriority');
            select.innerHTML = '<option value="">-- Change Priority --</option>' +
                result.data.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading priorities:', error);
    }
}

async function loadStaff() {
    try {
        const response = await fetch('/staff/api/helpdesk-meta.php?action=staff_list');
        const result = await response.json();

        if (result.success) {
            const assignSelect = document.getElementById('quickAssign');
            const watcherSelect = document.getElementById('addWatcherSelect');

            const options = result.data.map(s =>
                `<option value="${s.id}">${escapeHtml(s.name)}</option>`
            ).join('');

            assignSelect.innerHTML = '<option value="">-- Assign Staff --</option>' + options;
            watcherSelect.innerHTML = '<option value="">-- Select Staff --</option>' + options;
        }
    } catch (error) {
        console.error('Error loading staff:', error);
    }
}

async function loadTemplates() {
    try {
        const response = await fetch('/staff/api/templates.php?action=list');
        const result = await response.json();

        if (result.success) {
            const select = document.getElementById('replyTemplate');
            select.innerHTML = '<option value="">-- Use Template --</option>' +
                result.data.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading templates:', error);
    }
}

async function loadTags() {
    try {
        const response = await fetch('/staff/api/helpdesk-meta.php?action=tags');
        const result = await response.json();

        if (result.success) {
            const select = document.getElementById('editTags');
            select.innerHTML = result.data.map(tag => {
                const selected = currentTicket?.tags?.some(t => t.id === tag.id) ? 'selected' : '';
                return `<option value="${tag.id}" ${selected}>${escapeHtml(tag.name)}</option>`;
            }).join('');
        }
    } catch (error) {
        console.error('Error loading tags:', error);
    }
}

async function handleTemplateSelect(e) {
    const templateId = e.target.value;
    if (!templateId) return;

    try {
        const response = await fetch('/staff/api/templates.php?action=use', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                template_id: templateId,
                ticket_data: {
                    ticket_number: currentTicket.ticket_number,
                    subject: currentTicket.subject,
                    contact_name: currentTicket.contact_name,
                    contact_email: currentTicket.contact_email,
                    queue_name: currentTicket.queue_name
                }
            })
        });

        const result = await response.json();
        if (result.success) {
            document.getElementById('replyMessage').value = result.data.content;
        }
    } catch (error) {
        alert('Error loading template: ' + error.message);
    }
}

async function handleQuickStatusChange(e) {
    const status = e.target.value;
    if (!status) return;

    if (!confirm(`Change ticket status to ${status}?`)) {
        e.target.value = '';
        return;
    }

    try {
        const response = await fetch('/staff/api/tickets.php?action=change_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_id: ticketId, status })
        });

        const result = await response.json();
        if (result.success) {
            loadTicket();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error changing status: ' + error.message);
    }

    e.target.value = '';
}

async function handleQuickPriorityChange(e) {
    const priorityId = e.target.value;
    if (!priorityId) return;

    try {
        const response = await fetch('/staff/api/tickets.php?action=change_priority', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_id: ticketId, priority_id: priorityId })
        });

        const result = await response.json();
        if (result.success) {
            loadTicket();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error changing priority: ' + error.message);
    }

    e.target.value = '';
}

async function handleQuickAssignChange(e) {
    const userId = e.target.value;
    if (!userId) return;

    try {
        const response = await fetch('/staff/api/tickets.php?action=assign', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_id: ticketId, user_id: userId })
        });

        const result = await response.json();
        if (result.success) {
            loadTicket();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error assigning ticket: ' + error.message);
    }

    e.target.value = '';
}

async function handleQuickQueueChange(e) {
    const queueId = e.target.value;
    if (!queueId) return;

    try {
        const response = await fetch('/staff/api/tickets.php?action=change_queue', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_id: ticketId, queue_id: queueId })
        });

        const result = await response.json();
        if (result.success) {
            loadTicket();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error changing queue: ' + error.message);
    }

    e.target.value = '';
}

async function handleReplySubmit(e) {
    e.preventDefault();

    const message = document.getElementById('replyMessage').value;
    const resolveAfter = document.getElementById('resolveOnReply').checked;

    if (!message.trim()) {
        alert('Please enter a message');
        return;
    }

    try {
        const response = await fetch('/staff/api/tickets.php?action=add_reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: ticketId,
                message: message,
                is_note: false
            })
        });

        const result = await response.json();
        if (result.success) {
            if (resolveAfter) {
                await handleResolveTicket(message);
            }
            document.getElementById('replyForm').reset();
            loadTicket();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error adding reply: ' + error.message);
    }
}

async function handleNoteSubmit(e) {
    e.preventDefault();

    const message = document.getElementById('noteMessage').value;

    if (!message.trim()) {
        alert('Please enter a note');
        return;
    }

    try {
        const response = await fetch('/staff/api/tickets.php?action=add_note', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: ticketId,
                message: message,
                is_note: true
            })
        });

        const result = await response.json();
        if (result.success) {
            document.getElementById('noteForm').reset();
            loadTicket();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error adding note: ' + error.message);
    }
}

async function handleResolveTicket(resolutionNotes) {
    try {
        const response = await fetch('/staff/api/tickets.php?action=resolve', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: ticketId,
                resolution_notes: resolutionNotes
            })
        });

        const result = await response.json();
        if (!result.success) {
            alert('Error resolving ticket: ' + result.message);
        }
    } catch (error) {
        alert('Error resolving ticket: ' + error.message);
    }
}

async function handleEditTicket(e) {
    e.preventDefault();

    const subject = document.getElementById('editSubject').value;
    const contactName = document.getElementById('editContactName').value;
    const contactEmail = document.getElementById('editContactEmail').value;
    const contactPhone = document.getElementById('editContactPhone').value;
    const tagIds = Array.from(document.getElementById('editTags').selectedOptions).map(opt => opt.value);

    try {
        const response = await fetch('/staff/api/tickets.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: ticketId,
                subject,
                contact_name: contactName,
                contact_email: contactEmail,
                contact_phone: contactPhone
            })
        });

        const result = await response.json();
        if (result.success) {
            // Update tags
            await updateTicketTags(tagIds);

            bootstrap.Modal.getInstance(document.getElementById('editTicketModal')).hide();
            loadTicket();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error updating ticket: ' + error.message);
    }
}

async function updateTicketTags(tagIds) {
    try {
        await fetch('/staff/api/tickets.php?action=add_tags', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: ticketId,
                tag_ids: tagIds
            })
        });
    } catch (error) {
        console.error('Error updating tags:', error);
    }
}

async function handleAddWatcher() {
    const userId = document.getElementById('addWatcherSelect').value;
    if (!userId) {
        alert('Please select a staff member');
        return;
    }

    try {
        const response = await fetch('/staff/api/tickets.php?action=add_watcher', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: ticketId,
                user_id: userId
            })
        });

        const result = await response.json();
        if (result.success) {
            document.getElementById('addWatcherSelect').value = '';
            loadTicket();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error adding watcher: ' + error.message);
    }
}

async function handleAddCc() {
    const email = document.getElementById('addCcEmail').value;
    const name = document.getElementById('addCcName').value;

    if (!email) {
        alert('Please enter an email address');
        return;
    }

    try {
        const response = await fetch('/staff/api/tickets.php?action=add_cc', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_id: ticketId,
                email,
                name
            })
        });

        const result = await response.json();
        if (result.success) {
            document.getElementById('addCcEmail').value = '';
            document.getElementById('addCcName').value = '';
            loadTicket();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error adding CC: ' + error.message);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function escapeHtmlWithLineBreaks(text) {
    // Escape HTML first
    const div = document.createElement('div');
    div.textContent = text;
    const escaped = div.innerHTML;
    // Convert line breaks to <br> tags
    return escaped.replace(/\n/g, '<br>');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
