<?php
define('IS_ADMIN_PAGE', true); 
define('REQUIRED_TYPE', 'admin'); 

session_start();
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';
require_once '../../registration/includes/mailer.php'; 

/* ================= 1. SEGURANÇA E ACESSO ================= */
if (!isset($_SESSION['auth']['role']) || $_SESSION['auth']['role'] !== 'superadmin') {
    header("Location: ../dashboard.php?error=acesso_negado");
    exit;
}

$fingerprint = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
if ($_SESSION['secure_fingerprint'] !== $fingerprint) {
    session_destroy();
    header("Location: ../../registration/login/login.php?error=sessao_invalida");
    exit;
}

/* ================= 2. PROCESSAMENTO DO CADASTRO ================= */
$status_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate($_POST['csrf'])) {
    
    $nome = cleanInput($_POST['nome']);
    $apelido = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', cleanInput($_POST['apelido'])));
    $email_pessoal = strtolower(cleanInput($_POST['email_normal']));
    $telefone = cleanInput($_POST['telefone']);
    $uid_gerado = cleanInput($_POST['admin_uid']); // UID vindo do campo gerado via JS (ex: VG-XXXXXX)
    
    // Configuração do E-mail Corporativo (Username)
    $empresa_slug = "visiongreen"; 
    $email_corporativo = $apelido . "@" . $empresa_slug . ".vsg.com";
    
    // IDENTIFICADORES DE SEGURANÇA: 
    // public_id agora termina com 'A' para administradores
    $public_id = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT) . 'A';
    
    $senha_plana = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789!@#$"), 0, 12);
    $hash = password_hash($senha_plana, PASSWORD_BCRYPT);

    // SQL preparado para inserir tanto o Public ID (ID de sistema) quanto o UID (ID Visual)
    $stmt = $mysqli->prepare("INSERT INTO users (public_id, uid, type, role, nome, apelido, email, email_pessoal, telefone, password_hash, status, registration_step, password_changed_at) VALUES (?, ?, 'admin', 'admin', ?, ?, ?, ?, ?, ?, 'active', 'complete', NOW())");
    
    $stmt->bind_param("ssssssss", $public_id, $uid_gerado, $nome, $apelido, $email_corporativo, $email_pessoal, $telefone, $hash);
    
    if ($stmt->execute()) {
        $assunto = "Bem-vindo à Equipe VisionGreen";
        $corpo = "### CREDENCIAIS DE ACESSO VISION GREEN ###\n\n"
               . "Sua conta de Auditor foi criada com sucesso.\n\n"
               . "UID Visual: $uid_gerado\n"
               . "ID de Sistema: $public_id\n"
               . "Login (E-mail Corp): $email_corporativo\n"
               . "Senha Temporária: $senha_plana\n\n"
               . "O código de verificação para logins futuros será enviado para este e-mail pessoal.";
        
        if (enviarEmailVisionGreen($email_pessoal, $nome, $corpo)) {
            $status_msg = "Auditor <strong>$public_id</strong> registrado! Login: <strong>$email_corporativo</strong>. Acessos enviados para o e-mail pessoal.";
        } else {
            $status_msg = "Auditor registrado (ID: $public_id), mas falhou ao enviar o e-mail de notificação.";
        }
    } else {
        $error_msg = "Erro crítico: UID, Apelido ou E-mail já estão em uso no sistema.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>VG OS | Registro de Elite</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-deep: #05070a; --bg-card: #0d1117; --accent: #00a63e;
            --accent-neon: #00ff41; --text-main: #e6edf3; --text-dim: #8b949e;
            --border: #30363d; --danger: #f85149;
        }

        body {
            margin: 0; background: var(--bg-deep); color: var(--text-main);
            font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh;
        }

        .card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; 
            padding: 40px; width: 100%; max-width: 600px; box-shadow: 0 15px 35px rgba(0,0,0,0.7);
        }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full { grid-column: span 2; }

        .label-vg { display: block; font-size: 0.65rem; color: var(--accent); margin-bottom: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        
        .input-dark {
            width: 100%; background: #000; border: 1px solid var(--border); color: #fff;
            padding: 12px; border-radius: 8px; box-sizing: border-box; font-size: 0.9rem;
        }

        .uid-container { display: flex; gap: 10px; }
        .btn-gen { background: transparent; border: 1px solid var(--accent); color: var(--accent); padding: 0 15px; border-radius: 8px; cursor: pointer; transition: 0.3s; }
        .btn-gen:hover { background: var(--accent); color: #000; }

        .corp-preview { 
            background: rgba(0, 166, 62, 0.05); border: 1px dashed var(--accent); 
            padding: 10px; border-radius: 8px; margin-top: 5px; font-family: monospace; font-size: 0.8rem; color: var(--accent-neon);
        }

        .btn-submit {
            width: 100%; background: var(--accent); color: #000; border: none; padding: 15px;
            border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 25px; font-size: 1rem;
        }
    </style>
</head>
<body>

<div class="card">
    <div style="text-align: center; margin-bottom: 30px;">
        <h2 style="color: var(--accent-neon); margin: 0;">REGISTRO <span style="color:#fff">CORE</span></h2>
        <p style="color: var(--text-dim); font-size: 0.75rem;">GERADOR DE CREDENCIAIS DE ALTO NÍVEL (TERMINAL A)</p>
    </div>

    <?php if($status_msg): ?>
        <div style="border: 1px solid var(--accent); color: var(--accent-neon); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem; background: rgba(0,166,62,0.1);">
            <i class="fa-solid fa-shield-check"></i> <?= $status_msg ?>
        </div>
    <?php endif; ?>

    <?php if($error_msg): ?>
        <div style="border: 1px solid var(--danger); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem; background: rgba(248,81,73,0.1);">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        
        <div class="form-grid">
            <div class="form-group full">
                <label class="label-vg">UID do Administrador (Visual)</label>
                <div class="uid-container">
                    <input type="text" name="admin_uid" id="uid_field" class="input-dark" readonly required placeholder="Clique em Gerar">
                    <button type="button" class="btn-gen" onclick="generateUID()">GERAR</button>
                </div>
            </div>

            <div class="form-group">
                <label class="label-vg">Nome Completo</label>
                <input type="text" name="nome" class="input-dark" required>
            </div>

            <div class="form-group">
                <label class="label-vg">Apelido (ID Interno)</label>
                <input type="text" name="apelido" id="apelido_field" class="input-dark" required oninput="updateCorpEmail()">
            </div>

            <div class="form-group full">
                <label class="label-vg">E-mail Corporativo (Auto-gerado)</label>
                <div id="corp_preview" class="corp-preview">aguardando apelido...</div>
            </div>

            <div class="form-group">
                <label class="label-vg">E-mail Pessoal (Verificação)</label>
                <input type="email" name="email_normal" class="input-dark" required placeholder="Ex: joao@gmail.com">
            </div>

            <div class="form-group">
                <label class="label-vg">Telefone</label>
                <input type="text" name="telefone" class="input-dark" id="phone_field" required placeholder="+244">
            </div>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fa-solid fa-microchip"></i> FINALIZAR E ENVIAR ACESSOS
        </button>
    </form>

    <a href="../dashboard.php" style="display: block; text-align: center; margin-top: 20px; color: var(--text-dim); text-decoration: none; font-size: 0.75rem;">
        <i class="fa-solid fa-arrow-left"></i> Voltar ao Centro de Comando
    </a>
</div>

<script>
    function generateUID() {
        const chars = 'ABCDEF0123456789';
        let uid = 'VG-';
        for (let i = 0; i < 8; i++) {
            uid += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('uid_field').value = uid;
    }

    function updateCorpEmail() {
        const apelido = document.getElementById('apelido_field').value.toLowerCase().replace(/[^a-z0-9]/g, '');
        const preview = document.getElementById('corp_preview');
        if(apelido) {
            preview.textContent = apelido + "@visiongreen.vsg.com";
        } else {
            preview.textContent = "aguardando apelido...";
        }
    }

    const phone = document.getElementById('phone_field');
    phone.addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g, '');
        if(v.length > 0) e.target.value = '+' + v;
    });
</script>

</body>
</html>