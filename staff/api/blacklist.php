<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

// Ensure user is logged in and is staff/admin
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
        case 'add':
            handleAdd();
            break;
        case 'update':
            handleUpdate();
            break;
        case 'remove':
            handleRemove();
            break;
        case 'search':
            handleSearch();
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

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 50;
    $offset = ($page - 1) * $perPage;

    $isActive = isset($_GET['active']) ? (int)$_GET['active'] : 1;
    $category = $_GET['category'] ?? null;
    $severity = $_GET['severity'] ?? null;

    // Build query
    $where = ['is_active = :is_active'];
    $params = [':is_active' => $isActive];

    if ($category) {
        $where[] = 'category = :category';
        $params[':category'] = $category;
    }

    if ($severity) {
        $where[] = 'severity = :severity';
        $params[':severity'] = $severity;
    }

    $whereClause = implode(' AND ', $where);

    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM blacklisted_words WHERE $whereClause");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    // Get words
    $stmt = $pdo->prepare("
        SELECT
            bw.*,
            CONCAT(u.first_name, ' ', u.last_name) as added_by_name,
            CONCAT(u2.first_name, ' ', u2.last_name) as removed_by_name
        FROM blacklisted_words bw
        LEFT JOIN users u ON bw.added_by = u.id
        LEFT JOIN users u2 ON bw.removed_by = u2.id
        WHERE $whereClause
        ORDER BY bw.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $words,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => ceil($total / $perPage)
        ]
    ]);
}

function handleAdd() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);

    $word = trim($input['word'] ?? '');
    $severity = $input['severity'] ?? 'medium';
    $category = trim($input['category'] ?? '');
    $notes = trim($input['notes'] ?? '');

    if (empty($word)) {
        throw new Exception('Word is required');
    }

    $currentUser = currentUser();

    // Check if word already exists as active
    $stmt = $pdo->prepare("SELECT id FROM blacklisted_words WHERE word = ? AND is_active = 1");
    $stmt->execute([$word]);
    if ($stmt->fetch()) {
        throw new Exception('This word is already blacklisted');
    }

    // Insert word
    $stmt = $pdo->prepare("
        INSERT INTO blacklisted_words (word, severity, category, notes, added_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        strtolower($word),
        $severity,
        $category ?: null,
        $notes ?: null,
        $currentUser['id']
    ]);

    $wordId = $pdo->lastInsertId();

    // Log action
    logBlacklistAction($wordId, $word, 'added', $currentUser['id'], [
        'severity' => $severity,
        'category' => $category
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Word added successfully',
        'id' => $wordId
    ]);
}

function handleUpdate() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);

    $id = (int)($input['id'] ?? 0);
    $severity = $input['severity'] ?? null;
    $category = trim($input['category'] ?? '');
    $notes = trim($input['notes'] ?? '');

    if (!$id) {
        throw new Exception('ID is required');
    }

    // Get current word data
    $stmt = $pdo->prepare("SELECT * FROM blacklisted_words WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        throw new Exception('Word not found');
    }

    $currentUser = currentUser();
    $changes = [];

    // Build update query
    $updates = [];
    $params = [];

    if ($severity && $severity !== $current['severity']) {
        $updates[] = 'severity = ?';
        $params[] = $severity;
        $changes['severity'] = ['from' => $current['severity'], 'to' => $severity];
    }

    if ($category !== $current['category']) {
        $updates[] = 'category = ?';
        $params[] = $category ?: null;
        $changes['category'] = ['from' => $current['category'], 'to' => $category];
    }

    if ($notes !== $current['notes']) {
        $updates[] = 'notes = ?';
        $params[] = $notes ?: null;
        $changes['notes'] = ['from' => $current['notes'], 'to' => $notes];
    }

    if (empty($updates)) {
        throw new Exception('No changes to update');
    }

    $params[] = $id;

    $stmt = $pdo->prepare("
        UPDATE blacklisted_words
        SET " . implode(', ', $updates) . "
        WHERE id = ?
    ");
    $stmt->execute($params);

    // Log action
    logBlacklistAction($id, $current['word'], 'updated', $currentUser['id'], $changes);

    echo json_encode([
        'success' => true,
        'message' => 'Word updated successfully'
    ]);
}

function handleRemove() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);

    $id = (int)($input['id'] ?? 0);

    if (!$id) {
        throw new Exception('ID is required');
    }

    // Get current word data
    $stmt = $pdo->prepare("SELECT * FROM blacklisted_words WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        throw new Exception('Word not found');
    }

    $currentUser = currentUser();

    // Soft delete - mark as inactive
    $stmt = $pdo->prepare("
        UPDATE blacklisted_words
        SET is_active = 0, removed_by = ?, removed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$currentUser['id'], $id]);

    // Log action
    logBlacklistAction($id, $current['word'], 'removed', $currentUser['id'], [
        'is_active' => ['from' => true, 'to' => false]
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Word removed successfully'
    ]);
}

function handleSearch() {
    global $pdo;

    $query = trim($_GET['q'] ?? '');

    if (empty($query)) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            bw.*,
            CONCAT(u.first_name, ' ', u.last_name) as added_by_name
        FROM blacklisted_words bw
        LEFT JOIN users u ON bw.added_by = u.id
        WHERE bw.word LIKE ? AND bw.is_active = 1
        ORDER BY bw.word ASC
        LIMIT 50
    ");
    $stmt->execute(['%' . $query . '%']);

    $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $words
    ]);
}

function logBlacklistAction($blacklistId, $word, $action, $userId, $changes) {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO blacklist_audit_log (blacklist_id, word, action, user_id, changes, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $blacklistId,
        $word,
        $action,
        $userId,
        json_encode($changes),
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
