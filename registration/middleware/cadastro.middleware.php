<?php
/**
 * Middleware de Validação de Dispositivo e Sessão
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../bootstrap.php'; // Certifique-se que este arquivo existe
require_once __DIR__ . '/../includes/device.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/errors.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= BLOQUEIO DE FLUXO ================= */
if (empty($_SESSION['cadastro']) || empty($_SESSION['cadastro']['started'])) {
    errorRedirect('flow');
}

/* ================= DETECÇÃO DE DISPOSITIVO ================= */
$device = detectDevice();
$isMobileReal  = in_array($device['os'], ['android', 'ios'], true);
$isDesktopReal = in_array($device['os'], ['windows', 'mac', 'linux'], true);

/* ================= FINGERPRINT DE SESSÃO ================= */
/** * Criamos uma "assinatura" do navegador. 
 * Removido REMOTE_ADDR para evitar quedas em conexões móveis instáveis.
 */
$fingerprint = hash(
    'sha256',
    $device['os'] .
    $device['browser'] .
    ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
    ($_SERVER['HTTP_USER_AGENT'] ?? '')
);

if (!isset($_SESSION['fingerprint'])) {
    // Regenera ID para prevenir Session Fixation no início do cadastro
    session_regenerate_id(true);
    $_SESSION['fingerprint'] = $fingerprint;
}

if (!hash_equals($_SESSION['fingerprint'], $fingerprint)) {
    errorRedirect('device'); 
}

/* ================= REGRAS DE NEGÓCIO ================= */

// 1. Bloqueio de Cadastro Business em Mobile
if (isset($_POST['tipo']) && $_POST['tipo'] === 'business' && $isMobileReal) {
    header('Content-Type: application/json');
    http_response_code(403);
    exit(json_encode(['errors' => ['device' => 'Cadastro Business deve ser feito em um computador.']]));
}

// 2. Definição de Permissões de Sessão
if ($isMobileReal) {
    $_SESSION['tipos_permitidos'] = ['pessoal'];
    $_SESSION['tipo_atual'] = 'pessoal';
} elseif ($isDesktopReal) {
    $_SESSION['tipos_permitidos'] = ['business', 'pessoal'];
    // Mantém a escolha do usuário se já houver uma, caso contrário, padrão business
    $_SESSION['tipo_atual'] = $_SESSION['tipo_atual'] ?? 'business';
} else {
    // Tablets ou OS desconhecidos
    errorRedirect('device');
}

/* ================= LOG DE DEPURAÇÃO (DEV) ================= */
$appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'prod';

if ($appEnv === 'dev') {
    $logPath = __DIR__ . '/../logs/device.log';
    if (!is_dir(dirname($logPath))) {
        mkdir(dirname($logPath), 0777, true);
    }
    
    $logData = sprintf(
        "[%s] %s | OS: %s | Browser: %s\n",
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'],
        $device['os'],
        $device['browser']
    );
    file_put_contents($logPath, $logData, FILE_APPEND);
}