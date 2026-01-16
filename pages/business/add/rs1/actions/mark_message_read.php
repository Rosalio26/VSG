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
$data = json_decode(file_get_contents('php://input'), true);
$messageId = (int) ($data['id'] ?? 0);

if ($messageId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

// Verificar se a mensagem pertence ao usuário
$stmt = $mysqli->prepare("SELECT id FROM notifications WHERE id = ? AND receiver_id = ?");
$stmt->bind_param('ii', $messageId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Mensagem não encontrada']);
    exit;
}

// Atualizar status
$stmt = $mysqli->prepare("UPDATE notifications SET status = 'read' WHERE id = ? AND receiver_id = ?");
$stmt->bind_param('ii', $messageId, $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar']);
}
