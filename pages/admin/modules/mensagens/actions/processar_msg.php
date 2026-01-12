<?php
/**
 * PROCESSAR MENSAGENS - API ENDPOINT
 * Caminho: modules/mensagens/actions/processar_msg.php
 */

// Habilitar log de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("=== PROCESSAR_MSG.PHP INICIADO ===");

require_once '../../../../../registration/includes/db.php';
session_start();

// Configurar headers para JSON
header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['auth']['user_id'])) {
    error_log("Erro: Usuário não autenticado");
    echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
    exit;
}

$adminId = $_SESSION['auth']['user_id'];
error_log("Admin ID: $adminId");

// Pegar ação
$action = $_GET['action'] ?? $_POST['action'] ?? '';
error_log("Ação recebida: $action");
error_log("POST: " . print_r($_POST, true));
error_log("GET: " . print_r($_GET, true));

/* ================= ENVIAR MENSAGEM ================= */
if ($action === 'send_message' || isset($_POST['send_msg'])) {
    error_log("=== PROCESSANDO ENVIO DE MENSAGEM ===");
    
    try {
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $subject = $_POST['subject'] ?? 'Mensagem';
        
        error_log("Receiver: $receiverId, Message: $message");
        
        if ($receiverId <= 0) {
            throw new Exception('ID do destinatário inválido');
        }
        
        if (empty($message)) {
            throw new Exception('Mensagem vazia');
        }
        
        // Escapar dados
        $messageEscaped = $mysqli->real_escape_string($message);
        $subjectEscaped = $mysqli->real_escape_string($subject);
        
        // Inserir mensagem
        $sql = "INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) 
                VALUES ($adminId, $receiverId, 'chat', 'low', '$subjectEscaped', '$messageEscaped', 'unread', NOW())";
        
        error_log("SQL: $sql");
        
        if ($mysqli->query($sql)) {
            $messageId = $mysqli->insert_id;
            error_log("Mensagem inserida com ID: $messageId");
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Mensagem enviada',
                'message_id' => $messageId
            ]);
        } else {
            throw new Exception('Erro SQL: ' . $mysqli->error);
        }
        
    } catch (Exception $e) {
        error_log("Erro no envio: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

/* ================= BUSCAR MENSAGENS ================= */
if ($action === 'fetch_messages') {
    error_log("=== BUSCANDO MENSAGENS ===");
    
    try {
        $userId = (int)($_GET['id'] ?? $_GET['user_id'] ?? 0);
        error_log("User ID: $userId");
        
        if ($userId <= 0) {
            throw new Exception('ID inválido');
        }
        
        // Marcar como lidas
        $mysqli->query("UPDATE notifications 
                       SET status = 'read' 
                       WHERE sender_id = $userId 
                       AND receiver_id = $adminId 
                       AND category = 'chat'
                       AND status = 'unread'");
        
        // Buscar mensagens
        $sql = "SELECT id, message, sender_id, receiver_id, status, created_at
                FROM notifications 
                WHERE category = 'chat'
                AND ((sender_id = $adminId AND receiver_id = $userId) 
                     OR (sender_id = $userId AND receiver_id = $adminId))
                ORDER BY created_at ASC";
        
        $result = $mysqli->query($sql);
        $messages = [];
        
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        error_log("Mensagens encontradas: " . count($messages));
        
        echo json_encode([
            'status' => 'success',
            'messages' => $messages
        ]);
        
    } catch (Exception $e) {
        error_log("Erro: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

/* ================= DELETAR MENSAGEM ================= */
if ($action === 'delete_message') {
    try {
        $msgId = (int)($_GET['msg_id'] ?? 0);
        
        if ($msgId <= 0) {
            throw new Exception('ID inválido');
        }
        
        $result = $mysqli->query("DELETE FROM notifications WHERE id = $msgId AND sender_id = $adminId");
        
        if ($result) {
            echo json_encode(['status' => 'success', 'message' => 'Mensagem excluída']);
        } else {
            throw new Exception('Erro ao deletar');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

/* ================= MARCAR COMO NÃO LIDA ================= */
if ($action === 'mark_unread') {
    try {
        $chatId = (int)($_GET['chat_id'] ?? 0);
        
        $mysqli->query("UPDATE notifications 
                       SET status = 'unread' 
                       WHERE sender_id = $chatId 
                       AND receiver_id = $adminId 
                       AND category = 'chat'
                       LIMIT 1");
        
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

/* ================= LIMPAR CONVERSA ================= */
if ($action === 'clear_chat') {
    try {
        $userId = (int)($_POST['user_id'] ?? 0);
        
        if ($userId <= 0) {
            throw new Exception('ID inválido');
        }
        
        $result = $mysqli->query("DELETE FROM notifications 
                                 WHERE category = 'chat'
                                 AND ((sender_id = $adminId AND receiver_id = $userId) 
                                      OR (sender_id = $userId AND receiver_id = $adminId))");
        
        if ($result) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Conversa limpa',
                'deleted' => $mysqli->affected_rows
            ]);
        } else {
            throw new Exception('Erro ao limpar');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

/* ================= EXPORTAR CONVERSA ================= */
if ($action === 'export_chat') {
    try {
        $userId = (int)($_GET['user_id'] ?? 0);
        
        if ($userId <= 0) {
            throw new Exception('ID inválido');
        }
        
        // Buscar nome do usuário
        $userInfo = $mysqli->query("SELECT nome FROM users WHERE id = $userId")->fetch_assoc();
        $userName = $userInfo['nome'] ?? 'Usuário';
        
        // Buscar mensagens
        $sql = "SELECT 
                    n.message,
                    n.sender_id,
                    n.created_at,
                    COALESCE(u.nome, 'Sistema') as sender_name
                FROM notifications n
                LEFT JOIN users u ON n.sender_id = u.id
                WHERE n.category = 'chat'
                AND ((n.sender_id = $adminId AND n.receiver_id = $userId) 
                     OR (n.sender_id = $userId AND n.receiver_id = $adminId))
                ORDER BY n.created_at ASC";
        
        $result = $mysqli->query($sql);
        $messages = [];
        
        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'sender' => $row['sender_name'],
                'message' => $row['message'],
                'timestamp' => $row['created_at']
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'user_name' => $userName,
            'messages' => $messages,
            'export_date' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

/* ================= AÇÃO NÃO RECONHECIDA ================= */
error_log("Ação não reconhecida: $action");
echo json_encode([
    'status' => 'error',
    'message' => 'Ação não reconhecida: ' . $action
]);
exit;