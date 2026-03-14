<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once 'login_rate_limit.php';
require_once '../includes/mailer.php';

date_default_timezone_set('UTC');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

/* ── INPUT ───────────────────────────────────────────────────────── */
$identifier = strtolower(cleanInput($_POST['identifier'] ?? ''));
$password   = $_POST['password'] ?? '';
$csrf       = $_POST['csrf']     ?? '';
$remember   = isset($_POST['remember']);
$ip_address = $_SERVER['REMOTE_ADDR'];

/*
 * ── REDIRECT SEGURO ───────────────────────────────────────────────
 * Aceita apenas:
 *   - Caminhos absolutos de domínio:  /checkout.php?buy_now=5
 *   - Caminhos relativos simples:     pages/person/index.php
 *
 * Rejeita:
 *   - URLs absolutas (http://, https://, //)   → open redirect
 *   - Path traversal (../)                     → directory escape
 *
 * NOTA: checkout.php passa o redirect como /checkout.php?...
 * (caminho absoluto de domínio) para evitar ambiguidade de profundidade.
 */
function sanitize_redirect(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') return '';

    // Rejeitar URLs absolutas e protocol-relative (open redirect)
    if (preg_match('#^(https?:)?//#i', $raw)) return '';

    // Rejeitar path traversal
    if (strpos($raw, '..') !== false) return '';

    // Aceitar: caminhos absolutos de domínio (/checkout.php?...)
    //          e caminhos relativos normais (pages/person/index.php)
    if (!preg_match('#^[a-zA-Z0-9_./\-?&=%+]+$#', $raw)) return '';

    return $raw;
}

$redirect_raw  = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
$redirect_safe = sanitize_redirect($redirect_raw);

/* ── Guardar formulário para repopular em caso de erro ───────────── */
$_SESSION['login_form_data'] = [
    'identifier' => $identifier,
    'remember'   => $remember,
    'redirect'   => $redirect_raw, // preservar para o login.php repassar
];

/* ── CSRF ────────────────────────────────────────────────────────── */
if (!csrf_validate($csrf)) {
    $_SESSION['login_error'] = '🔒 Sessão inválida. Por favor, recarregue a página e tente novamente.';
    $loc = 'login.php' . ($redirect_raw ? '?redirect=' . urlencode($redirect_raw) : '');
    header("Location: $loc");
    exit;
}

/* ── VALIDAÇÃO ───────────────────────────────────────────────────── */
if (empty($identifier) || empty($password)) {
    $_SESSION['login_error'] = '⚠️ Por favor, preencha todos os campos (email/UID e senha).';
    $loc = 'login.php' . ($redirect_raw ? '?redirect=' . urlencode($redirect_raw) : '');
    header("Location: $loc");
    exit;
}

/* ── Detectar funcionário ────────────────────────────────────────── */
$isEmployeeLogin = (strpos($identifier, '@') !== false && strpos($identifier, '.vsg.com') !== false);

/* ── Rate limit ──────────────────────────────────────────────────── */
$stmt_check = $mysqli->prepare("
    SELECT attempts FROM login_attempts
    WHERE email = ? AND last_attempt > NOW() - INTERVAL 30 MINUTE
");
$stmt_check->bind_param('s', $identifier);
$stmt_check->execute();
$res_check = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if ($res_check && $res_check['attempts'] >= 5) {
    $_SESSION['login_error'] = '🚫 Muitas tentativas de login falhadas. Por segurança, aguarde 30 minutos antes de tentar novamente.';
    $loc = 'login.php' . ($redirect_raw ? '?redirect=' . urlencode($redirect_raw) : '');
    header("Location: $loc");
    exit;
}

/* ── Helper: registar tentativa falhada ──────────────────────────── */
function record_failed_attempt(mysqli $db, string $email, string $ip): int
{
    $s = $db->prepare("
        INSERT INTO login_attempts (email, ip_address, attempts, last_attempt)
        VALUES (?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
    ");
    $s->bind_param('ss', $email, $ip);
    $s->execute();
    $s->close();

    $s2 = $db->prepare("SELECT attempts FROM login_attempts WHERE email = ?");
    $s2->bind_param('s', $email);
    $s2->execute();
    $row = $s2->get_result()->fetch_assoc();
    $s2->close();
    return (int)($row['attempts'] ?? 1);
}

/* ── Helper: limpar tentativas ───────────────────────────────────── */
function clear_attempts(mysqli $db, string $email): void
{
    $s = $db->prepare("DELETE FROM login_attempts WHERE email = ?");
    $s->bind_param('s', $email);
    $s->execute();
    $s->close();
}

/* ── Helper: URL de erro (preserva redirect) ─────────────────────── */
function err_location(string $redirect_raw): string
{
    return 'login.php' . ($redirect_raw ? '?redirect=' . urlencode($redirect_raw) : '');
}

/* ── Helper: URL de sucesso ──────────────────────────────────────── */
function success_location(string $type, string $redirect_safe): string
{
    // Empresas: SEMPRE para o dashboard — nunca seguem um redirect externo.
    // Páginas como shopping/checkout têm o company_guard que as bloqueia,
    // por isso não faz sentido enviar uma empresa para lá.
    if ($type === 'company') {
        return '../../pages/business/dashboard_business.php';
    }

    // Pessoa / admin: respeitar o redirect se existir
    if ($redirect_safe !== '') {
        return $redirect_safe;
    }

    return match($type) {
        'person'  => '../../pages/person/index.php',
        default   => '../../pages/person/index.php',
    };
}

try {
    /* ════════════════════════════════════════════════════════════════
       FLUXO FUNCIONÁRIO
    ════════════════════════════════════════════════════════════════ */
    if ($isEmployeeLogin) {
        $stmt = $mysqli->prepare("
            SELECT
                u.id, u.nome, u.email AS email_pessoal,
                u.telefone, u.password_hash, u.status,
                e.id AS employee_id, e.cargo, e.user_id AS empresa_id,
                e.email_company, e.pode_acessar_sistema,
                e.primeiro_acesso, e.status AS employee_status,
                emp.nome AS empresa_nome
            FROM employees e
            INNER JOIN users u   ON e.user_employee_id = u.id
            INNER JOIN users emp ON e.user_id = emp.id
            WHERE e.email_company COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
              AND u.type  = 'employee'
              AND e.is_active = 1
        ");
        $stmt->bind_param('s', $identifier);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$employee) {
            record_failed_attempt($mysqli, $identifier, $ip_address);
            $_SESSION['login_error'] = '❌ Email corporativo <strong>' . htmlspecialchars($identifier) . '</strong> não encontrado ou sem permissão de acesso ao sistema.';
            header('Location: ' . err_location($redirect_raw));
            exit;
        }
        if (!$employee['pode_acessar_sistema']) {
            $_SESSION['login_error'] = '🚫 Você não tem permissão para acessar o sistema. Entre em contato com seu gestor.';
            header('Location: ' . err_location($redirect_raw));
            exit;
        }
        if ($employee['primeiro_acesso']) {
            $_SESSION['login_error'] = '📧 Você ainda não definiu sua senha. Verifique seu email corporativo e clique no link de ativação.';
            header('Location: ' . err_location($redirect_raw));
            exit;
        }
        if ($employee['employee_status'] !== 'ativo') {
            $status_msg  = ['inativo' => 'inativa', 'ferias' => 'em férias', 'afastado' => 'afastada'];
            $status_text = $status_msg[$employee['employee_status']] ?? $employee['employee_status'];
            $_SESSION['login_error'] = '⚠️ Sua conta está <strong>' . $status_text . '</strong>. Entre em contato com seu gestor para mais informações.';
            header('Location: ' . err_location($redirect_raw));
            exit;
        }
        if ($employee['status'] === 'blocked') {
            $_SESSION['login_error'] = '🔒 Sua conta está bloqueada. Entre em contato com o departamento de RH ou seu gestor.';
            header('Location: ' . err_location($redirect_raw));
            exit;
        }
        if (!password_verify($password, $employee['password_hash'])) {
            $attempts  = record_failed_attempt($mysqli, $identifier, $ip_address);
            $remaining = 5 - $attempts;
            $_SESSION['login_error'] = $remaining > 0
                ? "🔑 <strong>Senha incorreta.</strong> Você tem mais {$remaining} tentativa(s) antes do bloqueio temporário."
                : '🚫 Senha incorreta. Sua conta foi bloqueada por 30 minutos devido a múltiplas tentativas falhadas.';
            header('Location: ' . err_location($redirect_raw));
            exit;
        }

        clear_attempts($mysqli, $identifier);
        session_regenerate_id(true);
        unset($_SESSION['login_form_data']);

        $_SESSION['employee_auth'] = [
            'employee_id'  => $employee['employee_id'],
            'user_id'      => $employee['id'],
            'empresa_id'   => $employee['empresa_id'],
            'nome'         => $employee['nome'],
            'email_pessoal'=> $employee['email_pessoal'],
            'email_company'=> $employee['email_company'],
            'cargo'        => $employee['cargo'],
            'empresa_nome' => $employee['empresa_nome'],
            'login_time'   => time(),
        ];

        $mysqli->query("UPDATE employees SET ultimo_login = NOW() WHERE id = " . (int)$employee['employee_id']);

        $stmt_log = $mysqli->prepare("
            INSERT INTO employee_access_logs (employee_id, action, ip_address, user_agent)
            VALUES (?, 'login', ?, ?)
        ");
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt_log->bind_param('iss', $employee['employee_id'], $ip_address, $ua);
        $stmt_log->execute();
        $stmt_log->close();

        // Funcionários: redirect válido ou dashboard empresarial
        $dest = $redirect_safe ?: '../../pages/business/dashboard_business.php';
        header("Location: $dest");
        exit;
    }

    /* ════════════════════════════════════════════════════════════════
       FLUXO UTILIZADOR NORMAL (gestor / pessoa)
    ════════════════════════════════════════════════════════════════ */
    $stmt = $mysqli->prepare("
        SELECT id, type, role, nome, email, password_hash,
               email_verified_at, public_id, status, password_changed_at
        FROM users
        WHERE (email = ? OR email_corporativo = ? OR public_id = ?)
          AND type != 'employee'
        LIMIT 1
    ");
    $stmt->bind_param('sss', $identifier, $identifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        record_failed_attempt($mysqli, $identifier, $ip_address);
        $_SESSION['login_error'] = '❌ Nenhuma conta encontrada com o identificador <strong>' . htmlspecialchars($identifier) . '</strong>. Verifique se digitou corretamente ou <a href="../process/start.php" style="color:#00a63e;text-decoration:underline;">cadastre-se aqui</a>.';
        header('Location: ' . err_location($redirect_raw));
        exit;
    }

    if ($user['status'] === 'blocked') {
        $_SESSION['login_error'] = '🔒 Esta conta está <strong>permanentemente bloqueada</strong> por motivos de segurança. Entre em contato com o suporte se achar que isso é um erro.';
        header('Location: ' . err_location($redirect_raw));
        exit;
    }

    if ($user['status'] === 'pending') {
        $_SESSION['login_error'] = '⏳ Sua conta ainda está <strong>pendente de ativação</strong>. Verifique seu email para completar o cadastro.';
        header('Location: ' . err_location($redirect_raw));
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        $attempts  = record_failed_attempt($mysqli, $identifier, $ip_address);
        $remaining = 5 - $attempts;
        $_SESSION['login_error'] = $remaining > 0
            ? "🔑 <strong>Senha incorreta.</strong> Você tem mais <strong>{$remaining} tentativa(s)</strong> antes do bloqueio temporário. <a href='forgot_password.php' style='color:#00a63e;'>Esqueceu sua senha?</a>"
            : '🚫 Senha incorreta. Sua conta foi <strong>bloqueada por 30 minutos</strong> devido a múltiplas tentativas falhadas.';
        header('Location: ' . err_location($redirect_raw));
        exit;
    }

    /* ── Admin ── */
    if (in_array($user['role'], ['admin', 'superadmin']) || $user['type'] === 'admin') {
        clear_attempts($mysqli, $identifier);
        session_regenerate_id(true);
        unset($_SESSION['login_form_data']);

        $_SESSION['temp_admin_auth'] = [
            'user_id'   => $user['id'],
            'email'     => $user['email'],
            'public_id' => $user['public_id'],
            'nome'      => $user['nome'],
            'type'      => $user['type'],
            'role'      => $user['role'],
        ];
        unset($_SESSION['auth']);
        // Admins: após 2FA volta para redirect ou dashboard admin
        if ($redirect_safe !== '') {
            $_SESSION['temp_admin_auth']['post_verify_redirect'] = $redirect_safe;
        }
        header("Location: ../../pages/admin/verify_secure_access.php");
        exit;
    }

    /* ── Email não verificado ── */
    if (!$user['email_verified_at']) {
        $token_mail = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $upd = $mysqli->prepare("
            UPDATE users
            SET email_token = ?, email_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR)
            WHERE id = ?
        ");
        $upd->bind_param('si', $token_mail, $user['id']);
        $upd->execute();
        $upd->close();

        enviarEmailVisionGreen($user['email'], $user['nome'], $token_mail);

        $_SESSION['user_id']    = $user['id'];
        // Guardar redirect para depois da verificação de email
        if ($redirect_safe !== '') $_SESSION['post_verify_redirect'] = $redirect_safe;

        $_SESSION['login_error'] = '📧 Seu email ainda não foi verificado. Um novo código foi enviado para <strong>' . htmlspecialchars($user['email']) . '</strong>.';
        header("Location: ../process/verify_email.php");
        exit;
    }

    /* ── Login bem-sucedido ── */
    clear_attempts($mysqli, $identifier);
    session_regenerate_id(true);
    unset($_SESSION['login_form_data']);

    $_SESSION['auth'] = [
        'user_id'   => $user['id'],
        'email'     => $user['email'],
        'public_id' => $user['public_id'],
        'nome'      => $user['nome'],
        'type'      => $user['type'],
        'role'      => $user['role'],
    ];

    $stmt_log = $mysqli->prepare("
        INSERT INTO login_logs (user_id, ip_address, user_agent, status)
        VALUES (?, ?, ?, 'success')
    ");
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $stmt_log->bind_param('iss', $user['id'], $ip_address, $ua);
    $stmt_log->execute();
    $stmt_log->close();

    header('Location: ' . success_location($user['type'], $redirect_safe));
    exit;

} catch (Exception $e) {
    error_log("Erro Crítico no Login: " . $e->getMessage() . " | " . $e->getFile() . ":" . $e->getLine());
    $_SESSION['login_error'] = '🔧 Ocorreu um erro interno no sistema. Por favor, tente novamente em alguns instantes.';
    header('Location: ' . err_location($redirect_raw));
    exit;
}