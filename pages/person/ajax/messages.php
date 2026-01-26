<?php
/**
 * AJAX Handler para Sistema de Mensagens
 * Processa envio, recebimento e marcação de mensagens
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['auth']['user_id'] ?? 0;

if ($user_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

switch ($action) {
    
    // ========================================
    // ENVIAR MENSAGEM
    // ========================================
    case 'send_message':
        $receiver_id = intval($_POST['receiver_id']);
        $message = trim($_POST['message']);
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Mensagem vazia']);
            exit;
        }
        
        // Criar notificação como mensagem
        $sql = "INSERT INTO notifications 
                (sender_id, receiver_id, category, priority, subject, message, reply_to)
                VALUES (?, ?, 'sistema', 'baixa', 'Mensagem', ?, 
                        (SELECT id FROM notifications 
                         WHERE (sender_id = ? OR receiver_id = ?) 
                         ORDER BY created_at DESC LIMIT 1))";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("iisii", $user_id, $receiver_id, $message, $receiver_id, $receiver_id);
        
        if ($stmt->execute()) {
            $message_id = $stmt->insert_id;
            
            echo json_encode([
                'success' => true,
                'message_id' => $message_id,
                'time' => date('H:i')
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao enviar']);
        }
        
        $stmt->close();
        break;
    
    // ========================================
    // BUSCAR MENSAGENS
    // ========================================
    case 'get_messages':
        $contact_id = intval($_GET['contact_id']);
        
        $sql = "SELECT 
                n.id,
                n.sender_id,
                n.receiver_id,
                n.message,
                n.created_at,
                DATE_FORMAT(n.created_at, '%H:%i') as time,
                CASE WHEN n.sender_id = ? THEN 1 ELSE 0 END as is_sent
                FROM notifications n
                WHERE ((n.sender_id = ? AND n.receiver_id = ?)
                    OR (n.sender_id = ? AND n.receiver_id = ?))
                AND n.reply_to IS NOT NULL
                AND n.deleted_at IS NULL
                ORDER BY n.created_at ASC
                LIMIT 100";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("iiiii", $user_id, $user_id, $contact_id, $contact_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        // Marcar como lidas
        $sql_update = "UPDATE notifications 
                       SET status = 'lida', read_at = NOW()
                       WHERE receiver_id = ? 
                       AND sender_id = ?
                       AND status = 'nao_lida'";
        
        $stmt_update = $mysqli->prepare($sql_update);
        $stmt_update->bind_param("ii", $user_id, $contact_id);
        $stmt_update->execute();
        $stmt_update->close();
        
        echo json_encode([
            'success' => true,
            'messages' => $messages
        ]);
        
        $stmt->close();
        break;
    
    // ========================================
    // VERIFICAR NOVAS MENSAGENS
    // ========================================
    case 'check_new':
        $contact_id = intval($_GET['contact_id']);
        
        $sql = "SELECT COUNT(*) as total
                FROM notifications
                WHERE sender_id = ?
                AND receiver_id = ?
                AND status = 'nao_lida'
                AND reply_to IS NOT NULL
                AND deleted_at IS NULL";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $contact_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['total'];
        
        echo json_encode([
            'success' => true,
            'has_new' => $count > 0,
            'count' => $count
        ]);
        
        $stmt->close();
        break;
    
    // ========================================
    // CONTAR MENSAGENS NÃO LIDAS TOTAIS
    // ========================================
    case 'get_unread_count':
        $sql = "SELECT COUNT(*) as total
                FROM notifications
                WHERE receiver_id = ?
                AND status = 'nao_lida'
                AND reply_to IS NOT NULL
                AND deleted_at IS NULL";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['total'];
        
        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
        
        $stmt->close();
        break;
    
    // ========================================
    // MARCAR CONVERSA COMO LIDA
    // ========================================
    case 'mark_conversation_read':
        $contact_id = intval($_POST['contact_id']);
        
        $sql = "UPDATE notifications
                SET status = 'lida', read_at = NOW()
                WHERE receiver_id = ?
                AND sender_id = ?
                AND status = 'nao_lida'";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $user_id, $contact_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'updated' => $stmt->affected_rows
            ]);
        } else {
            echo json_encode(['success' => false]);
        }
        
        $stmt->close();
        break;
    
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Ação inválida'
        ]);
}

$mysqli->close();
?>