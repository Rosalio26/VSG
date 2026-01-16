<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../registration/includes/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['auth']['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];
$senderId = isset($_GET['sender_id']) && $_GET['sender_id'] !== '' ? (int) $_GET['sender_id'] : null;

// Buscar mensagens da conversa
if ($senderId === null) {
    // Mensagens do sistema
    $stmt = $mysqli->prepare("
        SELECT * FROM notifications 
        WHERE receiver_id = ? AND sender_id IS NULL 
        ORDER BY created_at ASC
    ");
    $stmt->bind_param('i', $userId);
} else {
    // Mensagens de um usuário específico
    $stmt = $mysqli->prepare("
        SELECT * FROM notifications 
        WHERE receiver_id = ? AND sender_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->bind_param('ii', $userId, $senderId);
}

$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar info do remetente
$senderInfo = null;
if ($senderId !== null) {
    $stmt = $mysqli->prepare("
        SELECT u.nome, b.logo_path, u.type 
        FROM users u
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE u.id = ? 
        LIMIT 1
    ");
    $stmt->bind_param('i', $senderId);
    $stmt->execute();
    $senderInfo = $stmt->get_result()->fetch_assoc();
}

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'sender_info' => $senderInfo
]);
