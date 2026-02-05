<?php
/**
 * Currency Exchange Bootstrap (Versão 3.0)
 * 
 * Inicialização automática do sistema de câmbio
 * Com atualização inteligente de taxas em background
 * 
 * Usage: require_once __DIR__ . '/currency_bootstrap.php';
 */

// Prevenir múltiplas inicializações
if (defined('CURRENCY_EXCHANGE_LOADED')) {
    return;
}
define('CURRENCY_EXCHANGE_LOADED', true);

// Carregar dependências
require_once __DIR__ . '/CurrencyExchange.php';
require_once __DIR__ . '/currency_helpers.php';

// Inicializar sistema
try {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        init_currency_exchange($mysqli);
        
        // Verificar saúde do sistema
        if (!is_currency_exchange_working()) {
            error_log("AVISO: Sistema de câmbio pode não estar funcionando corretamente");
        }
    } else {
        error_log("AVISO: Sistema de câmbio não inicializado - variável \$mysqli não encontrada");
    }
} catch (Exception $e) {
    error_log("Erro ao inicializar sistema de câmbio: " . $e->getMessage());
}

// Constantes
if (!defined('DEFAULT_CURRENCY')) {
    define('DEFAULT_CURRENCY', 'MZN');
}

if (!defined('DEFAULT_COUNTRY')) {
    define('DEFAULT_COUNTRY', 'MZ');
}

/**
 * Atualização automática de taxas em background
 */
if (function_exists('register_shutdown_function') && !defined('CURRENCY_AUTO_UPDATE_REGISTERED')) {
    define('CURRENCY_AUTO_UPDATE_REGISTERED', true);
    
    $last_update_file = sys_get_temp_dir() . '/vsg_currency_last_update.txt';
    $update_interval = 3600; // 1 hora
    
    // Verificar se precisa atualizar
    $should_update = !file_exists($last_update_file) || 
                     (time() - filemtime($last_update_file)) > $update_interval;
    
    if ($should_update) {
        register_shutdown_function(function() use ($last_update_file) {
            // Finalizar resposta ao cliente
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            
            try {
                // Atualizar taxas
                $result = update_exchange_rates();
                
                if ($result && !isset($result['error'])) {
                    // Salvar timestamp
                    file_put_contents($last_update_file, time());
                    
                    error_log(sprintf(
                        "Taxas de câmbio atualizadas: %d sucesso, %d falhas de %d total",
                        $result['updated'],
                        $result['failed'],
                        $result['total']
                    ));
                }
            } catch (Exception $e) {
                error_log("Erro ao atualizar taxas em background: " . $e->getMessage());
            }
        });
    }
}

/**
 * Limpeza automática de cache antigo (1x por dia)
 */
if (function_exists('register_shutdown_function') && !defined('CURRENCY_AUTO_CLEAN_REGISTERED')) {
    define('CURRENCY_AUTO_CLEAN_REGISTERED', true);
    
    $last_clean_file = sys_get_temp_dir() . '/vsg_currency_last_clean.txt';
    $clean_interval = 86400; // 24 horas
    
    if (!file_exists($last_clean_file) || (time() - filemtime($last_clean_file)) > $clean_interval) {
        register_shutdown_function(function() use ($last_clean_file) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            
            try {
                $deleted = clean_old_exchange_rates(30);
                
                if ($deleted > 0) {
                    error_log("Limpeza de cache: {$deleted} registros antigos removidos");
                }
                
                file_put_contents($last_clean_file, time());
            } catch (Exception $e) {
                error_log("Erro na limpeza de cache: " . $e->getMessage());
            }
        });
    }
}

/**
 * Middleware para detecção de moeda preferida
 */
if (!function_exists('detect_preferred_currency')) {
    function detect_preferred_currency() {
        // 1. Cookie explícito
        if (isset($_COOKIE['vsg_currency'])) {
            return strtoupper($_COOKIE['vsg_currency']);
        }
        
        // 2. País do usuário
        $country = detect_user_country();
        return get_country_currency($country);
    }
}

/**
 * Helper para setar moeda preferida
 */
if (!function_exists('set_preferred_currency')) {
    function set_preferred_currency($currency) {
        $currency = strtoupper($currency);
        
        // Validar moeda
        $exchange = get_currency_exchange();
        if ($exchange) {
            $symbol = $exchange->getCurrencySymbol($currency);
            if ($symbol !== $currency || in_array($currency, ['MZN', 'EUR', 'USD', 'BRL', 'GBP'])) {
                // Salvar cookie
                $expires = time() + (30 * 24 * 60 * 60); // 30 dias
                setcookie('vsg_currency', $currency, $expires, '/');
                
                // Salvar na sessão
                $_SESSION['preferred_currency'] = $currency;
                
                return true;
            }
        }
        
        return false;
    }
}