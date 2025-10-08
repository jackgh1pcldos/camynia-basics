<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

if (!isLoggedIn() || isSupplier()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

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
        case 'delete':
            handleDelete();
            break;
        case 'staff':
            handleStaff();
            break;
        case 'assign_staff':
            handleAssignStaff();
            break;
        case 'remove_staff':
            handleRemoveStaff();
            break;
        case 'my_queues':
            handleMyQueues();
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

    $stmt = $pdo->prepare("
        SELECT
            q.*,
            (SELECT COUNT(*) FROM tickets WHERE queue_id = q.id AND status NOT IN ('resolved', 'closed')) as open_tickets,
            (SELECT COUNT(*) FROM queue_staff WHERE queue_id = q.id) as staff_count
        FROM ticket_queues q
        WHERE q.company_id = ? AND q.is_active = 1
        ORDER BY q.sort_order, q.name
    ");
    $stmt->execute([$currentUser['company_id']]);
    $queues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $queues
    ]);
}

function handleView() {
    global $pdo;
    $currentUser = currentUser();
    $queueId = $_GET['id'] ?? null;

    if (!$queueId) {
        throw new Exception('Queue ID required');
    }

    $stmt = $pdo->prepare("
        SELECT * FROM ticket_queues
        WHERE id = ? AND company_id = ?
    ");
    $stmt->execute([$queueId, $currentUser['company_id']]);
    $queue = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$queue) {
        throw new Exception('Queue not found');
    }

    // Get assigned staff
    $staffStmt = $pdo->prepare("
        SELECT
            qs.*,
            u.id as user_id,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            u.email,
            u.avatar
        FROM queue_staff qs
        INNER JOIN users u ON qs.user_id = u.id
        WHERE qs.queue_id = ?
    ");
    $staffStmt->execute([$queueId]);
    $queue['staff'] = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $queue
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $required = ['name', 'slug'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field $field is required");
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO ticket_queues (
            company_id, name, slug, description, email, color, icon,
            sort_order, sla_first_response_hours, sla_resolution_hours
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $currentUser['company_id'],
        $input['name'],
        $input['slug'],
        $input['description'] ?? null,
        $input['email'] ?? null,
        $input['color'] ?? '#3498db',
        $input['icon'] ?? 'inbox',
        $input['sort_order'] ?? 0,
        $input['sla_first_response_hours'] ?? null,
        $input['sla_resolution_hours'] ?? null
    ]);

    $queueId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Queue created successfully',
        'id' => $queueId
    ]);
}

function handleUpdate() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $queueId = $input['id'] ?? null;
    if (!$queueId) {
        throw new Exception('Queue ID required');
    }

    $stmt = $pdo->prepare("
        UPDATE ticket_queues
        SET name = ?,
            description = ?,
            email = ?,
            color = ?,
            icon = ?,
            sort_order = ?,
            sla_first_response_hours = ?,
            sla_resolution_hours = ?
        WHERE id = ? AND company_id = ?
    ");

    $stmt->execute([
        $input['name'],
        $input['description'] ?? null,
        $input['email'] ?? null,
        $input['color'] ?? '#3498db',
        $input['icon'] ?? 'inbox',
        $input['sort_order'] ?? 0,
        $input['sla_first_response_hours'] ?? null,
        $input['sla_resolution_hours'] ?? null,
        $queueId,
        $currentUser['company_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Queue updated successfully'
    ]);
}

function handleDelete() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $queueId = $input['id'] ?? null;
    if (!$queueId) {
        throw new Exception('Queue ID required');
    }

    // Soft delete
    $stmt = $pdo->prepare("UPDATE ticket_queues SET is_active = 0 WHERE id = ? AND company_id = ?");
    $stmt->execute([$queueId, $currentUser['company_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Queue deleted successfully'
    ]);
}

function handleStaff() {
    global $pdo;
    $currentUser = currentUser();
    $queueId = $_GET['queue_id'] ?? null;

    if (!$queueId) {
        throw new Exception('Queue ID required');
    }

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            u.email,
            u.avatar,
            qs.can_assign,
            qs.can_view,
            qs.can_respond
        FROM queue_staff qs
        INNER JOIN users u ON qs.user_id = u.id
        WHERE qs.queue_id = ?
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$queueId]);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $staff
    ]);
}

function handleAssignStaff() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $queueId = $input['queue_id'] ?? null;
    $userIds = $input['user_ids'] ?? [];
    $canAssign = $input['can_assign'] ?? true;
    $canView = $input['can_view'] ?? true;
    $canRespond = $input['can_respond'] ?? true;

    if (!$queueId || empty($userIds)) {
        throw new Exception('Queue ID and user IDs required');
    }

    foreach ($userIds as $userId) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO queue_staff (queue_id, user_id, can_assign, can_view, can_respond)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    can_assign = VALUES(can_assign),
                    can_view = VALUES(can_view),
                    can_respond = VALUES(can_respond)
            ");
            $stmt->execute([$queueId, $userId, $canAssign, $canView, $canRespond]);
        } catch (PDOException $e) {
            // Ignore errors
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Staff assigned successfully'
    ]);
}

function handleRemoveStaff() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $queueId = $input['queue_id'] ?? null;
    $userId = $input['user_id'] ?? null;

    if (!$queueId || !$userId) {
        throw new Exception('Queue ID and user ID required');
    }

    $stmt = $pdo->prepare("DELETE FROM queue_staff WHERE queue_id = ? AND user_id = ?");
    $stmt->execute([$queueId, $userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Staff removed successfully'
    ]);
}

function handleMyQueues() {
    global $pdo;
    $currentUser = currentUser();

    $stmt = $pdo->prepare("
        SELECT
            q.*,
            qs.can_assign,
            qs.can_view,
            qs.can_respond,
            (SELECT COUNT(*) FROM tickets WHERE queue_id = q.id AND status NOT IN ('resolved', 'closed')) as open_tickets,
            (SELECT COUNT(*) FROM tickets WHERE queue_id = q.id AND assigned_to IS NULL AND status NOT IN ('resolved', 'closed')) as unassigned_tickets
        FROM ticket_queues q
        INNER JOIN queue_staff qs ON q.id = qs.queue_id
        WHERE qs.user_id = ? AND q.is_active = 1
        ORDER BY q.sort_order, q.name
    ");
    $stmt->execute([$currentUser['id']]);
    $queues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $queues
    ]);
}
