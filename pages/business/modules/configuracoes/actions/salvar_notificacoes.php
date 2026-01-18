<?php
/**
 * SALVAR PREFERÊNCIAS DE NOTIFICAÇÕES
 * Atualiza configurações de notificações do gestor
 */

session_start();
header('Content-Type: application/json');

require_once '../../../../../registration/includes/db.php';

// Verificar autenticação do gestor
if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$emailNotif = isset($input['email']) ? (int)$input['email'] : 0;
$vendasNotif = isset($input['vendas']) ? (int)$input['vendas'] : 0;
$funcionariosNotif = isset($input['funcionarios']) ? (int)$input['funcionarios'] : 0;
$relatoriosNotif = isset($input['relatorios']) ? (int)$input['relatorios'] : 0;

// Verificar se já existe configuração
$stmt = $mysqli->prepare("
    SELECT id FROM user_notification_settings WHERE user_id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$exists = $result->num_rows > 0;
$stmt->close();

try {
    if ($exists) {
        // Atualizar configuração existente
        $stmt = $mysqli->prepare("
            UPDATE user_notification_settings 
            SET 
                email_notifications = ?,
                vendas_notifications = ?,
                funcionarios_notifications = ?,
                relatorios_notifications = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->bind_param('iiiii', 
            $emailNotif, 
            $vendasNotif, 
            $funcionariosNotif, 
            $relatoriosNotif,
            $userId
        );
    } else {
        // Criar nova configuração
        $stmt = $mysqli->prepare("
            INSERT INTO user_notification_settings 
            (user_id, email_notifications, vendas_notifications, funcionarios_notifications, relatorios_notifications)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iiiii', 
            $userId,
            $emailNotif, 
            $vendasNotif, 
            $funcionariosNotif, 
            $relatoriosNotif
        );
    }

    $stmt->execute();
    $stmt->close();

    // Registrar log de auditoria
    $stmt = $mysqli->prepare("
        INSERT INTO audit_logs 
        (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (?, 'update_notifications', 'settings', ?, ?, ?, ?)
    ");
    
    $action = 'update_notifications';
    $entityType = 'settings';
    $details = json_encode([
        'email' => $emailNotif,
        'vendas' => $vendasNotif,
        'funcionarios' => $funcionariosNotif,
        'relatorios' => $relatoriosNotif
    ]);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt->bind_param('iisss', 
        $userId,
        $userId,
        $details,
        $ipAddress,
        $userAgent
    );
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => '✅ Preferências salvas com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar preferências: ' . $e->getMessage()
    ]);
}