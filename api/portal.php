<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

header('Content-Type: application/json');

// Public API for customer portal
// No authentication but requires ticket number + email match

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'verify':
            handleVerify($pdo);
            break;
        case 'view':
            handleView($pdo);
            break;
        case 'reply':
            handleReply($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleVerify($pdo) {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $ticketNumber = $input['ticket_number'] ?? '';
    $email = $input['email'] ?? '';

    if (!$ticketNumber || !$email) {
        throw new Exception('Ticket number and email are required');
    }

    // Find ticket matching number and email
    $stmt = $pdo->prepare("
        SELECT id, ticket_number, subject, status, created_at
        FROM tickets
        WHERE ticket_number = ? AND contact_email = ?
    ");
    $stmt->execute([$ticketNumber, $email]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('Ticket not found or email does not match');
    }

    // Generate a temporary access token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Store token (you could create a customer_sessions table, or use a simple approach)
    // For now, we'll return the ticket data directly
    // In production, you'd want proper session management

    echo json_encode([
        'success' => true,
        'ticket' => $ticket,
        'token' => $token
    ]);
}

function handleView($pdo) {
    $ticketNumber = $_GET['ticket_number'] ?? '';
    $email = $_GET['email'] ?? '';

    if (!$ticketNumber || !$email) {
        throw new Exception('Ticket number and email are required');
    }

    // Get ticket details
    $stmt = $pdo->prepare("
        SELECT
            t.*,
            p.name as priority_name,
            p.color as priority_color,
            q.name as queue_name,
            q.color as queue_color
        FROM tickets t
        LEFT JOIN ticket_priorities p ON t.priority_id = p.id
        LEFT JOIN ticket_queues q ON t.queue_id = q.id
        WHERE t.ticket_number = ? AND t.contact_email = ?
    ");
    $stmt->execute([$ticketNumber, $email]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('Ticket not found or email does not match');
    }

    // Get public replies only (not internal notes)
    $stmt = $pdo->prepare("
        SELECT
            tr.*,
            CONCAT(u.first_name, ' ', u.last_name) as staff_name
        FROM ticket_replies tr
        LEFT JOIN users u ON tr.created_by = u.id
        WHERE tr.ticket_id = ? AND tr.is_note = 0
        ORDER BY tr.created_at ASC
    ");
    $stmt->execute([$ticket['id']]);
    $ticket['replies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get attachments
    $stmt = $pdo->prepare("
        SELECT * FROM ticket_attachments
        WHERE ticket_id = ?
        ORDER BY uploaded_at ASC
    ");
    $stmt->execute([$ticket['id']]);
    $ticket['attachments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $ticket
    ]);
}

function handleReply($pdo) {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $ticketNumber = $input['ticket_number'] ?? '';
    $email = $input['email'] ?? '';
    $message = $input['message'] ?? '';

    if (!$ticketNumber || !$email || !$message) {
        throw new Exception('All fields are required');
    }

    // Verify ticket ownership
    $stmt = $pdo->prepare("
        SELECT id, status FROM tickets
        WHERE ticket_number = ? AND contact_email = ?
    ");
    $stmt->execute([$ticketNumber, $email]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('Ticket not found or email does not match');
    }

    // Check if ticket is closed
    if (in_array($ticket['status'], ['closed', 'cancelled'])) {
        throw new Exception('Cannot reply to a closed ticket');
    }

    // Add reply
    $stmt = $pdo->prepare("
        INSERT INTO ticket_replies (ticket_id, message, is_note, is_customer)
        VALUES (?, ?, 0, 1)
    ");
    $stmt->execute([$ticket['id'], $message]);

    // Update ticket status if it was resolved
    if ($ticket['status'] === 'resolved') {
        $stmt = $pdo->prepare("UPDATE tickets SET status = 'open' WHERE id = ?");
        $stmt->execute([$ticket['id']]);
    }

    // Update last_activity_at
    $stmt = $pdo->prepare("UPDATE tickets SET last_activity_at = NOW() WHERE id = ?");
    $stmt->execute([$ticket['id']]);

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO ticket_activity_log (ticket_id, activity_type, description)
        VALUES (?, 'customer_reply', 'Customer added a reply')
    ");
    $stmt->execute([$ticket['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Reply added successfully'
    ]);
}
