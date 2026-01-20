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

if ($isEmployee) {
    $companyId = (int)$_SESSION['employee_auth']['empresa_id'];
} else {
    $companyId = (int)$_SESSION['auth']['user_id'];
}

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
            CONCAT(u.nome, ' ', COALESCE(u.apelido, '')) AS customer_name,
            u.email AS customer_email,
            u.telefone AS customer_phone
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        WHERE o.id = ? AND o.company_id = ? AND o.deleted_at IS NULL
    ");
    $stmt->bind_param('ii', $id, $companyId);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
        exit;
    }
    
    // Buscar itens do pedido
    $stmt = $mysqli->prepare("
        SELECT 
            oi.*,
            p.nome AS current_product_name,
            p.imagem
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'pedido' => $pedido,
        'items' => $items
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro em get_pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar detalhes']);
}