<?php
/**
 * Middleware de Validação de Dispositivo e Sessão - VisionGreen
 * USO: Páginas de CADASTRO (novos usuários)
 */

// Carrega o bootstrap (que já inicia sessão e CSRF)
if (file_exists(__DIR__ . '/../bootstrap.php')) {
    require_once __DIR__ . '/../bootstrap.php';
}

// Carrega security.php (que tem funções como cleanInput, csrf_*, etc)
if (file_exists(__DIR__ . '/../includes/security.php')) {
    require_once __DIR__ . '/../includes/security.php';
}

/* ================= 1. EXCEÇÃO PARA USUÁRIOS JÁ LOGADOS ================= */
if (isset($_SESSION['auth']['user_id'])) {
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
if (!isset($_SESSION['cadastro']) || !isset($_SESSION['cadastro']['started'])) {
    $_SESSION['cadastro'] = [
        'started' => true,
        'timestamp' => time()
    ];
}

/* ================= 3. VALIDAÇÃO DE FINGERPRINT (Segurança) ================= */
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

validarFingerprint();

/* ================= 4. DETECÇÃO DE DISPOSITIVO E REGRAS ================= */
$tiposPermitidos = getTiposPermitidos();

// Define tipo atual baseado no dispositivo
if (!isset($_SESSION['tipo_atual'])) {
    $_SESSION['tipo_atual'] = in_array('business', $tiposPermitidos) ? 'business' : 'pessoal';
}

// Armazena na sessão
$_SESSION['tipos_permitidos'] = $tiposPermitidos;

/* ================= 5. BLOQUEIO DE CADASTRO BUSINESS VIA POST (Mobile) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tipo']) && $_POST['tipo'] === 'company') {
        if (!isBusinessDeviceAllowed()) {
            header('Content-Type: application/json');
            http_response_code(403);
            exit(json_encode([
                'errors' => [
                    'device' => 'Cadastros empresariais requerem Desktop com largura mínima de 1080px.'
                ]
            ]));
        }
    }
}

/* ================= 6. TIMEOUT DE SESSÃO DE CADASTRO (30 MIN) ================= */
$timeout_cadastro = 1800; // 30 minutos
if (isset($_SESSION['cadastro']['timestamp'])) {
    $elapsed = time() - $_SESSION['cadastro']['timestamp'];
    if ($elapsed > $timeout_cadastro) {
        unset($_SESSION['cadastro']);
        unset($_SESSION['tipo_atual']);
        unset($_SESSION['tipos_permitidos']);
        header("Location: ../../index.php?info=session_expired");
        exit;
    }
}

// Atualiza timestamp
$_SESSION['cadastro']['timestamp'] = time();

/* ================= 7. LOG DE DEPURAÇÃO (Apenas em DEV) ================= */
if (defined('APP_ENV') && APP_ENV === 'dev') {
    $logPath = __DIR__ . '/../logs/device.log';
    $logDir = dirname($logPath);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $device = detectDevice();
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