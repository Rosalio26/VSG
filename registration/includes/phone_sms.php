<?php
/**
 * VisionGreen - Serviço de Envio de SMS via Twilio
 */

require_once __DIR__ . '/twilio/vendor/autoload.php'; // Carrega a SDK do Twilio
use Twilio\Rest\Client;

function enviarSMSVerificacao($numero_destino, $codigo_verificacao) {
    // --- CONFIGURAÇÕES DO TWILIO (Mova para um .env ou config.php depois) ---
    $sid    = ""; // Seu Account SID
    $token  = "";               // Seu Auth Token
    $from   = "";                       // Seu número Twilio (com +)

    try {
        $client = new Client($sid, $token);

        // Mensagem personalizada para a VisionGreen
        $mensagem = "VisionGreen: Seu codigo de verificacao e: {$codigo_verificacao}. Valido por 10 minutos. Nao o compartilhe.";

        $client->messages->create(
            $numero_destino, // O número captado pelo phone_handler.js (+244...)
            [
                'from' => $from,
                'body' => $mensagem
            ]
        );

        return [
            'success' => true,
            'message' => 'SMS enviado com sucesso.'
        ];

    } catch (Exception $e) {
        // Log de erro interno
        error_log("Erro Twilio ao enviar para $numero_destino: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Falha técnica ao enviar SMS.'
        ];
    }
}