<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/db.php';

/* ================= MÉTODO ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorRedirect('method', 405);
}

/* ================= CSRF ================= */
if (!csrf_validate($_POST['csrf'] ?? null)) {
    errorRedirect('csrf');
}

/* ================= FLUXO ================= */
if (
    empty($_SESSION['cadastro_pessoa']) ||
    ($_SESSION['registration_step'] ?? '') !== 'id_generated'
) {
    errorRedirect('method');
}

$data = $_SESSION['cadastro_pessoa'];

try {
    $pdo->beginTransaction();

    /* ================= INSERIR USUÁRIO ================= */
    $stmt = $pdo->prepare("
        INSERT INTO users (
            public_id,
            tipo,
            nome,
            apelido,
            email,
            telefone,
            password_hash,
            status,
            registration_step,
            created_at
        ) VALUES (
            :public_id,
            'pessoa',
            :nome,
            :apelido,
            :email,
            :telefone,
            :password_hash,
            'pending',
            'completed',
            NOW()
        )
    ");

    $stmt->execute([
        ':public_id'    => $data['uid'],
        ':nome'         => $data['nome'],
        ':apelido'      => $data['apelido'],
        ':email'        => $data['email'],
        ':telefone'     => $data['telefone'],
        ':password_hash'=> $data['password_hash'],
    ]);

    /* ================= TOKEN EMAIL ================= */
    $token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("
        UPDATE users
        SET email_token = :token,
            email_token_expira = DATE_ADD(NOW(), INTERVAL 24 HOUR)
        WHERE email = :email
    ");

    $stmt->execute([
        ':token' => $token,
        ':email' => $data['email'],
    ]);

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();

    if (APP_ENV === 'dev') {
        exit('Erro: ' . $e->getMessage());
    }

    errorRedirect('unknown');
}

/* ================= LIMPAR SESSÃO ================= */
unset($_SESSION['cadastro_pessoa']);
unset($_SESSION['registration_step']);

/* ================= REDIRECIONAR ================= */
header('Location: ../success/verifique_email.php');
exit;
