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
        // Tags
        case 'tags':
            handleTags();
            break;
        case 'create_tag':
            handleCreateTag();
            break;
        case 'update_tag':
            handleUpdateTag();
            break;
        case 'delete_tag':
            handleDeleteTag();
            break;

        // Priorities
        case 'priorities':
            handlePriorities();
            break;
        case 'create_priority':
            handleCreatePriority();
            break;
        case 'update_priority':
            handleUpdatePriority();
            break;
        case 'delete_priority':
            handleDeletePriority();
            break;

        // Stats
        case 'stats':
            handleStats();
            break;

        // Staff
        case 'staff_list':
            handleStaffList();
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleTags() {
    global $pdo;
    $currentUser = currentUser();

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            (SELECT COUNT(*) FROM ticket_tag_assignments WHERE tag_id = t.id) as usage_count
        FROM ticket_tags t
        WHERE t.company_id = ?
        ORDER BY t.name ASC
    ");
    $stmt->execute([$currentUser['company_id']]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $tags
    ]);
}

function handleCreateTag() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $name = $input['name'] ?? null;
    $slug = $input['slug'] ?? null;
    $color = $input['color'] ?? '#6c757d';

    if (!$name || !$slug) {
        throw new Exception('Name and slug required');
    }

    $stmt = $pdo->prepare("
        INSERT INTO ticket_tags (company_id, name, slug, color)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$currentUser['company_id'], $name, $slug, $color]);

    echo json_encode([
        'success' => true,
        'message' => 'Tag created successfully',
        'id' => $pdo->lastInsertId()
    ]);
}

function handleUpdateTag() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $tagId = $input['id'] ?? null;
    if (!$tagId) {
        throw new Exception('Tag ID required');
    }

    $stmt = $pdo->prepare("
        UPDATE ticket_tags
        SET name = ?, color = ?
        WHERE id = ? AND company_id = ?
    ");
    $stmt->execute([
        $input['name'],
        $input['color'] ?? '#6c757d',
        $tagId,
        $currentUser['company_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Tag updated successfully'
    ]);
}

function handleDeleteTag() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $tagId = $input['id'] ?? null;
    if (!$tagId) {
        throw new Exception('Tag ID required');
    }

    // Delete tag assignments first
    $stmt = $pdo->prepare("DELETE FROM ticket_tag_assignments WHERE tag_id = ?");
    $stmt->execute([$tagId]);

    // Delete tag
    $stmt = $pdo->prepare("DELETE FROM ticket_tags WHERE id = ? AND company_id = ?");
    $stmt->execute([$tagId, $currentUser['company_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Tag deleted successfully'
    ]);
}

function handlePriorities() {
    global $pdo;

    $stmt = $pdo->query("SELECT * FROM ticket_priorities ORDER BY level ASC");
    $priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $priorities
    ]);
}

function handleCreatePriority() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $name = $input['name'] ?? null;
    $level = $input['level'] ?? null;
    $color = $input['color'] ?? '#6c757d';
    $slaMinutes = $input['sla_minutes'] ?? null;

    if (!$name || !$level) {
        throw new Exception('Name and level required');
    }

    // Check if setting as default
    $isDefault = $input['is_default'] ?? 0;
    if ($isDefault) {
        // Remove default from others
        $pdo->exec("UPDATE ticket_priorities SET is_default = 0");
    }

    $stmt = $pdo->prepare("
        INSERT INTO ticket_priorities (name, level, color, sla_minutes, is_default)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $level, $color, $slaMinutes, $isDefault]);

    echo json_encode([
        'success' => true,
        'message' => 'Priority created successfully',
        'id' => $pdo->lastInsertId()
    ]);
}

function handleUpdatePriority() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $priorityId = $input['id'] ?? null;
    if (!$priorityId) {
        throw new Exception('Priority ID required');
    }

    // Check if setting as default
    $isDefault = $input['is_default'] ?? 0;
    if ($isDefault) {
        // Remove default from others
        $pdo->exec("UPDATE ticket_priorities SET is_default = 0");
    }

    $stmt = $pdo->prepare("
        UPDATE ticket_priorities
        SET name = ?, level = ?, color = ?, sla_minutes = ?, is_default = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $input['name'],
        $input['level'],
        $input['color'] ?? '#6c757d',
        $input['sla_minutes'] ?? null,
        $isDefault,
        $priorityId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Priority updated successfully'
    ]);
}

function handleDeletePriority() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $priorityId = $input['id'] ?? null;
    if (!$priorityId) {
        throw new Exception('Priority ID required');
    }

    // Check if any tickets are using this priority
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE priority_id = ?");
    $stmt->execute([$priorityId]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        throw new Exception("Cannot delete priority: $count ticket(s) are using it");
    }

    // Delete priority
    $stmt = $pdo->prepare("DELETE FROM ticket_priorities WHERE id = ?");
    $stmt->execute([$priorityId]);

    echo json_encode([
        'success' => true,
        'message' => 'Priority deleted successfully'
    ]);
}

function handleStats() {
    global $pdo;
    $currentUser = currentUser();

    $stats = [];

    // Total tickets
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE company_id = ?");
    $stmt->execute([$currentUser['company_id']]);
    $stats['total_tickets'] = $stmt->fetchColumn();

    // Open tickets
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE company_id = ? AND status IN ('new', 'open', 'pending')");
    $stmt->execute([$currentUser['company_id']]);
    $stats['open_tickets'] = $stmt->fetchColumn();

    // Unassigned tickets
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE company_id = ? AND assigned_to IS NULL AND status NOT IN ('resolved', 'closed', 'cancelled')");
    $stmt->execute([$currentUser['company_id']]);
    $stats['unassigned_tickets'] = $stmt->fetchColumn();

    // My tickets
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE company_id = ? AND assigned_to = ? AND status NOT IN ('resolved', 'closed', 'cancelled')");
    $stmt->execute([$currentUser['company_id'], $currentUser['id']]);
    $stats['my_tickets'] = $stmt->fetchColumn();

    // Tickets by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM tickets
        WHERE company_id = ?
        GROUP BY status
    ");
    $stmt->execute([$currentUser['company_id']]);
    $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Tickets by priority
    $stmt = $pdo->prepare("
        SELECT p.name, COUNT(t.id) as count
        FROM ticket_priorities p
        LEFT JOIN tickets t ON p.id = t.priority_id AND t.company_id = ?
        GROUP BY p.id, p.name
        ORDER BY p.level ASC
    ");
    $stmt->execute([$currentUser['company_id']]);
    $stats['by_priority'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Tickets by queue
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(q.name, 'Unassigned') as queue_name,
            COUNT(t.id) as count
        FROM tickets t
        LEFT JOIN ticket_queues q ON t.queue_id = q.id
        WHERE t.company_id = ?
        GROUP BY q.id, q.name
    ");
    $stmt->execute([$currentUser['company_id']]);
    $stats['by_queue'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Average first response time (in hours)
    $stmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, first_response_at)) as avg_hours
        FROM tickets
        WHERE company_id = ? AND first_response_at IS NOT NULL
    ");
    $stmt->execute([$currentUser['company_id']]);
    $stats['avg_first_response_hours'] = round($stmt->fetchColumn(), 2);

    // Average resolution time (in hours)
    $stmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours
        FROM tickets
        WHERE company_id = ? AND resolved_at IS NOT NULL
    ");
    $stmt->execute([$currentUser['company_id']]);
    $stats['avg_resolution_hours'] = round($stmt->fetchColumn(), 2);

    // SLA breaches
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tickets
        WHERE company_id = ? AND (first_response_sla_breached = 1 OR resolution_sla_breached = 1)
    ");
    $stmt->execute([$currentUser['company_id']]);
    $stats['sla_breaches'] = $stmt->fetchColumn();

    // New tickets today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tickets
        WHERE company_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$currentUser['company_id']]);
    $stats['new_today'] = $stmt->fetchColumn();

    // Resolved today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tickets
        WHERE company_id = ? AND DATE(resolved_at) = CURDATE()
    ");
    $stmt->execute([$currentUser['company_id']]);
    $stats['resolved_today'] = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}

function handleStaffList() {
    global $pdo;
    $currentUser = currentUser();

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            u.email,
            u.avatar,
            (SELECT COUNT(*) FROM tickets WHERE assigned_to = u.id AND status NOT IN ('resolved', 'closed', 'cancelled')) as active_tickets
        FROM users u
        WHERE u.company_id = ? AND u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$currentUser['company_id']]);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $staff
    ]);
}
