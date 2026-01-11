<?php
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

/**
 * 1. VERIFICAÇÃO DE HIERARQUIA
 */
$checkSuper = $mysqli->query("SELECT id FROM users WHERE role = 'superadmin' LIMIT 1");
$hasSuperAdmin = ($checkSuper->num_rows > 0);

if ($hasSuperAdmin) {
    if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'superadmin') {
        header("Location: ../../registration/login/login.php?error=access_denied");
        exit;
    }
}

$status_msg = "";
$generated_data = null; 

/* ================= 2. PROCESSAMENTO DO REGISTRO ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome             = trim($_POST['nome']);
    $apelido          = strtolower(trim($_POST['apelido']));
    $email_pessoal    = trim($_POST['email']);
    $telefone         = trim($_POST['telefone']);
    $password         = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // Validação: Senha no mínimo 10 dígitos
    if ($password !== $password_confirm) {
        $status_msg = "Erro: As senhas não coincidem.";
    } elseif (strlen($password) < 10) {
        $status_msg = "Erro: A senha precisa ter no mínimo 10 dígitos.";
    } else {
        $roleParaCriar = !$hasSuperAdmin ? 'superadmin' : 'admin';
        $sufixo = !$hasSuperAdmin ? 'S' : 'A';
        
        $randDigits = str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $publicId = $randDigits . $sufixo;
        $email_corporativo = $apelido . ".admin@vsg.com";

        // GERAÇÃO DO SECURE ID (Exatamente 15 caracteres: V0000-S0000-G0000)
        $n1 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $n2 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $n3 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $secure_id_raw = "V{$n1}-S{$n2}-G{$n3}";

        $passHash = password_hash($password, PASSWORD_BCRYPT);
        $secureHash = password_hash($secure_id_raw, PASSWORD_BCRYPT);

        $stmt = $mysqli->prepare("INSERT INTO users (public_id, type, role, nome, apelido, email, email_corporativo, telefone, password_hash, secure_id_hash, status, registration_step, email_verified_at) VALUES (?, 'admin', ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'completed', NOW())");
        $stmt->bind_param("sssssssss", $publicId, $roleParaCriar, $nome, $apelido, $email_pessoal, $email_corporativo, $telefone, $passHash, $secureHash);

        if ($stmt->execute()) {
            $generated_data = [
                'uid' => $publicId,
                'email' => $email_corporativo,
                'secure_id' => $secure_id_raw,
                'nome' => $nome
            ];
            
            if ($hasSuperAdmin && isset($_SESSION['auth']['user_id'])) {
                $admin_id = $_SESSION['auth']['user_id'];
                $action = "CREATED_AUDITOR_" . $publicId;
                $stmt_audit = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES (?, ?, ?)");
                $stmt_audit->bind_param("iss", $admin_id, $action, $_SERVER['REMOTE_ADDR']);
                $stmt_audit->execute();
            }
        } else {
            $status_msg = "Erro ao registrar: " . $mysqli->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>VisionGreen | Registro Administrativo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --accent: #00ff88; --bg: #0a0a0a; }
        body { background: var(--bg); color: white; font-family: 'Plus Jakarta Sans', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        
        /* Card Estilizado */
        .form-card { background: #111; padding: 40px; border-radius: 20px; border: 1px solid #222; width: 100%; max-width: 550px; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        
        /* Inputs com Ícones */
        .input-group { margin-bottom: 20px; position: relative; }
        .input-group label { display: block; font-size: 0.7rem; color: #666; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
        
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper i { position: absolute; left: 15px; color: var(--accent); font-size: 0.9rem; opacity: 0.7; }
        .input-wrapper input { width: 100%; padding: 14px 14px 14px 45px; background: #050505; border: 1px solid #222; color: white; border-radius: 12px; outline: none; box-sizing: border-box; transition: 0.3s; font-size: 0.9rem; }
        .input-wrapper input:focus { border-color: var(--accent); background: #080808; box-shadow: 0 0 15px rgba(0, 255, 136, 0.1); }
        
        /* Textos de Ajuda */
        .helper-text { font-size: 0.65rem; color: #444; margin-top: 5px; display: block; }

        /* Modal e Sucesso */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); backdrop-filter: blur(20px); display: flex; justify-content: center; align-items: center; z-index: 9999; }
        .success-modal { background: #111; border: 1px solid var(--accent); padding: 40px; border-radius: 25px; width: 90%; max-width: 420px; text-align: center; box-shadow: 0 0 50px rgba(0,255,136,0.2); }
        .uid-text { font-size: 2.2rem; font-weight: 800; letter-spacing: 5px; margin: 15px 0; color: white; }
        .secure-badge { background: #000; padding: 15px; border-radius: 12px; border: 1px dashed var(--accent); color: var(--accent); font-family: monospace; font-size: 1.3rem; margin-top: 10px; }
        
        .btn-finish { display: block; width: 100%; padding: 15px; background: var(--accent); color: #000; text-decoration: none; font-weight: 800; border-radius: 12px; margin-top: 20px; text-align: center; text-transform: uppercase; transition: 0.3s; }
        .btn-finish:hover { transform: scale(1.02); filter: brightness(1.1); }

        .btn-download-modal { width: 100%; margin-top: 25px; padding: 14px; background: #1a1a1a; color: white; border: 1px solid #333; border-radius: 12px; cursor: pointer; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.3s; }
        .btn-download-modal:hover { background: #222; border-color: var(--accent); }

        .btn-submit-main { width: 100%; padding: 16px; background: var(--accent); color: black; border: none; border-radius: 12px; font-weight: 800; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.3s; }
        .btn-submit-main:hover { filter: brightness(1.1); box-shadow: 0 5px 20px rgba(0, 255, 136, 0.2); }
    </style>
</head>
<body>

<?php if ($generated_data): ?>
    <div class="modal-overlay">
        <div class="success-modal">
            <div style="background: rgba(0,255,136,0.1); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fa-solid fa-shield-check" style="font-size: 3rem; color: var(--accent);"></i>
            </div>
            <h2 style="letter-spacing: -1px;">ACESSO ATIVADO</h2>
            <p style="color:#666; font-size:0.8rem;">As credenciais de auditoria foram geradas com criptografia de ponta.</p>
            
            <p style="color:#888; font-size:0.65rem; margin-top:25px; font-weight: 700; text-transform: uppercase;">Identificador Único (UID)</p>
            <div class="uid-text"><?= $generated_data['uid'] ?></div>
            
            <p style="color:#888; font-size:0.65rem; font-weight: 700; text-transform: uppercase;">E-mail do Sistema</p>
            <strong style="color:var(--accent); font-size: 1.1rem;"><?= $generated_data['email'] ?></strong>
            
            <p style="color:#888; font-size:0.65rem; margin-top:25px; font-weight: 700; text-transform: uppercase;">Protocolo Secure ID (V-S-G)</p>
            <div class="secure-badge"><?= $generated_data['secure_id'] ?></div>

            <button onclick="downloadInfo()" class="btn-download-modal">
                <i class="fa-solid fa-file-arrow-down"></i> SALVAR BACKUP LOCAL
            </button>
            
            <a href="../../registration/login/login.php" class="btn-finish">Entrar no Sistema <i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>
    <script>
        function downloadInfo() {
            const content = "SISTEMA VISIONGREEN - CREDENCIAIS MESTRE\n--------------------------------------\nNOME: <?= $generated_data['nome'] ?>\nUID: <?= $generated_data['uid'] ?>\nEMAIL: <?= $generated_data['email'] ?>\nSECURE ID: <?= $generated_data['secure_id'] ?>\n--------------------------------------\nAVISO: Não compartilhe estes dados.";
            const blob = new Blob([content], { type: 'text/plain' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'backup_vsg_<?= $generated_data['uid'] ?>.txt';
            a.click();
        }
    </script>
<?php endif; ?>

<div class="reg-admin-wrapper">
    <div class="form-card">
        <div style="text-align:center; margin-bottom:35px;">
            <div style="color: var(--accent); font-size: 2.5rem; margin-bottom: 10px;">
                <i class="fa-solid <?= $hasSuperAdmin ? 'fa-user-gear' : 'fa-leaf' ?>"></i>
            </div>
            <h2 style="margin:0; letter-spacing: -1px;"><?= $hasSuperAdmin ? 'REGISTRAR AUDITOR' : 'REGISTRO SUPER ADMIN' ?></h2>
            <p style="color: #555; font-size: 0.85rem; margin-top: 5px;">
                <?= $hasSuperAdmin ? 'Crie um novo perfil com permissões administrativas.' : 'Configure a conta raiz para ativação total do sistema.' ?>
            </p>
        </div>

        <?php if($status_msg): ?>
            <div style="background:rgba(255,77,77,0.1); color:#ff4d4d; padding:12px; border-radius:12px; margin-bottom:25px; border:1px solid rgba(255,77,77,0.2); text-align:center; font-size: 0.85rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= $status_msg ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="regForm">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="input-group">
                    <label>Nome Completo</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-signature"></i>
                        <input type="text" name="nome" placeholder="Ex: João Silva" required>
                    </div>
                </div>
                <div class="input-group">
                    <label>Apelido</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-at"></i>
                        <input type="text" name="apelido" placeholder="Username" required>
                    </div>
                </div>
            </div>

            <div class="input-group">
                <label>E-mail Pessoal</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" placeholder="admin@exemplo.com" required>
                </div>
                <span class="helper-text">Usado para notificações críticas e recuperação.</span>
            </div>

            <div class="input-group">
                <label>Telefone Pessoal</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-phone"></i>
                    <input type="tel" name="telefone" placeholder="+0..." required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="input-group">
                    <label>Palavra Passe</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" id="pass" placeholder="••••••••••" minlength="10" required>
                    </div>
                </div>
                <div class="input-group">
                    <label>Confirmação Palavra Passe</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user-lock"></i>
                        <input type="password" name="password_confirm" id="pass_conf" placeholder="••••••••••" minlength="10" required>
                    </div>
                </div>
            </div>

            <div style="margin: 10px 0 25px 0;">
                <button type="submit" class="btn-submit-main">
                    <i class="fa-solid fa-key-skeleton"></i> <?= $hasSuperAdmin ? 'GERAR CREDENCIAIS' : 'ATIVAR SISTEMA' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('regForm').onsubmit = function() {
        const p1 = document.getElementById('pass').value;
        const p2 = document.getElementById('pass_conf').value;
        if (p1.length < 10) {
            return false;
        }
        if (p1 !== p2) {
            alert("Atenção: As senhas digitadas não são idênticas.");
            return false;
        }
        return true;
    };
</script>

</body>
</html>