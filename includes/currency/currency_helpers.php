<?php
/**
 * Helper Functions - Sistema de Câmbio VSG (Versão 3.0)
 * 
 * Funções auxiliares otimizadas para conversão automática
 * 
 * @author VSG Development Team
 */

require_once __DIR__ . '/CurrencyExchange.php';

// Instância global (singleton)
$GLOBALS['currency_exchange'] = null;

/**
 * Inicializar sistema de câmbio
 */
function init_currency_exchange($mysqli) {
    if ($GLOBALS['currency_exchange'] === null) {
        $GLOBALS['currency_exchange'] = new CurrencyExchange($mysqli);
    }
    return $GLOBALS['currency_exchange'];
}

/**
 * Obter instância do sistema de câmbio
 */
function get_currency_exchange() {
    return $GLOBALS['currency_exchange'];
}

/**
 * Detectar país do usuário (melhorado)
 */
function detect_user_country() {
    // 1. Verificar cookie de preferência
    if (isset($_COOKIE['vsg_country'])) {
        return strtoupper($_COOKIE['vsg_country']);
    }
    
    // 2. Verificar sessão
    if (!empty($_SESSION['user_location']['country_code'])) {
        return strtoupper($_SESSION['user_location']['country_code']);
    }
    
    // 3. Verificar usuário logado
    if (!empty($_SESSION['auth']['user_id'])) {
        global $mysqli;
        if ($mysqli && $mysqli instanceof mysqli) {
            $stmt = $mysqli->prepare("SELECT country_code FROM users WHERE id = ? LIMIT 1");
            if ($stmt) {
                $user_id = (int)$_SESSION['auth']['user_id'];
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    if (!empty($row['country_code'])) {
                        $stmt->close();
                        return strtoupper($row['country_code']);
                    }
                }
                $stmt->close();
            }
        }
    }
    
    // 4. Tentar detectar pelo IP (se disponível)
    if (function_exists('geoip_country_code_by_name') && !empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $country = @geoip_country_code_by_name($ip);
            if ($country) {
                return strtoupper($country);
            }
        }
    }
    
    // 5. Default: Moçambique
    return 'MZ';
}

/**
 * Converter preço de produto automaticamente
 */
function convert_product_price($price_mzn, $user_country = null) {
    $exchange = get_currency_exchange();
    
    if (!$exchange) {
        return [
            'amount' => $price_mzn,
            'currency' => 'MZN',
            'symbol' => 'MT',
            'formatted' => number_format($price_mzn, 2, ',', '.') . ' MT',
            'error' => 'Sistema de câmbio não inicializado'
        ];
    }
    
    if ($user_country === null) {
        $user_country = detect_user_country();
    }
    
    return $exchange->convertProductPrice($price_mzn, $user_country);
}

/**
 * Formatar preço com moeda
 */
function format_currency($amount, $currency = 'MZN') {
    $exchange = get_currency_exchange();
    
    if ($exchange) {
        return $exchange->formatPrice($amount, $currency);
    }
    
    return number_format($amount, 2, ',', '.') . ' ' . $currency;
}

/**
 * Converter valor entre moedas
 */
function currency_convert($amount, $from, $to) {
    $exchange = get_currency_exchange();
    
    if (!$exchange) {
        return null;
    }
    
    return $exchange->convert($amount, $from, $to);
}

/**
 * Obter moeda do país
 */
function get_country_currency($country_code) {
    $exchange = get_currency_exchange();
    
    if ($exchange) {
        return $exchange->getCurrencyByCountry($country_code);
    }
    
    return 'MZN';
}

/**
 * Obter símbolo de moeda
 */
function get_currency_symbol($currency) {
    $exchange = get_currency_exchange();
    
    if ($exchange) {
        return $exchange->getCurrencySymbol($currency);
    }
    
    return $currency;
}

/**
 * Template helper: exibir preço formatado
 * Uso: <?= price($product['preco']) ?>
 */
function price($price, $country = null) {
    $converted = convert_product_price($price, $country);
    return htmlspecialchars($converted['formatted'], ENT_QUOTES, 'UTF-8');
}

/**
 * Template helper: obter valor convertido sem formatação
 */
function price_value($price, $country = null) {
    $converted = convert_product_price($price, $country);
    return $converted['amount'];
}

/**
 * Obter moeda do usuário atual
 */
function user_currency() {
    $country = detect_user_country();
    return get_country_currency($country);
}

/**
 * Formatar preço de produto (com dados do array)
 */
function format_product_price($product, $user_country = null) {
    $price_mzn = $product['preco'] ?? 0;
    $product_currency = $product['currency'] ?? 'MZN';
    
    // Se produto não está em MZN, converter primeiro
    if ($product_currency !== 'MZN') {
        $converted = currency_convert($price_mzn, $product_currency, 'MZN');
        $price_mzn = $converted ?? $price_mzn;
    }
    
    return convert_product_price($price_mzn, $user_country);
}

/**
 * Criar badge de conversão
 */
function get_conversion_badge($original_mzn, $converted) {
    if (!isset($converted['original_mzn']) || $converted['currency'] === 'MZN') {
        return '';
    }
    
    $exchange = get_currency_exchange();
    $original_formatted = $exchange ? 
        $exchange->formatPrice($original_mzn, 'MZN') : 
        number_format($original_mzn, 2);
    
    return sprintf(
        '<span class="price-conversion-info" title="Preço original: %s">≈ %s</span>',
        htmlspecialchars($original_formatted, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($original_formatted, ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Atualizar taxas de câmbio (CRON)
 */
function update_exchange_rates() {
    $exchange = get_currency_exchange();
    
    if (!$exchange) {
        return ['error' => 'Sistema não inicializado'];
    }
    
    return $exchange->updateAllRates();
}

/**
 * Limpar cache antigo
 */
function clean_old_exchange_rates($days = 30) {
    $exchange = get_currency_exchange();
    
    if (!$exchange) {
        return 0;
    }
    
    return $exchange->cleanOldRates($days);
}

/**
 * Verificar se conversão está funcionando
 */
function is_currency_exchange_working() {
    $exchange = get_currency_exchange();
    
    if (!$exchange) {
        return false;
    }
    
    $test_rate = $exchange->getExchangeRate('MZN', 'EUR');
    return $test_rate !== null;
}

/**
 * Obter informações completas de moeda
 */
function get_user_currency_info($country_code = null) {
    if ($country_code === null) {
        $country_code = detect_user_country();
    }
    
    $exchange = get_currency_exchange();
    
    if (!$exchange) {
        return [
            'country' => $country_code,
            'currency' => 'MZN',
            'symbol' => 'MT'
        ];
    }
    
    $currency = $exchange->getCurrencyByCountry($country_code);
    
    return [
        'country' => $country_code,
        'currency' => $currency,
        'symbol' => $exchange->getCurrencySymbol($currency)
    ];
}

/**
 * Debug: exibir informações do sistema
 */
function debug_currency_system() {
    $exchange = get_currency_exchange();
    
    if (!$exchange) {
        echo "❌ Sistema de câmbio não inicializado\n";
        return;
    }
    
    echo "✅ Sistema de câmbio inicializado\n\n";
    
    $test_currencies = ['EUR', 'USD', 'BRL', 'GBP', 'CAD', 'AUD'];
    
    echo "Taxas de câmbio (1 MZN =):\n";
    echo str_repeat("-", 50) . "\n";
    
    foreach ($test_currencies as $currency) {
        $rate = $exchange->getExchangeRate('MZN', $currency);
        if ($rate) {
            printf("  %-5s : %10.6f %s\n", 
                $currency, 
                $rate, 
                $exchange->getCurrencySymbol($currency)
            );
        } else {
            printf("  %-5s : ❌ Não disponível\n", $currency);
        }
    }
    
    echo "\n";
    echo "Exemplo de conversão (1000 MZN):\n";
    echo str_repeat("-", 50) . "\n";
    
    foreach (['PT', 'BR', 'US', 'GB'] as $country) {
        $converted = convert_product_price(1000, $country);
        printf("  %-3s : %s\n", $country, $converted['formatted']);
    }
    
    echo "\n";
    echo "País detectado: " . detect_user_country() . "\n";
    echo "Moeda do usuário: " . user_currency() . "\n";
    echo "Sistema funcionando: " . (is_currency_exchange_working() ? 'Sim ✓' : 'Não ✗') . "\n";
}