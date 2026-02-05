<?php
/**
 * API Endpoint - Taxas de Câmbio
 * * Retorna taxas de câmbio em tempo real para o JavaScript
 * Corrigido para evitar que avisos do PHP quebrem o JSON
 * * @author VSG Development Team
 */

// Iniciar buffer de saída para capturar qualquer lixo (Warnings, notices)
ob_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// CORS para desenvolvimento
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

try {
    // Ajuste de caminhos (assumindo que este arquivo está em /api/ ou similar)
    // Se o erro de require persistir, ajuste o número de ../ conforme sua pasta
    $bootstrapPath = __DIR__ . '/../registration/bootstrap.php';
    $classPath = __DIR__ . '/../includes/currency/CurrencyExchange.php';

    if (!file_exists($bootstrapPath)) {
        throw new Exception("Bootstrap nao encontrado em: " . $bootstrapPath);
    }
    
    require_once $bootstrapPath;
    require_once $classPath;

    // Limpar qualquer conteúdo que tenha sido gerado acidentalmente no buffer (como Warnings)
    if (ob_get_length()) ob_clean();

    // Verificar se MySQLi está disponível
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new Exception('Conexão com banco de dados não disponível. Verifique o db.php');
    }
    
    // Inicializar sistema de câmbio
    $exchange = new CurrencyExchange($mysqli);
    
    // Obter todas as taxas do cache
    $rates = $exchange->getAllRates();
    
    // Se não houver taxas em cache, buscar algumas principais
    if (empty($rates)) {
        $main_currencies = ['EUR', 'USD', 'BRL', 'GBP', 'CAD', 'AUD'];
        
        foreach ($main_currencies as $currency) {
            $rate = $exchange->getExchangeRate('MZN', $currency);
            if ($rate !== null) {
                $rates["MZN_{$currency}"] = $rate;
                
                // Adicionar inversa
                $rates["{$currency}_MZN"] = 1 / $rate;
            }
        }
    }
    
    // Adicionar algumas conversões cruzadas comuns
    $cross_rates = [];
    if (isset($rates['MZN_EUR']) && isset($rates['MZN_USD'])) {
        $cross_rates['EUR_USD'] = $rates['MZN_USD'] / $rates['MZN_EUR'];
        $cross_rates['USD_EUR'] = $rates['MZN_EUR'] / $rates['MZN_USD'];
    }
    
    if (isset($rates['MZN_EUR']) && isset($rates['MZN_BRL'])) {
        $cross_rates['EUR_BRL'] = $rates['MZN_BRL'] / $rates['MZN_EUR'];
        $cross_rates['BRL_EUR'] = $rates['MZN_EUR'] / $rates['MZN_BRL'];
    }
    
    $rates = array_merge($rates, $cross_rates);
    
    // Enviar a resposta JSON limpa
    echo json_encode([
        'success' => true,
        'rates' => $rates,
        'base_currency' => 'MZN',
        'timestamp' => time(),
        'cached' => !empty($rates)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Limpar o buffer em caso de erro para enviar apenas o JSON de erro
    if (ob_get_length()) ob_clean();
    
    // Log do erro real no servidor
    error_log("Erro na API de câmbio: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar taxas de câmbio',
        'message' => $e->getMessage(),
        'rates' => []
    ], JSON_PRETTY_PRINT);
}

// Finalizar e enviar buffer
ob_end_flush();