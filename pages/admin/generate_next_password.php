<?php
define('IS_ADMIN_PAGE', true);
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

// Bloqueio de segurança: apenas administradores autenticados podem acionar a rotação
if (!isset($_SESSION['auth']) || !in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    echo json_encode(['error' => 'Acesso negado. Protocolo de segurança violado.']);
    exit;
}

/**
 * Gera senha de 10 caracteres: letras (MAI/min), números e símbolos
 * Esta senha será válida apenas pelos próximos 60 minutos.
 */
function generateRollingPassword($length = 10) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*";
    $password = "";
    // Usando random_int para maior segurança criptográfica
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

$new_pass = generateRollingPassword();
$new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
$admin_id = $_SESSION['auth']['user_id'];
$ip_address = $_SERVER['REMOTE_ADDR'];

// Inicia transação para garantir que o log e o update ocorram juntos
$mysqli->begin_transaction();

try {
    // 1. Atualiza o hash da senha e o timestamp de mudança na tabela users
    $stmt = $mysqli->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_hash, $admin_id);
    $stmt->execute();

    // 2. Registra o evento de rotação na tabela admin_audit_logs
    $log_stmt = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES (?, 'PASSWORD_ROTATION_EVENT', ?)");
    $log_stmt->bind_param("is", $admin_id, $ip_address);
    $log_stmt->execute();

    $mysqli->commit();

    // Retorna a senha em texto limpo apenas desta vez para o Admin copiar do Dashboard
    echo json_encode([
        'success' => true, 
        'new_password' => $new_pass,
        'expires_in' => '60 seconds'
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Erro na rotação de senha: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno ao processar rotação.']);
}