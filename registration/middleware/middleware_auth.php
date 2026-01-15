<?php
/**
 * MIDDLEWARE DE AUTENTICAÇÃO VISIONGREEN
 * USO: Páginas PROTEGIDAS (usuários já logados)
 * 
 * EXEMPLO:
 * <?php
 * define('REQUIRED_TYPE', 'person');
 * require_once 'middleware_auth.php';
 * ?>
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

$currentPage = basename($_SERVER['PHP_SELF']);

/* ================= 1. VERIFICAÇÃO DE LOGIN ================= */
if (empty($_SESSION['auth']['user_id'])) {
    $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
    header("Location: ../../registration/login/login.php?redirect=intended");
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];

/* ================= 2. BUSCAR DADOS ATUALIZADOS ================= */
try {
    $stmt = $mysqli->prepare("
        SELECT 
            id, nome, apelido, email, type, role,
            email_verified_at, public_id, status,
            password_changed_at, last_activity
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $authUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("MIDDLEWARE ERROR: " . $e->getMessage());
    die("Erro interno de autenticação.");
}

/* ================= 3. VALIDAÇÃO DE EXISTÊNCIA ================= */
if (!$authUser) {
    error_log("MIDDLEWARE SECURITY: Usuário ID {$userId} não encontrado");
    session_unset();
    session_destroy();
    header("Location: ../../registration/login/login.php?error=user_not_found");
    exit;
}

/* ================= 4. SINCRONIZAR SESSÃO ================= */
$_SESSION['auth'] = array_merge($_SESSION['auth'], [
    'nome'      => $authUser['nome'],
    'email'     => $authUser['email'],
    'public_id' => $authUser['public_id'],
    'type'      => $authUser['type'],
    'role'      => $authUser['role']
]);

/* ================= 5. BLOQUEIO DE CONTA ================= */
if ($authUser['status'] === 'blocked') {
    error_log("MIDDLEWARE SECURITY: Conta bloqueada - ID {$userId}");
    session_unset();
    session_destroy();
    header("Location: ../../registration/login/login.php?error=blocked");
    exit;
}

/* ================= 6. FLUXO OBRIGATÓRIO (Email e UID) ================= */
if (!$authUser['email_verified_at'] && 
    !in_array($currentPage, ['verify_email.php', 'logout.php'])) {
    header("Location: ../../registration/process/verify_email.php");
    exit;
}

if (empty($authUser['public_id']) && 
    !in_array($currentPage, ['gerar_uid.php', 'verify_email.php', 'logout.php'])) {
    header("Location: ../../registration/register/gerar_uid.php");
    exit;
}

/* ================= 7. VALIDAÇÃO DE TYPE ================= */
if (defined('REQUIRED_TYPE')) {
    $allowedTypes = is_array(REQUIRED_TYPE) ? REQUIRED_TYPE : [REQUIRED_TYPE];
    
    if (!in_array($authUser['type'], $allowedTypes)) {
        error_log("MIDDLEWARE SECURITY: Type '{$authUser['type']}' tentou acessar área '" . implode('/', $allowedTypes) . "'");
        
        switch ($authUser['type']) {
            case 'person':
                header("Location: ../../pages/person/dashboard_person.php?error=unauthorized");
                break;
            case 'company':
                header("Location: ../../pages/business/dashboard_business.php?error=unauthorized");
                break;
            case 'admin':
                header("Location: ../../pages/admin/dashboard_admin.php?error=unauthorized");
                break;
            default:
                session_unset();
                session_destroy();
                header("Location: ../../registration/login/login.php?error=invalid_type");
        }
        exit;
    }
}

/* ================= 8. VALIDAÇÃO DE ROLE ================= */
if (defined('REQUIRED_ROLE')) {
    $allowedRoles = is_array(REQUIRED_ROLE) ? REQUIRED_ROLE : [REQUIRED_ROLE];
    
    if (!in_array($authUser['role'], $allowedRoles)) {
        error_log("MIDDLEWARE SECURITY: Role '{$authUser['role']}' negado");
        
        if (in_array($authUser['role'], ['admin', 'superadmin'])) {
            header("Location: ../../pages/admin/dashboard_admin.php?error=insufficient_permissions");
        } else {
            header("Location: ../../pages/person/dashboard_person.php?error=unauthorized");
        }
        exit;
    }
}

/* ================= 9. VERIFICAÇÃO DE DOCUMENTOS (EMPRESAS) ================= */
if (defined('REQUIRE_APPROVED_DOCS') && REQUIRE_APPROVED_DOCS === true && $authUser['type'] === 'company') {
    
    // Ignora a verificação se a página atual for o próprio dashboard de negócios
    // Assim evitamos o loop infinito.
    if ($currentPage !== 'dashboard_business.php') {
        
        $stmt_docs = $mysqli->prepare("SELECT status_documentos FROM businesses WHERE user_id = ? LIMIT 1");
        $stmt_docs->bind_param('i', $userId);
        $stmt_docs->execute();
        $business = $stmt_docs->get_result()->fetch_assoc();
        $stmt_docs->close();
        
        if (!$business || $business['status_documentos'] !== 'aprovado') {
            header("Location: ../../pages/business/dashboard_business.php?error=documents_pending");
            exit;
        }
    }
}

/* ================= 10. ATUALIZAR ATIVIDADE ================= */
$currentTime = time();
$stmt_activity = $mysqli->prepare("UPDATE users SET last_activity = ? WHERE id = ?");
if ($stmt_activity) {
    $stmt_activity->bind_param('ii', $currentTime, $userId);
    $stmt_activity->execute();
    $stmt_activity->close();
}

/* ================= ACESSO AUTORIZADO ================= */
// Variável $authUser disponível para uso
?>