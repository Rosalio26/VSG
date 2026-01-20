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
        SELECT 
            n.id,
            n.sender_id,
            n.subject,
            n.message,
            n.category,
            n.priority,
            n.status,
            n.related_order_id,
            n.attachment_url,
            n.read_at,
            n.created_at,
            COALESCE(u.nome, 'VisionGreen') as sender_name,
            o.order_number as related_order_number
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.id
        LEFT JOIN orders o ON n.related_order_id = o.id
        WHERE n.receiver_id = ? AND (n.deleted_at IS NULL OR n.deleted_at > NOW())
        ORDER BY n.created_at DESC
        LIMIT 100
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar notificações: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar notificações'
    ]);
}