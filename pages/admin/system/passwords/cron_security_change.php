<?php
// Caminhos baseados na sua estrutura
require_once __DIR__ . '/../../../../registration/includes/db.php';
require_once __DIR__ . '/../../../../registration/includes/mailer.php'; 

// 1. Busca todos os Admins cuja senha expirou (mais de 1 hora) 
// E que ainda não tiveram um reset registrado na última hora
$sql = "SELECT id, nome, email, password_changed_at FROM users 
        WHERE (role = 'admin' OR role = 'superadmin') 
        AND status != 'blocked'
        AND password_changed_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";

$result = $mysqli->query($sql);

if ($result->num_rows > 0) {
    while ($admin = $result->fetch_assoc()) {
        
        // 2. Gerar nova senha segura (10 caracteres como no seu sistema)
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*";
        $nova_senha = substr(str_shuffle($chars), 0, 10);
        $hash = password_hash($nova_senha, PASSWORD_BCRYPT);
        $adminId = $admin['id'];

        $mysqli->begin_transaction();
        try {
            // 3. Atualiza o banco
            $update = $mysqli->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
            $update->bind_param("si", $hash, $adminId);
            $update->execute();

            // 4. Log de Auditoria do Reset Automático
            $log = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES (?, 'AUTO_EXPIRATION_SMTP_SENT', 'SYSTEM_CORE')");
            $log->bind_param("i", $adminId);
            $log->execute();

            // 5. Envio via SMTP (Usando sua função enviarEmailVisionGreen)
            // Certifique-se que o mailer.php está configurado corretamente com Host, User e Pass do SMTP
            $mensagem = "Sua senha de auditor expirou automaticamente. Nova senha de acesso: " . $nova_senha;
            enviarEmailVisionGreen($admin['email'], $admin['nome'], $mensagem);

            $mysqli->commit();
            echo "Senha rotacionada e e-mail enviado para: " . $admin['email'] . "\n";

        } catch (Exception $e) {
            $mysqli->rollback();
            error_log("Erro no Cron de Segurança: " . $e->getMessage());
        }
    }
} else {
    echo "Nenhum Admin com senha expirada encontrado.\n";
}