<?php
header('Content-Type: application/json');
require_once '../../../registration/includes/db.php';
require_once '../../../registration/includes/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Não autenticado'
    ]);
    exit;
}

try {
    $userId = (int)$_SESSION['auth']['user_id'];
    
    $stats = [
        'mensagens_nao_lidas' => 0,
        'pedidos_em_andamento' => 0,
        'total_gasto' => 0
    ];
    
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = ? AND status = 'nao_lida'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $stats['mensagens_nao_lidas'] = (int)$result->fetch_assoc()['total'];
    }
    $stmt->close();
    
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = ? AND status IN ('pendente', 'confirmado', 'processando')");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $stats['pedidos_em_andamento'] = (int)$result->fetch_assoc()['total'];
    }
    $stmt->close();
    
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE customer_id = ? AND payment_status = 'pago'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $stats['total_gasto'] = (float)$result->fetch_assoc()['total'];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'mensagens_nao_lidas' => $stats['mensagens_nao_lidas'],
        'pedidos_em_andamento' => $stats['pedidos_em_andamento'],
        'total_gasto' => $stats['total_gasto']
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro em get_stats.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar estatísticas'
    ]);
}
?>