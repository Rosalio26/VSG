<?php
/**
 * Sistema de Câmbio VSG Marketplace - VERSÃO OTIMIZADA
 * 
 * Busca taxas de câmbio diretamente das APIs em tempo real
 * Com cache inteligente e fallback entre múltiplas APIs
 * 
 * @author VSG Development Team
 * @version 3.0 - API Direta
 */

class CurrencyExchange {
    
    private $mysqli;
    private $cache_duration = 3600; // 1 hora de cache
    
    // Moeda base do sistema (Moçambique)
    private $base_currency = 'MZN';
    
    // APIs disponíveis (ordem de prioridade)
    private $apis = [
        [
            'name' => 'exchangerate-api',
            'url' => 'https://api.exchangerate-api.com/v4/latest/{currency}',
            'requires_key' => false,
            'rate_path' => 'rates'
        ],
        [
            'name' => 'frankfurter',
            'url' => 'https://api.frankfurter.app/latest?from={currency}',
            'requires_key' => false,
            'rate_path' => 'rates'
        ],
        [
            'name' => 'exchangeratesapi',
            'url' => 'https://api.exchangeratesapi.io/latest?base={currency}',
            'requires_key' => false,
            'rate_path' => 'rates'
        ]
    ];
    
    // Mapeamento de países para moedas
    private $country_currencies = [
        'MZ' => 'MZN',  'PT' => 'EUR',  'BR' => 'BRL',  'AO' => 'AOA',
        'ZA' => 'ZAR',  'US' => 'USD',  'GB' => 'GBP',  'CN' => 'CNY',
        'IN' => 'INR',  'ZW' => 'USD',  'TZ' => 'TZS',  'KE' => 'KES',
        'MW' => 'MWK',  'ES' => 'EUR',  'FR' => 'EUR',  'DE' => 'EUR',
        'IT' => 'EUR',  'CA' => 'CAD',  'AU' => 'AUD',  'JP' => 'JPY',
        'MX' => 'MXN',  'AR' => 'ARS',  'CL' => 'CLP',  'CO' => 'COP',
        'PE' => 'PEN',  'VE' => 'VES',  'RU' => 'RUB',  'TR' => 'TRY',
        'SA' => 'SAR',  'AE' => 'AED',  'EG' => 'EGP',  'NG' => 'NGN',
        'GH' => 'GHS',  'KR' => 'KRW',  'TH' => 'THB',  'VN' => 'VND',
        'ID' => 'IDR',  'MY' => 'MYR',  'SG' => 'SGD',  'PH' => 'PHP',
        'NZ' => 'NZD',  'CH' => 'CHF',  'SE' => 'SEK',  'NO' => 'NOK',
        'DK' => 'DKK',  'PL' => 'PLN',  'CZ' => 'CZK',  'HU' => 'HUF'
    ];
    
    // Símbolos de moedas
    private $currency_symbols = [
        'MZN' => 'MT',   'EUR' => '€',    'BRL' => 'R$',   'USD' => '$',
        'GBP' => '£',    'AOA' => 'Kz',   'ZAR' => 'R',    'CNY' => '¥',
        'INR' => '₹',    'TZS' => 'TSh',  'KES' => 'KSh',  'MWK' => 'MK',
        'CAD' => 'CA$',  'AUD' => 'A$',   'JPY' => '¥',    'MXN' => 'MX$',
        'ARS' => 'AR$',  'CLP' => 'CL$',  'COP' => 'CO$',  'PEN' => 'S/',
        'VES' => 'Bs',   'RUB' => '₽',    'TRY' => '₺',    'SAR' => '﷼',
        'AED' => 'د.إ',  'EGP' => '£',    'NGN' => '₦',    'GHS' => '₵',
        'KRW' => '₩',    'THB' => '฿',    'VND' => '₫',    'IDR' => 'Rp',
        'MYR' => 'RM',   'SGD' => 'S$',   'PHP' => '₱',    'NZD' => 'NZ$',
        'CHF' => 'Fr',   'SEK' => 'kr',   'NOK' => 'kr',   'DKK' => 'kr',
        'PLN' => 'zł',   'CZK' => 'Kč',   'HUF' => 'Ft'
    ];
    
    /**
     * Construtor
     */
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->createExchangeRatesTable();
    }
    
    /**
     * Criar tabela de taxas de câmbio
     */
    private function createExchangeRatesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS exchange_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_currency VARCHAR(3) NOT NULL,
            to_currency VARCHAR(3) NOT NULL,
            rate DECIMAL(18, 8) NOT NULL,
            last_updated DATETIME NOT NULL,
            source VARCHAR(50) DEFAULT 'api',
            INDEX idx_currencies (from_currency, to_currency),
            INDEX idx_updated (last_updated),
            UNIQUE KEY unique_pair (from_currency, to_currency)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$this->mysqli->query($sql)) {
            error_log("Erro ao criar tabela exchange_rates: " . $this->mysqli->error);
        }
    }
    
    /**
     * Obter moeda do país
     */
    public function getCurrencyByCountry($country_code) {
        $country_code = strtoupper(trim($country_code));
        return $this->country_currencies[$country_code] ?? $this->base_currency;
    }
    
    /**
     * Obter símbolo da moeda
     */
    public function getCurrencySymbol($currency_code) {
        return $this->currency_symbols[$currency_code] ?? $currency_code;
    }
    
    /**
     * Buscar taxa de câmbio com cache inteligente
     */
    public function getExchangeRate($from, $to) {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));
        
        if ($from === $to) {
            return 1.0;
        }
        
        // 1. Verificar cache recente
        $cached_rate = $this->getCachedRate($from, $to);
        if ($cached_rate !== null) {
            return $cached_rate;
        }
        
        // 2. Buscar da API em tempo real
        $rate = $this->fetchRateFromAPIs($from, $to);
        
        if ($rate !== null) {
            $this->saveRate($from, $to, $rate);
            return $rate;
        }
        
        // 3. Tentar taxa inversa do cache
        $inverse_rate = $this->getCachedRate($to, $from);
        if ($inverse_rate !== null && $inverse_rate > 0) {
            $rate = 1 / $inverse_rate;
            $this->saveRate($from, $to, $rate);
            return $rate;
        }
        
        // 4. Tentar conversão através de USD
        $from_to_usd = $this->fetchRateFromAPIs($from, 'USD');
        $usd_to_target = $this->fetchRateFromAPIs('USD', $to);
        
        if ($from_to_usd !== null && $usd_to_target !== null) {
            $rate = $from_to_usd * $usd_to_target;
            $this->saveRate($from, $to, $rate);
            return $rate;
        }
        
        error_log("Não foi possível obter taxa de câmbio de {$from} para {$to}");
        return null;
    }
    
    /**
     * Obter taxa do cache
     */
    private function getCachedRate($from, $to) {
        $stmt = $this->mysqli->prepare("
            SELECT rate 
            FROM exchange_rates 
            WHERE from_currency = ? 
            AND to_currency = ? 
            AND last_updated > DATE_SUB(NOW(), INTERVAL ? SECOND)
            LIMIT 1
        ");
        
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param("ssi", $from, $to, $this->cache_duration);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return (float)$row['rate'];
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Buscar taxa de múltiplas APIs (com fallback)
     */
    private function fetchRateFromAPIs($from, $to) {
        foreach ($this->apis as $api) {
            $rate = $this->fetchFromAPI($api, $from, $to);
            if ($rate !== null) {
                return $rate;
            }
            // Pequena pausa entre tentativas
            usleep(100000); // 0.1 segundo
        }
        
        return null;
    }
    
    /**
     * Buscar de uma API específica
     */
    private function fetchFromAPI($api, $from, $to) {
        try {
            $url = str_replace('{currency}', $from, $api['url']);
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'VSG-Marketplace/3.0',
                    'ignore_errors' => true
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data[$api['rate_path']])) {
                return null;
            }
            
            $rates = $data[$api['rate_path']];
            
            if (isset($rates[$to])) {
                return (float)$rates[$to];
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar de {$api['name']}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Salvar taxa no cache
     */
    private function saveRate($from, $to, $rate) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO exchange_rates (from_currency, to_currency, rate, last_updated)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                rate = VALUES(rate),
                last_updated = NOW()
        ");
        
        if (!$stmt) {
            return;
        }
        
        $stmt->bind_param("ssd", $from, $to, $rate);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Converter valor entre moedas
     */
    public function convert($amount, $from, $to) {
        $rate = $this->getExchangeRate($from, $to);
        
        if ($rate === null) {
            return null;
        }
        
        return $amount * $rate;
    }
    
    /**
     * Formatar preço com símbolo
     */
    public function formatPrice($amount, $currency, $decimals = 2) {
        $symbol = $this->getCurrencySymbol($currency);
        $formatted = number_format($amount, $decimals, ',', '.');
        
        // Moedas que vão antes do valor
        $prefix_currencies = ['USD', 'GBP', 'EUR', 'CAD', 'AUD', 'CHF'];
        
        if (in_array($currency, $prefix_currencies)) {
            return "{$symbol} {$formatted}";
        }
        
        return "{$formatted} {$symbol}";
    }
    
    /**
     * Converter preço de produto
     */
    public function convertProductPrice($price_mzn, $user_country_code) {
        $target_currency = $this->getCurrencyByCountry($user_country_code);
        
        if ($target_currency === 'MZN') {
            return [
                'amount' => $price_mzn,
                'currency' => 'MZN',
                'symbol' => $this->getCurrencySymbol('MZN'),
                'formatted' => $this->formatPrice($price_mzn, 'MZN')
            ];
        }
        
        $converted_amount = $this->convert($price_mzn, 'MZN', $target_currency);
        
        if ($converted_amount === null) {
            return [
                'amount' => $price_mzn,
                'currency' => 'MZN',
                'symbol' => $this->getCurrencySymbol('MZN'),
                'formatted' => $this->formatPrice($price_mzn, 'MZN'),
                'conversion_failed' => true
            ];
        }
        
        return [
            'amount' => $converted_amount,
            'currency' => $target_currency,
            'symbol' => $this->getCurrencySymbol($target_currency),
            'formatted' => $this->formatPrice($converted_amount, $target_currency),
            'original_mzn' => $price_mzn
        ];
    }
    
    /**
     * Atualizar todas as taxas principais
     */
    public function updateAllRates() {
        $updated = 0;
        $failed = 0;
        $currencies = array_unique(array_values($this->country_currencies));
        
        foreach ($currencies as $currency) {
            if ($currency === $this->base_currency) continue;
            
            $rate = $this->fetchRateFromAPIs($this->base_currency, $currency);
            
            if ($rate !== null) {
                $this->saveRate($this->base_currency, $currency, $rate);
                $updated++;
            } else {
                $failed++;
            }
            
            usleep(200000); // 0.2 segundo entre requisições
        }
        
        return [
            'updated' => $updated,
            'failed' => $failed,
            'total' => count($currencies) - 1
        ];
    }
    
    /**
     * Obter todas as taxas disponíveis (para JSON)
     */
    public function getAllRates() {
        $query = "
            SELECT from_currency, to_currency, rate, last_updated
            FROM exchange_rates
            WHERE last_updated > DATE_SUB(NOW(), INTERVAL {$this->cache_duration} SECOND)
            ORDER BY from_currency, to_currency
        ";
        
        $result = $this->mysqli->query($query);
        
        if (!$result) {
            return [];
        }
        
        $rates = [];
        while ($row = $result->fetch_assoc()) {
            $key = "{$row['from_currency']}_{$row['to_currency']}";
            $rates[$key] = (float)$row['rate'];
        }
        
        $result->free();
        return $rates;
    }
    
    /**
     * Limpar taxas antigas
     */
    public function cleanOldRates($days = 30) {
        $stmt = $this->mysqli->prepare("
            DELETE FROM exchange_rates 
            WHERE last_updated < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        if (!$stmt) {
            return 0;
        }
        
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected;
    }
}