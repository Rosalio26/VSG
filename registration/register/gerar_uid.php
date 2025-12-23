<?php
require_once '../bootstrap.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/db.php';

/* ================= BLOQUEIO DE ACESSO DIRETO ================= */
if (empty($_SESSION['user_id']) || empty($_SESSION['cadastro']['started'])) {
    errorRedirect('flow'); // Evita acesso direto
}

$userId = $_SESSION['user_id'];

/* ================= VERIFICA SE JÁ TEM UID ================= */
$stmt = $mysqli->prepare("SELECT public_id FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user['public_id']) {
    /* ================= GERA UID ÚNICO ================= */
    do {
        $uid = random_int(10000000, 99999999) . 'P';

        $check = $mysqli->prepare("SELECT id FROM users WHERE public_id = ?");
        $check->bind_param('s', $uid);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();

    } while ($exists);

    /* ================= ATUALIZA USUÁRIO ================= */
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
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Seu Identificador</title>
    <link rel="stylesheet" href="../assets/style/painel.css">
</head>
<body>
    <h1>Seu identificador</h1>
    <p><strong><?= htmlspecialchars($uid) ?></strong></p>

    <form method="post" action="../process/pessoa.finalize.php">
        <button type="submit">Finalizar Cadastro</button>
    </form>
</body>
</html>
