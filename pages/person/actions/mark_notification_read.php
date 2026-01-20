<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../../registration/includes/db.php';

if (!isset($_SESSION['auth']['user_id']) || $_SESSION['auth']['type'] !== 'person') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$notificationId = (int)($input['notification_id'] ?? 0);

if ($notificationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $stmt = $mysqli->prepare("
        UPDATE notifications 
        SET status = 'lida', read_at = NOW()
        WHERE id = ? AND receiver_id = ?
    ");
    
    $stmt->bind_param('ii', $notificationId, $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'success' => $affected > 0,
        'message' => $affected > 0 ? 'Notificação marcada como lida' : 'Notificação não encontrada'
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao marcar notificação: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao marcar notificação'
    ]);
}