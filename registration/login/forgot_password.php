<?php
require_once '../includes/security.php';
require_once '../includes/db.php';

// Caso o usuário já esteja logado, redireciona para o dashboard correto
if (!empty($_SESSION['auth']['user_id'])) {
    $userId = (int) $_SESSION['auth']['user_id'];
    
    $stmt = $mysqli->prepare("SELECT type FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res && $res['type'] === 'company') {
        header("Location: ../../pages/business/dashboard_business.php");
    } else {
        header("Location: ../../pages/person/dashboard_person.php");
    }
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
        :root {
            --color-primary: #28a745;
            --color-bg: #f0f2f5;
        }
        body { font-family: 'Segoe UI', sans-serif; background: var(--color-bg); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { width: 100%; max-width: 400px; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); text-align: center; }
        h2 { color: #333; margin-top: 0; }
        p { color: #666; font-size: 0.9em; margin-bottom: 20px; line-height: 1.5; }
        
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 0.85em; border: 1px solid; text-align: center; }
        .alert-error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }

        /* MUDANÇA: type="text" para suportar identificadores */
        input[type="text"] { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: var(--color-primary); border: none; color: white; border-radius: 6px; cursor: pointer; font-weight: bold; transition: background 0.3s; }
        button:hover { background: #218838; }
        
        .links { margin-top: 20px; font-size: 0.85em; }
        .links a { color: var(--color-primary); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

    <div class="box">
        <h2>Recuperar Senha</h2>
        <p>Informe seu e-mail ou UID. Enviaremos um código de 6 dígitos para o e-mail de recuperação cadastrado.</p>

        <?php 
        if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    switch($_GET['error']) {
                        case 'rate_limit': echo "Muitas solicitações. Aguarde 5 minutos para tentar de novo."; break;
                        case 'csrf': echo "Sessão expirada. Por favor, tente novamente."; break;
                        case 'invalid_id': echo "O identificador digitado não foi reconhecido."; break;
                        case 'mail_fail': echo "Falha ao enviar e-mail. Tente em alguns minutos."; break;
                        default: echo "Ocorreu um erro ao processar o pedido.";
                    }
                ?>
            </div>
        <?php 
        elseif (isset($_GET['info']) && $_GET['info'] === 'locked'): ?>
            <div class="alert alert-info">
                <strong>Conta Bloqueada:</strong> Muitas falhas de senha detectadas. Confirme sua identidade para liberar o acesso.
            </div>
        <?php 
        elseif (isset($_GET['status']) && $_GET['status'] === 'sent'): ?>
            <div class="alert alert-success">
                Se os dados informados estiverem em nossa base, você receberá o código de recuperação no seu e-mail principal em instantes.
            </div>
        <?php endif; ?>

        <form action="send_recovery.php" method="POST" id="formRecovery">
            <?= csrf_field(); ?>
            
            <input type="text" name="identifier" placeholder="E-mail ou UID" required autofocus>
            
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
            btn.innerText = 'Processando...';
        };
    </script>

</body>
</html>