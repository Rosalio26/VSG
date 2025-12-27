<?php
require_once '../includes/security.php';

// Caso o usuário já esteja logado, não faz sentido ele estar aqui
if (!empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../pages/person/dashboard_person.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Conta - VisionGreen</title>
    <link rel="stylesheet" href="../../assets/style/geral.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background:#f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { width: 100%; max-width: 400px; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); text-align: center; }
        h2 { color: #333; margin-top: 0; }
        p { color: #666; font-size: 0.9em; margin-bottom: 20px; line-height: 1.5; }
        
        /* Estilos de Alerta */
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 0.85em; border: 1px solid; text-align: center; }
        .alert-error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }

        input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #28a745; border: none; color: white; border-radius: 6px; cursor: pointer; font-weight: bold; transition: background 0.3s; }
        button:hover { background: #218838; }
        
        .links { margin-top: 20px; font-size: 0.85em; }
        .links a { color: #28a745; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

    <div class="box">
        <h2>Recuperar Senha</h2>
        <p>Enviaremos um código de 6 dígitos para o seu e-mail cadastrado.</p>

        <?php 
        // 1. Tratamento de Erros vindo do send_recovery.php
        if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    switch($_GET['error']) {
                        case 'rate_limit': echo "Muitas solicitações. Aguarde 5 minutos para tentar de novo."; break;
                        case 'csrf': echo "Sessão expirada. Por favor, tente novamente."; break;
                        case 'invalid_email': echo "O e-mail digitado não é válido."; break;
                        case 'mail_fail': echo "Falha ao enviar e-mail. Tente em alguns minutos."; break;
                        default: echo "Ocorreu um erro ao processar o pedido.";
                    }
                ?>
            </div>
        <?php 
        // 2. Mensagem de bloqueio vindo do login.process.php
        elseif (isset($_GET['info']) && $_GET['info'] === 'locked'): ?>
            <div class="alert alert-info">
                <strong>Conta Bloqueada:</strong> Detectamos muitas falhas de senha. Recupere o acesso para desbloquear.
            </div>
        <?php 
        // 3. Decoy de Segurança (Sucesso aparente para e-mails inexistentes)
        elseif (isset($_GET['status']) && $_GET['status'] === 'sent'): ?>
            <div class="alert alert-success">
                Se o e-mail estiver em nossa base, você receberá o código em instantes.
            </div>
        <?php endif; ?>

        <form action="send_recovery.php" method="POST" id="formRecovery">
            <?= csrf_field(); ?>
            
            <input type="email" name="email" placeholder="Digite seu e-mail" required autofocus>
            
            <button type="submit" id="btnSubmit">Solicitar Código</button>
        </form>

        <div class="links">
            <a href="login.php">Voltar para o Login</a>
        </div>
    </div>

    <script>
        document.getElementById('formRecovery').onsubmit = function() {
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerText = 'Enviando...';
        };
    </script>

</body>
</html>