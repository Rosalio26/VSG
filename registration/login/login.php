<?php
session_start();

// Se já estiver logado, não precisa voltar ao login
if (!empty($_SESSION['auth']['user_id'])) {
    header("Location: /dashboard");
    exit;
}

$csrf = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf'] = $csrf;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        body { font-family: Arial; background:#f5f5f5; }
        .box { width:360px; margin:80px auto; background:#fff; padding:20px; border-radius:6px; }
        input { width:100%; padding:10px; margin-bottom:10px; }
        button { padding:10px 20px; }
        .error { color:red; font-size:0.9em; }
    </style>
</head>
<body>

<div class="box">
    <h2>Entrar</h2>

    <form method="post" action="login.process.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Senha" required>

        <?php if (!empty($_SESSION['login_error'])): ?>
            <p class="error"><?= htmlspecialchars($_SESSION['login_error']) ?></p>
            <?php unset($_SESSION['login_error']); ?>
        <?php endif; ?>

        <button type="submit">Entrar</button>
    </form>
</div>

</body>
</html>
