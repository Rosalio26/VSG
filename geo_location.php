<?php
/**
 * geo_location.php — VSG Marketplace
 *
 * FIX PERFORMANCE: Versão anterior fazia 2-3 chamadas HTTP externas
 * (ipify.org + ipinfo.io + ip-api.com) a cada request quando IP = localhost,
 * causando 5-30s de atraso. Esta versão:
 *  1. Detecta localhost e usa país padrão (MZ) sem chamadas externas
 *  2. Respeita o cache de sessão (1h) correctamente
 *  3. Timeout reduzido de 5s para 2s
 *  4. Uma única chamada HTTP (ip-api.com) com fallback silencioso
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Já temos localização válida em sessão? ─────────────────────────────
// Cache de 1 hora — evita qualquer chamada HTTP
if (
    isset($_SESSION['user_location'], $_SESSION['location_timestamp']) &&
    (time() - $_SESSION['location_timestamp']) < 3600 &&
    !empty($_SESSION['user_location']['source'])
) {
    // Disponibiliza variáveis globais e sai
    _geo_set_globals($_SESSION['user_location']);
    return; // ← sai do include sem executar o resto
}

// ── Detecta IP do cliente ──────────────────────────────────────────────
function _geo_get_ip(): string {
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '127.0.0.1';
}

// ── É localhost / IP privado? ──────────────────────────────────────────
function _geo_is_local(string $ip): bool {
    return in_array($ip, ['::1', '127.0.0.1'], true)
        || str_starts_with($ip, '192.168.')
        || str_starts_with($ip, '10.')
        || str_starts_with($ip, '172.');
}

// ── Chamada HTTP leve (curl ou stream, timeout 2s) ─────────────────────
function _geo_http(string $url): ?array {
    $response = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,      // ← era 5s, agora 2s
            CURLOPT_CONNECTTIMEOUT => 1,      // ← era 5s, agora 1s
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $ok       = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        if (!$ok) $response = false;
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $response = @file_get_contents($url, false, $ctx);
    }

    if (!$response) return null;
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

// ── Resolve localização ────────────────────────────────────────────────
$ip       = _geo_get_ip();
$location = null;

if (_geo_is_local($ip)) {
    // Localhost: não faz chamadas externas — usa padrão MZ directamente
    $location = [
        'ip'           => $ip,
        'country'      => 'Mozambique',
        'country_code' => 'MZ',
        'region'       => '',
        'city'         => '',
        'latitude'     => -18.665695,
        'longitude'    => 35.529562,
        'timezone'     => 'Africa/Maputo',
        'source'       => 'localhost-default',
    ];
} else {
    // IP real: uma única chamada a ip-api.com
    $data = _geo_http("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,regionName,city,lat,lon,timezone,query");

    if ($data && ($data['status'] ?? '') === 'success') {
        $location = [
            'ip'           => $data['query']      ?? $ip,
            'country'      => $data['country']    ?? '',
            'country_code' => $data['countryCode'] ?? '',
            'region'       => $data['regionName'] ?? '',
            'city'         => $data['city']        ?? '',
            'latitude'     => $data['lat']         ?? 0,
            'longitude'    => $data['lon']         ?? 0,
            'timezone'     => $data['timezone']    ?? '',
            'source'       => 'ip-api.com',
        ];
    }
}

// Fallback se tudo falhar
if (!$location) {
    $location = [
        'ip'           => $ip,
        'country'      => 'Mozambique',
        'country_code' => 'MZ',
        'region'       => '', 'city'      => '',
        'latitude'     => 0,  'longitude' => 0,
        'timezone'     => 'Africa/Maputo',
        'source'       => 'fallback',
    ];
}

// Guarda em sessão (1 hora de cache)
$_SESSION['user_location']      = $location;
$_SESSION['location_timestamp'] = time();

_geo_set_globals($location);

// ── Variáveis globais para templates ──────────────────────────────────
function _geo_set_globals(array $loc): void {
    global $user_location, $user_city, $user_country, $user_region, $user_full_location, $location_json;
    $user_location    = $loc;
    $user_city        = $loc['city']    ?? '';
    $user_country     = $loc['country'] ?? '';
    $user_region      = $loc['region']  ?? '';
    $user_full_location = $user_city
        ? "$user_city, $user_country"
        : ($user_country ?: 'Selecionar localização');
    $location_json = json_encode($loc, JSON_UNESCAPED_UNICODE);
}
?>
<script>
const userLocation = <?= $location_json ?? '{}' ?>;
document.addEventListener('DOMContentLoaded', function () {
    const el = document.querySelector('.location-now');
    if (el) el.textContent = <?= json_encode($user_full_location ?? '') ?>;
});
</script>
<style>
.location-now { font-weight:600; color:#2ecc71; }
.top-link:hover .location-now { text-decoration:underline; }
</style>