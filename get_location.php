<?php
session_start();
header('Content-Type: application/json');

// Se o país já estiver na sessão, devolvemos direto sem gastar API
if (isset($_SESSION['user_country'])) {
    echo json_encode(['country' => $_SESSION['user_country']]);
    exit;
}

function getRealIP() {
    // Verifica se o site está atrás de um Proxy ou Load Balancer (como Cloudflare)
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

$ip = getRealIP();

// API do ip-api.com (Rápida e sem necessidade de Key para baixo volume)
$url = "http://ip-api.com/json/{$ip}?fields=status,country";

// Iniciamos o CURL com tratamento de erros para o caso de queda de internet no servidor
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout curto para não travar o carregamento
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // Tempo limite para estabelecer conexão

// O @ ou o tratamento interno evita que erros de DNS ou rede apareçam para o usuário
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Decodificamos a resposta apenas se houver sucesso na requisição
$data = ($response !== false) ? json_decode($response, true) : null;

if ($data && isset($data['status']) && $data['status'] === 'success') {
    $country = $data['country'];
    $_SESSION['user_country'] = $country; // Salva para a próxima página
    echo json_encode(['country' => $country]);
} else {
    /** * Se falhar (servidor offline ou API fora do ar), 
     * define o padrão "Moçambique" como fallback seguro.
     */
    echo json_encode(['country' => 'Desconhecido']);
}