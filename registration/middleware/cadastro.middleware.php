<?php

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/device.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/errors.php';

/* ================= BLOQUEIO DE FLUXO ================= */
if (empty($_SESSION['cadastro']) || empty($_SESSION['cadastro']['started'])) {
    errorRedirect('flow'); // evita acesso direto via URL
}

/* ================= DETECÇÃO DE DISPOSITIVO ================= */
$device = detectDevice();
$isMobileReal  = in_array($device['os'], ['android', 'ios'], true);
$isDesktopReal = in_array($device['os'], ['windows', 'mac', 'linux'], true);

/* ================= BLOQUEIO BUSINESS EM MOBILE ================= */
if (isset($_POST['tipo']) && $_POST['tipo'] === 'business' && $isMobileReal) {
    http_response_code(403);
    exit('Cadastro Business bloqueado em dispositivo móvel.');
}

/* ================= FINGERPRINT DE SESSÃO ================= */
$fingerprint = hash(
    'sha256',
    $device['os'] .
    $device['browser'] .
    ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
    ($_SERVER['HTTP_USER_AGENT'] ?? '') .
    ($_SERVER['REMOTE_ADDR'] ?? '')
);

if (!isset($_SESSION['fingerprint'])) {
    session_regenerate_id(true);
    $_SESSION['fingerprint'] = $fingerprint;
}

if (!hash_equals($_SESSION['fingerprint'], $fingerprint)) {
    errorRedirect('device'); // sessão adulterada
}

/* ================= REGRAS DE DISPOSITIVO ================= */
if ($isMobileReal) {
    $_SESSION['tipos_permitidos'] = ['pessoal'];
    $_SESSION['tipo_atual'] = 'pessoal';
} elseif ($isDesktopReal) {
    $_SESSION['tipos_permitidos'] = ['business', 'pessoal'];
    $_SESSION['tipo_atual'] = $_SESSION['tipo_atual'] ?? 'business';
} else {
    errorRedirect('device');
}

/* ================= DEBUG (DEV ONLY) ================= */
if (($_ENV['APP_ENV'] ?? 'prod') === 'dev') {
    file_put_contents(
        __DIR__ . '/../logs/device.log',
        date('Y-m-d H:i:s') . PHP_EOL .
        json_encode($device, JSON_PRETTY_PRINT) . PHP_EOL .
        str_repeat('-', 40) . PHP_EOL,
        FILE_APPEND
    );
}
