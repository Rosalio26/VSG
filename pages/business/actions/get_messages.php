<?php
/**
 * AÇÃO: Buscar mensagens de uma conversa
 * Retorna: JSON válido
 */

// Header JSON obrigatório
header('Content-Type: application/json; charset=utf-8');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação
if (empty($_SESSION['auth']['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autenticado'
    ]);
    exit;
}

require_once __DIR__ . '/../../../registration/includes/db.php';

$userId = (int) $_SESSION['auth']['user_id'];
$senderId = isset($_GET['sender_id']) && $_GET['sender_id'] !== '' ? (int) $_GET['sender_id'] : null;

try {
    // Buscar mensagens
    if ($senderId === null) {
        // Mensagens do sistema
        $stmt = $mysqli->prepare("
            SELECT 
                id, sender_id, receiver_id, subject, message, 
                priority, category, status, created_at
            FROM notifications
            WHERE receiver_id = ? AND sender_id IS NULL
            ORDER BY created_at ASC
        ");
        $stmt->bind_param('i', $userId);
    } else {
        // Mensagens de um usuário específico
        $stmt = $mysqli->prepare("
            SELECT 
                id, sender_id, receiver_id, subject, message, 
                priority, category, status, created_at
            FROM notifications
            WHERE receiver_id = ? AND sender_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->bind_param('ii', $userId, $senderId);
    }

    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar query: ' . $mysqli->error);
    }

    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);

    // Buscar informações do remetente
    $senderInfo = null;
    if ($senderId !== null) {
        $senderStmt = $mysqli->prepare("
            SELECT u.nome, b.logo_path 
            FROM users u
            LEFT JOIN businesses b ON u.id = b.user_id
            WHERE u.id = ? LIMIT 1
        ");
        $senderStmt->bind_param('i', $senderId);
        $senderStmt->execute();
        $senderInfo = $senderStmt->get_result()->fetch_assoc();
        $senderStmt->close();
    }

    // Retornar resposta JSON
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'sender_info' => $senderInfo,
        'message_count' => count($messages)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$mysqli->close();
?>