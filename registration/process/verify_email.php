<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';

$erro = '';

if (empty($_SESSION['user_id'])) {
    die("Acesso negado.");
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');

    if (!$codigo) {
        $erro = "Informe o código enviado para seu email.";
    } else {
        // Busca usuário com o código e ainda não confirmado
        $stmt = $mysqli->prepare("
            SELECT id, email_token, email_token_expires
            FROM users
            WHERE id = ? AND email_token = ?
        ");
        $stmt->bind_param('is', $userId, $codigo);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $erro = "Código inválido.";
        } elseif (strtotime($user['email_token_expires']) < time()) {
            $erro = "Código expirou. Solicite um novo email.";
        } else {
            // Atualiza usuário
            $now = date('Y-m-d H:i:s');
            $update = $mysqli->prepare("
                UPDATE users
                SET email_verified_at = ?, registration_step = 'form_completed',
                    email_token = NULL, email_token_expires = NULL
                WHERE id = ?
            ");
            $update->bind_param('si', $now, $userId);
            $update->execute();

            // Redireciona para gerar UID
            header("Location: ../register/gerar_uid.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Verificação de Email</title>
<link rel="stylesheet" href="../assets/style/painel.css">
</head>
<body>
<h1>Verificação de Email</h1>
<p>Digite o código enviado para seu email.</p>
<?php if ($erro): ?>
    <p style="color:red"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>
<form method="post">
    <label for="codigo">Código</label>
    <input type="text" name="codigo" id="codigo" required>
    <button type="submit">Confirmar</button>
</form>
</body>
</html>
