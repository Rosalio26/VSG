<?php
/**
 * ARQUIVO: admin/system/force_password_change.php
 * Página de renovação obrigatória quando senha passa de 48h sem renovação
 */

session_start();
require_once '../../../../registration/includes/db.php';
require_once '../../../../registration/includes/security.php';
require_once '../../../../registration/includes/mailer.php';

// Verifica se foi redirecionado corretamente
if (!isset($_SESSION['force_password_renewal']) || !isset($_SESSION['temp_admin_auth'])) {
    header("Location: ../../../../registration/login/login.php");
    exit;
}

$admin = $_SESSION['temp_admin_auth'];
$adminId = $admin['user_id'];
$adminName = $admin['nome'];
$adminEmail = $admin['email'];
$adminRole = $admin['role'];

// Busca dados atualizados
$stmt = $mysqli->prepare("SELECT password_changed_at FROM users WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

$lastChange = strtotime($userData['password_changed_at']);
$timeSinceChange = time() - $lastChange;
$hoursSince = floor($timeSinceChange / 3600);

// Processa renovação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_password'])) {
    try {
        // Gera nova senha
        function generateSecurePassword($length = 10) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*";
            $password = "";
            for ($i = 0; $i < $length; $i++) {
                $password .= $chars[random_int(0, strlen($chars) - 1)];
            }
            return $password;
        }
        
        $newPassword = generateSecurePassword(10);
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $mysqli->begin_transaction();
        
        // Atualiza senha
        $stmt = $mysqli->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $newHash, $adminId);
        $stmt->execute();
        
        // Registra no log
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt_log = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address, details) VALUES (?, 'FORCED_PASSWORD_RENEWAL_COMPLETED', ?, '48h+ sem renovação')");
        $stmt_log->bind_param("is", $adminId, $ip_address);
        $stmt_log->execute();
        
        $mysqli->commit();
        
        // Envia email
        $emailSent = enviarEmailVisionGreen($adminEmail, $adminName, $newPassword);
        
        // Salva resultado para mostrar
        $_SESSION['renewal_success'] = true;
        $_SESSION['new_password'] = $newPassword;
        $_SESSION['email_sent'] = $emailSent;
        
        // Libera sessão de renovação forçada
        unset($_SESSION['force_password_renewal']);
        $_SESSION['auth'] = $_SESSION['temp_admin_auth'];
        unset($_SESSION['temp_admin_auth']);
        
        // Redireciona para continuar o login (Secure ID)
        header("Location: ../../verify_secure_access.php?renewed=1");
        exit;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $error = "Erro ao renovar senha: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renovação Obrigatória - VisionGreen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header {
            background: linear-gradient(135deg, #ff4d4d 0%, #ff1a1a 100%);
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(
                45deg,
                rgba(0,0,0,0.1),
                rgba(0,0,0,0.1) 10px,
                transparent 10px,
                transparent 20px
            );
            animation: slide 20s linear infinite;
        }
        
        @keyframes slide {
            0% { transform: translateX(0); }
            100% { transform: translateX(28.28px); }
        }
        
        .header i {
            font-size: 4rem;
            color: #fff;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .header h1 {
            color: #fff;
            font-size: 1.8rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }
        
        .content {
            padding: 40px;
        }
        
        .alert {
            background: rgba(255,77,77,0.1);
            border-left: 4px solid #ff4d4d;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .alert-title {
            color: #ff4d4d;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-text {
            color: #aaa;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .info-box {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #666;
            font-size: 0.85rem;
        }
        
        .info-value {
            color: #fff;
            font-weight: 600;
        }
        
        .role-badge {
            background: <?= $adminRole === 'superadmin' ? '#ffcc00' : '#00ff88' ?>;
            color: #000;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 900;
        }
        
        .time-expired {
            color: #ff4d4d;
            font-weight: 900;
            font-size: 1.2rem;
        }
        
        .actions {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            flex: 1;
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-renew {
            background: #00ff88;
            color: #000;
        }
        
        .btn-renew:hover {
            background: #00cc6a;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,255,136,0.3);
        }
        
        .btn-logout {
            background: rgba(255,255,255,0.05);
            color: #fff;
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .warning-list {
            background: rgba(255,204,0,0.05);
            border: 1px solid rgba(255,204,0,0.2);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .warning-list ul {
            list-style: none;
            padding: 0;
        }
        
        .warning-list li {
            color: #aaa;
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
        }
        
        .warning-list li:before {
            content: '⚠️';
            position: absolute;
            left: 0;
        }
        
        .error-message {
            background: rgba(255,77,77,0.2);
            color: #ff4d4d;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fa-solid fa-shield-halved"></i>
            <h1>🚨 RENOVAÇÃO OBRIGATÓRIA</h1>
            <p>Protocolo de Segurança Avançado</p>
        </div>
        
        <div class="content">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fa-solid fa-exclamation-triangle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <div class="alert">
                <div class="alert-title">
                    <i class="fa-solid fa-clock"></i>
                    SENHA EXPIRADA HÁ MAIS DE 48 HORAS
                </div>
                <div class="alert-text">
                    Sua senha não foi renovada dentro do período de segurança estabelecido. 
                    Por medidas de proteção, você deve renovar sua senha antes de continuar.
                </div>
            </div>
            
            <div class="info-box">
                <div class="info-item">
                    <span class="info-label">Administrador:</span>
                    <span class="info-value"><?= htmlspecialchars($adminName) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Role:</span>
                    <span class="role-badge"><?= strtoupper($adminRole) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= htmlspecialchars($adminEmail) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Última Renovação:</span>
                    <span class="info-value"><?= date('d/m/Y H:i', $lastChange) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tempo Decorrido:</span>
                    <span class="time-expired"><?= $hoursSince ?>h</span>
                </div>
            </div>
            
            <div class="warning-list">
                <ul>
                    <li>Nova senha será gerada automaticamente</li>
                    <li>Email será enviado para <?= htmlspecialchars($adminEmail) ?></li>
                    <li>A senha atual será invalidada imediatamente</li>
                    <li>Use a nova senha no próximo login</li>
                </ul>
            </div>
            
            <form method="POST">
                <div class="actions">
                    <button type="submit" name="renew_password" class="btn btn-renew">
                        <i class="fa-solid fa-rotate"></i>
                        Renovar Senha Agora
                    </button>
                    <a href="../../../../registration/login/logout.php" class="btn btn-logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        Sair
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>