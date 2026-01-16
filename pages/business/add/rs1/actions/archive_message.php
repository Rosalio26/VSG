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
$messageId = (int) ($data['id'] ?? 0);

$stmt = $mysqli->prepare("UPDATE notifications SET status = 'archived' WHERE id = ? AND receiver_id = ?");
$stmt->bind_param('ii', $messageId, $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao arquivar']);
}
