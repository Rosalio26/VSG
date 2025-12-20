<?php

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/device.php';
require_once __DIR__ . '/../includes/rate_limit.php';

$device = detectDevice();

/* ===== DETECÇÃO ===== */
$isMobileReal = $device['os'] === 'android' || $device['os'] === 'ios';
$isDesktopReal = in_array($device['os'], ['windows', 'mac', 'linux'], true);

/* ===== FINGERPRINT ===== */
$fingerprint = hash(
    'sha256',
    $device['os'] .
    $device['browser'] .
    ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
    ($_SERVER['HTTP_USER_AGENT'] ?? '')
);

if (!isset($_SESSION['fingerprint'])) {
    $_SESSION['fingerprint'] = $fingerprint;
}

if ($_SESSION['fingerprint'] !== $fingerprint) {
    http_response_code(403);
    exit('Sessão inválida.');
}

/* ===== REGRAS DE NEGÓCIO ===== */
if ($isMobileReal) {
    $_SESSION['tipos_permitidos'] = ['pessoal'];
    $_SESSION['tipo_atual'] = 'pessoal';
} elseif ($isDesktopReal) {
    $_SESSION['tipos_permitidos'] = ['business', 'pessoal'];
    $_SESSION['tipo_atual'] = $_SESSION['tipo_atual'] ?? 'business';
} else {
    http_response_code(403);
    exit('Dispositivo não suportado.');
}

/* ===== BLOQUEIO POST ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tipo = $_POST['tipo'] ?? 'geral';
    $tipo = in_array($tipo, ['pessoal', 'business'], true) ? $tipo : 'geral';

    rateLimit('cadastro_' . $tipo, 5, 60);

    if (!in_array($tipo, $_SESSION['tipos_permitidos'], true)) {
        http_response_code(403);
        exit('Tipo de cadastro não permitido neste dispositivo.');
    }
}

/* ===== DEBUG DEV ===== */
if (($_ENV['APP_ENV'] ?? 'prod') === 'dev') {
    file_put_contents(
        __DIR__ . '/../logs/device.log',
        date('Y-m-d H:i:s') . PHP_EOL .
        json_encode($device, JSON_PRETTY_PRINT) .
        PHP_EOL . str_repeat('-', 40) . PHP_EOL,
        FILE_APPEND
    );
}
