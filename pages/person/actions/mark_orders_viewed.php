<?php
/**
 * ================================================================================
 * VISIONGREEN - MARCAR PEDIDOS COMO VISUALIZADOS
 * Arquivo: pages/person/actions/mark_orders_viewed.php
 * ================================================================================
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['auth']['user_id']) || $_SESSION['auth']['type'] !== 'person') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];
$_SESSION['orders_viewed_at'] = time();

echo json_encode([
    'success' => true,
    'message' => 'Pedidos marcados como visualizados',
    'timestamp' => $_SESSION['orders_viewed_at']
]);