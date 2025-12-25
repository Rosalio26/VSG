<?php
require_once '../bootstrap.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/db.php';

/* ================= BLOQUEIO DE FLUXO ================= */
if (empty($_SESSION['user_id']) || empty($_SESSION['cadastro']['started'])) {
    errorRedirect('flow'); // Evita acesso direto
}

$userId = $_SESSION['user_id'];

/* ================= BUSCA USUÁRIO ================= */
$stmt = $mysqli->prepare("
    SELECT public_id, email_verified_at, status
    FROM users
    WHERE id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Usuário não encontrado.");
}

/* ================= GERA UID SE NÃO EXISTIR ================= */
if (!$user['public_id']) {
    do {
        $uid = random_int(10000000, 99999999) . 'P';
        $check = $mysqli->prepare("SELECT id FROM users WHERE public_id = ?");
        $check->bind_param('s', $uid);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
    } while ($exists);

    $update = $mysqli->prepare("
        UPDATE users
        SET public_id = ?, registration_step = 'uid_generated'
        WHERE id = ?
    ");
    $update->bind_param('si', $uid, $userId);
    $update->execute();
} else {
    $uid = $user['public_id'];
}

/* ================= VERIFICA EMAIL ================= */
$emailPending = $user['email_verified_at'] === null;
$status = $user['status'];

/* ================= ATIVA CONTA SE UID E EMAIL CONFIRMADOS ================= */
if ($uid && !$emailPending && $status !== 'active') {
    $update = $mysqli->prepare("
        UPDATE users
        SET status = 'active', registration_step = 'completed'
        WHERE id = ?
    ");
    $update->bind_param('i', $userId);
    $update->execute();
    $status = 'active';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Seu Identificador</title>
    <link rel="stylesheet" href="../assets/style/painel.css">
</head>
<body>
    <h1>Seu Identificador</h1>
    <p><strong><?= htmlspecialchars($uid) ?></strong></p>

    <?php if ($emailPending): ?>
        <p style="color:red;">
            Confirme seu email para finalizar o cadastro. Verifique sua caixa de entrada.
        </p>
        <form method="post" action="../process/reset_email.php">
            <button type="submit">Reenviar Email de Confirmação</button>
        </form>
    <?php else: ?>
        <form method="post" action="../process/pessoa.finalize.php">
            <button type="submit">Finalizar Cadastro</button>
        </form>
    <?php endif; ?>
</body>
</html>
