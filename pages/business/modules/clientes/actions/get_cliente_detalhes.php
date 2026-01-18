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

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Dados do cliente
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.nome,
            u.apelido,
            u.email,
            u.telefone,
            u.created_at
        FROM users u
        WHERE u.id = ? AND u.type = 'person'
    ");
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
        exit;
    }
    
    $cliente = $result->fetch_assoc();
    $stmt->close();
    
    // Pedidos do cliente
    $stmtPedidos = $mysqli->prepare("
        SELECT 
            id,
            order_number,
            order_date,
            total,
            currency,
            status,
            payment_status
        FROM orders
        WHERE customer_id = ? AND company_id = ? AND deleted_at IS NULL
        ORDER BY order_date DESC
        LIMIT 10
    ");
    
    $stmtPedidos->bind_param('ii', $id, $companyId);
    $stmtPedidos->execute();
    $resultPedidos = $stmtPedidos->get_result();
    
    $pedidos = [];
    while ($row = $resultPedidos->fetch_assoc()) {
        $pedidos[] = $row;
    }
    $stmtPedidos->close();
    
    // Estatísticas
    $stmtStats = $mysqli->prepare("
        SELECT 
            COUNT(*) as total_pedidos,
            SUM(total) as total_gasto,
            AVG(total) as ticket_medio
        FROM orders
        WHERE customer_id = ? AND company_id = ? AND deleted_at IS NULL
    ");
    
    $stmtStats->bind_param('ii', $id, $companyId);
    $stmtStats->execute();
    $stats = $stmtStats->get_result()->fetch_assoc();
    $stmtStats->close();
    
    echo json_encode([
        'success' => true,
        'cliente' => $cliente,
        'pedidos' => $pedidos,
        'stats' => [
            'total_pedidos' => (int)$stats['total_pedidos'],
            'total_gasto' => (float)$stats['total_gasto'],
            'ticket_medio' => (float)$stats['ticket_medio']
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar detalhes']);
}