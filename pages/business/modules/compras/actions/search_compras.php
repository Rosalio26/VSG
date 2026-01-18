<?php
/**
 * Buscar compras/pedidos da empresa
 * Empresa VÊ quem comprou, não edita clientes
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../../../../../registration/includes/db.php';

// Autenticação
$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']);

if (!$isEmployee && !$isCompany) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Obter company_id
if ($isEmployee) {
    $companyId = (int)$_SESSION['employee_auth']['empresa_id'];
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    
    // Verificar permissão
    $stmt = $mysqli->prepare("
        SELECT can_view FROM employee_permissions 
        WHERE employee_id = ? AND module IN ('vendas', 'compras')
    ");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $perm = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$perm || !$perm['can_view']) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        exit;
    }
} else {
    $companyId = (int)$_SESSION['auth']['user_id'];
}

// Filtros
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$payment_status = trim($_GET['payment_status'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

try {
    // Query
    $sql = "
        SELECT 
            o.*,
            u.nome as customer_name,
            u.apelido as customer_apelido,
            u.email as customer_email,
            u.telefone as customer_phone,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count,
            (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as total_items
        FROM orders o
        INNER JOIN users u ON o.customer_id = u.id
        WHERE o.company_id = ? AND o.deleted_at IS NULL
    ";
    
    $params = [$companyId];
    $types = "i";
    
    // Filtro de busca
    if (!empty($search)) {
        $sql .= " AND (o.order_number LIKE ? OR u.nome LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
        $types .= "sss";
    }
    
    // Filtro de status
    if (!empty($status)) {
        $sql .= " AND o.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Filtro de pagamento
    if (!empty($payment_status)) {
        $sql .= " AND o.payment_status = ?";
        $params[] = $payment_status;
        $types .= "s";
    }
    
    // Filtro de data
    if (!empty($date_from)) {
        $sql .= " AND o.order_date >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if (!empty($date_to)) {
        $sql .= " AND o.order_date <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    $sql .= " ORDER BY o.order_date DESC, o.id DESC";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $compras = [];
    while ($row = $result->fetch_assoc()) {
        $compras[] = $row;
    }
    $stmt->close();
    
    // Estatísticas
    $statsStmt = $mysqli->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(total) as faturamento_total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as entregues,
            SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as valor_pago,
            SUM(CASE WHEN payment_status = 'pending' THEN total ELSE 0 END) as valor_pendente
        FROM orders
        WHERE company_id = ? AND deleted_at IS NULL
    ");
    
    $statsStmt->bind_param('i', $companyId);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    $statsStmt->close();
    
    echo json_encode([
        'success' => true,
        'compras' => $compras,
        'stats' => [
            'total' => (int)$stats['total'],
            'faturamento_total' => (float)$stats['faturamento_total'],
            'pendentes' => (int)$stats['pendentes'],
            'entregues' => (int)$stats['entregues'],
            'valor_pago' => (float)$stats['valor_pago'],
            'valor_pendente' => (float)$stats['valor_pendente']
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro ao buscar compras: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar compras']);
}