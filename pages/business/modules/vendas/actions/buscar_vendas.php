<?php
header('Content-Type: application/json');

function logDebug($message, $data = null) {
    $logDir = __DIR__ . '/../debug/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $log = date('H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $log .= ' - ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $log .= "\n";
    
    file_put_contents($logDir . 'buscar_vendas.log', $log, FILE_APPEND);
}

logDebug('=== INÍCIO ===');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company';

if (!$isEmployee && !$isCompany) {
    logDebug('ERRO: Não autenticado');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id'];
    $userType = 'funcionario';
    logDebug('Funcionário acessando', ['empresa_id' => $userId]);
} else {
    $userId = (int)$_SESSION['auth']['user_id'];
    $userType = 'gestor';
    logDebug('Gestor acessando', ['user_id' => $userId]);
}

if (isset($_GET['user_id'])) {
    $requestUserId = (int)$_GET['user_id'];
    if ($requestUserId !== $userId) {
        logDebug('ERRO: User ID não corresponde');
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        exit;
    }
}

require_once __DIR__ . '/../../../../../registration/includes/db.php';

$periodo = (int)($_GET['periodo'] ?? 30);
$status = $_GET['status'] ?? '';
$produtoId = $_GET['produto_id'] ?? '';
$search = $_GET['search'] ?? '';

logDebug('Parâmetros', [
    'user_id' => $userId,
    'user_type' => $userType,
    'periodo' => $periodo,
    'status' => $status,
    'produto_id' => $produtoId,
    'search' => $search
]);

try {
    $sql = "
        SELECT 
            o.id,
            o.order_number,
            o.order_date,
            o.total,
            o.currency,
            o.status,
            o.payment_status,
            o.payment_method,
            u.nome as cliente_nome,
            u.email as cliente_email,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as total_items
        FROM orders o
        LEFT JOIN users u ON o.customer_id = u.id
        WHERE o.company_id = ?
        AND o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND o.deleted_at IS NULL
    ";
    
    $params = [$userId, $periodo];
    $types = 'ii';
    
    if ($status) {
        $sql .= " AND o.status = ?";
        $params[] = $status;
        $types .= 's';
        logDebug('Filtro status', ['status' => $status]);
    }
    
    if ($produtoId) {
        $sql .= " AND EXISTS (
            SELECT 1 FROM order_items oi 
            WHERE oi.order_id = o.id 
            AND oi.product_id = ?
        )";
        $params[] = (int)$produtoId;
        $types .= 'i';
        logDebug('Filtro produto', ['produto_id' => $produtoId]);
    }
    
    if ($search) {
        $sql .= " AND (o.order_number LIKE ? OR u.nome LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
        logDebug('Filtro busca', ['search' => $search]);
    }
    
    $sql .= " ORDER BY o.order_date DESC LIMIT 100";
    
    logDebug('Preparando query');
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        logDebug('ERRO prepare', ['error' => $mysqli->error]);
        throw new Exception('Erro ao preparar query');
    }
    
    $stmt->bind_param($types, ...$params);
    logDebug('Executando query');
    $stmt->execute();
    $result = $stmt->get_result();
    
    $vendas = [];
    while ($row = $result->fetch_assoc()) {
        $vendas[] = $row;
    }
    
    logDebug('Vendas encontradas', ['count' => count($vendas)]);
    logDebug('=== FIM (SUCESSO) ===');
    
    echo json_encode([
        'success' => true,
        'vendas' => $vendas,
        'user_type' => $userType
    ]);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}