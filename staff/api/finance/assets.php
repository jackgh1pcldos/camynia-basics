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

    $where = ['fa.company_id = :company_id'];
    $params = [':company_id' => $companyId];

    if (!empty($_GET['status'])) {
        $where[] = 'fa.status = :status';
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['category_id'])) {
        $where[] = 'fa.category_id = :category_id';
        $params[':category_id'] = $_GET['category_id'];
    }

    if (!empty($_GET['search'])) {
        $where[] = '(fa.asset_tag LIKE :search OR fa.description LIKE :search)';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            fa.*,
            ac.category_name
        FROM fixed_assets fa
        LEFT JOIN asset_categories ac ON fa.category_id = ac.id
        WHERE $whereClause
        ORDER BY fa.asset_tag ASC
    ");

    $stmt->execute($params);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $assets
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['asset_tag']) || empty($input['description'])) {
        throw new Exception('Asset tag and description are required');
    }

    $stmt = $pdo->prepare("
        INSERT INTO fixed_assets (
            company_id, asset_tag, description, category_id,
            acquisition_date, acquisition_cost, depreciation_method,
            useful_life_years, salvage_value, book_value,
            status, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
    ");

    $stmt->execute([
        $companyId,
        $input['asset_tag'],
        $input['description'],
        $input['category_id'] ?? null,
        $input['acquisition_date'] ?? date('Y-m-d'),
        $input['acquisition_cost'],
        $input['depreciation_method'] ?? 'straight_line',
        $input['useful_life_years'] ?? 5,
        $input['salvage_value'] ?? 0,
        $input['acquisition_cost'], // Initial book value = acquisition cost
        $currentUser['id']
    ]);

    $assetId = $pdo->lastInsertId();

    logFinanceAudit(
        'fixed_asset',
        $assetId,
        'created',
        $input['asset_tag'],
        "Fixed asset '{$input['description']}' ({$input['asset_tag']}) created",
        null, null, null, null,
        ['acquisition_cost' => $input['acquisition_cost'], 'status' => 'active']
    );

    echo json_encode([
        'success' => true,
        'message' => 'Fixed asset created successfully',
        'id' => $assetId
    ]);
}
