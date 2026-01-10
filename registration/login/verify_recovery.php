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
        
        // CORREÇÃO: Removidas as colunas inexistentes na tabela users
        // A limpeza de tentativas agora depende exclusivamente da função clearLoginAttempts
        $update = $mysqli->prepare("
            UPDATE users 
            SET password_hash = ?, 
                email_token = NULL 
            WHERE id = ?
        ");
        $update->bind_param('si', $new_hash, $user_id);
        $update->execute();
        $update->close();

        // Limpa as tentativas na sua tabela externa 'login_attempts'
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?= $isBusiness ? '#2563eb' : '#10b981' ?>;
            --primary-hover: <?= $isBusiness ? '#1d4ed8' : '#059669' ?>;
            --bg: #f8fafc;
            --white: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --danger: #ef4444;
            --border: #e2e8f0;
            --radius: 12px;
            --shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
            color: var(--text-main);
        }

        .box { 
            width: 100%; 
            max-width: 440px; 
            background: var(--white); 
            padding: 40px; 
            border-radius: var(--radius); 
            box-shadow: var(--shadow);
            border-top: 6px solid var(--primary);
        }
        
        h2 { text-align: center; font-size: 1.5rem; font-weight: 800; margin-bottom: 8px; letter-spacing: -0.5px; }
        p.subtitle { text-align: center; font-size: 0.9rem; color: var(--text-muted); margin-bottom: 30px; }

        .form-group { margin-bottom: 18px; position: relative; }

        label { font-size: 0.85rem; color: var(--text-main); font-weight: 600; display: block; margin-bottom: 6px; }
        
        input { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            box-sizing: border-box; 
            transition: all 0.2s ease;
            font-family: inherit;
            font-size: 0.95rem;
            background: #fdfdfd;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(var(--primary), 0.1);
            background: var(--white);
        }
        
        input.is-invalid { border-color: var(--danger); background-color: #fffafa; }
        .error-text { color: var(--danger); font-size: 0.75rem; margin-top: 5px; font-weight: 600; }

        button { 
            width: 100%; 
            padding: 14px; 
            background: var(--primary); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            margin-top: 10px; 
            font-weight: 700; 
            font-size: 1rem;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        button:hover { 
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .strength-meter { height: 4px; width: 100%; background: #e2e8f0; margin-top: 8px; border-radius: 10px; overflow: hidden; }
        .strength-bar { height: 100%; width: 0%; transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .weak { background: var(--danger); width: 33.33%; }
        .medium { background: #f59e0b; width: 66.66%; }
        .strong { background: #10b981; width: 100%; }

        hr { border: 0; border-top: 1px solid var(--border); margin: 30px 0; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Recuperar Acesso</h2>
        <p class="subtitle">Verificação de Segurança: Conta <?= $isBusiness ? 'Empresarial' : 'Pessoal' ?></p>

        <form method="POST" novalidate>
            <div class="form-group">
                <label>Código de Verificação (E-mail)</label>
                <input type="text" name="codigo" class="<?= isset($errors['codigo']) ? 'is-invalid' : '' ?>" 
                       value="<?= htmlspecialchars($_POST['codigo'] ?? '') ?>" required maxlength="6" placeholder="000000">
                <?php if (isset($errors['codigo'])): ?><div class="error-text"><?= $errors['codigo'] ?></div><?php endif; ?>
            </div>

            <?php if ($isBusiness): ?>
                <div class="form-group">
                    <label>UID da Empresa</label>
                    <input type="text" name="uid" class="<?= isset($errors['uid']) ? 'is-invalid' : '' ?>" 
                           value="<?= htmlspecialchars($_POST['uid'] ?? '') ?>" placeholder="Ex: 12345678C" required>
                    <?php if (isset($errors['uid'])): ?><div class="error-text"><?= $errors['uid'] ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label>E-mail Corporativo</label>
                    <input type="text" name="email_corp" class="<?= isset($errors['email_corp']) ? 'is-invalid' : '' ?>" 
                           value="<?= htmlspecialchars($_POST['email_corp'] ?? '') ?>" placeholder="empresa@visiongreen.com" required>
                    <?php if (isset($errors['email_corp'])): ?><div class="error-text"><?= $errors['email_corp'] ?></div><?php endif; ?>
                </div>

            <?php else: ?>
                <div class="form-group">
                    <label>Telefone Cadastrado</label>
                    <input type="text" name="telefone" class="<?= isset($errors['telefone']) ? 'is-invalid' : '' ?>" 
                           value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" placeholder="+244..." required>
                    <?php if (isset($errors['telefone'])): ?><div class="error-text"><?= $errors['telefone'] ?></div><?php endif; ?>
                </div>
            <?php endif; ?>

            <hr>

            <div class="form-group">
                <label>Nova Senha</label>
                <input type="password" name="password" id="passInput" class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                       placeholder="Mínimo 8 caracteres" required oninput="checkStrength(this.value)">
                <div class="strength-meter"><div id="strengthBar" class="strength-bar"></div></div>
                <?php if (isset($errors['password'])): ?><div class="error-text"><?= $errors['password'] ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label>Confirmar Nova Senha</label>
                <input type="password" name="confirm_password" class="<?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" 
                       placeholder="Repita a nova senha" required>
                <?php if (isset($errors['confirm_password'])): ?><div class="error-text"><?= $errors['confirm_password'] ?></div><?php endif; ?>
            </div>

            <button type="submit">Redefinir Senha</button>
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