<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/mailer.php';
require_once 'login_rate_limit.php'; // Proteção contra abuso de envio

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar CSRF para evitar disparos automáticos de outros sites
    $csrf = $_POST['csrf'] ?? '';
    if (!csrf_validate($csrf)) {
        header("Location: forgot_password.php?error=csrf");
        exit;
    }

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: forgot_password.php?error=invalid_email");
        exit;
    }

    // 2. APLICAR RATE LIMIT (CORREÇÃO AQUI)
    // Usamos o sufixo '_recovery' para que os erros do Login.php 
    // NÃO bloqueiem o envio do e-mail de recuperação.
    if (!checkLoginRateLimit($mysqli, $email . '_recovery', 3, 300)) {
        header("Location: forgot_password.php?error=rate_limit");
        exit;
    }
    
    // 3. Buscar usuário no banco
    $stmt = $mysqli->prepare("SELECT id, nome, status FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 4. Se o usuário existe e não está banido
    if ($user && $user['status'] !== 'banned') {
        $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 1800); // 30 minutos de validade

        // Grava o token de recuperação
        $upd = $mysqli->prepare("UPDATE users SET email_token = ?, email_token_expires = ? WHERE id = ?");
        $upd->bind_param('ssi', $token, $expires, $user['id']);
        $upd->execute();
        $upd->close();

        // Salva os dados na sessão para a próxima etapa (verify_recovery.php)
        $_SESSION['recovery'] = [
            'user_id' => $user['id'], 
            'email'   => $email
        ];
        
        // Envia o e-mail usando a função centralizada
        try {
            enviarEmailVisionGreen($email, $user['nome'], $token);
            header("Location: verify_recovery.php");
            exit;
        } catch (Exception $e) {
            error_log("Erro Mailer na Recuperação: " . $e->getMessage());
            header("Location: forgot_password.php?error=mail_fail");
            exit;
        }

    } else {
        /** * SEGURANÇA: Mesmo que o e-mail não exista, redirecionamos para uma página 
         * de "sucesso aparente". Isso impede hackers de descobrirem e-mails válidos.
         */
        header("Location: forgot_password.php?status=sent");
        exit;
    }
} else {
    header("Location: forgot_password.php");
    exit;
}