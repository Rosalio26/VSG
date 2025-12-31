<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/mailer.php';
require_once 'login_rate_limit.php'; // Proteção contra abuso de envio

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar CSRF para evitar disparos automáticos
    $csrf = $_POST['csrf'] ?? '';
    if (!csrf_validate($csrf)) {
        header("Location: forgot_password.php?error=csrf");
        exit;
    }

    // Recebemos 'identifier' (E-mail Real, Corporativo ou UID)
    $identifier = strtolower(trim($_POST['identifier'] ?? ''));

    if (empty($identifier)) {
        header("Location: forgot_password.php?error=invalid_id");
        exit;
    }

    /* ================= DETECÇÃO DE TIPO PARA ERRO ESPECÍFICO ================= */
    // Se contiver '@', assumimos que o usuário tentou um e-mail. Caso contrário, um UID.
    $isEmailFormat = (strpos($identifier, '@') !== false);
    $errorType = $isEmailFormat ? 'email_not_found' : 'uid_not_found';

    // 2. APLICAR RATE LIMIT
    // Usamos o identificador para controlar tentativas de spam
    if (!checkLoginRateLimit($mysqli, $identifier . '_recovery', 3, 300)) {
        header("Location: forgot_password.php?error=rate_limit");
        exit;
    }
    
    /* ================= 3. BUSCA TRIPLA DE USUÁRIO ================= */
    // Procuramos em: email real, email corporativo ou public_id
    $stmt = $mysqli->prepare("
        SELECT id, nome, email, status 
        FROM users 
        WHERE email = ? OR email_corporativo = ? OR public_id = ? 
        LIMIT 1
    ");
    $stmt->bind_param('sss', $identifier, $identifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    /* ================= 4. VALIDAÇÃO DE EXISTÊNCIA ================= */
    // Se o usuário não for encontrado, redireciona com o erro específico detectado
    if (!$user) {
        header("Location: forgot_password.php?error=" . $errorType);
        exit;
    }

    // 5. Se o usuário existe e não está banido
    if ($user['status'] !== 'banned') {
        $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 1800); // 30 minutos

        // Grava o token de recuperação
        $upd = $mysqli->prepare("UPDATE users SET email_token = ?, email_token_expires = ? WHERE id = ?");
        $upd->bind_param('ssi', $token, $expires, $user['id']);
        $upd->execute();
        $upd->close();

        // Salva os dados na sessão. 
        // IMPORTANTE: salvamos o e-mail REAL ($user['email']) para a próxima etapa
        $_SESSION['recovery'] = [
            'user_id' => $user['id'], 
            'email'   => $user['email']
        ];
        
        // Envia o e-mail para o e-mail REAL vinculado à conta
        try {
            enviarEmailVisionGreen($user['email'], $user['nome'], $token);
            header("Location: verify_recovery.php");
            exit;
        } catch (Exception $e) {
            error_log("Erro Mailer na Recuperação: " . $e->getMessage());
            header("Location: forgot_password.php?error=mail_fail");
            exit;
        }

    } else {
        // Caso o usuário esteja banido ou status inválido
        header("Location: forgot_password.php?error=invalid_id");
        exit;
    }
} else {
    header("Location: forgot_password.php");
    exit;
}