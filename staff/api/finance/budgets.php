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
        case 'create':
            handleCreate();
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

    $where = ['b.company_id = :company_id'];
    $params = [':company_id' => $companyId];

    if (!empty($_GET['fiscal_year'])) {
        $where[] = 'bp.fiscal_year = :fiscal_year';
        $params[':fiscal_year'] = $_GET['fiscal_year'];
    }

    if (!empty($_GET['period'])) {
        $where[] = 'bp.period_name = :period';
        $params[':period'] = $_GET['period'];
    }

    if (!empty($_GET['search'])) {
        $where[] = 'cc.name LIKE :search';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            b.*,
            bp.fiscal_year,
            bp.period_name,
            cc.name as cost_center_name
        FROM budgets b
        LEFT JOIN budget_periods bp ON b.budget_period_id = bp.id
        LEFT JOIN cost_centers cc ON b.cost_center_id = cc.id
        WHERE $whereClause
        ORDER BY bp.fiscal_year DESC, bp.period_name ASC
    ");

    $stmt->execute($params);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $budgets
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['cost_center_id']) || empty($input['budget_period_id'])) {
        throw new Exception('Cost center and budget period are required');
    }

    $stmt = $pdo->prepare("
        SELECT id FROM budgets
        WHERE company_id = ? AND cost_center_id = ? AND budget_period_id = ?
    ");
    $stmt->execute([$companyId, $input['cost_center_id'], $input['budget_period_id']]);

    if ($stmt->fetch()) {
        throw new Exception('Budget already exists for this cost center and period');
    }

    $stmt = $pdo->prepare("
        INSERT INTO budgets (
            company_id, cost_center_id, budget_period_id,
            budget_amount, notes
        ) VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $companyId,
        $input['cost_center_id'],
        $input['budget_period_id'],
        $input['budget_amount'],
        $input['notes'] ?? null
    ]);

    $budgetId = $pdo->lastInsertId();

    logFinanceAudit(
        'budget',
        $budgetId,
        'created',
        null,
        "Budget created - Amount: \${$input['budget_amount']}",
        null, null, null, null,
        ['cost_center_id' => $input['cost_center_id'], 'budget_period_id' => $input['budget_period_id'], 'amount' => $input['budget_amount']]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Budget created successfully',
        'id' => $budgetId
    ]);
}
