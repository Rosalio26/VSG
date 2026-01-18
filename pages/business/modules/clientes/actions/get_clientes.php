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

try {
    $stmt = $mysqli->prepare("
        SELECT DISTINCT
            u.id,
            u.nome,
            u.apelido,
            u.email,
            u.telefone,
            COUNT(o.id) as total_pedidos,
            COALESCE(SUM(o.total), 0) as total_gasto,
            MAX(o.order_date) as ultimo_pedido
        FROM users u
        INNER JOIN orders o ON u.id = o.customer_id
        WHERE o.company_id = ? AND u.type = 'person' AND o.deleted_at IS NULL
        GROUP BY u.id
        ORDER BY u.nome ASC
    ");
    
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clientes = [];
    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'clientes' => $clientes], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar clientes']);
}