<?php
/**
 * BUSCAR VENDAS
 * Retorna vendas de produtos da empresa
 * ATUALIZADO: Suporta empresa e funcionário
 */

header('Content-Type: application/json');

// Sistema de Debug
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

// Verificar autenticação (empresa OU funcionário)
$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company';

if (!$isEmployee && !$isCompany) {
    logDebug('ERRO: Não autenticado');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Determinar userId (ID da empresa)
if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id'];
    $userType = 'funcionario';
    logDebug('Funcionário acessando', ['empresa_id' => $userId]);
} else {
    $userId = (int)$_SESSION['auth']['user_id'];
    $userType = 'gestor';
    logDebug('Gestor acessando', ['user_id' => $userId]);
}

// Se vier user_id por GET, validar que é o mesmo
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
            pp.id,
            pp.quantity,
            pp.unit_price,
            pp.total_amount,
            pp.status,
            pp.purchase_date,
            p.name as produto_nome,
            t.invoice_number,
            u.nome as cliente_nome,
            u.email as cliente_email
        FROM product_purchases pp
        INNER JOIN products p ON pp.product_id = p.id
        LEFT JOIN transactions t ON pp.transaction_id = t.id
        LEFT JOIN users u ON pp.user_id = u.id
        WHERE p.user_id = ?
        AND pp.purchase_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ";
    
    $params = [$userId, $periodo];
    $types = 'ii';
    
    if ($status) {
        $sql .= " AND pp.status = ?";
        $params[] = $status;
        $types .= 's';
        logDebug('Filtro status', ['status' => $status]);
    }
    
    if ($produtoId) {
        $sql .= " AND pp.product_id = ?";
        $params[] = (int)$produtoId;
        $types .= 'i';
        logDebug('Filtro produto', ['produto_id' => $produtoId]);
    }
    
    if ($search) {
        $sql .= " AND (t.invoice_number LIKE ? OR u.nome LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
        logDebug('Filtro busca', ['search' => $search]);
    }
    
    $sql .= " ORDER BY pp.purchase_date DESC LIMIT 100";
    
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