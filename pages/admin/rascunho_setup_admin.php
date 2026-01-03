<?php
// Impedir cache por segurança
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

require_once '../../registration/includes/db.php';

// Função para gerar o Secure ID estruturado
function generateSecureID() {
    $n1 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $n2 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $n3 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    return "V$n1-S$n2-G$n3";
}

$secure_id_original = generateSecureID();
$secure_id_hash = password_hash($secure_id_original, PASSWORD_BCRYPT);

$email = "admin@visiongreen.com";
$password_inicial = "Vsg@2026!Admin"; 
$hash_senha = password_hash($password_inicial, PASSWORD_BCRYPT);

$mysqli->begin_transaction();

try {
    // Limpa registros anteriores
    $mysqli->query("DELETE FROM users WHERE role = 'superadmin' OR (role = 'admin' AND email = '$email')");

    // Cadastro com type 'admin'
    $stmt = $mysqli->prepare("
        INSERT INTO users (
            nome, email, telefone, password_hash, secure_id_hash, 
            type, role, status, public_id, email_verified_at, registration_step, password_changed_at
        ) VALUES ('Super Admin', ?, '000000000', ?, ?, 'admin', 'superadmin', 'active', '00000000C', NOW(), 'completed', NOW())
    ");
    
    $stmt->bind_param("sss", $email, $hash_senha, $secure_id_hash);
    $stmt->execute();
    $mysqli->commit();

    // Conteúdo do arquivo de backup que será baixado
    $backup_content = "=== VISIONGREEN ADMIN ACCESS DETAILS ===\n";
    $backup_content .= "DATA: " . date('d/m/Y H:i:s') . " (UTC)\n";
    $backup_content .= "----------------------------------------\n";
    $backup_content .= "EMAIL: $email\n";
    $backup_content .= "SENHA: $password_inicial\n";
    $backup_content .= "SECURE ID: $secure_id_original\n";
    $backup_content .= "----------------------------------------\n";
    $backup_content .= "AVISO: GUARDE ESTE ARQUIVO EM LOCAL SEGURO (OFFLINE).\n";
    $backup_content .= "ESTE ARQUIVO SE AUTO-DESTRUIRÁ NO SERVIDOR APÓS O DOWNLOAD.";

    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>VisionGreen - Security Setup</title>
        <style>
            body { background: #000; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; overflow: hidden; }
            .terminal { background:#000; color:#0f0; padding:40px; font-family:monospace; border:5px solid #00a63e; max-width:700px; width: 90%; line-height:1.6; box-shadow: 0 0 20px rgba(0, 166, 62, 0.5); }
            .highlight { color:#fff; background:#222; padding:5px; border:1px dashed #0f0; font-size: 20px; display: inline-block; margin-top: 5px; }
            .btn-download { background: #00a63e; color: #fff; border: none; padding: 15px 25px; font-family: monospace; font-weight: bold; cursor: pointer; margin-top: 20px; transition: 0.3s; text-decoration: none; display: inline-block; }
            .btn-download:hover { background: #00ff5f; color: #000; }
            .warning { color: red; font-weight: bold; }
            .timer { font-size: 25px; color: #fff; }
        </style>
    </head>
    <body>
        <div class="terminal">
            <h2>[VISIONGREEN SECURITY CORE]</h2>
            <p>ADMINISTRADOR CRIADO COM SUCESSO.</p>
            
            <p>ID DE SEGURANÇA: <br><strong class="highlight"><?php echo $secure_id_original; ?></strong></p>
            <p>SENHA ATUAL: <br><strong class="highlight"><?php echo $password_inicial; ?></strong></p>
            
            <a href="data:text/plain;charset=utf-8,<?php echo rawurlencode($backup_content); ?>" 
               download="VisionGreen_Admin_Keys.txt" 
               class="btn-download" id="downloadBtn">
               BAIXAR CHAVES DE ACESSO (.TXT)
            </a>

            <hr style="border:0; border-top:1px solid #00a63e; margin: 20px 0;">
            <p class="warning">SISTEMA EM AUTO-DESTRUIÇÃO. REDIRECIONANDO EM <span id="secs" class="timer">20</span>s...</p>
        </div>

        <script>
            // Força o download automático ao carregar a página
            window.onload = function() {
                const link = document.createElement("a");
                link.href = "data:text/plain;charset=utf-8,<?php echo rawurlencode($backup_content); ?>";
                link.download = "VisionGreen_Admin_Keys.txt";
                link.click();
            };

            // Bloqueio de navegação
            history.pushState(null, null, location.href);
            window.onpopstate = function () { history.go(1); };

            let count = 20; // Aumentei para 20s para dar tempo do download e cópia
            const counter = setInterval(() => {
                count--;
                document.getElementById('secs').textContent = count;
                if (count <= 0) {
                    clearInterval(counter);
                    window.location.replace("../../registration/login/login.php");
                }
            }, 1000);
        </script>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->rollback();
    die("<div style='color:red; font-family:monospace; padding:20px;'>ERRO: " . $e->getMessage() . "</div>");
}

unlink(__FILE__); 
exit;
?>