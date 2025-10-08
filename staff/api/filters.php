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
        case 'set_default':
            handleSetDefault();
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
            f.*,
            CONCAT(u.first_name, ' ', u.last_name) as owner_name
        FROM ticket_filters f
        LEFT JOIN users u ON f.user_id = u.id
        WHERE f.user_id = ? OR f.is_shared = 1
        ORDER BY f.is_default DESC, f.name ASC
    ");
    $stmt->execute([$currentUser['id']]);
    $filters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse JSON filter_data
    foreach ($filters as &$filter) {
        $filter['filter_data'] = json_decode($filter['filter_data'], true);
    }

    echo json_encode([
        'success' => true,
        'data' => $filters
    ]);
}

function handleView() {
    global $pdo;
    $currentUser = currentUser();
    $filterId = $_GET['id'] ?? null;

    if (!$filterId) {
        throw new Exception('Filter ID required');
    }

    $stmt = $pdo->prepare("
        SELECT * FROM ticket_filters
        WHERE id = ? AND (user_id = ? OR is_shared = 1)
    ");
    $stmt->execute([$filterId, $currentUser['id']]);
    $filter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$filter) {
        throw new Exception('Filter not found');
    }

    $filter['filter_data'] = json_decode($filter['filter_data'], true);

    echo json_encode([
        'success' => true,
        'data' => $filter
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $required = ['name', 'filter_data'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Field $field is required");
        }
    }

    // If setting as default, unset other defaults
    if (!empty($input['is_default'])) {
        $stmt = $pdo->prepare("UPDATE ticket_filters SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$currentUser['id']]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO ticket_filters (
            user_id, name, is_shared, is_default, filter_data, sort_by, sort_direction
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $currentUser['id'],
        $input['name'],
        $input['is_shared'] ?? 0,
        $input['is_default'] ?? 0,
        json_encode($input['filter_data']),
        $input['sort_by'] ?? 'created_at',
        $input['sort_direction'] ?? 'desc'
    ]);

    $filterId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Filter created successfully',
        'id' => $filterId
    ]);
}

function handleUpdate() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $filterId = $input['id'] ?? null;
    if (!$filterId) {
        throw new Exception('Filter ID required');
    }

    // Check ownership
    $stmt = $pdo->prepare("SELECT user_id FROM ticket_filters WHERE id = ?");
    $stmt->execute([$filterId]);
    $filter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$filter || $filter['user_id'] != $currentUser['id']) {
        throw new Exception('Filter not found or unauthorized');
    }

    // If setting as default, unset other defaults
    if (!empty($input['is_default'])) {
        $stmt = $pdo->prepare("UPDATE ticket_filters SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$currentUser['id']]);
    }

    $stmt = $pdo->prepare("
        UPDATE ticket_filters
        SET name = ?,
            is_shared = ?,
            is_default = ?,
            filter_data = ?,
            sort_by = ?,
            sort_direction = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $input['name'],
        $input['is_shared'] ?? 0,
        $input['is_default'] ?? 0,
        json_encode($input['filter_data']),
        $input['sort_by'] ?? 'created_at',
        $input['sort_direction'] ?? 'desc',
        $filterId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Filter updated successfully'
    ]);
}

function handleDelete() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $filterId = $input['id'] ?? null;
    if (!$filterId) {
        throw new Exception('Filter ID required');
    }

    $stmt = $pdo->prepare("DELETE FROM ticket_filters WHERE id = ? AND user_id = ?");
    $stmt->execute([$filterId, $currentUser['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Filter deleted successfully'
    ]);
}

function handleSetDefault() {
    global $pdo;
    $currentUser = currentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    $filterId = $input['id'] ?? null;
    if (!$filterId) {
        throw new Exception('Filter ID required');
    }

    // Unset all defaults
    $stmt = $pdo->prepare("UPDATE ticket_filters SET is_default = 0 WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);

    // Set new default
    $stmt = $pdo->prepare("UPDATE ticket_filters SET is_default = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$filterId, $currentUser['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Default filter set successfully'
    ]);
}
