<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Envia o e-mail de confirmação/recuperação com template profissional VisionGreen.
 */
function enviarEmailVisionGreen($emailDestino, $nomeDestino, $codigo) {
    $mail = new PHPMailer(true);
    try {
        // --- CONFIGURAÇÕES SMTP ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'eanixr@gmail.com'; 
        $mail->Password   = 'zwirfytkoskulbfx'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // --- DESTINATÁRIOS ---
        $mail->setFrom('eanixr@gmail.com', 'VisionGreen');
        $mail->addAddress($emailDestino, $nomeDestino);

        // --- CONTEÚDO DO E-MAIL ---
        $mail->isHTML(true);
        $mail->Subject = $codigo . ' é o seu código de confirmação';

        // Paleta de cores VisionGreen
        $bg_page        = "#f3f4f6"; 
        $verde_vision   = "#00a63e"; 
        $preto_card     = "#111827"; 
        $texto_principal= "#1f2937";
        $texto_secundario= "#4b5563"; 

        $mail->Body = "
        <div style='margin: 0; padding: 0; background-color: {$bg_page}; width: 100%; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif;'>
            <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                <tr>
                    <td align='center' style='padding: 40px 10px;'>
                        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>
                            <tr>
                                <td align='center' style='background-color: {$verde_vision}; padding: 30px 20px;'>
                                    <h1 style='color: #ffffff; margin: 0; font-size: 28px; letter-spacing: 1px;'>VisionGreen</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 40px 30px; text-align: center;'>
                                    <h2 style='color: {$texto_principal}; margin: 0 0 20px 0;'>Olá, {$nomeDestino}!</h2>
                                    <p style='color: {$texto_secundario}; font-size: 16px; line-height: 1.5; margin: 0 0 30px 0;'>
                                        Recebemos uma solicitação de verificação para a sua conta. Use o código abaixo para prosseguir:
                                    </p>
                                    
                                    <div style='background-color: {$preto_card}; color: #4ade80; padding: 25px; font-size: 36px; font-weight: bold; border-radius: 8px; display: inline-block; letter-spacing: 10px;'>
                                        {$codigo}
                                    </div>
                                    
                                    <p style='color: {$texto_secundario}; font-size: 14px; margin-top: 30px; line-height: 1.5;'>
                                        Este código é válido por <strong>1 hora</strong>.<br>
                                        Se você não solicitou este código, pode ignorar este e-mail com segurança.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 20px; background-color: #f9fafb; text-align: center; border-top: 1px solid #edf2f7;'>
                                    <p style='color: #9ca3af; font-size: 12px; margin: 0;'>
                                        &copy; " . date('Y') . " VisionGreen - Sustentando um futuro verde.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro PHPMailer: " . $mail->ErrorInfo);
        return false;
    }
}