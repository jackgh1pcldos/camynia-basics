<?php
require_once __DIR__ . '/includes/core/bootstrap.php';

$companyName = 'Support Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($companyName) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }

        .portal-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .portal-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .portal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .portal-body {
            padding: 40px;
        }

        .ticket-view {
            display: none;
        }

        .reply-card {
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .reply-card.customer {
            border-left-color: #28a745;
            background: #e8f5e9;
        }

        .status-badge {
            font-size: 0.9rem;
            padding: 6px 12px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
    </style>
</head>
<body>
    <div class="portal-container">
        <div class="portal-card">
            <div class="portal-header">
                <i class="bi bi-ticket-perforated" style="font-size: 3rem;"></i>
                <h1>Customer Support Portal</h1>
                <p>Track and manage your support tickets</p>
            </div>

            <!-- Login View -->
            <div class="portal-body" id="loginView">
                <h4 class="mb-4">Access Your Ticket</h4>
                <p class="text-muted mb-4">Enter your ticket number and email address to view your support ticket.</p>

                <form id="loginForm">
                    <div class="mb-3">
                        <label class="form-label">Ticket Number</label>
                        <input type="text" class="form-control" id="ticketNumber" placeholder="e.g., TKT-2025-00001" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" placeholder="your@email.com" required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Access Ticket
                        </button>
                    </div>
                </form>

                <hr class="my-4">

                <div class="text-center">
                    <p class="text-muted mb-2">Don't have a ticket yet?</p>
                    <a href="/support.php" class="btn btn-outline-primary">
                        <i class="bi bi-plus-circle"></i> Submit New Ticket
                    </a>
                </div>
            </div>

            <!-- Ticket View -->
            <div class="portal-body ticket-view" id="ticketView">
                <!-- Ticket Header -->
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4><span id="viewTicketNumber"></span></h4>
                        <h5 class="text-muted" id="viewSubject"></h5>
                    </div>
                    <div>
                        <span id="viewStatus" class="badge status-badge"></span>
                        <span id="viewPriority" class="badge status-badge"></span>
                    </div>
                </div>

                <!-- Ticket Details -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Created</small>
                        <span id="viewCreated"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Queue</small>
                        <span id="viewQueue"></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Last Updated</small>
                        <span id="viewUpdated"></span>
                    </div>
                </div>

                <hr>

                <!-- Original Message -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <strong>Original Request</strong>
                    </div>
                    <div class="card-body">
                        <div id="viewDescription"></div>
                    </div>
                </div>

                <!-- Conversation Timeline -->
                <h5 class="mb-3">Conversation</h5>
                <div id="conversationTimeline"></div>

                <!-- Reply Form -->
                <div class="card mt-4" id="replySection">
                    <div class="card-header">
                        <strong>Add a Reply</strong>
                    </div>
                    <div class="card-body">
                        <form id="replyForm">
                            <div class="mb-3">
                                <textarea class="form-control" id="replyMessage" rows="4" placeholder="Type your message here..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> Send Reply
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="logout()">
                                <i class="bi bi-box-arrow-left"></i> View Another Ticket
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Closed Ticket Notice -->
                <div class="alert alert-warning mt-4" id="closedNotice" style="display: none;">
                    <i class="bi bi-exclamation-triangle"></i>
                    This ticket is closed and cannot receive new replies. Please submit a new ticket if you need further assistance.
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentTicketNumber = '';
        let currentEmail = '';
        let currentTicket = null;

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('loginForm').addEventListener('submit', handleLogin);
            document.getElementById('replyForm').addEventListener('submit', handleReply);
        });

        async function handleLogin(e) {
            e.preventDefault();

            const ticketNumber = document.getElementById('ticketNumber').value.trim().toUpperCase();
            const email = document.getElementById('email').value.trim();

            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';

            try {
                const response = await fetch('/api/portal.php?action=view&ticket_number=' + encodeURIComponent(ticketNumber) + '&email=' + encodeURIComponent(email));
                const result = await response.json();

                if (result.success) {
                    currentTicketNumber = ticketNumber;
                    currentEmail = email;
                    currentTicket = result.data;

                    showTicket(result.data);
                } else {
                    alert(result.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                alert('Error loading ticket: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }

        function showTicket(ticket) {
            // Hide login, show ticket
            document.getElementById('loginView').style.display = 'none';
            document.getElementById('ticketView').style.display = 'block';

            // Populate ticket details
            document.getElementById('viewTicketNumber').textContent = ticket.ticket_number;
            document.getElementById('viewSubject').textContent = ticket.subject;

            // Status
            const statusColors = {
                'new': 'primary', 'open': 'info', 'pending': 'warning',
                'on-hold': 'secondary', 'resolved': 'success',
                'closed': 'dark', 'cancelled': 'danger'
            };
            const statusBadge = document.getElementById('viewStatus');
            statusBadge.textContent = ticket.status.toUpperCase();
            statusBadge.className = `badge status-badge bg-${statusColors[ticket.status] || 'secondary'}`;

            // Priority
            if (ticket.priority_name) {
                const priorityColors = {
                    'Low': 'success', 'Medium': 'warning',
                    'High': 'danger', 'Critical': 'dark'
                };
                const priorityBadge = document.getElementById('viewPriority');
                priorityBadge.textContent = ticket.priority_name;
                priorityBadge.className = `badge status-badge bg-${priorityColors[ticket.priority_name] || 'secondary'}`;
            }

            // Dates
            document.getElementById('viewCreated').textContent = new Date(ticket.created_at).toLocaleString();
            document.getElementById('viewUpdated').textContent = ticket.last_activity_at
                ? new Date(ticket.last_activity_at).toLocaleString()
                : 'N/A';

            // Queue
            document.getElementById('viewQueue').textContent = ticket.queue_name || 'General';

            // Description
            document.getElementById('viewDescription').textContent = ticket.description;

            // Render conversation
            renderConversation(ticket.replies || []);

            // Handle closed tickets
            if (ticket.status === 'closed' || ticket.status === 'cancelled') {
                document.getElementById('replySection').style.display = 'none';
                document.getElementById('closedNotice').style.display = 'block';
            } else {
                document.getElementById('replySection').style.display = 'block';
                document.getElementById('closedNotice').style.display = 'none';
            }
        }

        function renderConversation(replies) {
            const timeline = document.getElementById('conversationTimeline');

            if (replies.length === 0) {
                timeline.innerHTML = '<p class="text-muted">No replies yet. Our support team will respond soon.</p>';
                return;
            }

            timeline.innerHTML = replies.map(reply => {
                const isCustomer = reply.is_customer == 1;
                const cardClass = isCustomer ? 'customer' : '';
                const author = isCustomer ? 'You' : (reply.staff_name || 'Support Team');

                return `
                    <div class="reply-card ${cardClass}">
                        <div class="d-flex justify-content-between mb-2">
                            <strong><i class="bi bi-${isCustomer ? 'person' : 'headset'}"></i> ${escapeHtml(author)}</strong>
                            <small class="text-muted">${new Date(reply.created_at).toLocaleString()}</small>
                        </div>
                        <div>${escapeHtml(reply.message)}</div>
                    </div>
                `;
            }).join('');
        }

        async function handleReply(e) {
            e.preventDefault();

            const message = document.getElementById('replyMessage').value.trim();

            if (!message) {
                alert('Please enter a message');
                return;
            }

            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';

            try {
                const response = await fetch('/api/portal.php?action=reply', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ticket_number: currentTicketNumber,
                        email: currentEmail,
                        message: message
                    })
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('replyMessage').value = '';

                    // Reload ticket
                    const viewResponse = await fetch('/api/portal.php?action=view&ticket_number=' + encodeURIComponent(currentTicketNumber) + '&email=' + encodeURIComponent(currentEmail));
                    const viewResult = await viewResponse.json();

                    if (viewResult.success) {
                        currentTicket = viewResult.data;
                        renderConversation(viewResult.data.replies || []);
                    }

                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                } else {
                    alert(result.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                alert('Error sending reply: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }

        function logout() {
            currentTicketNumber = '';
            currentEmail = '';
            currentTicket = null;

            document.getElementById('loginView').style.display = 'block';
            document.getElementById('ticketView').style.display = 'none';

            document.getElementById('loginForm').reset();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
