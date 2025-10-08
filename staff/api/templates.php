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
        case 'search':
            handleSearch();
            break;
        case 'use':
            handleUse();
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

    $queueId = $_GET['queue_id'] ?? null;
    $includeGlobal = $_GET['include_global'] ?? true;

    $where = ['t.company_id = :company_id', 't.is_active = 1'];
    $params = [':company_id' => $currentUser['company_id']];

    if ($queueId) {
        $where[] = '(t.queue_id = :queue_id OR t.queue_id IS NULL)';
        $params[':queue_id'] = $queueId;
    }

    if ($includeGlobal) {
        $where[] = '(t.is_global = 1 OR t.created_by = :user_id)';
        $params[':user_id'] = $currentUser['id'];
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
            q.name as queue_name
        FROM ticket_templates t
        LEFT JOIN users u ON t.created_by = u.id
        LEFT JOIN ticket_queues q ON t.queue_id = q.id
        WHERE $whereClause
        ORDER BY t.use_count DESC, t.name ASC
    ");
    $stmt->execute($params);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $templates
    ]);
}

function handleView() {
    global $pdo;
    $currentUser = currentUser();
    $templateId = $_GET['id'] ?? null;

    if (!$templateId) {
        throw new Exception('Template ID required');
    }

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
            q.name as queue_name
        FROM ticket_templates t
        LEFT JOIN users u ON t.created_by = u.id
        LEFT JOIN ticket_queues q ON t.queue_id = q.id
        WHERE t.id = ? AND t.company_id = ?
    ");
    $stmt->execute([$templateId, $currentUser['company_id']]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        throw new Exception('Template not found');
    }

    echo json_encode([
        'success' => true,
        'data' => $template
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $required = ['name', 'content'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field $field is required");
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO ticket_templates (
            company_id, queue_id, name, subject, content, content_html,
            is_global, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $currentUser['company_id'],
        $input['queue_id'] ?? null,
        $input['name'],
        $input['subject'] ?? null,
        $input['content'],
        $input['content_html'] ?? null,
        $input['is_global'] ?? 0,
        $currentUser['id']
    ]);

    $templateId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Template created successfully',
        'id' => $templateId
    ]);
}

function handleUpdate() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $templateId = $input['id'] ?? null;
    if (!$templateId) {
        throw new Exception('Template ID required');
    }

    $stmt = $pdo->prepare("
        UPDATE ticket_templates
        SET name = ?,
            subject = ?,
            content = ?,
            content_html = ?,
            queue_id = ?,
            is_global = ?
        WHERE id = ? AND company_id = ?
    ");

    $stmt->execute([
        $input['name'],
        $input['subject'] ?? null,
        $input['content'],
        $input['content_html'] ?? null,
        $input['queue_id'] ?? null,
        $input['is_global'] ?? 0,
        $templateId,
        $currentUser['company_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Template updated successfully'
    ]);
}

function handleDelete() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $templateId = $input['id'] ?? null;
    if (!$templateId) {
        throw new Exception('Template ID required');
    }

    // Soft delete
    $stmt = $pdo->prepare("UPDATE ticket_templates SET is_active = 0 WHERE id = ? AND company_id = ?");
    $stmt->execute([$templateId, $currentUser['company_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Template deleted successfully'
    ]);
}

function handleSearch() {
    global $pdo;
    $currentUser = currentUser();
    $query = $_GET['q'] ?? '';

    if (empty($query)) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            q.name as queue_name
        FROM ticket_templates t
        LEFT JOIN ticket_queues q ON t.queue_id = q.id
        WHERE t.company_id = ?
            AND t.is_active = 1
            AND (t.name LIKE ? OR t.content LIKE ?)
        ORDER BY t.use_count DESC
        LIMIT 20
    ");
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$currentUser['company_id'], $searchTerm, $searchTerm]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $templates
    ]);
}

function handleUse() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $templateId = $input['template_id'] ?? null;
    $ticketData = $input['ticket_data'] ?? [];

    if (!$templateId) {
        throw new Exception('Template ID required');
    }

    // Get template
    $stmt = $pdo->prepare("SELECT * FROM ticket_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        throw new Exception('Template not found');
    }

    // Increment use count
    $stmt = $pdo->prepare("UPDATE ticket_templates SET use_count = use_count + 1 WHERE id = ?");
    $stmt->execute([$templateId]);

    // Process template variables
    $content = $template['content'];
    $subject = $template['subject'];

    // Replace placeholders with actual data
    $replacements = [
        '{{ticket.ticket_number}}' => $ticketData['ticket_number'] ?? '',
        '{{ticket.subject}}' => $ticketData['subject'] ?? '',
        '{{ticket.contact_name}}' => $ticketData['contact_name'] ?? '',
        '{{ticket.contact_email}}' => $ticketData['contact_email'] ?? '',
        '{{queue.name}}' => $ticketData['queue_name'] ?? '',
        '{{staff.name}}' => $ticketData['staff_name'] ?? '',
        '{{ticket.resolution_notes}}' => $ticketData['resolution_notes'] ?? ''
    ];

    foreach ($replacements as $placeholder => $value) {
        $content = str_replace($placeholder, $value, $content);
        if ($subject) {
            $subject = str_replace($placeholder, $value, $subject);
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'subject' => $subject,
            'content' => $content,
            'template_name' => $template['name']
        ]
    ]);
}
