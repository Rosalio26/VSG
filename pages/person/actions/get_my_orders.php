<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../../registration/includes/db.php';

$customerId = (int)($_SESSION['auth']['user_id'] ?? 0);

if ($customerId === 0) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Buscar pedidos do cliente
    $stmt = $mysqli->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.order_date,
            o.subtotal,
            o.total,
            o.currency,
            o.status,
            o.payment_status,
            o.payment_method,
            o.shipping_address,
            o.shipping_city,
            COUNT(oi.id) as items_count,
            u.nome as company_name
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN users u ON o.company_id = u.id
        WHERE o.customer_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    
    // Calcular estatísticas
    $stmtStats = $mysqli->prepare("
        SELECT 
            COUNT(*) as total,
            COALESCE(SUM(total), 0) as total_gasto,
            SUM(CASE WHEN status IN ('pending', 'confirmed', 'processing') THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as entregues
        FROM orders
        WHERE customer_id = ?
    ");
    
    $stmtStats->bind_param('i', $customerId);
    $stmtStats->execute();
    $stats = $stmtStats->get_result()->fetch_assoc();
    $stmtStats->close();
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('Erro get_my_orders: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao carregar pedidos'
    ], JSON_UNESCAPED_UNICODE);
}