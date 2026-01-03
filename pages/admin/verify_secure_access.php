<?php
define('IS_ADMIN_PAGE', true);
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

if (!isset($_SESSION['temp_admin_auth'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

$admin_id = $_SESSION['temp_admin_auth']['user_id'];
$ip_address = $_SERVER['REMOTE_ADDR'];
$error = "";

/* ================= 1. CONSULTA TENTATIVAS VIA LOGS (AUDITORIA VIVA) ================= */
$check_attempts = $mysqli->prepare("
    SELECT COUNT(*) as total 
    FROM admin_audit_logs 
    WHERE admin_id = ? AND action = 'SECURE_ID_FAIL' 
    AND created_at > NOW() - INTERVAL 1 DAY
");
$check_attempts->bind_param('i', $admin_id);
$check_attempts->execute();
$res_attempts = $check_attempts->get_result()->fetch_assoc();
$current_attempts = $res_attempts['total'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_validate($_POST['csrf'])) {
        $code_2fa = trim($_POST['code_2fa']);
        $secure_id = strtoupper(trim($_POST['secure_id'])); // Recebe V0000-S0000-G0000

        // Busca dados atualizados (considerando que a senha pode ter mudado via rotação silenciosa)
        $stmt = $mysqli->prepare("SELECT email_token, secure_id_hash, status, password_changed_at FROM users WHERE id = ?");
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin['status'] === 'blocked') {
            die("ACESSO BLOQUEADO PERMANENTEMENTE PELO PROTOCOLO DE SEGURANÇA.");
        }

        /* ================= 2. VALIDAÇÃO CÓDIGO E-MAIL (2FA) ================= */
        if ($code_2fa !== $admin['email_token']) {
            $error = "Código de verificação de e-mail inválido.";
        } 
        /* ================= 3. VALIDAÇÃO SECURE ID (V-S-G) ================= */
        else if (!password_verify($secure_id, $admin['secure_id_hash'])) {
            
            // Registra a falha na tabela de auditoria
            $log_fail = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES (?, 'SECURE_ID_FAIL', ?)");
            $log_fail->bind_param('is', $admin_id, $ip_address);
            $log_fail->execute();

            $current_attempts++; 

            if ($current_attempts >= 2) {
                // LOCKDOWN FATAL: Errou o Secure ID 2 vezes
                $mysqli->query("UPDATE users SET status = 'blocked', is_in_lockdown = 1 WHERE id = $admin_id");
                $mysqli->query("UPDATE businesses SET status_documentos = 'rejeitado', motivo_rejeicao = 'BLOQUEIO CRÍTICO: VIOLAÇÃO DO PROTOCOLO SECURE ID'");
                $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ($admin_id, 'ACCOUNT_LOCKDOWN_FATAL', '$ip_address')");
                
                session_destroy();
                header("Location: ../../registration/login/login.php?error=lockdown");
                exit;
            } else {
                $error = "SECURE ID INCORRETO. Você tem apenas mais UMA tentativa antes do bloqueio total do sistema.";
            }
        } else {
            /* ================= 4. SUCESSO DO PROTOCOLO ================= */
            
            // Registra sucesso final
            $log_success = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES (?, 'SECURE_LOGIN_COMPLETE', ?)");
            $log_success->bind_param('is', $admin_id, $ip_address);
            $log_success->execute();

            // Transfere da sessão temporária para a oficial
            $_SESSION['auth'] = $_SESSION['temp_admin_auth'];
            // Registra o timestamp do login para controle da contagem de 1 hora
            $_SESSION['auth']['login_time'] = time(); 
            
            unset($_SESSION['temp_admin_auth']);
            
            // Limpa o token usado
            $mysqli->query("UPDATE users SET email_token = NULL WHERE id = $admin_id");
            
            header("Location: dashboard_admin.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Protocolo VisionGreen - Nível 2</title>
    <style>
        body { background: #0a0f1a; color: #fff; font-family: 'Courier New', monospace; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; overflow: hidden; }
        .secure-container { background: #111b2d; padding: 40px; border: 2px solid #00a63e; border-radius: 8px; width: 450px; text-align: center; box-shadow: 0 0 20px rgba(0, 166, 62, 0.2); }
        h2 { color: #00a63e; border-bottom: 1px solid #00a63e; padding-bottom: 10px; text-transform: uppercase; font-size: 1.2rem; letter-spacing: 2px; }
        
        .input-group { margin: 25px 0; text-align: left; }
        label { font-size: 0.75rem; color: #00a63e; display: block; margin-bottom: 8px; font-weight: bold; }
        
        .secure-input {
            width: 100%;
            padding: 15px;
            background: #050810;
            border: 1px solid #00a63e;
            color: #00ff41;
            font-size: 1.4rem;
            text-align: center;
            letter-spacing: 3px;
            box-sizing: border-box;
            outline: none;
            font-family: 'Courier New', monospace;
        }
        .secure-input:focus { box-shadow: 0 0 15px rgba(0, 255, 65, 0.3); border-color: #00ff41; }

        .btn-verify { background: #00a63e; color: #000; border: none; padding: 18px; width: 100%; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 1rem; transition: 0.3s; margin-top: 10px; }
        .btn-verify:hover { background: #00ff41; box-shadow: 0 0 20px #00ff41; }
        
        .error-msg { color: #ff3232; background: rgba(255,0,0,0.1); padding: 12px; border: 1px solid #ff3232; margin-bottom: 20px; font-size: 0.85rem; font-weight: bold; }
    </style>
</head>
<body>

<div class="secure-container">
    <h2>AUTENTICAÇÃO DE NÍVEL 2</h2>
    
    <?php if($error): ?> <div class="error-msg"><?= $error ?></div> <?php endif; ?>

    <form method="POST" id="secureForm">
        <?= csrf_field(); ?>

        <div class="input-group">
            <label>1. CÓDIGO DE E-MAIL (6 DÍGITOS)</label>
            <input type="text" name="code_2fa" class="secure-input" placeholder="000000" maxlength="6" autocomplete="off" required>
        </div>

        <div class="input-group">
            <label>2. SECURE ID ESTRUTURADO (V-S-G)</label>
            <input type="text" id="secure_id_input" name="secure_id" class="secure-input" value="V____-S____-G____" autocomplete="off" required>
        </div>

        <button type="submit" class="btn-verify">Validar Credenciais</button>
    </form>
</div>

<script>
const input = document.getElementById('secure_id_input');

input.addEventListener('input', function(e) {
    let val = e.target.value.replace(/[^0-9]/g, ''); 
    let result = "V";
    
    result += (val.substring(0, 4) + "____").substring(0, 4) + "-S";
    result += (val.substring(4, 8) + "____").substring(0, 4) + "-G";
    result += (val.substring(8, 12) + "____").substring(0, 4);
    
    e.target.value = result;

    let pos = val.length;
    if (pos <= 4) pos += 1;
    else if (pos <= 8) pos += 3;
    else pos += 5;
    
    setTimeout(() => e.target.setSelectionRange(pos, pos), 0);
});

input.addEventListener('keydown', function(e) {
    if (e.key === "Backspace" && (input.selectionStart === 1 || input.selectionStart === 7 || input.selectionStart === 13)) {
        e.preventDefault();
    }
});

input.addEventListener('focus', () => {
    setTimeout(() => {
        let val = input.value.replace(/[^0-9]/g, '');
        let pos = val.length === 0 ? 1 : (val.length <= 4 ? val.length + 1 : (val.length <= 8 ? val.length + 3 : val.length + 5));
        input.setSelectionRange(pos, pos);
    }, 0);
});
</script>

</body>
</html>