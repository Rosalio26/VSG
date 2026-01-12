<?php
/**
 * ARQUIVO: generate_password.php (VERSÃO MELHORADA)
 * Geração manual de senha com envio automático de email usando o mailer VisionGreen
 */

define('IS_ADMIN_PAGE', true);
require_once '../../../../registration/includes/db.php';
require_once '../../../../registration/includes/security.php';
require_once '../../../../registration/includes/mailer.php'; // Importa o mailer VisionGreen

// Bloqueio de segurança
if (!isset($_SESSION['auth']) || !in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    echo json_encode(['error' => 'Acesso negado. Protocolo de segurança violado.']);
    exit;
}

/**
 * Gera senha segura de 10 caracteres (conforme o padrão original)
 */
function generateRollingPassword($length = 10) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*";
    $password = "";
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Busca informações do admin
$stmt = $mysqli->prepare("SELECT nome, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['auth']['user_id']);
$stmt->execute();
$adminData = $stmt->get_result()->fetch_assoc();

if (!$adminData) {
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
    exit;
}

$new_pass = generateRollingPassword(10); // Mantém 10 caracteres conforme original
$new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
$admin_id = $_SESSION['auth']['user_id'];
$ip_address = $_SERVER['REMOTE_ADDR'];

$mysqli->begin_transaction();

try {
    // Atualiza senha
    $stmt = $mysqli->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_hash, $admin_id);
    $stmt->execute();

    // Registra no log
    $log_stmt = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address, details) VALUES (?, 'MANUAL_PASSWORD_ROTATION', ?, ?)");
    $details = "Regeneração manual solicitada pelo usuário - Role: " . $adminData['role'];
    $log_stmt->bind_param("iss", $admin_id, $ip_address, $details);
    $log_stmt->execute();

    $mysqli->commit();

    // Envia email usando o mailer VisionGreen
    // O mailer vai detectar que é uma senha (não é numérico de 6 dígitos) e ajustar automaticamente
    $emailSent = enviarEmailVisionGreen(
        $adminData['email'],
        $adminData['nome'],
        $new_pass
    );

    $expiresIn = $adminData['role'] === 'superadmin' ? '1 hora' : '24 horas';

    echo json_encode([
        'success' => true, 
        'new_password' => $new_pass,
        'expires_in' => $expiresIn,
        'email_sent' => $emailSent,
        'email_address' => $adminData['email'],
        'role' => $adminData['role'],
        'message' => $emailSent ? 
            "✅ Senha gerada com sucesso! Um email foi enviado para {$adminData['email']}" : 
            "⚠️ Senha gerada, mas houve erro ao enviar o email. Copie a senha abaixo:"
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Erro na rotação manual de senha: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno ao processar rotação: ' . $e->getMessage()
    ]);
}
?>