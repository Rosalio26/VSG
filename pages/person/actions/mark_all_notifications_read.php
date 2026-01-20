<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../../registration/includes/db.php';

if (!isset($_SESSION['auth']['user_id']) || $_SESSION['auth']['type'] !== 'person') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

try {
    $stmt = $mysqli->prepare("
        UPDATE notifications 
        SET status = 'lida', read_at = NOW()
        WHERE receiver_id = ? AND status = 'nao_lida'
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => "  $affected notificação(ões) marcada(s) como lida(s)",
        'count' => $affected
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao marcar notificações: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao marcar notificações'
    ]);
}