<?php
/**
 * Middleware de Validação de Dispositivo e Sessão - VisionGreen
 * USO: Páginas de CADASTRO (novos usuários)
 */

require_once __DIR__ . '/../includes/security.php';
if (file_exists(__DIR__ . '/../bootstrap.php')) {
    require_once __DIR__ . '/../bootstrap.php';
}
require_once __DIR__ . '/../includes/device.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/errors.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= 1. EXCEÇÃO TOTAL PARA LOGADOS ================= */
if (isset($_SESSION['auth']['user_id'])) {
    validarFingerprint();
    return; // ✅ Usuário logado, libera acesso
}

/* ================= 2. BLOQUEIO DE FLUXO (APENAS PARA VISITANTES) ================= */
if (empty($_SESSION['cadastro']) || empty($_SESSION['cadastro']['started'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

/* ================= 3. DETECÇÃO E REGRAS DE DISPOSITIVO ================= */
function validarFingerprint() {
    $device = detectDevice();
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

    if (!hash_equals($_SESSION['fingerprint'], $fingerprint)) {
        session_destroy();
        header("Location: ../../registration/login/login.php?error=device_change");
        exit;
    }
}

$device = detectDevice();
$isMobileReal  = in_array($device['os'], ['android', 'ios'], true);
$isDesktopReal = in_array($device['os'], ['windows', 'mac', 'linux'], true);

/* ================= 4. REGRAS DE NEGÓCIO DE CADASTRO ================= */
if (isset($_POST['tipo']) && $_POST['tipo'] === 'business' && $isMobileReal) {
    header('Content-Type: application/json');
    http_response_code(403);
    exit(json_encode(['errors' => ['device' => 'Cadastros empresariais requerem Desktop.']]));
}

if ($isMobileReal) {
    $_SESSION['tipos_permitidos'] = ['pessoal'];
    $_SESSION['tipo_atual'] = 'person';
} elseif ($isDesktopReal) {
    $_SESSION['tipos_permitidos'] = ['business', 'pessoal'];
    $_SESSION['tipo_atual'] = $_SESSION['tipo_atual'] ?? 'company';
} else {
    header("Location: ../../registration/login/login.php?error=unsupported_device");
    exit;
}

/* ================= 5. LOG DE DEPURAÇÃO ================= */
if ((defined('APP_ENV') && APP_ENV === 'dev')) {
    $logPath = __DIR__ . '/../logs/device.log';
    $logData = sprintf("[%s] %s | OS: %s | Role: %s\n", date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'], $device['os'], ($_SESSION['auth']['role'] ?? 'Visitante'));
    @file_put_contents($logPath, $logData, FILE_APPEND);
}
?>