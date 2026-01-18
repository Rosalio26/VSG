<?php
/**
 * Ver detalhes de UM pedido específico
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

$companyId = $isEmployee 
    ? (int)$_SESSION['employee_auth']['empresa_id'] 
    : (int)$_SESSION['auth']['user_id'];

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Buscar pedido
    $stmt = $mysqli->prepare("
        SELECT 
            o.*,
            u.nome as customer_name,
            u.apelido as customer_apelido,
            u.email as customer_email,
            u.telefone as customer_phone
        FROM orders o
        INNER JOIN users u ON o.customer_id = u.id
        WHERE o.id = ? AND o.company_id = ? AND o.deleted_at IS NULL
    ");
    
    $stmt->bind_param('ii', $id, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
        exit;
    }
    
    $pedido = $result->fetch_assoc();
    $stmt->close();
    
    // Buscar itens
    $stmtItems = $mysqli->prepare("
        SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC
    ");
    
    $stmtItems->bind_param('i', $id);
    $stmtItems->execute();
    $resultItems = $stmtItems->get_result();
    
    $items = [];
    while ($row = $resultItems->fetch_assoc()) {
        $items[] = $row;
    }
    $stmtItems->close();
    
    // Buscar histórico
    $stmtHistory = $mysqli->prepare("
        SELECT * FROM order_status_history 
        WHERE order_id = ? 
        ORDER BY changed_at DESC
    ");
    
    $stmtHistory->bind_param('i', $id);
    $stmtHistory->execute();
    $resultHistory = $stmtHistory->get_result();
    
    $historico = [];
    while ($row = $resultHistory->fetch_assoc()) {
        $historico[] = $row;
    }
    $stmtHistory->close();
    
    echo json_encode([
        'success' => true,
        'pedido' => $pedido,
        'items' => $items,
        'historico' => $historico
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro ao buscar pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar pedido']);
}