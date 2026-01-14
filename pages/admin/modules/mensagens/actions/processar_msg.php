<?php
/**
 * ================================================================================
 * PROCESSADOR DE AÇÕES - SISTEMA DE MENSAGENS
 * Arquivo: modules/mensagens/actions/processar_msg.php
 * Descrição: Backend para todas as ações do chat com proteção role-based
 * ================================================================================
 */

require_once '../../../../../registration/includes/db.php';
session_start();

header('Content-Type: application/json');

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

if (!$adminId) {
    echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // ========== ENVIAR MENSAGEM ==========
        case 'send_message':
            $receiverId = (int)($_POST['receiver_id'] ?? 0);
            $subject = $_POST['subject'] ?? 'Mensagem';
            $message = trim($_POST['message'] ?? '');
            
            if (!$receiverId || !$message) {
                throw new Exception('Dados inválidos');
            }
            
            // 🔐 PROTEÇÃO: Admin não pode enviar para SuperAdmin
            if (!$isSuperAdmin) {
                $checkUser = $mysqli->query("SELECT role FROM users WHERE id = $receiverId AND deleted_at IS NULL");
                $targetUser = $checkUser ? $checkUser->fetch_assoc() : null;
                
                if (!$targetUser || $targetUser['role'] === 'superadmin') {
                    throw new Exception('Permissão negada');
                }
            }
            
            $stmt = $mysqli->prepare("
                INSERT INTO notifications 
                (sender_id, receiver_id, subject, message, category, status, created_at) 
                VALUES (?, ?, ?, ?, 'chat', 'unread', NOW())
            ");
            
            $stmt->bind_param("iiss", $adminId, $receiverId, $subject, $message);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Mensagem enviada',
                    'msg_id' => $mysqli->insert_id
                ]);
            } else {
                throw new Exception('Erro ao enviar mensagem');
            }
            break;
        
        // ========== BUSCAR MENSAGENS ==========
        case 'fetch_messages':
            $userId = (int)($_GET['id'] ?? 0);
            
            if (!$userId) {
                throw new Exception('ID inválido');
            }
            
            // 🔐 PROTEÇÃO: Admin não pode ver mensagens com SuperAdmin
            if (!$isSuperAdmin) {
                $checkUser = $mysqli->query("SELECT role FROM users WHERE id = $userId AND deleted_at IS NULL");
                $targetUser = $checkUser ? $checkUser->fetch_assoc() : null;
                
                if (!$targetUser || $targetUser['role'] === 'superadmin') {
                    throw new Exception('Permissão negada');
                }
            }
            
            $messages = $mysqli->query("
                SELECT id, message, sender_id, status, created_at 
                FROM notifications 
                WHERE category = 'chat'
                AND ((sender_id = $adminId AND receiver_id = $userId) 
                     OR (sender_id = $userId AND receiver_id = $adminId))
                ORDER BY created_at ASC
            ");
            
            $result = [];
            while ($msg = $messages->fetch_assoc()) {
                $result[] = $msg;
            }
            
            // Marcar como lidas
            $mysqli->query("
                UPDATE notifications 
                SET status = 'read' 
                WHERE sender_id = $userId 
                AND receiver_id = $adminId 
                AND category = 'chat' 
                AND status = 'unread'
            ");
            
            echo json_encode([
                'status' => 'success',
                'messages' => $result
            ]);
            break;
        
        // ========== DELETAR MENSAGEM ==========
        case 'delete_message':
            $msgId = (int)($_GET['msg_id'] ?? 0);
            
            if (!$msgId) {
                throw new Exception('ID inválido');
            }
            
            // Verificar se é o remetente
            $check = $mysqli->query("SELECT sender_id FROM notifications WHERE id = $msgId");
            $msg = $check ? $check->fetch_assoc() : null;
            
            if (!$msg || $msg['sender_id'] != $adminId) {
                throw new Exception('Permissão negada');
            }
            
            if ($mysqli->query("DELETE FROM notifications WHERE id = $msgId")) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Mensagem deletada'
                ]);
            } else {
                throw new Exception('Erro ao deletar');
            }
            break;
        
        // ========== LIMPAR CONVERSA ==========
        case 'clear_chat':
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if (!$userId) {
                throw new Exception('ID inválido');
            }
            
            // 🔐 PROTEÇÃO: Admin não pode limpar conversa com SuperAdmin
            if (!$isSuperAdmin) {
                $checkUser = $mysqli->query("SELECT role FROM users WHERE id = $userId AND deleted_at IS NULL");
                $targetUser = $checkUser ? $checkUser->fetch_assoc() : null;
                
                if (!$targetUser || $targetUser['role'] === 'superadmin') {
                    throw new Exception('Permissão negada');
                }
            }
            
            if ($mysqli->query("
                DELETE FROM notifications 
                WHERE category = 'chat'
                AND ((sender_id = $adminId AND receiver_id = $userId) 
                     OR (sender_id = $userId AND receiver_id = $adminId))
            ")) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Conversa limpa'
                ]);
            } else {
                throw new Exception('Erro ao limpar conversa');
            }
            break;
        
        // ========== EXPORTAR CONVERSA ==========
        case 'export_chat':
            $userId = (int)($_GET['user_id'] ?? 0);
            
            if (!$userId) {
                throw new Exception('ID inválido');
            }
            
            // 🔐 PROTEÇÃO: Admin não pode exportar conversa com SuperAdmin
            if (!$isSuperAdmin) {
                $checkUser = $mysqli->query("SELECT role FROM users WHERE id = $userId AND deleted_at IS NULL");
                $targetUser = $checkUser ? $checkUser->fetch_assoc() : null;
                
                if (!$targetUser || $targetUser['role'] === 'superadmin') {
                    throw new Exception('Permissão negada');
                }
            }
            
            $userInfo = $mysqli->query("SELECT nome FROM users WHERE id = $userId")->fetch_assoc();
            
            $messages = $mysqli->query("
                SELECT 
                    n.message,
                    n.sender_id,
                    n.created_at,
                    CASE 
                        WHEN n.sender_id = $adminId THEN 'Você'
                        ELSE '{$userInfo['nome']}'
                    END as sender_name
                FROM notifications n
                WHERE category = 'chat'
                AND ((sender_id = $adminId AND receiver_id = $userId) 
                     OR (sender_id = $userId AND receiver_id = $adminId))
                ORDER BY created_at ASC
            ");
            
            $result = [];
            while ($msg = $messages->fetch_assoc()) {
                $result[] = [
                    'sender' => $msg['sender_name'],
                    'message' => $msg['message'],
                    'timestamp' => date('d/m/Y H:i:s', strtotime($msg['created_at']))
                ];
            }
            
            echo json_encode([
                'status' => 'success',
                'user_name' => $userInfo['nome'],
                'export_date' => date('d/m/Y H:i:s'),
                'messages' => $result
            ]);
            break;
        
        // ========== MARCAR COMO NÃO LIDA ==========
        case 'mark_unread':
            $chatId = (int)($_GET['chat_id'] ?? 0);
            
            if (!$chatId) {
                throw new Exception('ID inválido');
            }
            
            if ($mysqli->query("
                UPDATE notifications 
                SET status = 'unread' 
                WHERE sender_id = $chatId 
                AND receiver_id = $adminId 
                AND category = 'chat'
            ")) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Marcado como não lida'
                ]);
            } else {
                throw new Exception('Erro ao atualizar');
            }
            break;
        
        // ========== BUSCAR LISTA DE CONVERSAS (PARA SIDEBAR) ==========
        case 'get_conversations':
            // Admin não vê conversas com SuperAdmins
            if ($isSuperAdmin) {
                $queryContatos = "
                    SELECT 
                        u.id as contato_id, 
                        u.nome,
                        u.email,
                        u.role,
                        u.last_activity,
                        conv.ultima_msg,
                        conv.data_msg,
                        conv.nao_lidas,
                        (u.last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 15 MINUTE))) as is_online
                    FROM users u
                    INNER JOIN (
                        SELECT 
                            CASE 
                                WHEN n.sender_id = $adminId THEN n.receiver_id
                                ELSE n.sender_id
                            END as user_id,
                            MAX(n.created_at) as data_msg,
                            SUBSTRING_INDEX(GROUP_CONCAT(n.message ORDER BY n.created_at DESC), ',', 1) as ultima_msg,
                            SUM(CASE WHEN n.receiver_id = $adminId AND n.status = 'unread' THEN 1 ELSE 0 END) as nao_lidas
                        FROM notifications n
                        WHERE n.category = 'chat'
                        AND (n.sender_id = $adminId OR n.receiver_id = $adminId)
                        GROUP BY user_id
                    ) as conv ON conv.user_id = u.id
                    WHERE u.deleted_at IS NULL
                    ORDER BY conv.data_msg DESC
                ";
            } else {
                $queryContatos = "
                    SELECT 
                        u.id as contato_id, 
                        u.nome,
                        u.email,
                        u.role,
                        u.last_activity,
                        conv.ultima_msg,
                        conv.data_msg,
                        conv.nao_lidas,
                        (u.last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 15 MINUTE))) as is_online
                    FROM users u
                    INNER JOIN (
                        SELECT 
                            CASE 
                                WHEN n.sender_id = $adminId THEN n.receiver_id
                                ELSE n.sender_id
                            END as user_id,
                            MAX(n.created_at) as data_msg,
                            SUBSTRING_INDEX(GROUP_CONCAT(n.message ORDER BY n.created_at DESC), ',', 1) as ultima_msg,
                            SUM(CASE WHEN n.receiver_id = $adminId AND n.status = 'unread' THEN 1 ELSE 0 END) as nao_lidas
                        FROM notifications n
                        WHERE n.category = 'chat'
                        AND (n.sender_id = $adminId OR n.receiver_id = $adminId)
                        GROUP BY user_id
                    ) as conv ON conv.user_id = u.id
                    WHERE u.deleted_at IS NULL
                    AND u.role != 'superadmin'
                    ORDER BY conv.data_msg DESC
                ";
            }
            
            $contatos = $mysqli->query($queryContatos);
            $conversations = [];
            
            while ($c = $contatos->fetch_assoc()) {
                $roleBadge = null;
                if ($isSuperAdmin && $c['role']) {
                    $roleBadge = [
                        'text' => strtoupper($c['role']),
                        'class' => $c['role'] === 'superadmin' ? 'error' : ($c['role'] === 'admin' ? 'info' : 'neutral')
                    ];
                }
                
                $conversations[] = [
                    'contato_id' => $c['contato_id'],
                    'nome' => $c['nome'],
                    'email' => $c['email'],
                    'role' => $c['role'],
                    'ultima_msg' => $c['ultima_msg'],
                    'data_msg' => $c['data_msg'],
                    'nao_lidas' => (int)$c['nao_lidas'],
                    'is_online' => (bool)$c['is_online'],
                    'role_badge' => $roleBadge
                ];
            }
            
            echo json_encode([
                'status' => 'success',
                'conversations' => $conversations
            ]);
            break;
        
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
