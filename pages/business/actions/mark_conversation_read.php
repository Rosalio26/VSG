<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../registration/includes/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['auth']['user_id'])) {
    http_response_code(401);
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$senderId = isset($data['sender_id']) ? (int) $data['sender_id'] : null;

if ($senderId === null) {
    // Marcar mensagens do sistema
    $stmt = $mysqli->prepare("
        UPDATE notifications 
        SET status = 'read' 
        WHERE receiver_id = ? AND sender_id IS NULL AND status = 'unread'
    ");
    $stmt->bind_param('i', $userId);
} else {
    // Marcar mensagens de um usuário
    $stmt = $mysqli->prepare("
        UPDATE notifications 
        SET status = 'read' 
        WHERE receiver_id = ? AND sender_id = ? AND status = 'unread'
    ");
    $stmt->bind_param('ii', $userId, $senderId);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'updated' => $stmt->affected_rows]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar']);
}
