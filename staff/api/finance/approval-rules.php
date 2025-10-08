<?php
require_once __DIR__ . '/../../../includes/core/bootstrap.php';

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

    $where = ['company_id = :company_id'];
    $params = [':company_id' => $companyId];

    // Filters
    if (!empty($_GET['document_type'])) {
        $where[] = 'document_type = :document_type';
        $params[':document_type'] = $_GET['document_type'];
    }

    if (!empty($_GET['status'])) {
        $isActive = ($_GET['status'] === 'active') ? 1 : 0;
        $where[] = 'is_active = :is_active';
        $params[':is_active'] = $isActive;
    }

    if (!empty($_GET['search'])) {
        $where[] = 'rule_name LIKE :search';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT *
        FROM approval_rules
        WHERE $whereClause
        ORDER BY priority ASC, rule_name ASC
    ");

    $stmt->execute($params);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $rules
    ]);
}

function handleView() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $ruleId = $_GET['id'] ?? null;

    if (!$ruleId) {
        throw new Exception('Rule ID required');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM approval_rules
        WHERE id = ? AND company_id = ?
    ");

    $stmt->execute([$ruleId, $companyId]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rule) {
        throw new Exception('Approval rule not found');
    }

    echo json_encode([
        'success' => true,
        'data' => $rule
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    // Validation
    if (empty($input['rule_name']) || empty($input['document_type'])) {
        throw new Exception('Rule name and document type are required');
    }

    if (empty($input['approval_chain'])) {
        throw new Exception('At least one approval level is required');
    }

    // Insert rule
    $stmt = $pdo->prepare("
        INSERT INTO approval_rules (
            company_id, rule_name, document_type, priority,
            conditions, approval_chain,
            escalation_hours, notify_requester,
            is_active, created_by
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?,
            ?, ?,
            ?, ?
        )
    ");

    $stmt->execute([
        $companyId,
        $input['rule_name'],
        $input['document_type'],
        $input['priority'] ?? 100,

        json_encode($input['conditions'] ?? []),
        json_encode($input['approval_chain']),

        $input['escalation_hours'] ?? 24,
        $input['notify_requester'] ?? 1,

        $input['is_active'] ?? 1,
        $currentUser['id']
    ]);

    $ruleId = $pdo->lastInsertId();

    // **AUDIT LOG** - Rule created
    logFinanceAudit(
        'approval_rule',
        $ruleId,
        'created',
        null,
        "Approval rule '{$input['rule_name']}' created for {$input['document_type']}",
        null,
        null,
        null,
        null,
        [
            'rule_name' => $input['rule_name'],
            'document_type' => $input['document_type'],
            'approval_levels' => count($input['approval_chain']),
            'is_active' => $input['is_active'] ?? 1
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Approval rule created successfully',
        'id' => $ruleId
    ]);
}

function handleUpdate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $ruleId = $input['id'] ?? null;
    if (!$ruleId) {
        throw new Exception('Rule ID required');
    }

    // Get current rule data for audit trail
    $stmt = $pdo->prepare("SELECT * FROM approval_rules WHERE id = ? AND company_id = ?");
    $stmt->execute([$ruleId, $companyId]);
    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldData) {
        throw new Exception('Approval rule not found');
    }

    // Update rule
    $stmt = $pdo->prepare("
        UPDATE approval_rules SET
            rule_name = ?,
            document_type = ?,
            priority = ?,
            conditions = ?,
            approval_chain = ?,
            escalation_hours = ?,
            notify_requester = ?,
            is_active = ?
        WHERE id = ? AND company_id = ?
    ");

    $stmt->execute([
        $input['rule_name'],
        $input['document_type'],
        $input['priority'] ?? 100,
        json_encode($input['conditions'] ?? []),
        json_encode($input['approval_chain']),
        $input['escalation_hours'] ?? 24,
        $input['notify_requester'] ?? 1,
        $input['is_active'] ?? 1,
        $ruleId,
        $companyId
    ]);

    // **AUDIT LOG** - Track all field changes
    $fieldsToTrack = [
        'rule_name', 'document_type', 'priority', 'is_active',
        'escalation_hours', 'notify_requester'
    ];

    // Also track JSON fields
    if (json_encode($input['conditions'] ?? []) !== $oldData['conditions']) {
        logFinanceAudit(
            'approval_rule',
            $ruleId,
            'updated',
            null,
            "Approval conditions updated",
            'conditions',
            $oldData['conditions'],
            json_encode($input['conditions'] ?? [])
        );
    }

    if (json_encode($input['approval_chain']) !== $oldData['approval_chain']) {
        logFinanceAudit(
            'approval_rule',
            $ruleId,
            'updated',
            null,
            "Approval chain updated",
            'approval_chain',
            $oldData['approval_chain'],
            json_encode($input['approval_chain'])
        );
    }

    // Track other field changes
    logFieldChanges(
        'approval_rule',
        $ruleId,
        $input['rule_name'],
        $oldData,
        $input,
        $fieldsToTrack
    );

    echo json_encode([
        'success' => true,
        'message' => 'Approval rule updated successfully'
    ]);
}

function handleDelete() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $ruleId = $input['id'] ?? null;
    if (!$ruleId) {
        throw new Exception('Rule ID required');
    }

    // Get rule for audit
    $stmt = $pdo->prepare("SELECT rule_name, is_active FROM approval_rules WHERE id = ? AND company_id = ?");
    $stmt->execute([$ruleId, $companyId]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rule) {
        throw new Exception('Approval rule not found');
    }

    // Soft delete (set to inactive)
    $stmt = $pdo->prepare("UPDATE approval_rules SET is_active = 0 WHERE id = ? AND company_id = ?");
    $stmt->execute([$ruleId, $companyId]);

    // **AUDIT LOG** - Rule deactivated
    logFinanceAudit(
        'approval_rule',
        $ruleId,
        'deleted',
        null,
        "Approval rule '{$rule['rule_name']}' deactivated",
        'is_active',
        $rule['is_active'],
        0
    );

    echo json_encode([
        'success' => true,
        'message' => 'Approval rule deactivated successfully'
    ]);
}
