<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../registration/includes/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['auth']['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];

$stmt = $mysqli->prepare("UPDATE notifications SET status = 'read' WHERE receiver_id = ? AND status = 'unread'");
$stmt->bind_param('i', $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'updated' => $stmt->affected_rows]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar']);
}
