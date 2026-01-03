<?php
/**
 * Middleware de autenticação e fluxo
 * Deve ser incluído no TOPO de páginas protegidas
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

// Detecta a página atual para evitar loops de redirecionamento
$currentPage = basename($_SERVER['PHP_SELF']);

/* ================= 1. VERIFICAÇÃO DE LOGIN ================= */
if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];

/* ================= 2. BUSCAR DADOS ATUALIZADOS ================= */
try {
    $stmt = $mysqli->prepare("
        SELECT 
            id, nome, apelido, email, type,
            email_verified_at, public_id, status
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $authUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Erro no middleware de auth: " . $e->getMessage());
    die("Erro interno de autenticação.");
}

/* ================= 3. VALIDAÇÃO DE EXISTÊNCIA ================= */
if (!$authUser) {
    $_SESSION = [];
    session_destroy();
    header("Location: ../../registration/login/login.php?error=user_not_found");
    exit;
}

/* ================= 4. BLOQUEIO DE CONTA ================= */
if ($authUser['status'] === 'blocked') {
    $_SESSION = [];
    session_destroy();
    header("Location: ../../registration/login/login.php?error=blocked");
    exit;
}

/* ================= 5. FLUXO OBRIGATÓRIO (E-mail e UID) ================= */

// Verifica E-mail (exceto se já estiver na página de verificação)
if (!$authUser['email_verified_at'] && $currentPage !== 'verify_email.php') {
    header("Location: ../../registration/process/verify_email.php");
    exit;
}

// Verifica UID (exceto se já estiver na página de gerar_uid ou de verificação)
if (empty($authUser['public_id']) && 
    !in_array($currentPage, ['gerar_uid.php', 'verify_email.php'])) {
    header("Location: ../../registration/register/gerar_uid.php");
    exit;
}

/* ================= 6. VALIDAÇÃO DE TIPO DE CONTA (NOVO) ================= */
/**
 * Impede que Admin acesse área de Pessoa e vice-versa.
 * A página deve definir: define('REQUIRED_TYPE', 'p'); ou 'admin';
 */
if (defined('REQUIRED_TYPE')) {
    // Compara o 'type' vindo do banco de dados com o requerido pela página
    if ($authUser['type'] !== REQUIRED_TYPE) {
        
        // Log de segurança para o admin
        error_log("Acesso negado: Usuário ID {$userId} do tipo '{$authUser['type']}' tentou acessar área reservada para '" . REQUIRED_TYPE . "'");

        // Redireciona conforme o tipo real do usuário para o dashboard correto dele
        if ($authUser['type'] === 'admin') {
            header("Location: ../../admin/dashboard/index.php?error=unauthorized_area");
        } else {
            header("Location: ../../user/dashboard/index.php?error=unauthorized_area");
        }
        exit;
    }
}

/* ================= 7. ACESSO AUTORIZADO ================= */
// Agora, qualquer página que incluir este middleware terá a variável $authUser disponível.