<?php
session_start();

// Configura o mysqli para lançar exceções em caso de erro
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/rate_limit.php';
require_once '../includes/mailer.php'; // Função centralizada enviarEmailVisionGreen

/* ================= RATE LIMIT ================= */
// Limita o reenvio de código a 3 vezes a cada 5 minutos por sessão
rateLimit('resend_email', 3, 300);

/* ================= BLOQUEIO DE ACESSO ================= */
if (empty($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* ================= BUSCA USUÁRIO ================= */
try {
    $stmt = $mysqli->prepare("
        SELECT email, nome, email_verified_at 
        FROM users 
        WHERE id = ? 
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: ../login/login.php?error=user_not_found");
        exit;
    }

    // Se já estiver verificado, não faz sentido reenviar código
    if ($user['email_verified_at'] !== null) {
        header("Location: ../register/gerar_uid.php");
        exit;
    }

    /* ================= GERA NOVO CÓDIGO ================= */
    $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $update = $mysqli->prepare("
        UPDATE users 
        SET email_token = ?, email_token_expires = ? 
        WHERE id = ?
    ");
    $update->bind_param('ssi', $verification_code, $expires, $userId);
    $update->execute();
    $update->close();

    /* ================= ENVIA EMAIL (USANDO FUNÇÃO CENTRALIZADA) ================= */
    // Chamamos a mesma função usada no cadastro inicial
    $enviado = enviarEmailVisionGreen($user['email'], $user['nome'], $verification_code);

    if ($enviado) {
        $status = "codigo_enviado";
    } else {
        $status = "erro_envio";
    }

} catch (Exception $e) {
    // ESTA LINHA VAI TE MOSTRAR O ERRO REAL NO CONSOLE DO NAVEGADOR (F12)
    echo json_encode([
        'error' => 'Erro no Banco: ' . $e->getMessage() 
    ]);
    exit;
}

/* ================= REDIRECIONA PARA VERIFICAÇÃO ================= */
header("Location: verify_email.php?info=" . $status);
exit;