<?php
session_start();
require_once '../includes/db.php';
require_once 'login_rate_limit.php'; 

if (!isset($_SESSION['recovery'])) {
    header("Location: forgot_password.php");
    exit;
}

$errors = []; // Array para armazenar erros específicos
$user_id = $_SESSION['recovery']['user_id'];
$email_usuario = $_SESSION['recovery']['email'];

// 1. Buscar dados reais do usuário
$stmt = $mysqli->prepare("SELECT type, telefone, public_id, email_corporativo, email_token, email_token_expires FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$db_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$db_user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$isBusiness = ($db_user['type'] === 'company');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo']);
    $nova_senha = $_POST['password'];
    $confirma_senha = $_POST['confirm_password'];

    // 2. VALIDAÇÕES ESPECÍFICAS
    if ($codigo !== $db_user['email_token'] || time() > strtotime($db_user['email_token_expires'])) {
        $errors['codigo'] = "Código inválido ou expirado.";
    } 

    if (!$isBusiness) {
        if (trim($_POST['telefone']) !== $db_user['telefone']) {
            $errors['telefone'] = "Telefone não corresponde ao cadastro.";
        }
    } else {
        if (trim($_POST['uid']) !== $db_user['public_id']) {
            $errors['uid'] = "UID incorreto.";
        }
        if (strtolower(trim($_POST['email_corp'])) !== strtolower($db_user['email_corporativo'])) {
            $errors['email_corp'] = "E-mail corporativo incorreto.";
        }
    }

    if (strlen($nova_senha) < 8) {
        $errors['password'] = "A senha deve ter no mínimo 8 caracteres.";
    } elseif ($nova_senha !== $confirma_senha) {
        $errors['confirm_password'] = "As senhas não coincidem.";
    }

    // Se não houver erros, processa a atualização
    if (empty($errors)) {
        $new_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        
        $update = $mysqli->prepare("
            UPDATE users 
            SET password_hash = ?, 
                email_token = NULL, 
                login_attempts = 0, 
                lock_until = NULL 
            WHERE id = ?
        ");
        $update->bind_param('si', $new_hash, $user_id);
        $update->execute();
        $update->close();

        clearLoginAttempts($mysqli, $email_usuario);
        unset($_SESSION['recovery']);

        header("Location: login.php?recovery=success");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Definir Nova Senha - VisionGreen</title>
    <link rel="stylesheet" href="../../assets/style/geral.css">
    <style>
        :root {
            --color-main: <?= $isBusiness ? '#2563eb' : '#28a745' ?>;
            --color-bg: #f0f2f5;
            --color-error: #dc3545;
        }
        body { font-family: 'Segoe UI', sans-serif; background: var(--color-bg); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { width: 100%; max-width: 450px; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); border-top: 5px solid var(--color-main); }
        
        h2 { text-align: center; color: #333; margin-top: 0; }
        p.subtitle { text-align: center; font-size: 0.9em; color: #777; margin-bottom: 20px; }

        label { font-size: 0.85em; color: #666; font-weight: bold; display: block; margin-top: 15px; }
        input { width: 100%; padding: 12px; margin-top: 5px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; transition: 0.3s; }
        
        /* Estilo de erro no input */
        input.is-invalid { border-color: var(--color-error); background-color: #fff8f8; }
        .error-text { color: var(--color-error); font-size: 0.75em; margin-top: 4px; font-weight: 500; }

        button { width: 100%; padding: 12px; background: var(--color-main); color: white; border: none; border-radius: 6px; cursor: pointer; margin-top: 25px; font-weight: bold; transition: 0.3s; }
        button:hover { opacity: 0.9; }

        .strength-meter { height: 6px; width: 100%; background: #eee; margin-top: 5px; border-radius: 6px; overflow: hidden; }
        .strength-bar { height: 100%; width: 0%; transition: 0.4s ease; }
        .weak { background: #ff4d4d; width: 33.33%; }
        .medium { background: #ffd633; width: 66.66%; }
        .strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Recuperar Acesso</h2>
        <p class="subtitle">Verificação para Conta <?= $isBusiness ? 'Empresarial' : 'Pessoal' ?></p>

        <form method="POST" novalidate>
            <label>Código de 6 dígitos (E-mail)</label>
            <input type="text" name="codigo" class="<?= isset($errors['codigo']) ? 'is-invalid' : '' ?>" 
                   value="<?= htmlspecialchars($_POST['codigo'] ?? '') ?>" required maxlength="6">
            <?php if (isset($errors['codigo'])): ?><div class="error-text"><?= $errors['codigo'] ?></div><?php endif; ?>

            <?php if ($isBusiness): ?>
                <label>Identificador UID da Empresa</label>
                <input type="text" name="uid" class="<?= isset($errors['uid']) ? 'is-invalid' : '' ?>" 
                       value="<?= htmlspecialchars($_POST['uid'] ?? '') ?>" placeholder="Ex: 12345678C" required>
                <?php if (isset($errors['uid'])): ?><div class="error-text"><?= $errors['uid'] ?></div><?php endif; ?>

                <label>E-mail Corporativo (@visiongreen.com)</label>
                <input type="text" name="email_corp" class="<?= isset($errors['email_corp']) ? 'is-invalid' : '' ?>" 
                       value="<?= htmlspecialchars($_POST['email_corp'] ?? '') ?>" placeholder="empresa@visiongreen.com" required>
                <?php if (isset($errors['email_corp'])): ?><div class="error-text"><?= $errors['email_corp'] ?></div><?php endif; ?>

            <?php else: ?>
                <label>Seu Telefone Cadastrado</label>
                <input type="text" name="telefone" class="<?= isset($errors['telefone']) ? 'is-invalid' : '' ?>" 
                       value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" placeholder="+244..." required>
                <?php if (isset($errors['telefone'])): ?><div class="error-text"><?= $errors['telefone'] ?></div><?php endif; ?>
            <?php endif; ?>

            <hr style="margin: 25px 0; border: 0; border-top: 1px solid #eee;">

            <label>Nova Senha</label>
            <input type="password" name="password" id="passInput" class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                   placeholder="Mínimo 8 caracteres" required oninput="checkStrength(this.value)">
            <div class="strength-meter"><div id="strengthBar" class="strength-bar"></div></div>
            <?php if (isset($errors['password'])): ?><div class="error-text"><?= $errors['password'] ?></div><?php endif; ?>

            <label>Confirmar Nova Senha</label>
            <input type="password" name="confirm_password" class="<?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" 
                   placeholder="Repita a senha" required>
            <?php if (isset($errors['confirm_password'])): ?><div class="error-text"><?= $errors['confirm_password'] ?></div><?php endif; ?>

            <button type="submit">Atualizar Senha e Liberar Login</button>
        </form>
    </div>

    <script>
        function checkStrength(password) {
            let bar = document.getElementById('strengthBar');
            let strength = 0;
            if (password.length === 0) { bar.className = 'strength-bar'; return; }
            if (password.length >= 8) strength++;
            if (password.match(/[A-Z]/) && password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            bar.className = 'strength-bar';
            if (strength === 1) bar.classList.add('weak');
            else if (strength === 2) bar.classList.add('medium');
            else if (strength >= 3) bar.classList.add('strong');
        }
    </script>
</body>
</html>