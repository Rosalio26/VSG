<?php
define('IS_ADMIN_PAGE', true);
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

/* ================= 1. TRAVA DE ACESSO RESTRITO ================= */
if (!isset($_SESSION['temp_admin_auth'])) {
    header("Location: ../../registration/login/login.php?error=session_invalid");
    exit;
}

$allowed_roles = ['admin', 'superadmin'];
if (!in_array($_SESSION['temp_admin_auth']['role'], $allowed_roles)) {
    unset($_SESSION['temp_admin_auth']);
    header("Location: ../../registration/login/login.php?error=unauthorized_access");
    exit;
}

$admin_id = $_SESSION['temp_admin_auth']['user_id'];
$ip_address = $_SERVER['REMOTE_ADDR'];
$error = "";

/* ================= 2. CONSULTA TENTATIVAS VIA LOGS ================= */
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

/* ================= 3. PROCESSAMENTO DO FORMULÁRIO ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_validate($_POST['csrf'] ?? '')) {
        $secure_id = strtoupper(trim($_POST['secure_id']));

        // Busca dados para validação (email_token removido da lógica de verificação)
        $stmt = $mysqli->prepare("SELECT secure_id_hash, status FROM users WHERE id = ?");
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin['status'] === 'blocked') {
            session_destroy();
            die("SISTEMA EM LOCKDOWN: Acesso bloqueado por violação de protocolo.");
        }

        // Validação Apenas do Secure ID (V-S-G)
        if (!password_verify($secure_id, $admin['secure_id_hash'])) {
            
            $log_fail = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES (?, 'SECURE_ID_FAIL', ?)");
            $log_fail->bind_param('is', $admin_id, $ip_address);
            $log_fail->execute();

            $current_attempts++; 

            if ($current_attempts >= 2) {
                // BLOQUEIO CRÍTICO
                $mysqli->query("UPDATE users SET status = 'blocked' WHERE id = $admin_id");
                $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ($admin_id, 'ACCOUNT_LOCKDOWN_FATAL', '$ip_address')");
                
                unset($_SESSION['temp_admin_auth']);
                header("Location: ../../registration/login/login.php?error=lockdown");
                exit;
            } else {
                $error = "SECURE ID INCORRETO! Resta apenas 1 tentativa antes do bloqueio total.";
            }
        } else {
            // SUCESSO FINAL
            $_SESSION['auth'] = $_SESSION['temp_admin_auth'];
            $_SESSION['auth']['login_time'] = time(); 
            
            unset($_SESSION['temp_admin_auth']);
            // Limpa o token de e-mail por segurança, embora não tenha sido usado
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0a0f1a; color: #fff; font-family: 'Courier New', monospace; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .secure-container { background: #111b2d; padding: 40px; border: 2px solid #00a63e; border-radius: 12px; width: 450px; text-align: center; box-shadow: 0 0 30px rgba(0, 166, 62, 0.2); position: relative; }
        h2 { color: #00a63e; border-bottom: 1px solid #00a63e; padding-bottom: 15px; text-transform: uppercase; font-size: 1.1rem; letter-spacing: 2px; }
        .input-group { margin: 25px 0; text-align: left; }
        label { font-size: 0.7rem; color: #00a63e; display: block; margin-bottom: 8px; font-weight: bold; }
        .secure-input { width: 100%; padding: 15px; background: #050810; border: 1px solid #00a63e; color: #00ff41; font-size: 1.4rem; text-align: center; letter-spacing: 3px; box-sizing: border-box; outline: none; border-radius: 6px; }
        .btn-verify { background: #00a63e; color: #000; border: none; padding: 18px; width: 100%; font-weight: bold; cursor: pointer; text-transform: uppercase; border-radius: 6px; transition: 0.3s; }
        .btn-verify:hover { background: #00ff41; box-shadow: 0 0 20px #00ff41; }
        .error-msg { color: #ff3232; background: rgba(255,0,0,0.1); padding: 12px; border: 1px solid #ff3232; margin-bottom: 20px; font-size: 0.8rem; }
        .leaf-icon { position: absolute; top: -30px; left: 50%; transform: translateX(-50%); background: #111b2d; padding: 10px; border-radius: 50%; border: 2px solid #00a63e; color: #00a63e; font-size: 2rem; }
    </style>
</head>
<body>

<div class="secure-container">
    <div class="leaf-icon"><i class="fa-solid fa-leaf"></i></div>
    <h2>PROTOCOLO DE ACESSO</h2>
    
    <?php if($error): ?> <div class="error-msg"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?></div> <?php endif; ?>

    <form method="POST" id="secureForm">
        <?= csrf_field(); ?>

        <div class="input-group">
            <label><i class="fa-solid fa-key"></i> SECURE ID ESTRUTURADO (V-S-G)</label>
            <input type="text" id="secure_id_input" name="secure_id" class="secure-input" value="V____-S____-G____" autocomplete="off" required>
        </div>

        <button type="submit" class="btn-verify">AUTENTICAR ACESSO <i class="fa-solid fa-shield-check"></i></button>
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
    if (pos <= 4) pos += 1; else if (pos <= 8) pos += 3; else pos += 5;
    setTimeout(() => e.target.setSelectionRange(pos, pos), 0);
});
</script>

</body>
</html>