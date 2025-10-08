<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

if (!isLoggedIn() || isSupplier()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleList();
            break;
        case 'view':
            handleView();
            break;
        case 'create':
            handleCreate();
            break;
        case 'update':
            handleUpdate();
            break;
        case 'assign':
            handleAssign();
            break;
        case 'change_status':
            handleChangeStatus();
            break;
        case 'change_priority':
            handleChangePriority();
            break;
        case 'change_queue':
            handleChangeQueue();
            break;
        case 'resolve':
            handleResolve();
            break;
        case 'add_reply':
            handleAddReply();
            break;
        case 'add_note':
            handleAddNote();
            break;
        case 'add_watcher':
            handleAddWatcher();
            break;
        case 'remove_watcher':
            handleRemoveWatcher();
            break;
        case 'add_cc':
            handleAddCC();
            break;
        case 'remove_cc':
            handleRemoveCC();
            break;
        case 'add_tags':
            handleAddTags();
            break;
        case 'remove_tag':
            handleRemoveTag();
            break;
        case 'activity':
            handleActivity();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleList() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];

    // Debug logging
    error_log("Tickets API called. User: {$currentUser['id']}, Company: {$companyId}");
    error_log("GET params: " . json_encode($_GET));

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
    $offset = ($page - 1) * $perPage;

    // Build WHERE conditions
    $where = ['t.company_id = :company_id'];
    $params = [':company_id' => $companyId];

    // Filter by queue
    if (isset($_GET['queue_id']) && $_GET['queue_id'] !== '') {
        $where[] = 't.queue_id = :queue_id';
        $params[':queue_id'] = $_GET['queue_id'];
    }

    // Filter by status
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $where[] = 't.status = :status';
        $params[':status'] = $_GET['status'];
    }

    // Filter by priority
    if (isset($_GET['priority_id']) && $_GET['priority_id'] !== '') {
        $where[] = 't.priority_id = :priority_id';
        $params[':priority_id'] = $_GET['priority_id'];
    }

    // Filter by assigned
    if (isset($_GET['assigned_to']) && $_GET['assigned_to'] !== '') {
        if ($_GET['assigned_to'] === 'unassigned') {
            $where[] = 't.assigned_to IS NULL';
        } elseif ($_GET['assigned_to'] === 'me') {
            $where[] = 't.assigned_to = :assigned_to';
            $params[':assigned_to'] = $currentUser['id'];
        } else {
            $where[] = 't.assigned_to = :assigned_to';
            $params[':assigned_to'] = $_GET['assigned_to'];
        }
    }

    // Filter by tag
    if (!empty($_GET['tag_id'])) {
        $where[] = 'EXISTS (SELECT 1 FROM ticket_tag_assignments tta WHERE tta.ticket_id = t.id AND tta.tag_id = :tag_id)';
        $params[':tag_id'] = $_GET['tag_id'];
    }

    // Search
    if (!empty($_GET['q'])) {
        $where[] = '(t.ticket_number LIKE :search OR t.subject LIKE :search OR t.contact_name LIKE :search OR t.contact_email LIKE :search)';
        $params[':search'] = '%' . $_GET['q'] . '%';
    }

    // Apply saved filter
    if (!empty($_GET['filter_id'])) {
        $stmt = $pdo->prepare("SELECT filter_data, sort_by, sort_direction FROM ticket_filters WHERE id = ? AND user_id = ?");
        $stmt->execute([$_GET['filter_id'], $currentUser['id']]);
        $filter = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($filter) {
            $filterData = json_decode($filter['filter_data'], true);
            // Apply filter conditions (simplified - expand as needed)
            foreach ($filterData as $field => $value) {
                if ($field === 'status' && !empty($value)) {
                    $where[] = "t.status = :filter_status";
                    $params[':filter_status'] = $value;
                }
                // Add more filter conditions as needed
            }
        }
    }

    $whereClause = implode(' AND ', $where);

    // Debug logging
    error_log("WHERE clause: $whereClause");
    error_log("Params: " . json_encode($params));

    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t WHERE $whereClause");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    error_log("Total tickets found: $total");

    // Get sort
    $sortBy = $_GET['sort_by'] ?? 'created_at';
    $sortDir = $_GET['sort_dir'] ?? 'desc';
    $allowedSort = ['created_at', 'updated_at', 'priority_id', 'status', 'ticket_number'];
    if (!in_array($sortBy, $allowedSort)) $sortBy = 'created_at';
    if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'desc';

    // Get tickets
    $stmt = $pdo->prepare("
        SELECT
            t.*,
            q.name as queue_name,
            q.color as queue_color,
            p.name as priority_name,
            p.color as priority_color,
            p.level as priority_level,
            CONCAT(u_assigned.first_name, ' ', u_assigned.last_name) as assigned_to_name,
            (SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = t.id) as reply_count,
            (SELECT COUNT(*) FROM ticket_watchers WHERE ticket_id = t.id) as watcher_count
        FROM tickets t
        LEFT JOIN ticket_queues q ON t.queue_id = q.id
        LEFT JOIN ticket_priorities p ON t.priority_id = p.id
        LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
        WHERE $whereClause
        ORDER BY t.$sortBy $sortDir
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get tags for each ticket
    foreach ($tickets as &$ticket) {
        $tagStmt = $pdo->prepare("
            SELECT tt.id, tt.name, tt.slug, tt.color
            FROM ticket_tags tt
            INNER JOIN ticket_tag_assignments tta ON tt.id = tta.tag_id
            WHERE tta.ticket_id = ?
        ");
        $tagStmt->execute([$ticket['id']]);
        $ticket['tags'] = $tagStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'data' => $tickets,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => ceil($total / $perPage)
        ]
    ]);
}

function handleView() {
    global $pdo;
    $currentUser = currentUser();
    $ticketId = $_GET['id'] ?? null;

    if (!$ticketId) {
        throw new Exception('Ticket ID required');
    }

    // Get ticket with full details
    $stmt = $pdo->prepare("
        SELECT
            t.*,
            q.name as queue_name,
            q.color as queue_color,
            q.icon as queue_icon,
            p.name as priority_name,
            p.color as priority_color,
            p.level as priority_level,
            CONCAT(u_assigned.first_name, ' ', u_assigned.last_name) as assigned_to_name,
            u_assigned.email as assigned_to_email,
            CONCAT(u_resolved.first_name, ' ', u_resolved.last_name) as resolved_by_name
        FROM tickets t
        LEFT JOIN ticket_queues q ON t.queue_id = q.id
        LEFT JOIN ticket_priorities p ON t.priority_id = p.id
        LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
        LEFT JOIN users u_resolved ON t.resolved_by = u_resolved.id
        WHERE t.id = ? AND t.company_id = ?
    ");
    $stmt->execute([$ticketId, $currentUser['company_id']]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('Ticket not found');
    }

    // Get replies
    $replyStmt = $pdo->prepare("
        SELECT
            r.*,
            CONCAT(u.first_name, ' ', u.last_name) as user_full_name,
            u.avatar as user_avatar
        FROM ticket_replies r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.ticket_id = ?
        ORDER BY r.created_at ASC
    ");
    $replyStmt->execute([$ticketId]);
    $ticket['replies'] = $replyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get tags
    $tagStmt = $pdo->prepare("
        SELECT tt.id, tt.name, tt.slug, tt.color
        FROM ticket_tags tt
        INNER JOIN ticket_tag_assignments tta ON tt.id = tta.tag_id
        WHERE tta.ticket_id = ?
    ");
    $tagStmt->execute([$ticketId]);
    $ticket['tags'] = $tagStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get watchers
    $watcherStmt = $pdo->prepare("
        SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, u.email, u.avatar
        FROM ticket_watchers tw
        INNER JOIN users u ON tw.user_id = u.id
        WHERE tw.ticket_id = ?
    ");
    $watcherStmt->execute([$ticketId]);
    $ticket['watchers'] = $watcherStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get CC
    $ccStmt = $pdo->prepare("SELECT * FROM ticket_cc WHERE ticket_id = ?");
    $ccStmt->execute([$ticketId]);
    $ticket['cc'] = $ccStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get attachments
    $attachStmt = $pdo->prepare("SELECT * FROM ticket_attachments WHERE ticket_id = ? ORDER BY created_at DESC");
    $attachStmt->execute([$ticketId]);
    $ticket['attachments'] = $attachStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $ticket
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    // Validation
    $required = ['contact_name', 'contact_email', 'subject', 'description'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field $field is required");
        }
    }

    // Generate ticket number
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(ticket_number, 10) AS UNSIGNED)) FROM tickets WHERE ticket_number LIKE ?");
    $stmt->execute(["TKT-$year-%"]);
    $lastNum = $stmt->fetchColumn() ?: 0;
    $ticketNumber = sprintf("TKT-%s-%05d", $year, $lastNum + 1);

    // Insert ticket
    $stmt = $pdo->prepare("
        INSERT INTO tickets (
            ticket_number, company_id, queue_id, priority_id,
            contact_name, contact_email, contact_phone, user_id,
            subject, description, status, source, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', 'web', ?, ?)
    ");

    $stmt->execute([
        $ticketNumber,
        $currentUser['company_id'],
        $input['queue_id'] ?? null,
        $input['priority_id'] ?? 2, // Default to Medium
        $input['contact_name'],
        $input['contact_email'],
        $input['contact_phone'] ?? null,
        $input['user_id'] ?? null,
        $input['subject'],
        $input['description'],
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $ticketId = $pdo->lastInsertId();

    // Log activity
    logTicketActivity($ticketId, null, 'created', null, null, null, "Ticket created by {$input['contact_name']}");

    // Auto-assign if specified
    if (!empty($input['assigned_to'])) {
        assignTicket($ticketId, $input['assigned_to'], $currentUser['id']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ticket created successfully',
        'ticket_id' => $ticketId,
        'ticket_number' => $ticketNumber
    ]);
}

function handleUpdate() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['id'] ?? null;
    if (!$ticketId) {
        throw new Exception('Ticket ID required');
    }

    // Get current ticket
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND company_id = ?");
    $stmt->execute([$ticketId, $currentUser['company_id']]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('Ticket not found');
    }

    // Update fields
    $updates = [];
    $params = [];

    if (isset($input['subject'])) {
        $updates[] = 'subject = ?';
        $params[] = $input['subject'];
    }

    if (isset($input['description'])) {
        $updates[] = 'description = ?';
        $params[] = $input['description'];
    }

    if (isset($input['contact_name'])) {
        $updates[] = 'contact_name = ?';
        $params[] = $input['contact_name'];
    }

    if (isset($input['contact_email'])) {
        $updates[] = 'contact_email = ?';
        $params[] = $input['contact_email'];
    }

    if (isset($input['contact_phone'])) {
        $updates[] = 'contact_phone = ?';
        $params[] = $input['contact_phone'];
    }

    if (empty($updates)) {
        throw new Exception('No fields to update');
    }

    $params[] = $ticketId;

    $stmt = $pdo->prepare("UPDATE tickets SET " . implode(', ', $updates) . " WHERE id = ?");
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Ticket updated successfully'
    ]);
}

function handleAssign() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $assignTo = $input['assign_to'] ?? null;

    if (!$ticketId) {
        throw new Exception('Ticket ID required');
    }

    assignTicket($ticketId, $assignTo, $currentUser['id']);

    echo json_encode([
        'success' => true,
        'message' => 'Ticket assigned successfully'
    ]);
}

function handleChangeStatus() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $newStatus = $input['status'] ?? null;

    if (!$ticketId || !$newStatus) {
        throw new Exception('Ticket ID and status required');
    }

    // Get current ticket
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND company_id = ?");
    $stmt->execute([$ticketId, $currentUser['company_id']]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('Ticket not found');
    }

    $oldStatus = $ticket['status'];

    // Update status
    $stmt = $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $ticketId]);

    // Log activity
    logTicketActivity($ticketId, $currentUser['id'], 'status_changed', 'status', $oldStatus, $newStatus,
        "Status changed from " . ucfirst($oldStatus) . " to " . ucfirst($newStatus));

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);
}

function handleChangePriority() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $priorityId = $input['priority_id'] ?? null;

    if (!$ticketId || !$priorityId) {
        throw new Exception('Ticket ID and priority required');
    }

    // Get current ticket
    $stmt = $pdo->prepare("SELECT priority_id FROM tickets WHERE id = ? AND company_id = ?");
    $stmt->execute([$ticketId, $currentUser['company_id']]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('Ticket not found');
    }

    $oldPriority = $ticket['priority_id'];

    // Update priority
    $stmt = $pdo->prepare("UPDATE tickets SET priority_id = ? WHERE id = ?");
    $stmt->execute([$priorityId, $ticketId]);

    // Log activity
    logTicketActivity($ticketId, $currentUser['id'], 'priority_changed', 'priority_id', $oldPriority, $priorityId,
        "Priority changed");

    echo json_encode([
        'success' => true,
        'message' => 'Priority updated successfully'
    ]);
}

function handleChangeQueue() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $queueId = $input['queue_id'] ?? null;

    if (!$ticketId) {
        throw new Exception('Ticket ID required');
    }

    // Get current ticket
    $stmt = $pdo->prepare("SELECT queue_id FROM tickets WHERE id = ? AND company_id = ?");
    $stmt->execute([$ticketId, $currentUser['company_id']]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('Ticket not found');
    }

    $oldQueue = $ticket['queue_id'];

    // Update queue
    $stmt = $pdo->prepare("UPDATE tickets SET queue_id = ? WHERE id = ?");
    $stmt->execute([$queueId, $ticketId]);

    // Log activity
    logTicketActivity($ticketId, $currentUser['id'], 'queue_changed', 'queue_id', $oldQueue, $queueId,
        "Queue changed");

    echo json_encode([
        'success' => true,
        'message' => 'Queue updated successfully'
    ]);
}

function handleResolve() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $resolutionNotes = $input['resolution_notes'] ?? null;

    if (!$ticketId || !$resolutionNotes) {
        throw new Exception('Ticket ID and resolution notes required');
    }

    // Update ticket
    $stmt = $pdo->prepare("
        UPDATE tickets
        SET status = 'resolved',
            resolved_by = ?,
            resolved_at = NOW(),
            resolution_notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$currentUser['id'], $resolutionNotes, $ticketId]);

    // Log activity
    logTicketActivity($ticketId, $currentUser['id'], 'resolved', 'status', 'open', 'resolved',
        "Ticket marked as resolved");

    echo json_encode([
        'success' => true,
        'message' => 'Ticket resolved successfully'
    ]);
}

function handleAddReply() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $message = $input['message'] ?? null;
    $isPrivate = $input['is_private'] ?? false;

    if (!$ticketId || !$message) {
        throw new Exception('Ticket ID and message required');
    }

    // Insert reply
    $stmt = $pdo->prepare("
        INSERT INTO ticket_replies (
            ticket_id, user_id, author_name, author_email,
            is_staff, is_private, message, source, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, 1, ?, ?, 'web', ?, ?)
    ");

    $stmt->execute([
        $ticketId,
        $currentUser['id'],
        $currentUser['first_name'] . ' ' . $currentUser['last_name'],
        $currentUser['email'],
        $isPrivate ? 1 : 0,
        $message,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $replyId = $pdo->lastInsertId();

    // Update ticket
    $stmt = $pdo->prepare("
        UPDATE tickets
        SET last_staff_reply_at = NOW(),
            first_response_at = COALESCE(first_response_at, NOW())
        WHERE id = ?
    ");
    $stmt->execute([$ticketId]);

    // Log activity
    $activityDesc = $isPrivate ? 'Added a private reply' : 'Replied to ticket';
    logTicketActivity(
        $ticketId,
        $currentUser['id'],
        'reply_added',
        'reply',
        null,
        null,
        $activityDesc
    );

    // Check for staff mentions (@username)
    if (preg_match_all('/@(\w+)/', $message, $matches)) {
        foreach ($matches[1] as $username) {
            // Find user by first/last name
            $userStmt = $pdo->prepare("
                SELECT id FROM users
                WHERE (first_name LIKE ? OR last_name LIKE ?)
                AND company_id = ?
                LIMIT 1
            ");
            $userStmt->execute(["%$username%", "%$username%", $currentUser['company_id']]);
            $mentionedUser = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($mentionedUser) {
                $mentionStmt = $pdo->prepare("
                    INSERT INTO ticket_mentions (ticket_id, reply_id, mentioned_user_id, mentioned_by)
                    VALUES (?, ?, ?, ?)
                ");
                $mentionStmt->execute([$ticketId, $replyId, $mentionedUser['id'], $currentUser['id']]);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Reply added successfully'
    ]);
}

function handleAddNote() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $note = $input['note'] ?? $input['message'] ?? null; // Accept both 'note' and 'message'

    if (!$ticketId || !$note) {
        throw new Exception('Ticket ID and note required');
    }

    // Insert private note (reply with is_private = true)
    $stmt = $pdo->prepare("
        INSERT INTO ticket_replies (
            ticket_id, user_id, author_name, author_email,
            is_staff, is_private, message, source
        ) VALUES (?, ?, ?, ?, 1, 1, ?, 'web')
    ");

    $stmt->execute([
        $ticketId,
        $currentUser['id'],
        $currentUser['first_name'] . ' ' . $currentUser['last_name'],
        $currentUser['email'],
        $note
    ]);

    // Log activity
    logTicketActivity(
        $ticketId,
        $currentUser['id'],
        'note_added',
        'note',
        null,
        null,
        'Added a private note'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Note added successfully'
    ]);
}

function handleAddWatcher() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $userId = $input['user_id'] ?? null;

    if (!$ticketId || !$userId) {
        throw new Exception('Ticket ID and user ID required');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO ticket_watchers (ticket_id, user_id) VALUES (?, ?)");
        $stmt->execute([$ticketId, $userId]);

        // Get user name
        $userStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $userName = $userStmt->fetchColumn();

        // Log activity
        logTicketActivity(
            $ticketId,
            $currentUser['id'],
            'watcher_added',
            'watchers',
            null,
            $userName,
            'Added watcher: ' . $userName
        );

        echo json_encode([
            'success' => true,
            'message' => 'Watcher added successfully'
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            throw new Exception('User is already watching this ticket');
        }
        throw $e;
    }
}

function handleRemoveWatcher() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $userId = $input['user_id'] ?? null;

    if (!$ticketId || !$userId) {
        throw new Exception('Ticket ID and user ID required');
    }

    // Get user name before deleting
    $userStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userName = $userStmt->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM ticket_watchers WHERE ticket_id = ? AND user_id = ?");
    $stmt->execute([$ticketId, $userId]);

    // Log activity
    if ($stmt->rowCount() > 0 && $userName) {
        logTicketActivity(
            $ticketId,
            $currentUser['id'],
            'watcher_removed',
            'watchers',
            $userName,
            null,
            'Removed watcher: ' . $userName
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Watcher removed successfully'
    ]);
}

function handleAddCC() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $email = $input['email'] ?? null;
    $name = $input['name'] ?? null;

    if (!$ticketId || !$email) {
        throw new Exception('Ticket ID and email required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO ticket_cc (ticket_id, email, name, added_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ticketId, $email, $name, $currentUser['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'CC added successfully'
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            throw new Exception('Email is already in CC list');
        }
        throw $e;
    }
}

function handleRemoveCC() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $ccId = $input['cc_id'] ?? null;

    if (!$ccId) {
        throw new Exception('CC ID required');
    }

    $stmt = $pdo->prepare("DELETE FROM ticket_cc WHERE id = ?");
    $stmt->execute([$ccId]);

    echo json_encode([
        'success' => true,
        'message' => 'CC removed successfully'
    ]);
}

function handleAddTags() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $tagIds = $input['tag_ids'] ?? [];

    if (!$ticketId || empty($tagIds)) {
        throw new Exception('Ticket ID and tag IDs required');
    }

    $addedTags = [];
    foreach ($tagIds as $tagId) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO ticket_tag_assignments (ticket_id, tag_id) VALUES (?, ?)");
            $stmt->execute([$ticketId, $tagId]);

            if ($stmt->rowCount() > 0) {
                // Get tag name
                $tagStmt = $pdo->prepare("SELECT name FROM ticket_tags WHERE id = ?");
                $tagStmt->execute([$tagId]);
                $tagName = $tagStmt->fetchColumn();
                $addedTags[] = $tagName;
            }
        } catch (PDOException $e) {
            // Ignore duplicate errors
        }
    }

    // Log activity for added tags
    if (!empty($addedTags)) {
        logTicketActivity(
            $ticketId,
            $currentUser['id'],
            'tags_added',
            'tags',
            null,
            implode(', ', $addedTags),
            'Tags added: ' . implode(', ', $addedTags)
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tags added successfully'
    ]);
}

function handleRemoveTag() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = $input['ticket_id'] ?? null;
    $tagId = $input['tag_id'] ?? null;

    if (!$ticketId || !$tagId) {
        throw new Exception('Ticket ID and tag ID required');
    }

    // Get tag name before deleting
    $tagStmt = $pdo->prepare("SELECT name FROM ticket_tags WHERE id = ?");
    $tagStmt->execute([$tagId]);
    $tagName = $tagStmt->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM ticket_tag_assignments WHERE ticket_id = ? AND tag_id = ?");
    $stmt->execute([$ticketId, $tagId]);

    // Log activity
    if ($stmt->rowCount() > 0 && $tagName) {
        logTicketActivity(
            $ticketId,
            $currentUser['id'],
            'tag_removed',
            'tags',
            $tagName,
            null,
            'Tag removed: ' . $tagName
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tag removed successfully'
    ]);
}

function handleActivity() {
    global $pdo;
    $ticketId = $_GET['ticket_id'] ?? null;

    if (!$ticketId) {
        throw new Exception('Ticket ID required');
    }

    $stmt = $pdo->prepare("
        SELECT
            tal.*,
            CONCAT(u.first_name, ' ', u.last_name) as user_name
        FROM ticket_activity_log tal
        LEFT JOIN users u ON tal.user_id = u.id
        WHERE tal.ticket_id = ?
        ORDER BY tal.created_at DESC
    ");
    $stmt->execute([$ticketId]);
    $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $activity
    ]);
}

// Helper functions
function assignTicket($ticketId, $assignTo, $assignedBy) {
    global $pdo;

    // Get current assignment
    $stmt = $pdo->prepare("SELECT assigned_to FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    $oldAssignee = $ticket['assigned_to'];

    // Update assignment
    $stmt = $pdo->prepare("UPDATE tickets SET assigned_to = ?, assigned_at = NOW() WHERE id = ?");
    $stmt->execute([$assignTo, $ticketId]);

    // Log activity
    $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
    $stmt->execute([$assignTo]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    logTicketActivity($ticketId, $assignedBy, 'assigned', 'assigned_to', $oldAssignee, $assignTo,
        "Ticket assigned to " . ($user['name'] ?? 'Unassigned'));
}

function logTicketActivity($ticketId, $userId, $action, $fieldName, $oldValue, $newValue, $description) {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO ticket_activity_log (
            ticket_id, user_id, action, field_name, old_value, new_value, description, ip_address
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $ticketId,
        $userId,
        $action,
        $fieldName,
        $oldValue,
        $newValue,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
