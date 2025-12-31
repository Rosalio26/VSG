<?php
session_start();
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

/* ================= 1. BLOQUEIO DE LOGIN E TIPO DE CONTA ================= */
if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];

/* ================= 2. BUSCAR DADOS COMPLETOS (JOIN) ================= */
// Adicionado b.license_path para visualizar o alvará
$stmt = $mysqli->prepare("
    SELECT 
        u.nome, u.apelido, u.email, u.email_corporativo, u.telefone, 
        u.public_id, u.status, u.registration_step, 
        u.email_verified_at, u.created_at, u.type,
        b.tax_id, b.business_type, b.country, b.region, b.city, b.logo_path, b.license_path
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['type'] !== 'company') {
    header("Location: ../person/dashboard_person.php");
    exit;
}

/* ================= 3. BLOQUEIOS DE SEGURANÇA ================= */
if (!$user['email_verified_at']) {
    header("Location: ../../registration/process/verify_email.php");
    exit;
}

if (!$user['public_id']) {
    header("Location: ../../registration/register/gerar_uid.php");
    exit;
}

if ($user['status'] === 'blocked') {
    die('Sua conta empresarial está suspensa. Contacte o suporte da VisionGreen.');
}

// Configuração de Caminhos de Upload
$uploadBase = "../../registration/uploads/business/";

$statusTraduzido = [
    'active' => 'Ativa ✅',
    'pending' => 'Pendente ⏳',
    'blocked' => 'Bloqueada ❌'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Business - VisionGreen</title>
    <link rel="stylesheet" href="../assets/style/geral.css">
    <style>
        :root {
            --color-main: #2563eb; 
            --color-success: #00a63e;
            --color-dark: #101828;
        }
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .sidebar { width: 250px; background: var(--color-dark); height: 100vh; position: fixed; color: white; padding: 20px; }
        .content { margin-left: 280px; padding: 30px; }
        
        .header-business { 
            background: #fff; padding: 20px; border-radius: 12px; 
            display: flex; justify-content: space-between; align-items: center; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
        }

        .grid-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        
        .card { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #e5e7eb; }
        .card h2 { font-size: 1.1rem; color: var(--color-main); margin-top: 0; border-bottom: 2px solid #f0f4f8; padding-bottom: 10px; }
        
        .uid-badge { background: #101828; color: #93c5fd; padding: 10px 15px; border-radius: 8px; font-family: monospace; font-size: 1.2rem; font-weight: bold; }
        
        .info-list { list-style: none; padding: 0; margin: 0; }
        .info-list li { margin-bottom: 12px; font-size: 0.95rem; border-bottom: 1px solid #f9fafb; padding-bottom: 8px; }
        .info-list li strong { color: #64748b; font-size: 0.8rem; text-transform: uppercase; display: block; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; background: #dcfce7; color: #166534; }
        
        .company-logo { width: 80px; height: 80px; border-radius: 10px; object-fit: cover; border: 2px solid #f0f4f8; margin-bottom: 15px; }
        
        .btn-doc { 
            display: inline-flex; align-items: center; gap: 8px;
            background: #f8fafc; color: #334155; padding: 8px 12px; 
            border-radius: 6px; border: 1px solid #e2e8f0; 
            text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: 0.2s;
        }
        .btn-doc:hover { background: #f1f5f9; border-color: var(--color-main); color: var(--color-main); }

        .logout-btn { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; padding: 10px 20px; border-radius: 6px; cursor: pointer; transition: 0.3s; font-weight: bold; }
        .logout-btn:hover { background: #f87171; color: white; }
    </style>
</head>
<body>

<aside class="sidebar">
    <h2>VisionGreen <br><small style="font-size: 10px; opacity: 0.6;">BUSINESS PANEL</small></h2>
    <hr style="opacity: 0.1; margin: 20px 0;">
    <nav>
        <p>📊 Início</p>
        <p>🏢 Perfil da Empresa</p>
        <p>📄 Documentos</p>
        <p>⚙️ Configurações</p>
    </nav>
</aside>

<div class="content">
    <header class="header-business">
        <div style="display: flex; align-items: center; gap: 20px;">
            <?php if (!empty($user['logo_path'])): ?>
                <img src="<?= $uploadBase . $user['logo_path'] ?>" class="company-logo" alt="Logo">
            <?php endif; ?>
            <div>
                <h1 style="margin: 0; font-size: 1.5rem;">Olá, <?= htmlspecialchars($user['nome']) ?></h1>
                <p style="margin: 5px 0 0; color: #64748b; font-size: 0.9rem;">Gerencie os dados da sua organização abaixo.</p>
            </div>
        </div>
        <div class="uid-badge">
            <?= htmlspecialchars($user['public_id']) ?>
        </div>
    </header>

    <div class="grid-info">
        <div class="card">
            <h2>🏢 Detalhes da Organização</h2>
            <ul class="info-list">
                <li><strong>Razão Social:</strong> <?= htmlspecialchars($user['nome']) ?></li>
                <li><strong>Tipo de Negócio:</strong> <?= strtoupper(htmlspecialchars($user['business_type'])) ?></li>
                <li><strong>Localização:</strong> <?= htmlspecialchars($user['city']) ?>, <?= htmlspecialchars($user['region']) ?> - <?= htmlspecialchars($user['country']) ?></li>
                
                <li>
                    <strong>Documento Fiscal (Tax ID):</strong>
                    <?php 
                        $tax = $user['tax_id'];
                        if (strpos($tax, 'FILE:') === 0): 
                            $taxFile = str_replace('FILE:', '', $tax);
                    ?>
                        <a href="<?= $uploadBase . $taxFile ?>" target="_blank" class="btn-doc">👁️ Ver Comprovante Fiscal</a>
                    <?php else: ?>
                        <?= htmlspecialchars($tax ?: 'Não informado') ?>
                    <?php endif; ?>
                </li>
                
                <li>
                    <strong>Alvará / Licença:</strong><br>
                    <?php if (!empty($user['license_path'])): ?>
                        <a href="<?= $uploadBase . $user['license_path'] ?>" target="_blank" class="btn-doc">📄 Abrir Alvará de Funcionamento</a>
                    <?php else: ?>
                        <span style="color: #94a3b8;">Não anexado</span>
                    <?php endif; ?>
                </li>
            </ul>
        </div>

        <div class="card">
            <h2>🔐 Identidade VisionGreen</h2>
            <ul class="info-list">
                <li><strong>E-mail Corporativo Interno:</strong> <span style="color: var(--color-main); font-weight: bold;"><?= htmlspecialchars($user['email_corporativo']) ?></span></li>
                <li><strong>E-mail de Recuperação:</strong> <?= htmlspecialchars($user['email']) ?></li>
                <li><strong>Status da Conta:</strong> <span class="status-badge"><?= $statusTraduzido[$user['status']] ?></span></li>
                <li><strong>Data de Registo:</strong> <?= date('d/m/Y', strtotime($user['created_at'])) ?></li>
            </ul>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: right;">
        <form method="post" action="../../registration/login/logout.php">
            <?= csrf_field(); ?>
            <button type="submit" class="logout-btn">Sair do Sistema</button>
        </form>
    </div>
</div>

</body>
</html>