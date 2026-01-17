<?php
/**
 * LOGIN DE FUNCIONÁRIOS
 * Arquivo: pages/business/employee/login_funcionario.php
 * ATUALIZADO: Usa email_company e user_employee_id
 */

session_start();

// Se já estiver logado como funcionário, redirecionar
if (isset($_SESSION['employee_auth']['employee_id'])) {
    header('Location: ../dashboard_business.php');
    exit;
}

require_once '../../../registration/includes/db.php';

$erro = '';
$sucesso = '';

// Mensagens de URL
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $sucesso = 'Senha definida com sucesso! Faça login abaixo.';
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'session_expired':
            $erro = 'Sua sessão expirou. Faça login novamente.';
            break;
        case 'access_revoked':
            $erro = 'Seu acesso foi revogado. Entre em contato com seu gestor.';
            break;
        case 'account_inactive':
            $erro = 'Sua conta está inativa. Entre em contato com seu gestor.';
            break;
        case 'logout':
            $sucesso = 'Você saiu do sistema com sucesso.';
            break;
    }
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        // Buscar funcionário usando email_company
        $stmt = $mysqli->prepare("
            SELECT 
                e.id, 
                e.nome, 
                e.email_company, 
                e.cargo, 
                e.user_id, 
                e.user_employee_id,
                e.primeiro_acesso, 
                e.status,
                u.password_hash,
                u.email as email_pessoal,
                u.status as user_status,
                emp.nome as empresa_nome
            FROM employees e
            INNER JOIN users u ON e.user_employee_id = u.id
            INNER JOIN users emp ON e.user_id = emp.id
            WHERE e.email_company COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
            AND e.is_active = 1
            AND e.pode_acessar_sistema = 1
            AND u.type = 'employee'
        ");
        
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $erro = 'Email corporativo não encontrado ou sem permissão de acesso.';
        } else {
            $funcionario = $result->fetch_assoc();
            
            // Verificar se ainda é primeiro acesso
            if ($funcionario['primeiro_acesso']) {
                $erro = 'Você ainda não definiu sua senha. Verifique seu email pessoal.';
            }
            // Verificar se está ativo
            elseif ($funcionario['status'] !== 'ativo') {
                $erro = 'Sua conta está ' . $funcionario['status'] . '. Entre em contato com seu gestor.';
            }
            // Verificar status do usuário
            elseif ($funcionario['user_status'] === 'blocked') {
                $erro = 'Sua conta foi bloqueada. Entre em contato com seu gestor.';
            }
            // Verificar senha
            elseif (!password_verify($senha, $funcionario['password_hash'])) {
                $erro = 'Senha incorreta.';
            } else {
                // LOGIN SUCESSO!
                session_regenerate_id(true);
                
                $_SESSION['employee_auth'] = [
                    'employee_id' => $funcionario['id'],
                    'user_id' => $funcionario['user_employee_id'], // ID do funcionário em users
                    'empresa_id' => $funcionario['user_id'], // ID da empresa
                    'nome' => $funcionario['nome'],
                    'email_pessoal' => $funcionario['email_pessoal'],
                    'email_company' => $funcionario['email_company'],
                    'cargo' => $funcionario['cargo'],
                    'empresa_nome' => $funcionario['empresa_nome'],
                    'login_time' => time()
                ];
                
                // Atualizar último login
                $mysqli->query("UPDATE employees SET ultimo_login = NOW() WHERE id = " . $funcionario['id']);
                
                // Registrar log de acesso
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                
                $stmt = $mysqli->prepare("
                    INSERT INTO employee_access_logs (employee_id, action, ip_address, user_agent)
                    VALUES (?, 'login', ?, ?)
                ");
                $stmt->bind_param('iss', $funcionario['id'], $ip, $userAgent);
                $stmt->execute();
                
                // Redirecionar para dashboard
                header('Location: ../dashboard_business.php');
                exit;
            }
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Funcionário - VisionGreen</title>
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
            max-width: 420px;
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
            background: #4da3ff;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
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

        .employee-badge {
            background: rgba(77, 163, 255, 0.1);
            border: 1px solid rgba(77, 163, 255, 0.3);
            color: #4da3ff;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 30px;
            font-size: 13px;
            font-weight: 600;
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
            border-color: #4da3ff;
            box-shadow: 0 0 0 3px rgba(77, 163, 255, 0.1);
        }

        .input-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #8b949e;
        }

        .toggle-password {
            cursor: pointer;
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: #c9d1d9;
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

        .forgot-password {
            text-align: right;
            margin-bottom: 20px;
        }

        .forgot-password a {
            color: #4da3ff;
            text-decoration: none;
            font-size: 13px;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        button {
            width: 100%;
            padding: 14px;
            background: #4da3ff;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        button:hover {
            background: #3d8ce6;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(77, 163, 255, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #30363d;
        }

        .divider span {
            background: #161b22;
            padding: 0 15px;
            color: #8b949e;
            font-size: 13px;
            position: relative;
            z-index: 1;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
        }

        .footer a {
            color: #4da3ff;
            text-decoration: none;
            font-size: 14px;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .info-box {
            background: rgba(77, 163, 255, 0.05);
            border: 1px solid rgba(77, 163, 255, 0.2);
            border-radius: 8px;
            padding: 15px;
            margin-top: 30px;
            text-align: center;
        }

        .info-box p {
            color: #8b949e;
            font-size: 13px;
            line-height: 1.6;
        }

        .info-box a {
            color: #4da3ff;
            text-decoration: none;
            font-weight: 600;
        }

        .email-hint {
            background: rgba(77, 163, 255, 0.05);
            border-left: 3px solid #4da3ff;
            padding: 10px 12px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 12px;
            color: #8b949e;
        }

        .email-hint i {
            color: #4da3ff;
            margin-right: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <h1>Acesso Funcionário</h1>
            <p class="subtitle">VisionGreen Business</p>
        </div>

        <div class="employee-badge">
            <i class="fa-solid fa-shield-halved"></i>
            Área restrita para funcionários autorizados
        </div>

        <?php if ($erro): ?>
            <div class="error">
                <i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="success">
                <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Corporativo</label>
                <div class="input-wrapper">
                    <input 
                        type="email" 
                        name="email" 
                        required 
                        autofocus
                        placeholder="seunome@empresa.vsg.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                    <i class="fa-solid fa-envelope input-icon"></i>
                </div>
                <div class="email-hint">
                    <i class="fa-solid fa-info-circle"></i>
                    Use seu email corporativo que termina com <strong>@empresa.vsg.com</strong>
                </div>
            </div>

            <div class="form-group">
                <label>Senha</label>
                <div class="input-wrapper">
                    <input 
                        type="password" 
                        name="senha" 
                        id="senha" 
                        required
                        placeholder="••••••••"
                    >
                    <i class="fa-solid fa-eye toggle-password input-icon" onclick="togglePassword()"></i>
                </div>
            </div>

            <div class="forgot-password">
                <a href="#" onclick="alert('Entre em contato com seu gestor para redefinir a senha.'); return false;">
                    Esqueceu a senha?
                </a>
            </div>

            <button type="submit">
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                Entrar no Sistema
            </button>
        </form>

        <div class="divider">
            <span>OU</span>
        </div>

        <div class="footer">
            <a href="../../../registration/login/login.php">
                <i class="fa-solid fa-arrow-left"></i> Voltar para login principal
            </a>
        </div>

        <div class="info-box">
            <p>
                <strong>Primeiro acesso?</strong><br>
                Você deve ter recebido um link por email para definir sua senha.
                Não recebeu? <a href="#" onclick="alert('Entre em contato com seu gestor.'); return false;">Fale com seu gestor</a>
            </p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('senha');
            const icon = document.querySelector('.toggle-password');
            
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

        // Auto-focus no primeiro campo
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }
        });
    </script>
</body>
</html>