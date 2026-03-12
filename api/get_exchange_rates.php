<?php
/**
 * API Endpoint — Taxas de Câmbio
 * GET /api/get_exchange_rates.php
 *
 * Caminhos esperados (relativo à raiz do projecto, onde está shopping.php):
 *   registration/includes/db.php          → define $mysqli
 *   includes/currency/CurrencyExchange.php → classe CurrencyExchange
 *
 * @version 4.0
 */

// ── Output buffer + suprimir erros no output ─────────────────────────────────
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ob_start();

// ── Headers ───────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

// CORS (desenvolvimento)
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
    exit(0);
}

// ── Helper: enviar JSON limpo e terminar ──────────────────────────────────────
function jsonOut(array $data, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

// ── Helper: log de erro ───────────────────────────────────────────────────────
function apiLog(string $msg, ?\Throwable $e = null): void
{
    $line = '[VSG Currency API] ' . $msg;
    if ($e) {
        $line .= ' | ' . $e->getMessage()
               . ' em ' . $e->getFile() . ':' . $e->getLine();
    }
    error_log($line);
}

// ── Validar método ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonOut(['success' => false, 'error' => 'Método não permitido.', 'rates' => []], 405);
}

// ── Localizar raiz do projecto ────────────────────────────────────────────────
//
// Este ficheiro fica em:  <raiz>/api/get_exchange_rates.php
// A raiz do projecto é:   dirname(__DIR__)
//
$root = dirname(__DIR__);   // sobe um nível acima de /api/

$dbPath       = $root . '/registration/includes/db.php';
$exchangePath = $root . '/includes/currency/CurrencyExchange.php';

// ── Verificar existência dos ficheiros ────────────────────────────────────────
if (!file_exists($dbPath)) {
    apiLog("db.php não encontrado em: {$dbPath}");
    jsonOut([
        'success' => false,
        'error'   => 'Configuração do servidor em falta (db).',
        'rates'   => [],
    ], 500);
}

if (!file_exists($exchangePath)) {
    apiLog("CurrencyExchange.php não encontrado em: {$exchangePath}");
    jsonOut([
        'success' => false,
        'error'   => 'Configuração do servidor em falta (exchange).',
        'rates'   => [],
    ], 500);
}

// ── Carregar dependências ─────────────────────────────────────────────────────
try {
    ob_start();
    require_once $dbPath;
    require_once $exchangePath;
    ob_end_clean();
    ob_start(); // buffer limpo para o resto
} catch (\Throwable $e) {
    apiLog('Erro ao carregar dependências', $e);
    jsonOut(['success' => false, 'error' => 'Erro ao inicializar servidor.', 'rates' => []], 500);
}

// ── Validar $mysqli ───────────────────────────────────────────────────────────
if (empty($mysqli) || !($mysqli instanceof mysqli) || $mysqli->connect_error) {
    $detail = isset($mysqli) && $mysqli instanceof mysqli ? $mysqli->connect_error : 'variável não definida';
    apiLog("Conexão MySQL inválida: {$detail}");
    jsonOut(['success' => false, 'error' => 'Erro de conexão com base de dados.', 'rates' => []], 500);
}

// ── Validar classe ────────────────────────────────────────────────────────────
if (!class_exists('CurrencyExchange')) {
    apiLog('Classe CurrencyExchange não carregada.');
    jsonOut(['success' => false, 'error' => 'Serviço de câmbio indisponível.', 'rates' => []], 500);
}

// ── Obter taxas ───────────────────────────────────────────────────────────────
try {
    $exchange  = new CurrencyExchange($mysqli);

    // 1. Tentar cache/BD
    $rates     = $exchange->getAllRates();
    $fromCache = !empty($rates);

    // 2. Se vazio, buscar moedas principais
    if (empty($rates)) {
        $main = ['EUR', 'USD', 'BRL', 'GBP', 'CAD', 'AUD', 'ZAR', 'AOA', 'CNY', 'JPY', 'CHF'];
        foreach ($main as $cur) {
            try {
                $rate = $exchange->getExchangeRate('MZN', $cur);
                if ($rate !== null && $rate > 0) {
                    $rates["MZN_{$cur}"] = round($rate, 6);
                    $rates["{$cur}_MZN"] = round(1 / $rate, 6);
                }
            } catch (\Throwable $e) {
                apiLog("Taxa MZN_{$cur} falhou", $e);
            }
        }
    }

    // 3. Taxas cruzadas derivadas
    $pairs = [
        ['EUR', 'USD'], ['EUR', 'BRL'], ['EUR', 'GBP'],
        ['USD', 'BRL'], ['USD', 'GBP'], ['USD', 'ZAR'],
        ['GBP', 'USD'], ['AOA', 'USD'],
    ];
    foreach ($pairs as [$a, $b]) {
        $ka = "MZN_{$a}";
        $kb = "MZN_{$b}";
        if (!empty($rates[$ka]) && !empty($rates[$kb])) {
            $rates["{$a}_{$b}"] = round($rates[$kb] / $rates[$ka], 6);
            $rates["{$b}_{$a}"] = round($rates[$ka] / $rates[$kb], 6);
        }
    }

    jsonOut([
        'success'       => true,
        'rates'         => $rates,
        'base_currency' => 'MZN',
        'timestamp'     => time(),
        'cached'        => $fromCache,
        'total_rates'   => count($rates),
        'server_time'   => date('Y-m-d H:i:s'),
        'api_version'   => '4.0',
    ]);

} catch (\Throwable $e) {
    apiLog('Erro ao obter taxas', $e);
    jsonOut([
        'success' => false,
        'error'   => 'Não foi possível obter as taxas de câmbio.',
        'message' => $e->getMessage(),
        'rates'   => [],
    ], 500);
}