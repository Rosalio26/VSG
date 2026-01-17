<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * MAILER VISIONGREEN - VERSÃO REFORÇADA COM SEGURANÇA
 * 
 * Envia emails seguros com templates profissionais
 * Apenas tipos de mensagem autorizados são permitidos
 * Sistema anti-spam e validação rigorosa
 */

/**
 * Configuração de tipos de email autorizados
 * SEGURANÇA: Apenas estes tipos podem ser enviados
 */
define('AUTHORIZED_EMAIL_TYPES', [
    'email_verification',     // Código 2FA de verificação de email
    'password_rotation',      // Nova senha de rotação automática
    'password_manual',        // Nova senha gerada manualmente
    'password_recovery',      // Recuperação de senha esquecida
    'secure_id_code',         // Código Secure ID (V-S-G)
    'account_blocked',        // Notificação de conta bloqueada
    'account_approved',       // Conta aprovada após auditoria
    'business_rejected',      // Documentos de empresa rejeitados
    'admin_alert',            // Alerta administrativo crítico
    'welcome_message'         // Mensagem de boas-vindas
]);

/**
 * Templates de conteúdo para cada tipo de email
 * Estrutura: 'tipo' => ['subject' => '...', 'title' => '...', 'message' => '...']
 */
function getEmailTemplate($type, $code, $nome, $extraData = []) {
    $templates = [
        'email_verification' => [
            'subject' => $code . ' é o seu código de confirmação',
            'title' => '🔐 Verificação de Email',
            'message' => 'Recebemos uma solicitação de verificação para a sua conta. Use o código abaixo para prosseguir:',
            'validity' => '1 hora',
            'footer_note' => 'Se você não solicitou este código, ignore este email.'
        ],
        
        'password_rotation' => [
            'subject' => '🔐 Rotação Automática de Senha - VisionGreen',
            'title' => '🔄 Nova Senha Gerada Automaticamente',
            'message' => 'Sua senha foi renovada automaticamente pelo sistema de segurança rotativa:',
            'validity' => ($extraData['role'] ?? 'admin') === 'superadmin' ? '1 hora' : '24 horas',
            'footer_note' => 'Use-a juntamente com seu Secure ID para acessar o painel administrativo.'
        ],
        
        'password_manual' => [
            'subject' => '🔐 Nova Senha Gerada - VisionGreen',
            'title' => '✅ Renovação Manual de Senha',
            'message' => 'Você solicitou a geração de uma nova senha. Use a credencial abaixo:',
            'validity' => ($extraData['role'] ?? 'admin') === 'superadmin' ? '1 hora' : '24 horas',
            'footer_note' => 'Esta senha foi gerada por sua solicitação manual.'
        ],
        
        'password_recovery' => [
            'subject' => '🔑 Recuperação de Senha - VisionGreen',
            'title' => '🔓 Recuperar Acesso',
            'message' => 'Recebemos uma solicitação de recuperação de senha. Use o código abaixo:',
            'validity' => '15 minutos',
            'footer_note' => 'Se você não solicitou recuperação, ignore este email e sua conta permanecerá segura.'
        ],
        
        'secure_id_code' => [
            'subject' => '🛡️ Código Secure ID - VisionGreen',
            'title' => '🔐 Protocolo V-S-G',
            'message' => 'Código de verificação Secure ID para autenticação em duas etapas:',
            'validity' => '5 minutos',
            'footer_note' => 'Este código é de uso único e expira rapidamente.'
        ],
        
        'account_blocked' => [
            'subject' => '⚠️ Conta Bloqueada - VisionGreen',
            'title' => '🚫 Acesso Bloqueado',
            'message' => 'Sua conta foi temporariamente bloqueada por motivos de segurança:',
            'validity' => 'N/A',
            'footer_note' => 'Entre em contato com o suporte para mais informações.'
        ],
        
        'account_approved' => [
            'subject' => '✅ Conta Aprovada - VisionGreen',
            'title' => '🎉 Bem-vindo!',
            'message' => 'Sua conta foi aprovada! Você já pode acessar todos os recursos:',
            'validity' => 'N/A',
            'footer_note' => 'Obrigado por escolher VisionGreen.'
        ],
        
        'business_rejected' => [
            'subject' => '❌ Documentos Rejeitados - VisionGreen',
            'title' => '📋 Revisão Necessária',
            'message' => 'Seus documentos foram revisados e precisam de correções:',
            'validity' => 'N/A',
            'footer_note' => 'Corrija os problemas apontados e envie novamente.'
        ],
        
        'admin_alert' => [
            'subject' => '🚨 Alerta Crítico - VisionGreen',
            'title' => '⚠️ Atenção Necessária',
            'message' => 'Um evento crítico requer sua atenção imediata:',
            'validity' => 'N/A',
            'footer_note' => 'Verifique o painel administrativo para mais detalhes.'
        ],
        
        'welcome_message' => [
            'subject' => '👋 Bem-vindo ao VisionGreen',
            'title' => '🌱 Conta Criada com Sucesso',
            'message' => 'Sua conta foi criada com sucesso! Suas credenciais de acesso:',
            'validity' => 'N/A',
            'footer_note' => 'Guarde suas credenciais em local seguro.'
        ]
    ];
    
    return $templates[$type] ?? null;
}

/**
 * Função principal de envio de email
 * 
 * @param string $emailDestino Email do destinatário
 * @param string $nomeDestino Nome do destinatário
 * @param string $conteudo Código ou mensagem a ser enviada
 * @param string $tipo Tipo de email (deve estar em AUTHORIZED_EMAIL_TYPES)
 * @param array $extraData Dados extras (role, motivo, etc)
 * @return bool True se enviou, False se falhou
 */
function enviarEmailVisionGreen($emailDestino, $nomeDestino, $conteudo, $tipo = 'auto', $extraData = []) {
    
    // ========== VALIDAÇÕES DE SEGURANÇA ==========
    
    // 1. Validar email
    if (!filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
        error_log("MAILER SECURITY: Email inválido: $emailDestino");
        return false;
    }
    
    // 2. Validar nome (anti-injection)
    if (empty($nomeDestino) || strlen($nomeDestino) > 100 || preg_match('/<script|javascript:|on\w+=/i', $nomeDestino)) {
        error_log("MAILER SECURITY: Nome suspeito: $nomeDestino");
        return false;
    }
    
    // 3. Detecção automática de tipo (backward compatibility)
    if ($tipo === 'auto') {
        if (is_numeric($conteudo) && strlen($conteudo) <= 6) {
            $tipo = 'email_verification';
        } else {
            $tipo = 'password_rotation';
        }
    }
    
    // 4. SEGURANÇA: Verificar se o tipo é autorizado
    if (!in_array($tipo, AUTHORIZED_EMAIL_TYPES)) {
        error_log("MAILER SECURITY: Tipo não autorizado: $tipo - Email bloqueado!");
        return false;
    }
    
    // 5. Obter template
    $template = getEmailTemplate($tipo, $conteudo, $nomeDestino, $extraData);
    if (!$template) {
        error_log("MAILER ERROR: Template não encontrado para tipo: $tipo");
        return false;
    }
    
    // 6. Sanitizar conteúdo
    $conteudo = htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8');
    
    // ========== CONFIGURAÇÃO DO PHPMailer ==========
    
    $mail = new PHPMailer(true);
    
    try {
        // Configurações SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'eanixr@gmail.com'; 
        $mail->Password   = 'zwirfytkoskulbfx'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Remetente e destinatário
        $mail->setFrom('eanixr@gmail.com', 'VisionGreen Security');
        $mail->addAddress($emailDestino, $nomeDestino);

        // Assunto e corpo
        $mail->isHTML(true);
        $mail->Subject = $template['subject'];
        
        // ========== TEMPLATE HTML ==========
        
        $bg_page = "#f3f4f6"; 
        $verde_vision = "#00a63e"; 
        $preto_card = "#111827"; 
        $texto_principal = "#1f2937";
        $texto_secundario = "#4b5563";
        
        // Determina a cor do código baseado no tipo
        $codeColor = match($tipo) {
            'email_verification' => '#4ade80',
            'password_rotation', 'password_manual' => '#00ff88',
            'password_recovery' => '#ffcc00',
            'secure_id_code' => '#4da3ff',
            'account_blocked' => '#ff4d4d',
            default => '#4ade80'
        };
        
        $mail->Body = "
        <div style='margin: 0; padding: 0; background-color: {$bg_page}; width: 100%; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif;'>
            <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                <tr>
                    <td align='center' style='padding: 40px 10px;'>
                        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>
                            
                            <!-- CABEÇALHO -->
                            <tr>
                                <td align='center' style='background-color: {$verde_vision}; padding: 30px 20px;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 28px; letter-spacing: 1px;'>VisionGreen</h1>
                                    <p style='color: rgba(255,255,255,0.9); margin: 5px 0 0; font-size: 14px;'>{$template['title']}</p>
                                </td>
                            </tr>
                            
                            <!-- CORPO -->
                            <tr>
                                <td style='padding: 40px 30px; text-align: center;'>
                                    <h2 style='color: {$texto_principal}; margin: 0 0 20px 0;'>Olá, {$nomeDestino}!</h2>
                                    <p style='color: {$texto_secundario}; font-size: 16px; line-height: 1.5; margin: 0 0 30px 0;'>
                                        {$template['message']}
                                    </p>
                                    
                                    <!-- CÓDIGO/SENHA -->
                                    <div style='background-color: {$preto_card}; color: {$codeColor}; padding: 25px; font-size: 32px; font-weight: bold; border-radius: 8px; display: inline-block; letter-spacing: 5px; font-family: monospace; word-break: break-all;'>
                                        {$conteudo}
                                    </div>
                                    
                                    <!-- INFORMAÇÕES ADICIONAIS -->
                                    <div style='background-color: #f9fafb; border-left: 4px solid {$verde_vision}; padding: 20px; margin-top: 30px; border-radius: 8px; text-align: left;'>
                                        <p style='color: {$texto_secundario}; font-size: 14px; margin: 0 0 10px; line-height: 1.5;'>
                                            <strong style='color: {$texto_principal};'>⏰ Validade:</strong> {$template['validity']}<br>
                                            <strong style='color: {$texto_principal};'>📧 Email:</strong> {$emailDestino}<br>
                                            <strong style='color: {$texto_principal};'>🕐 Enviado:</strong> " . date('d/m/Y H:i:s') . "
                                        </p>
                                    </div>
                                    
                                    <p style='color: {$texto_secundario}; font-size: 13px; margin-top: 25px; line-height: 1.5;'>
                                        {$template['footer_note']}
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- RODAPÉ -->
                            <tr>
                                <td style='padding: 20px; background-color: #f9fafb; text-align: center; border-top: 1px solid #edf2f7;'>
                                    <p style='color: #9ca3af; font-size: 12px; margin: 0;'>
                                        &copy; " . date('Y') . " VisionGreen - Sustentando um futuro verde.
                                    </p>
                                    <p style='color: #9ca3af; font-size: 11px; margin: 10px 0 0;'>
                                        Este é um email automático. Não responda esta mensagem.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>";

        // Envia o email
        $mail->send();
        
        // Log de sucesso
        error_log("MAILER SUCCESS: Email '$tipo' enviado para $emailDestino");
        
        return true;
        
    } catch (Exception $e) {
        error_log("MAILER ERROR: {$mail->ErrorInfo} - Tipo: $tipo - Destino: $emailDestino");
        return false;
    }
}

/**
 * EXEMPLOS DE USO:
 * 
 * // 1. Código 2FA de verificação (6 dígitos)
 * enviarEmailVisionGreen('user@email.com', 'João Silva', '123456', 'email_verification');
 * 
 * // 2. Nova senha de rotação automática
 * enviarEmailVisionGreen('admin@email.com', 'Maria Admin', 'Xk8#mP2@vL', 'password_rotation', ['role' => 'superadmin']);
 * 
 * // 3. Nova senha manual
 * enviarEmailVisionGreen('admin@email.com', 'João Admin', 'Bq9!nR5@wT', 'password_manual', ['role' => 'admin']);
 * 
 * // 4. Recuperação de senha
 * enviarEmailVisionGreen('user@email.com', 'Pedro User', '987654', 'password_recovery');
 * 
 * // 5. Secure ID
 * enviarEmailVisionGreen('admin@email.com', 'Ana Admin', '12345', 'secure_id_code');
 * 
 * // 6. Auto-detecção (backward compatibility)
 * enviarEmailVisionGreen('user@email.com', 'João Silva', '123456'); // Detecta como email_verification
 * enviarEmailVisionGreen('admin@email.com', 'Maria Admin', 'Xk8#mP2@vL'); // Detecta como password_rotation
 */
?>