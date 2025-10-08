<?php
/**
 * Helpdesk Email Notification Functions
 *
 * This file contains helper functions for sending email notifications
 * related to the helpdesk system.
 */

/**
 * Send notification when a new ticket is created
 */
function sendTicketCreatedEmail($ticketId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            c.name as company_name,
            c.email as company_email,
            q.name as queue_name,
            p.name as priority_name
        FROM tickets t
        LEFT JOIN companies c ON t.company_id = c.id
        LEFT JOIN ticket_queues q ON t.queue_id = q.id
        LEFT JOIN ticket_priorities p ON t.priority_id = p.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) return false;

    // Email to customer
    $customerSubject = "Ticket Created: {$ticket['ticket_number']} - {$ticket['subject']}";
    $customerBody = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #667eea;'>Your Support Ticket Has Been Created</h2>
            <p>Thank you for contacting us. Your support ticket has been created and our team will respond shortly.</p>

            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Ticket Number:</strong> {$ticket['ticket_number']}</p>
                <p><strong>Subject:</strong> {$ticket['subject']}</p>
                <p><strong>Status:</strong> " . ucfirst($ticket['status']) . "</p>
                <p><strong>Priority:</strong> {$ticket['priority_name']}</p>
                " . ($ticket['queue_name'] ? "<p><strong>Department:</strong> {$ticket['queue_name']}</p>" : "") . "
            </div>

            <h3>Your Message:</h3>
            <p>" . nl2br(htmlspecialchars($ticket['description'])) . "</p>

            <p style='margin-top: 30px;'>
                <a href='" . getBaseUrl() . "/portal.php' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    View Ticket
                </a>
            </p>

            <hr style='margin: 30px 0; border: none; border-top: 1px solid #e0e0e0;'>
            <p style='color: #6c757d; font-size: 12px;'>
                This is an automated message from {$ticket['company_name']}. Please do not reply directly to this email.
                To respond to your ticket, please use the customer portal.
            </p>
        </body>
        </html>
    ";

    sendEmail($ticket['contact_email'], $customerSubject, $customerBody);

    // Email to queue staff if queue is assigned
    if ($ticket['queue_id']) {
        sendNewTicketToQueue($ticketId, $ticket['queue_id']);
    }

    return true;
}

/**
 * Send notification to queue staff about new ticket
 */
function sendNewTicketToQueue($ticketId, $queueId) {
    global $pdo;

    // Get ticket details
    $stmt = $pdo->prepare("
        SELECT t.*, q.name as queue_name
        FROM tickets t
        LEFT JOIN ticket_queues q ON t.queue_id = q.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) return false;

    // Get queue staff with view permission
    $stmt = $pdo->prepare("
        SELECT u.email, u.first_name, u.last_name
        FROM queue_staff qs
        INNER JOIN users u ON qs.user_id = u.id
        WHERE qs.queue_id = ? AND qs.can_view = 1 AND u.status = 'active'
    ");
    $stmt->execute([$queueId]);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subject = "New Ticket: {$ticket['ticket_number']} - {$ticket['subject']}";
    $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #667eea;'>New Ticket in {$ticket['queue_name']}</h2>
            <p>A new support ticket has been created and assigned to your queue.</p>

            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Ticket:</strong> {$ticket['ticket_number']}</p>
                <p><strong>Subject:</strong> {$ticket['subject']}</p>
                <p><strong>From:</strong> {$ticket['contact_name']} ({$ticket['contact_email']})</p>
                <p><strong>Status:</strong> " . ucfirst($ticket['status']) . "</p>
            </div>

            <h3>Message:</h3>
            <p>" . nl2br(htmlspecialchars($ticket['description'])) . "</p>

            <p style='margin-top: 30px;'>
                <a href='" . getBaseUrl() . "/staff/helpdesk/ticket.php?id={$ticket['id']}' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    View Ticket
                </a>
            </p>
        </body>
        </html>
    ";

    foreach ($staff as $member) {
        sendEmail($member['email'], $subject, $body);
    }

    return true;
}

/**
 * Send notification when a ticket is assigned to staff
 */
function sendTicketAssignedEmail($ticketId, $userId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            u.email as staff_email,
            CONCAT(u.first_name, ' ', u.last_name) as staff_name
        FROM tickets t
        INNER JOIN users u ON u.id = ?
        WHERE t.id = ?
    ");
    $stmt->execute([$userId, $ticketId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) return false;

    $subject = "Ticket Assigned: {$data['ticket_number']} - {$data['subject']}";
    $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #667eea;'>Ticket Assigned to You</h2>
            <p>Hello {$data['staff_name']},</p>
            <p>A support ticket has been assigned to you.</p>

            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Ticket:</strong> {$data['ticket_number']}</p>
                <p><strong>Subject:</strong> {$data['subject']}</p>
                <p><strong>From:</strong> {$data['contact_name']} ({$data['contact_email']})</p>
                <p><strong>Status:</strong> " . ucfirst($data['status']) . "</p>
            </div>

            <p style='margin-top: 30px;'>
                <a href='" . getBaseUrl() . "/staff/helpdesk/ticket.php?id={$data['id']}' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    View Ticket
                </a>
            </p>
        </body>
        </html>
    ";

    sendEmail($data['staff_email'], $subject, $body);

    return true;
}

/**
 * Send notification when staff replies to a ticket
 */
function sendStaffReplyEmail($ticketId, $replyId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            tr.message as reply_message,
            CONCAT(u.first_name, ' ', u.last_name) as staff_name
        FROM tickets t
        INNER JOIN ticket_replies tr ON tr.id = ?
        LEFT JOIN users u ON tr.created_by = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$replyId, $ticketId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) return false;

    $subject = "Reply to Your Ticket: {$data['ticket_number']}";
    $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #667eea;'>New Reply to Your Support Ticket</h2>
            <p>Our support team has replied to your ticket.</p>

            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Ticket:</strong> {$data['ticket_number']}</p>
                <p><strong>Subject:</strong> {$data['subject']}</p>
                <p><strong>Status:</strong> " . ucfirst($data['status']) . "</p>
            </div>

            <h3>Response from {$data['staff_name']}:</h3>
            <div style='background: #e8f5e9; padding: 15px; border-left: 4px solid #28a745; border-radius: 4px;'>
                <p>" . nl2br(htmlspecialchars($data['reply_message'])) . "</p>
            </div>

            <p style='margin-top: 30px;'>
                <a href='" . getBaseUrl() . "/portal.php' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    View & Reply
                </a>
            </p>

            <hr style='margin: 30px 0; border: none; border-top: 1px solid #e0e0e0;'>
            <p style='color: #6c757d; font-size: 12px;'>
                To respond to this ticket, please use the customer portal. Do not reply directly to this email.
            </p>
        </body>
        </html>
    ";

    sendEmail($data['contact_email'], $subject, $body);

    // Send to watchers
    sendToWatchers($ticketId, $subject, $body);

    // Send to CC recipients
    sendToCC($ticketId, $subject, $body);

    return true;
}

/**
 * Send notification when customer replies to a ticket
 */
function sendCustomerReplyEmail($ticketId, $replyId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            tr.message as reply_message,
            u.email as assigned_email,
            CONCAT(u.first_name, ' ', u.last_name) as assigned_name
        FROM tickets t
        INNER JOIN ticket_replies tr ON tr.id = ?
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$replyId, $ticketId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) return false;

    $subject = "Customer Reply: {$data['ticket_number']} - {$data['subject']}";
    $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #667eea;'>Customer Reply on Ticket</h2>
            <p>The customer has replied to ticket {$data['ticket_number']}.</p>

            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Ticket:</strong> {$data['ticket_number']}</p>
                <p><strong>Subject:</strong> {$data['subject']}</p>
                <p><strong>From:</strong> {$data['contact_name']} ({$data['contact_email']})</p>
            </div>

            <h3>Customer's Message:</h3>
            <div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; border-radius: 4px;'>
                <p>" . nl2br(htmlspecialchars($data['reply_message'])) . "</p>
            </div>

            <p style='margin-top: 30px;'>
                <a href='" . getBaseUrl() . "/staff/helpdesk/ticket.php?id={$data['id']}' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    View & Respond
                </a>
            </p>
        </body>
        </html>
    ";

    // Send to assigned staff if assigned
    if ($data['assigned_email']) {
        sendEmail($data['assigned_email'], $subject, $body);
    }

    // Send to watchers
    sendToWatchers($ticketId, $subject, $body);

    return true;
}

/**
 * Send notification when ticket is resolved
 */
function sendTicketResolvedEmail($ticketId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            CONCAT(u.first_name, ' ', u.last_name) as resolved_by_name
        FROM tickets t
        LEFT JOIN users u ON t.resolved_by = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) return false;

    $subject = "Ticket Resolved: {$ticket['ticket_number']}";
    $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #28a745;'>Your Ticket Has Been Resolved</h2>
            <p>Your support ticket has been marked as resolved.</p>

            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Ticket:</strong> {$ticket['ticket_number']}</p>
                <p><strong>Subject:</strong> {$ticket['subject']}</p>
                <p><strong>Resolved By:</strong> {$ticket['resolved_by_name']}</p>
                <p><strong>Resolved At:</strong> " . date('F j, Y g:i A', strtotime($ticket['resolved_at'])) . "</p>
            </div>

            " . ($ticket['resolution_notes'] ? "
            <h3>Resolution Notes:</h3>
            <div style='background: #e8f5e9; padding: 15px; border-left: 4px solid #28a745; border-radius: 4px;'>
                <p>" . nl2br(htmlspecialchars($ticket['resolution_notes'])) . "</p>
            </div>
            " : "") . "

            <p style='margin-top: 30px;'>
                If you need further assistance, please reply to this ticket or submit a new one.
            </p>

            <p style='margin-top: 20px;'>
                <a href='" . getBaseUrl() . "/portal.php' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    View Ticket
                </a>
            </p>
        </body>
        </html>
    ";

    sendEmail($ticket['contact_email'], $subject, $body);

    return true;
}

/**
 * Send notification to staff mentioned in a ticket/reply
 */
function sendMentionNotification($ticketId, $mentionedUserId, $message) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            u.email as mentioned_email,
            CONCAT(u.first_name, ' ', u.last_name) as mentioned_name
        FROM tickets t
        CROSS JOIN users u
        WHERE t.id = ? AND u.id = ?
    ");
    $stmt->execute([$ticketId, $mentionedUserId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) return false;

    $subject = "You were mentioned in Ticket: {$data['ticket_number']}";
    $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #667eea;'>You Were Mentioned</h2>
            <p>Hello {$data['mentioned_name']},</p>
            <p>You were mentioned in a ticket.</p>

            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Ticket:</strong> {$data['ticket_number']}</p>
                <p><strong>Subject:</strong> {$data['subject']}</p>
            </div>

            <h3>Message:</h3>
            <div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; border-radius: 4px;'>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
            </div>

            <p style='margin-top: 30px;'>
                <a href='" . getBaseUrl() . "/staff/helpdesk/ticket.php?id={$data['id']}' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    View Ticket
                </a>
            </p>
        </body>
        </html>
    ";

    sendEmail($data['mentioned_email'], $subject, $body);

    return true;
}

/**
 * Send to ticket watchers
 */
function sendToWatchers($ticketId, $subject, $body) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT u.email
        FROM ticket_watchers tw
        INNER JOIN users u ON tw.user_id = u.id
        WHERE tw.ticket_id = ? AND u.status = 'active'
    ");
    $stmt->execute([$ticketId]);
    $watchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($watchers as $watcher) {
        sendEmail($watcher['email'], $subject, $body);
    }
}

/**
 * Send to CC recipients
 */
function sendToCC($ticketId, $subject, $body) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT email FROM ticket_cc WHERE ticket_id = ?
    ");
    $stmt->execute([$ticketId]);
    $ccList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ccList as $cc) {
        sendEmail($cc['email'], $subject, $body);
    }
}

/**
 * Get base URL for links in emails
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$protocol://$host";
}

/**
 * Wrapper function for sending emails
 * Uses the existing sendEmail function or PHPMailer
 */
function sendEmail($to, $subject, $body) {
    // Use existing email sending function if available
    if (function_exists('send_email')) {
        return send_email($to, $subject, $body);
    }

    // Fallback to PHP mail()
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: support@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    ];

    return mail($to, $subject, $body, implode("\r\n", $headers));
}
