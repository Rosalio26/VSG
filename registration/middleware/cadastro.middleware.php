<?php

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/device.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/errors.php';

/* ================= DETECÇÃO DE DISPOSITIVO ================= */

$device = detectDevice();

$isMobileReal  = in_array($device['os'], ['android', 'ios'], true);
$isDesktopReal = in_array($device['os'], ['windows', 'mac', 'linux'], true);

/* ================= FINGERPRINT DE SESSÃO ================= */

$fingerprint = hash(
    'sha256',
    $device['os'] .
    $device['browser'] .
    ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
    ($_SERVER['HTTP_USER_AGENT'] ?? '')
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

/**
 * ⚠️ IMPORTANTE
 * Este middleware:
 * - NÃO valida CSRF
 * - NÃO valida tipo POST
 * - NÃO valida fluxo
 *
 * Essas validações acontecem APENAS nos processadores
 */

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
