<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/uid.php';

/* ===== FLUXO ===== */
if (empty($_SESSION['cadastro_pessoa'])) {
    errorRedirect('method');
}

/* ===== NÃO GERAR DUAS VEZES ===== */
if (empty($_SESSION['cadastro_pessoa']['uid'])) {
    $_SESSION['cadastro_pessoa']['uid'] = gerarUID($pdo, 'P');
}

$uid = $_SESSION['cadastro_pessoa']['uid'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Identificador de Conta</title>
</head>
<body>

<h1>Seu identificador foi gerado</h1>

<p><strong><?= htmlspecialchars($uid) ?></strong></p>

<p>
    Guarde este identificador. Ele será usado para login e outros casos importantes
    .
</p>

<form method="post" action="../process/pessoa.finalize.php">
    <button type="submit">Finalizar Cadastro</button>
</form>

</body>
</html>
