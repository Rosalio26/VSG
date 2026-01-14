<?php
define('REQUIRED_TYPE', 'person');
require_once '../../registration/middleware/middleware_auth.php';

require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php'; // Inclusão para usar CSRF no logout

/* ================= 1. BLOQUEIO DE ACESSO (AUTENTICAÇÃO) ================= */
if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

/* ================= 2. REFORÇO DE SEGURANÇA (AUTORIZAÇÃO POR TIPO E CARGO) ================= */
/**
 * Se o usuário logado for um Admin, ele deve ser mandado para o painel dele.
 * Se for uma empresa (company), deve ser mandada para o dashboard_business.
 */
if (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    header("Location: ../../pages/admin/dashboard.php");
    exit;
}

if ($_SESSION['auth']['type'] !== 'person') {
    if ($_SESSION['auth']['type'] === 'company') {
        header("Location: ../business/dashboard_business.php");
    } else {
        header("Location: ../../registration/login/login.php?error=acesso_proibido");
    }
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];

/* ================= 3. BUSCAR USUÁRIO ================= */
$stmt = $mysqli->prepare("
    SELECT 
        nome, apelido, email, telefone, 
        public_id, status, registration_step, 
        email_verified_at, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../../registration/login/login.php?error=user_not_found");
    exit;
}

/* ================= 4. BLOQUEIOS DE SEGURANÇA ESPECÍFICOS ================= */

// Email não confirmado
if (!$user['email_verified_at']) {
    header("Location: ../../registration/process/verify_email.php");
    exit;
}

// UID não gerado
if (!$user['public_id']) {
    header("Location: ../../registration/register/gerar_uid.php");
    exit;
}

// Conta bloqueada
if ($user['status'] === 'blocked') {
    die('Acesso restrito: Sua conta está bloqueada. Por favor, contacte o suporte técnico.');
}

// Helper para exibição amigável
$statusTraduzido = [
    'active' => 'Ativa ✅',
    'pending' => 'Pendente ⏳',
    'blocked' => 'Bloqueada ❌'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - VisionGreen</title>
    <link rel="stylesheet" href="../assets/style/geral.css">
    <style>
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; margin-top: 20px; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; background: #e8f5e9; color: #2e7d32; }
        .logout-btn { background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; transition: 0.3s; }
        .logout-btn:hover { background: #c82333; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body>

<header>
    <div class="container">
        <h1>Bem-vindo, <?= htmlspecialchars($user['apelido'] ?: $user['nome']) ?>!</h1>
        <p>Status: <span class="status-badge"><?= $statusTraduzido[$user['status']] ?? $user['status'] ?></span></p>
    </div>
</header>

<main class="container">
    <div class="card">
        <h2>Suas Informações</h2>
        <hr>
        <ul style="list-style: none; padding: 0; line-height: 2;">
            <li><strong>Nome Completo:</strong> <?= htmlspecialchars($user['nome']) ?></li>
            <li><strong>Apelido:</strong> <?= htmlspecialchars($user['apelido'] ?: 'Não definido') ?></li>
            <li><strong>E-mail:</strong> <?= htmlspecialchars($user['email']) ?></li>
            <li><strong>Telefone:</strong> <?= htmlspecialchars($user['telefone'] ?: 'Não informado') ?></li>
            <li><strong>ID Público (UID):</strong> <code><?= htmlspecialchars($user['public_id']) ?></code></li>
            <li><strong>Membro desde:</strong> <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></li>
            <p>Type: <?= htmlspecialchars($authUser['type']) ?></p>
        </ul>
    </div>

    <div style="margin-top: 30px;">
        <form method="post" action="../../registration/login/logout.php">
            <?= csrf_field(); ?>
            <button type="submit" class="logout-btn">Encerrar Sessão</button>
        </form>
    </div>
</main>

</body>
</html>