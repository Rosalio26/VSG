<?php
require_once '../includes/db.php';
session_start();

if (empty($_SESSION['user_id'])) {
    die('Fluxo inválido');
}

$userId = $_SESSION['user_id'];

/* === VER SE JÁ TEM UID === */
$stmt = $mysqli->prepare("
    SELECT public_id 
    FROM users 
    WHERE id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user['public_id']) {

    do {
        $uid = random_int(10000000, 99999999) . 'P';

        $check = $mysqli->prepare(
            "SELECT id FROM users WHERE public_id = ?"
        );
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
?>
<h1>Seu identificador</h1>
<p><strong><?= htmlspecialchars($uid) ?></strong></p>

<form method="post" action="../process/pessoa.finalize.php">
  <button type="submit">Finalizar Cadastro</button>
</form>
