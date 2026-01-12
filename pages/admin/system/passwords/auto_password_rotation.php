<?php
/**
 * ARQUIVO: auto_password_rotation.php
 * Sistema automático de rotação de senha para Admins e Superadmins
 * - Superadmins: Rotação a cada 1 hora
 * - Admins: Rotação a cada 24 horas
 * Envia e-mail usando o mailer VisionGreen
 */
define('IS_ADMIN_PAGE', true);
require_once '../../../../registration/includes/db.php';
require_once '../../../../registration/includes/security.php';
require_once '../../../../registration/includes/mailer.php'; // Usa o mailer VisionGreen

/**
 * Gera senha segura de 10 caracteres
 */
function generateSecurePassword($length = 10) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*";
    $password = "";
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * EXECUÇÃO PRINCIPAL
 */
try {
    echo "🔐 VisionGreen - Sistema de Rotação Automática de Senha\n";
    echo "═══════════════════════════════════════════════════════\n";
    echo "Horário: " . date('d/m/Y H:i:s') . "\n\n";
    
    // Busca admins que precisam de rotação de senha
    $query = "
        SELECT 
            u.id, 
            u.nome, 
            u.email, 
            u.role, 
            u.password_changed_at,
            CASE 
                WHEN u.role = 'superadmin' THEN 3600
                ELSE 86400
            END as timeout_limit,
            TIMESTAMPDIFF(SECOND, u.password_changed_at, NOW()) as seconds_since_change
        FROM users u
        WHERE u.role IN ('admin', 'superadmin')
        AND (
            (u.role = 'superadmin' AND TIMESTAMPDIFF(SECOND, u.password_changed_at, NOW()) >= 3600)
            OR
            (u.role = 'admin' AND TIMESTAMPDIFF(SECOND, u.password_changed_at, NOW()) >= 86400)
        )
    ";
    
    $result = $mysqli->query($query);
    
    if ($result->num_rows > 0) {
        echo "🔄 Encontrados {$result->num_rows} administrador(es) que necessitam rotação...\n\n";
        
        $successCount = 0;
        $errorCount = 0;
        
        while ($admin = $result->fetch_assoc()) {
            echo "┌─ Processando: {$admin['nome']}\n";
            echo "│  Role: " . strtoupper($admin['role']) . "\n";
            echo "│  Email: {$admin['email']}\n";
            
            $mysqli->begin_transaction();
            
            try {
                // Gera nova senha
                $newPassword = generateSecurePassword(10);
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                
                // Atualiza no banco
                $stmt = $mysqli->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $newHash, $admin['id']);
                $stmt->execute();
                
                // Registra no log
                $logStmt = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address, details) VALUES (?, 'AUTO_PASSWORD_ROTATION', 'SYSTEM', ?)");
                $logDetails = "Rotação automática - Role: {$admin['role']} - Tempo decorrido: " . round($admin['seconds_since_change']/3600, 2) . "h";
                $logStmt->bind_param("is", $admin['id'], $logDetails);
                $logStmt->execute();
                
                $mysqli->commit();
                
                // Envia email usando o mailer VisionGreen
                // O mailer detecta automaticamente que é uma senha (não é código numérico de 6 dígitos)
                $emailSent = enviarEmailVisionGreen(
                    $admin['email'],
                    $admin['nome'],
                    $newPassword
                );
                
                if ($emailSent) {
                    echo "│  Status: ✅ Senha rotacionada com sucesso\n";
                    echo "│  Email: ✅ Enviado para {$admin['email']}\n";
                    $successCount++;
                } else {
                    echo "│  Status: ✅ Senha rotacionada\n";
                    echo "│  Email: ⚠️  ERRO ao enviar (verifique logs do PHPMailer)\n";
                    $errorCount++;
                }
                
                $expiresIn = $admin['role'] === 'superadmin' ? '1 hora' : '24 horas';
                echo "│  Validade: {$expiresIn}\n";
                echo "└─ Concluído\n\n";
                
            } catch (Exception $e) {
                $mysqli->rollback();
                echo "│  Status: ❌ ERRO ao processar\n";
                echo "│  Detalhes: {$e->getMessage()}\n";
                echo "└─ Falhou\n\n";
                $errorCount++;
                error_log("Erro na rotação de senha para usuário {$admin['id']}: " . $e->getMessage());
            }
        }
        
        echo "═══════════════════════════════════════════════════════\n";
        echo "✨ Processo concluído!\n";
        echo "   Sucessos: {$successCount}\n";
        echo "   Erros: {$errorCount}\n";
        echo "═══════════════════════════════════════════════════════\n\n";
        
    } else {
        echo "ℹ️  Nenhuma senha precisa ser rotacionada no momento.\n";
        echo "   • Superadmins: Rotação a cada 1 hora\n";
        echo "   • Admins: Rotação a cada 24 horas\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO CRÍTICO: {$e->getMessage()}\n";
    error_log("Erro crítico no sistema de rotação automática: " . $e->getMessage());
}

$mysqli->close();
?>