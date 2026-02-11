<?php
/**
 * API Endpoint - Taxas de Câmbio
 * Retorna taxas de câmbio em tempo real para o JavaScript
 * Versão corrigida com tratamento robusto de erros
 * 
 * @author VSG Development Team
 * @version 3.2
 */

// ============================================================================
// CONFIGURAÇÃO INICIAL - Suprimir TODOS os outputs não-JSON
// ============================================================================

// Desabilitar exibição de erros no output (mas continuar logando)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Iniciar buffer de saída ANTES de qualquer coisa
ob_start();

// Configurar headers HTTP primeiro
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Pragma: no-cache');

// CORS para desenvolvimento (ajuste conforme necessário)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

// Tratar requisições OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

// ============================================================================
// FUNÇÃO AUXILIAR - Enviar resposta JSON limpa
// ============================================================================

/**
 * Envia resposta JSON garantindo que não há lixo no output
 * 
 * @param array $data Dados para enviar
 * @param int $httpCode Código HTTP (padrão: 200)
 */
function sendJsonResponse($data, $httpCode = 200) {
    // Limpar completamente qualquer output anterior
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Definir código HTTP
    http_response_code($httpCode);
    
    // Reconfirmar header (em caso de redirecionamentos internos)
    header('Content-Type: application/json; charset=utf-8');
    
    // Enviar JSON formatado
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    exit(0);
}

// ============================================================================
// FUNÇÃO AUXILIAR - Log de erros seguro
// ============================================================================

/**
 * Registra erro no log do servidor sem quebrar a API
 * 
 * @param string $message Mensagem de erro
 * @param Exception|null $exception Exceção opcional
 */
function logApiError($message, $exception = null) {
    $logMessage = "[VSG Currency API] " . $message;
    
    if ($exception) {
        $logMessage .= " | Erro: " . $exception->getMessage();
        $logMessage .= " | Arquivo: " . $exception->getFile();
        $logMessage .= " | Linha: " . $exception->getLine();
    }
    
    error_log($logMessage);
}

// ============================================================================
// BLOCO TRY-CATCH PRINCIPAL
// ============================================================================

try {
    // ------------------------------------------------------------------------
    // VALIDAÇÃO DE MÉTODO HTTP
    // ------------------------------------------------------------------------
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido. Use GET.');
    }
    
    // ------------------------------------------------------------------------
    // DETECÇÃO E CARREGAMENTO DE ARQUIVOS NECESSÁRIOS
    // ------------------------------------------------------------------------
    
    // Detectar diretório raiz do projeto
    $currentDir = __DIR__;
    $projectRoot = null;
    
    // Tentar localizar bootstrap.php subindo na hierarquia de diretórios
    $maxLevels = 5; // Máximo de níveis para subir
    for ($i = 0; $i < $maxLevels; $i++) {
        $testPath = $currentDir . str_repeat('/..', $i) . '/registration/bootstrap.php';
        
        if (file_exists($testPath)) {
            $projectRoot = realpath(dirname($testPath) . '/..');
            break;
        }
    }
    
    if (!$projectRoot) {
        throw new Exception(
            'Não foi possível localizar o arquivo bootstrap.php. ' .
            'Verifique a estrutura de diretórios do projeto.'
        );
    }
    
    // Definir caminhos absolutos
    $bootstrapPath = $projectRoot . '/registration/bootstrap.php';
    $currencyClassPath = $projectRoot . '/includes/currency/CurrencyExchange.php';
    
    // Verificar existência dos arquivos
    if (!file_exists($bootstrapPath)) {
        throw new Exception("Bootstrap não encontrado: {$bootstrapPath}");
    }
    
    if (!file_exists($currencyClassPath)) {
        throw new Exception("Classe CurrencyExchange não encontrada: {$currencyClassPath}");
    }
    
    // ------------------------------------------------------------------------
    // CARREGAR DEPENDÊNCIAS
    // ------------------------------------------------------------------------
    
    // Capturar qualquer output gerado pelos requires
    ob_start();
    
    require_once $bootstrapPath;
    require_once $currencyClassPath;
    
    // Descartar output dos requires
    ob_end_clean();
    
    // Reiniciar buffer limpo
    ob_start();
    
    // ------------------------------------------------------------------------
    // VALIDAR CONEXÃO COM BANCO DE DADOS
    // ------------------------------------------------------------------------
    
    if (!isset($mysqli)) {
        throw new Exception('Variável $mysqli não foi definida pelo bootstrap.');
    }
    
    if (!($mysqli instanceof mysqli)) {
        throw new Exception('$mysqli não é uma instância válida de mysqli.');
    }
    
    if ($mysqli->connect_error) {
        throw new Exception('Erro de conexão MySQL: ' . $mysqli->connect_error);
    }
    
    // ------------------------------------------------------------------------
    // VALIDAR CLASSE CurrencyExchange
    // ------------------------------------------------------------------------
    
    if (!class_exists('CurrencyExchange')) {
        throw new Exception('Classe CurrencyExchange não foi carregada corretamente.');
    }
    
    // ------------------------------------------------------------------------
    // INICIALIZAR SISTEMA DE CÂMBIO
    // ------------------------------------------------------------------------
    
    $exchange = new CurrencyExchange($mysqli);
    
    // ------------------------------------------------------------------------
    // OBTER TAXAS DE CÂMBIO
    // ------------------------------------------------------------------------
    
    // Tentar obter do cache primeiro
    $rates = $exchange->getAllRates();
    $fromCache = !empty($rates);
    
    // Se cache vazio, buscar taxas principais
    if (empty($rates)) {
        $mainCurrencies = ['EUR', 'USD', 'BRL', 'GBP', 'CAD', 'AUD', 'ZAR', 'AOA'];
        
        foreach ($mainCurrencies as $currency) {
            try {
                $rate = $exchange->getExchangeRate('MZN', $currency);
                
                if ($rate !== null && $rate > 0) {
                    $rates["MZN_{$currency}"] = $rate;
                    $rates["{$currency}_MZN"] = 1 / $rate;
                }
            } catch (Exception $e) {
                logApiError("Erro ao buscar taxa MZN_{$currency}", $e);
                // Continuar com outras moedas mesmo se uma falhar
                continue;
            }
        }
    }
    
    // ------------------------------------------------------------------------
    // CALCULAR TAXAS CRUZADAS COMUNS
    // ------------------------------------------------------------------------
    
    $crossRates = [];
    
    // EUR <-> USD
    if (isset($rates['MZN_EUR']) && isset($rates['MZN_USD'])) {
        $crossRates['EUR_USD'] = $rates['MZN_USD'] / $rates['MZN_EUR'];
        $crossRates['USD_EUR'] = $rates['MZN_EUR'] / $rates['MZN_USD'];
    }
    
    // EUR <-> BRL
    if (isset($rates['MZN_EUR']) && isset($rates['MZN_BRL'])) {
        $crossRates['EUR_BRL'] = $rates['MZN_BRL'] / $rates['MZN_EUR'];
        $crossRates['BRL_EUR'] = $rates['MZN_EUR'] / $rates['MZN_BRL'];
    }
    
    // USD <-> BRL
    if (isset($rates['MZN_USD']) && isset($rates['MZN_BRL'])) {
        $crossRates['USD_BRL'] = $rates['MZN_BRL'] / $rates['MZN_USD'];
        $crossRates['BRL_USD'] = $rates['MZN_USD'] / $rates['MZN_BRL'];
    }
    
    // GBP <-> EUR
    if (isset($rates['MZN_GBP']) && isset($rates['MZN_EUR'])) {
        $crossRates['GBP_EUR'] = $rates['MZN_EUR'] / $rates['MZN_GBP'];
        $crossRates['EUR_GBP'] = $rates['MZN_GBP'] / $rates['MZN_EUR'];
    }
    
    // Mesclar taxas
    $rates = array_merge($rates, $crossRates);
    
    // ------------------------------------------------------------------------
    // PREPARAR RESPOSTA DE SUCESSO
    // ------------------------------------------------------------------------
    
    $response = [
        'success' => true,
        'rates' => $rates,
        'base_currency' => 'MZN',
        'timestamp' => time(),
        'cached' => $fromCache,
        'total_rates' => count($rates),
        'server_time' => date('Y-m-d H:i:s'),
        'api_version' => '3.2'
    ];
    
    sendJsonResponse($response, 200);
    
} catch (Exception $e) {
    // ------------------------------------------------------------------------
    // TRATAMENTO DE ERROS
    // ------------------------------------------------------------------------
    
    // Logar erro no servidor
    logApiError('Erro fatal na API', $e);
    
    // Preparar resposta de erro
    $errorResponse = [
        'success' => false,
        'error' => 'Erro ao buscar taxas de câmbio',
        'message' => $e->getMessage(),
        'rates' => [],
        'timestamp' => time(),
        'server_time' => date('Y-m-d H:i:s')
    ];
    
    // Adicionar detalhes extras em modo debug
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        $errorResponse['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    sendJsonResponse($errorResponse, 500);
}

// ============================================================================
// FALLBACK - Caso algo dê errado com o sendJsonResponse
// ============================================================================

// Este código só executa se sendJsonResponse() falhar (não deveria acontecer)
ob_end_clean();
http_response_code(500);
echo json_encode([
    'success' => false,
    'error' => 'Erro crítico no servidor',
    'rates' => []
]);