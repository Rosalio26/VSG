<?php
require_once '../../registration/includes/db.php';
session_start();

// Verifica se o usuário está logado
if (empty($_SESSION['user_id'])) {
    die('Acesso negado. Faça login.');
}

$userId = $_SESSION['user_id'];

// Busca dados do usuário
$stmt = $mysqli->prepare("
    SELECT nome, apelido, email, telefone, public_id, status, registration_step, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die('Usuário não encontrado.');
}

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Pessoa</title>
    <link rel="stylesheet" href="../assets/style/geral.css">
</head>
<body>
    <header>
        <h1>Bem-vindo, <?= htmlspecialchars($user['nome']) ?>!</h1>
        <p>Status da conta: <?= htmlspecialchars($user['status']) ?></p>
    </header>
    <main>
        <section>
            <h2>Informações da Conta</h2>
            <ul>
                <li><strong>Nome:</strong> <?= htmlspecialchars($user['nome']) ?></li>
                <li><strong>Apelido:</strong> <?= htmlspecialchars($user['apelido']) ?></li>
                <li><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></li>
                <li><strong>Telefone:</strong> <?= htmlspecialchars($user['telefone']) ?></li>
                <li><strong>Identificador (UID):</strong> <?= htmlspecialchars($user['public_id']) ?></li>
                <li><strong>Registro concluído em:</strong> <?= htmlspecialchars($user['created_at']) ?></li>
                <li><strong>Etapa do cadastro:</strong> <?= htmlspecialchars($user['registration_step']) ?></li>
            </ul>
        </section>
        <section>
            <form method="post" action="logout.php">
                <button type="submit">Sair</button>
            </form>
        </section>
    </main>
</body>
</html>
