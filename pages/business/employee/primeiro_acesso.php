<?php
/**
 * PRIMEIRO ACESSO - FUNCIONÁRIO
 * Página onde funcionário define sua senha
 * ATUALIZADO: Senha salva em users.password_hash
 */

require_once '../../../registration/includes/db.php';

$token = $_GET['token'] ?? '';
$erro = '';
$funcionario = null;

// Verificar token
if ($token) {
    $stmt = $mysqli->prepare("
        SELECT 
            e.id as employee_id,
            e.nome, 
            e.email_company, 
            e.cargo, 
            e.user_id,
            e.user_employee_id,
            u.email as email_pessoal,
            emp.nome as empresa_nome
        FROM employees e
        LEFT JOIN users u ON e.user_employee_id = u.id
        INNER JOIN users emp ON e.user_id = emp.id
        WHERE e.token_primeiro_acesso = ?
        AND e.token_expira_em > NOW()
        AND e.pode_acessar_sistema = 1
        AND e.is_active = 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $funcionario = $result->fetch_assoc();
        
        // Validar se tem user_employee_id
        if (!$funcionario['user_employee_id']) {
            $erro = 'Erro de configuração. Entre em contato com seu gestor.';
            $funcionario = null;
        }
    } else {
        $erro = 'Link inválido ou expirado';
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $funcionario) {
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';
    
    if (strlen($senha) < 8) {
        $erro = 'A senha deve ter no mínimo 8 caracteres';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem';
    } else {
        $passwordHash = password_hash($senha, PASSWORD_DEFAULT);
        
        // Iniciar transação
        $mysqli->begin_transaction();
        
        try {
            // 1. Atualizar senha em USERS
            $stmt = $mysqli->prepare("
                UPDATE users SET
                    password_hash = ?,
                    status = 'active',
                    registration_step = 'completed',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('si', $passwordHash, $funcionario['user_employee_id']);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao salvar senha');
            }
            
            // 2. Atualizar employee
            $stmt = $mysqli->prepare("
                UPDATE employees SET
                    primeiro_acesso = 0,
                    token_primeiro_acesso = NULL,
                    token_expira_em = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('i', $funcionario['employee_id']);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao atualizar registro');
            }
            
            // Commit
            $mysqli->commit();
            
            // Redirecionar para login
            header('Location: login_funcionario.php?success=1');
            exit;
            
        } catch (Exception $e) {
            $mysqli->rollback();
            $erro = 'Erro ao definir senha. Tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primeiro Acesso - VisionGreen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            max-width: 520px;
            width: 100%;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: #00ff88;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 28px;
            margin-bottom: 15px;
        }

        h1 {
            color: #c9d1d9;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #8b949e;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .empresa-info {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .empresa-info p {
            color: #c9d1d9;
            margin: 8px 0;
            font-size: 14px;
            line-height: 1.5;
        }

        .empresa-info strong {
            color: #00ff88;
            display: inline-block;
            min-width: 100px;
        }

        .email-login {
            background: #0d1117;
            border: 2px solid #1f6feb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
        }

        .email-login-title {
            color: #1f6feb;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .email-login-value {
            color: #58a6ff;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }

        .email-login-note {
            color: #8b949e;
            font-size: 11px;
            margin-top: 8px;
            line-height: 1.4;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #8b949e;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            color: #c9d1d9;
            font-size: 14px;
        }

        input:focus {
            outline: none;
            border-color: #00ff88;
            box-shadow: 0 0 0 3px rgba(0, 255, 136, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #8b949e;
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: #c9d1d9;
        }

        .password-requirements {
            font-size: 12px;
            color: #8b949e;
            margin-top: 8px;
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
        }

        .error {
            background: rgba(255, 77, 77, 0.1);
            border: 1px solid rgba(255, 77, 77, 0.3);
            color: #ff7b72;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            color: #00ff88;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        button {
            width: 100%;
            padding: 14px;
            background: #00ff88;
            border: none;
            border-radius: 8px;
            color: #000;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        button:hover {
            background: #00dd77;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 255, 136, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        button:disabled {
            background: #30363d;
            color: #6e7681;
            cursor: not-allowed;
            transform: none;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #30363d;
        }

        .footer a {
            color: #00ff88;
            text-decoration: none;
            font-size: 14px;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fa-solid fa-leaf"></i>
            </div>
            <h1>Bem-vindo ao VisionGreen</h1>
            <p class="subtitle">Defina sua senha para acessar o sistema</p>
        </div>

        <?php if ($erro): ?>
            <div class="error">
                <i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($funcionario): ?>
            <div class="empresa-info">
                <p><strong>Nome:</strong> <?= htmlspecialchars($funcionario['nome']) ?></p>
                <p><strong>Cargo:</strong> <?= htmlspecialchars($funcionario['cargo']) ?></p>
                <p><strong>Empresa:</strong> <?= htmlspecialchars($funcionario['empresa_nome']) ?></p>
            </div>

            <?php if ($funcionario['email_company']): ?>
            <div class="email-login">
                <div class="email-login-title">
                    <i class="fa-solid fa-envelope"></i>
                    Email para Login
                </div>
                <div class="email-login-value">
                    <?= htmlspecialchars($funcionario['email_company']) ?>
                </div>
                <div class="email-login-note">
                    <i class="fa-solid fa-info-circle"></i>
                    Use este email corporativo para fazer login no sistema após definir sua senha.
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" id="formSenha">
                <div class="form-group">
                    <label>Nova Senha *</label>
                    <div class="input-wrapper">
                        <input type="password" name="senha" id="senha" required minlength="8" autocomplete="new-password">
                        <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('senha')"></i>
                    </div>
                    <div class="password-requirements">
                        Sua senha deve ter:
                        <ul>
                            <li>Mínimo de 8 caracteres</li>
                            <li>Letras e números (recomendado)</li>
                            <li>Caracteres especiais para maior segurança</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirmar Senha *</label>
                    <div class="input-wrapper">
                        <input type="password" name="confirmar_senha" id="confirmar_senha" required minlength="8" autocomplete="new-password">
                        <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('confirmar_senha')"></i>
                    </div>
                </div>

                <button type="submit" id="btnSubmit">
                    <i class="fa-solid fa-check"></i> Definir Senha e Acessar
                </button>
            </form>
        <?php else: ?>
            <div class="error">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <?= $erro ?: 'Link inválido ou expirado. Entre em contato com seu gestor.' ?>
            </div>
            
            <div class="footer">
                <a href="login_funcionario.php">
                    <i class="fa-solid fa-arrow-left"></i> Voltar para Login
                </a>
            </div>
        <?php endif; ?>

        <?php if ($funcionario): ?>
        <div class="footer">
            <a href="login_funcionario.php">
                <i class="fa-solid fa-arrow-left"></i> Já tenho senha, fazer login
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Validação em tempo real
        const senhaInput = document.getElementById('senha');
        const confirmarInput = document.getElementById('confirmar_senha');
        const btnSubmit = document.getElementById('btnSubmit');

        if (confirmarInput) {
            confirmarInput.addEventListener('input', function() {
                const senha = senhaInput.value;
                const confirmar = this.value;
                
                if (confirmar && senha !== confirmar) {
                    this.style.borderColor = '#ff4d4d';
                } else {
                    this.style.borderColor = '';
                }
            });
        }

        // Validação no submit
        const form = document.getElementById('formSenha');
        if (form) {
            form.addEventListener('submit', function(e) {
                const senha = senhaInput.value;
                const confirmar = confirmarInput.value;
                
                if (senha !== confirmar) {
                    e.preventDefault();
                    alert('As senhas não coincidem!');
                    confirmarInput.focus();
                    return false;
                }
                
                if (senha.length < 8) {
                    e.preventDefault();
                    alert('A senha deve ter no mínimo 8 caracteres!');
                    senhaInput.focus();
                    return false;
                }
                
                // Desabilitar botão para evitar duplo clique
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processando...';
            });
        }

        // Copiar email ao clicar
        const emailValue = document.querySelector('.email-login-value');
        if (emailValue) {
            emailValue.style.cursor = 'pointer';
            emailValue.title = 'Clique para copiar';
            
            emailValue.addEventListener('click', function() {
                const email = this.textContent.trim();
                
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(email).then(() => {
                        const originalText = this.innerHTML;
                        this.innerHTML = '<i class="fa-solid fa-check"></i> Email copiado!';
                        this.style.color = '#00ff88';
                        
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.style.color = '';
                        }, 2000);
                    });
                }
            });
        }
    </script>
</body>
</html>