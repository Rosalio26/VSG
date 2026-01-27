<?php
/**
 * Middleware de Validação de Dispositivo e Sessão - VisionGreen
 * USO: Páginas de CADASTRO (novos usuários)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/security.php';
if (file_exists(__DIR__ . '/../bootstrap.php')) {
    require_once __DIR__ . '/../bootstrap.php';
}
require_once __DIR__ . '/../includes/device.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/errors.php';

/* ================= 1. EXCEÇÃO TOTAL PARA USUÁRIOS LOGADOS ================= */
if (isset($_SESSION['auth']['user_id'])) {
    // Usuário já está logado, não precisa de cadastro
    // Redireciona para o dashboard apropriado
    $userType = $_SESSION['auth']['type'] ?? 'person';
    $userRole = $_SESSION['auth']['role'] ?? 'user';
    
    if (in_array($userRole, ['admin', 'superadmin'])) {
        header("Location: ../../pages/admin/dashboard_admin.php");
    } elseif ($userType === 'company') {
        header("Location: ../../pages/business/dashboard_business.php");
    } else {
        header("Location: ../../pages/person/dashboard_person.php");
    }
    exit;
}

/* ================= 2. INICIALIZAÇÃO DA SESSÃO DE CADASTRO ================= */
// Se não existe sessão de cadastro, cria uma nova
if (!isset($_SESSION['cadastro']) || !isset($_SESSION['cadastro']['started'])) {
    $_SESSION['cadastro'] = [
        'started' => true,
        'timestamp' => time()
    ];
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

// Valida fingerprint para segurança
validarFingerprint();

$device = detectDevice();
$isMobileReal  = in_array($device['os'], ['android', 'ios'], true);
$isDesktopReal = in_array($device['os'], ['windows', 'mac', 'linux'], true);

/* ================= 4. REGRAS DE NEGÓCIO DE CADASTRO ================= */
// Bloqueia cadastro business no mobile (apenas via POST)
if (isset($_POST['tipo']) && $_POST['tipo'] === 'company' && $isMobileReal) {
    header('Content-Type: application/json');
    http_response_code(403);
    exit(json_encode(['errors' => ['device' => 'Cadastros empresariais requerem Desktop.']]));
}

// Define tipos permitidos baseado no dispositivo
if ($isMobileReal) {
    $_SESSION['tipos_permitidos'] = ['pessoal'];
    if (!isset($_SESSION['tipo_atual'])) {
        $_SESSION['tipo_atual'] = 'pessoal';
    }
} elseif ($isDesktopReal) {
    $_SESSION['tipos_permitidos'] = ['business', 'pessoal'];
    if (!isset($_SESSION['tipo_atual'])) {
        $_SESSION['tipo_atual'] = 'business';
    }
} else {
    // Dispositivo não suportado
    header("Location: ../../registration/login/login.php?error=unsupported_device");
    exit;
}

/* ================= 5. TIMEOUT DE SESSÃO DE CADASTRO (30 MINUTOS) ================= */
$timeout_cadastro = 1800; // 30 minutos
if (isset($_SESSION['cadastro']['timestamp'])) {
    $elapsed = time() - $_SESSION['cadastro']['timestamp'];
    if ($elapsed > $timeout_cadastro) {
        // Sessão de cadastro expirou
        unset($_SESSION['cadastro']);
        unset($_SESSION['tipo_atual']);
        unset($_SESSION['tipos_permitidos']);
        header("Location: ../../index.php?info=session_expired");
        exit;
    }
}

// Atualiza timestamp da última atividade
$_SESSION['cadastro']['timestamp'] = time();

/* ================= 6. LOG DE DEPURAÇÃO ================= */
if ((defined('APP_ENV') && APP_ENV === 'dev')) {
    $logPath = __DIR__ . '/../logs/device.log';
    $logDir = dirname($logPath);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logData = sprintf(
        "[%s] IP: %s | OS: %s | Browser: %s | Tipos: %s | Tipo Atual: %s\n", 
        date('Y-m-d H:i:s'), 
        $_SERVER['REMOTE_ADDR'], 
        $device['os'], 
        $device['browser'],
        implode(',', $_SESSION['tipos_permitidos'] ?? []),
        $_SESSION['tipo_atual'] ?? 'none'
    );
    @file_put_contents($logPath, $logData, FILE_APPEND);
}
?>