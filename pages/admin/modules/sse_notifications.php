<?php
/**
 * SSE Notifications Server - VERSÃO OTIMIZADA
 * Processamento leve com cache inteligente
 */

session_start();
require_once '../../../registration/includes/db.php';

// ==================== VALIDAÇÃO DE SEGURANÇA ====================
if (!isset($_SESSION['auth']['user_id'])) {
    http_response_code(403);
    exit('Não autorizado');
}

$adminId = $_SESSION['auth']['user_id'];

// ==================== HEADERS SSE ====================
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
ob_implicit_flush(true);
ob_end_flush();

// ==================== CONFIGURAÇÕES ====================
$checkInterval = 3; // Verificação leve a cada 3 segundos
$heartbeatInterval = 20; // Heartbeat a cada 20 segundos
$detailsInterval = 10; // Detalhes completos a cada 10 segundos

$lastCheck = 0;
$lastHeartbeat = time();
$lastDetailsCheck = 0;
$lastHash = ''; // Cache para detectar mudanças

// ==================== ENVIO SSE ====================
function sendSSE($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

// ==================== VERIFICAÇÃO RÁPIDA (SÓ CONTADORES) ====================
function getQuickCounts($mysqli, $adminId) {
    $sql = "SELECT 
                SUM(CASE WHEN category = 'chat' THEN 1 ELSE 0 END) as chat_count,
                SUM(CASE WHEN category IN ('alert', 'security', 'system_error', 'audit') THEN 1 ELSE 0 END) as alerts_count,
                MAX(created_at) as last_notification
            FROM notifications 
            WHERE receiver_id = ? AND status = 'unread'";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return [
        'chat' => (int)($result['chat_count'] ?? 0),
        'alerts' => (int)($result['alerts_count'] ?? 0),
        'total' => (int)(($result['chat_count'] ?? 0) + ($result['alerts_count'] ?? 0)),
        'last_notification' => $result['last_notification']
    ];
}

// ==================== DETALHES COMPLETOS (SÓ QUANDO NECESSÁRIO) ====================
function getFullDetails($mysqli, $adminId) {
    $details = ['messages' => [], 'alerts' => []];
    
    // Últimas 3 mensagens (chat)
    $sql_msgs = "SELECT n.id, 
                        IFNULL(u.nome, 'Sistema') AS sender_name, 
                        n.subject, 
                        n.priority,
                        DATE_FORMAT(n.created_at, '%d/%m %H:%i') as time
                 FROM notifications n 
                 LEFT JOIN users u ON n.sender_id = u.id 
                 WHERE n.receiver_id = ? 
                   AND n.status = 'unread' 
                   AND n.category = 'chat'
                 ORDER BY n.created_at DESC 
                 LIMIT 3";
    
    $stmt = $mysqli->prepare($sql_msgs);
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $details['messages'][] = $row;
    }
    $stmt->close();
    
    // Últimos 5 alertas
    $sql_alerts = "SELECT id, 
                          category, 
                          subject, 
                          priority,
                          DATE_FORMAT(created_at, '%d/%m %H:%i') as time 
                   FROM notifications 
                   WHERE receiver_id = ? 
                     AND category IN ('alert', 'security', 'system_error', 'audit') 
                     AND status = 'unread'
                   ORDER BY 
                     FIELD(priority, 'critical', 'high', 'medium', 'low'),
                     created_at DESC 
                   LIMIT 5";
    
    $stmt = $mysqli->prepare($sql_alerts);
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $details['alerts'][] = $row;
    }
    $stmt->close();
    
    return $details;
}

// ==================== CONEXÃO ESTABELECIDA ====================
sendSSE('connected', [
    'status' => 'ok',
    'user_id' => $adminId,
    'time' => time()
]);

// ==================== LOOP PRINCIPAL ====================
set_time_limit(0);

while (true) {
    if (connection_aborted()) {
        break;
    }
    
    $now = time();
    
    // ========== HEARTBEAT ==========
    if (($now - $lastHeartbeat) >= $heartbeatInterval) {
        sendSSE('heartbeat', ['time' => $now]);
        $lastHeartbeat = $now;
    }
    
    // ========== VERIFICAÇÃO RÁPIDA ==========
    if (($now - $lastCheck) >= $checkInterval) {
        try {
            $counts = getQuickCounts($mysqli, $adminId);
            $currentHash = md5(json_encode($counts));
            
            // Só envia se houve mudança
            if ($currentHash !== $lastHash) {
                $data = [
                    'unread_chat' => $counts['chat'],
                    'unread_alerts' => $counts['alerts'],
                    'unread_total' => $counts['total'],
                    'timestamp' => $now
                ];
                
                // Adiciona detalhes se for hora OU se mudou
                if (($now - $lastDetailsCheck) >= $detailsInterval || $lastHash === '') {
                    $details = getFullDetails($mysqli, $adminId);
                    $data['messages'] = $details['messages'];
                    $data['alerts'] = $details['alerts'];
                    $lastDetailsCheck = $now;
                }
                
                sendSSE('notification', $data);
                $lastHash = $currentHash;
            }
            
            $lastCheck = $now;
            
        } catch (Exception $e) {
            sendSSE('error', [
                'message' => 'Erro ao verificar notificações',
                'time' => $now
            ]);
        }
    }
    
    sleep(1);
}

// ==================== CLEANUP ====================
$mysqli->close();
exit();
?>