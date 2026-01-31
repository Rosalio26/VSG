<?php
/**
 * geo_location.php
 * Arquivo para incluir no topo do seu index.php ou header
 * Detecta a localização do usuário e disponibiliza as variáveis
 */

// Inicia sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Classe de Geolocalização
 */
class GeoLocator {
    
    public $ip;
    
    public function __construct($ip = null) {
        $this->ip = $ip ?? $this->getClientIP();
    }
    
    private function getClientIP() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        $ip = trim($ip);
        
        // Se for localhost, busca o IP público real
        if ($ip === '::1' || $ip === '127.0.0.1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
            $publicIP = $this->getPublicIP();
            if ($publicIP) {
                return $publicIP;
            }
        }
        
        return $ip;
    }
    
    private function getPublicIP() {
        $services = [
            'https://api.ipify.org?format=json',
            'https://ipinfo.io/json',
        ];
        
        foreach ($services as $service) {
            try {
                $response = $this->makeRequest($service);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['ip'])) {
                        return $data['ip'];
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        return null;
    }
    
    private function makeRequest($url) {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return ($httpCode == 200) ? $response : false;
        } else {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'ignore_errors' => true
                ]
            ]);
            return @file_get_contents($url, false, $context);
        }
    }
    
    public function getLocationFromIPAPI() {
        $ip = ($this->ip === '127.0.0.1' || $this->ip === '::1') ? '' : $this->ip;
        $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,region,regionName,city,lat,lon,timezone";
        
        $response = $this->makeRequest($url);
        
        if ($response === false) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['status']) && $data['status'] === 'success') {
            return [
                'ip' => $data['query'] ?? $this->ip,
                'country' => $data['country'] ?? '',
                'country_code' => $data['countryCode'] ?? '',
                'region' => $data['regionName'] ?? '',
                'city' => $data['city'] ?? '',
                'latitude' => $data['lat'] ?? 0,
                'longitude' => $data['lon'] ?? 0,
                'timezone' => $data['timezone'] ?? '',
                'source' => 'IP-API.com'
            ];
        }
        
        return null;
    }
    
    public function getLocationFromIPInfo($token = null) {
        $ip = ($this->ip === '127.0.0.1' || $this->ip === '::1') ? '' : $this->ip;
        $url = $token 
            ? "https://ipinfo.io/{$ip}?token={$token}"
            : "https://ipinfo.io/{$ip}/json";
        
        $response = $this->makeRequest($url);
        
        if ($response === false) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['city'])) {
            $coords = explode(',', $data['loc'] ?? '0,0');
            return [
                'ip' => $data['ip'] ?? $this->ip,
                'country' => $data['country'] ?? '',
                'country_code' => $data['country'] ?? '',
                'region' => $data['region'] ?? '',
                'city' => $data['city'] ?? '',
                'latitude' => $coords[0] ?? 0,
                'longitude' => $coords[1] ?? 0,
                'timezone' => $data['timezone'] ?? '',
                'source' => 'IPInfo.io'
            ];
        }
        
        return null;
    }
    
    public function getLocation($config = []) {
        // Tenta IP-API primeiro
        $location = $this->getLocationFromIPAPI();
        if ($location) return $location;
        
        // Tenta IPInfo
        $location = $this->getLocationFromIPInfo($config['ipinfo_token'] ?? null);
        if ($location) return $location;
        
        return null;
    }
}

// ============================================
// DETECTA E ARMAZENA LOCALIZAÇÃO NA SESSÃO
// ============================================

// Verifica se já existe localização na sessão e se ainda é válida (cache de 1 hora)
if (!isset($_SESSION['user_location']) || !isset($_SESSION['location_timestamp']) || 
    (time() - $_SESSION['location_timestamp']) > 3600) {
    
    $locator = new GeoLocator();
    $location = $locator->getLocation();
    
    if ($location && !empty($location['country'])) {
        $_SESSION['user_location'] = $location;
        $_SESSION['location_timestamp'] = time();
    } else {
        // Localização padrão VAZIA (Melhor para UX do que "Desconhecido")
        $_SESSION['user_location'] = [
            'ip' => $locator->ip,
            'country' => '', 
            'country_code' => '',
            'region' => '',
            'city' => '', 
            'latitude' => 0,
            'longitude' => 0,
            'timezone' => '',
            'source' => 'Default'
        ];
        $_SESSION['location_timestamp'] = time();
    }
}

// Disponibiliza variáveis globais para uso no template
$user_location = $_SESSION['user_location'];
$user_city = $user_location['city'];
$user_country = $user_location['country'];
$user_region = $user_location['region'];

// Lógica de exibição amigável
if (!empty($user_country)) {
    $user_full_location = (!empty($user_city)) ? $user_city . ', ' . $user_country : $user_country;
} else {
    $user_full_location = 'Selecionar localização';
}

// Para uso em JavaScript
$location_json = json_encode($user_location);
?>

<script>
// Dados de localização do usuário
const userLocation = <?= $location_json ?>;

// Atualiza o texto de localização no header quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    const locationElement = document.querySelector('.location-now');
    if (locationElement) {
        const fullLocation = "<?= htmlspecialchars($user_full_location) ?>";
        locationElement.textContent = fullLocation;
    }
});

// Função para abrir modal de seleção de localização (opcional)
function changeLocation() {
    // Aqui você pode adicionar um modal para o usuário escolher outra cidade
    alert('Recurso de alteração de localização em desenvolvimento');
}
</script>

<style>
/* Estilos adicionais para o indicador de localização */
.location-now {
    font-weight: 600;
    color: #2ecc71;
}

.top-link:hover .location-now {
    text-decoration: underline;
}
</style>