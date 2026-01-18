<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../../../../registration/includes/db.php';

$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']);

if (!$isEmployee && !$isCompany) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$companyId = $isEmployee 
    ? (int)$_SESSION['employee_auth']['empresa_id'] 
    : (int)$_SESSION['auth']['user_id'];

$search = trim($_GET['search'] ?? '');
$order_status = trim($_GET['order_status'] ?? '');
$payment_status = trim($_GET['payment_status'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

try {
    $sql = "
        SELECT DISTINCT
            u.id,
            u.nome,
            u.apelido,
            u.email,
            u.telefone,
            u.created_at,
            COUNT(DISTINCT o.id) as total_pedidos,
            COALESCE(SUM(o.total), 0) as total_gasto,
            MAX(o.order_date) as ultimo_pedido,
            GROUP_CONCAT(DISTINCT oi.product_name SEPARATOR ', ') as produtos_comprados
        FROM users u
        INNER JOIN orders o ON u.id = o.customer_id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.company_id = ? AND u.type = 'person' AND o.deleted_at IS NULL
    ";
    
    $params = [$companyId];
    $types = "i";
    
    // Busca por cliente OU produto
    if (!empty($search)) {
        $sql .= " AND (
            u.nome LIKE ? OR 
            u.apelido LIKE ? OR 
            u.email LIKE ? OR 
            u.telefone LIKE ? OR
            oi.product_name LIKE ?
        )";
        $searchParam = "%$search%";
        $params = array_merge($params, array_fill(0, 5, $searchParam));
        $types .= str_repeat('s', 5);
    }
    
    // Filtro status do pedido
    if (!empty($order_status)) {
        $sql .= " AND o.status = ?";
        $params[] = $order_status;
        $types .= "s";
    }
    
    // Filtro status pagamento
    if (!empty($payment_status)) {
        $sql .= " AND o.payment_status = ?";
        $params[] = $payment_status;
        $types .= "s";
    }
    
    // Filtro data início
    if (!empty($date_from)) {
        $sql .= " AND o.order_date >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    // Filtro data fim
    if (!empty($date_to)) {
        $sql .= " AND o.order_date <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    $sql .= " GROUP BY u.id ORDER BY ultimo_pedido DESC";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clientes = [];
    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }
    $stmt->close();
    
    // Stats
    $statsStmt = $mysqli->prepare("
        SELECT 
            COUNT(DISTINCT o.customer_id) as total,
            COUNT(o.id) as total_pedidos,
            SUM(o.total) as faturamento_total,
            SUM(CASE WHEN o.payment_status = 'paid' THEN o.total ELSE 0 END) as valor_pago,
            SUM(CASE WHEN o.payment_status = 'pending' THEN o.total ELSE 0 END) as valor_pendente
        FROM orders o
        INNER JOIN users u ON o.customer_id = u.id
        WHERE o.company_id = ? AND u.type = 'person' AND o.deleted_at IS NULL
    ");
    
    $statsStmt->bind_param('i', $companyId);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    $statsStmt->close();
    
    echo json_encode([
        'success' => true,
        'clientes' => $clientes,
        'stats' => [
            'total' => (int)$stats['total'],
            'total_pedidos' => (int)$stats['total_pedidos'],
            'faturamento_total' => (float)$stats['faturamento_total'],
            'valor_pago' => (float)$stats['valor_pago'],
            'valor_pendente' => (float)$stats['valor_pendente']
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar clientes']);
}