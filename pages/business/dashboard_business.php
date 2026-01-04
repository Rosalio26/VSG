<?php
session_start();
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

/* ================= 1. SEGURANÇA & DADOS ================= */
if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];

// Buscamos dados do perfil e algumas métricas fictícias (que depois você ligará ao banco)
$stmt = $mysqli->prepare("
    SELECT u.nome, u.public_id, b.logo_path, b.status_documentos, b.motivo_rejeicao, b.business_type
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    WHERE u.id = ? LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$statusDoc = $user['status_documentos'] ?? 'pendente';
$uploadBase = "../../registration/uploads/business/";

// Simulação de Métricas (Aqui você faria COUNT e SUM nas suas tabelas de vendas/produtos futuramente)
$vendasMes = 12540.50;
$crescimentoVendas = 12.5; // +12.5%
$produtosAtivos = 48;
$novosProdutosSemana = 5;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionGreen Business - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #111827;
            --bg-deep: #0b0f19;
            --card-bg: #1f2937;
            --vg-green: #00a63e;
            --vg-neon: #4ade80;
            --accent-blue: #3b82f6;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --danger: #ff3232;
        }

        body { background-color: var(--bg-deep); font-family: 'Inter', system-ui, sans-serif; margin: 0; color: var(--text-main); display: flex; }

        /* Sidebar Moderna */
        .sidebar { width: 260px; background: var(--bg-dark); height: 100vh; position: fixed; border-right: 1px solid #374151; padding: 20px; display: flex; flex-direction: column; }
        .sidebar-brand { font-size: 1.5rem; font-weight: 800; color: var(--vg-green); margin-bottom: 40px; display: flex; align-items: center; gap: 10px; }
        
        .nav-menu { flex-grow: 1; list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link { 
            display: flex; align-items: center; gap: 12px; padding: 12px 16px; 
            color: var(--text-muted); text-decoration: none; border-radius: 10px; 
            transition: 0.3s; font-weight: 500;
        }
        .nav-link:hover, .nav-link.active { background: #374151; color: var(--vg-neon); }
        .nav-link.active { border-left: 4px solid var(--vg-green); }

        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 30px; }
        
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .profile-section { display: flex; align-items: center; gap: 15px; }
        .mini-logo { width: 45px; height: 45px; border-radius: 10px; border: 2px solid var(--card-bg); }

        /* Grid de Status/Kpis */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: var(--card-bg); padding: 20px; border-radius: 16px; border: 1px solid #374151; }
        .kpi-header { display: flex; justify-content: space-between; color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 15px; }
        .kpi-value { font-size: 1.8rem; font-weight: 700; margin-bottom: 5px; }
        .kpi-trend { font-size: 0.85rem; display: flex; align-items: center; gap: 5px; }
        .trend-up { color: var(--vg-neon); }
        .trend-down { color: var(--danger); }

        /* Status de Documentação Card */
        .status-banner { 
            background: rgba(59, 130, 246, 0.1); border: 1px solid var(--accent-blue); 
            padding: 20px; border-radius: 16px; margin-bottom: 30px; display: flex; 
            align-items: center; justify-content: space-between;
        }
        .status-info { display: flex; align-items: center; gap: 15px; }
        .badge-status { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        
        /* Botões */
        .btn-action { background: var(--vg-green); color: black; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 166, 62, 0.3); }

        .logout-btn { color: var(--danger); text-decoration: none; font-weight: bold; margin-top: auto; padding: 10px; border: 1px solid var(--danger); border-radius: 8px; text-align: center; }
        .logout-btn:hover { background: var(--danger); color: white; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-leaf"></i> VisionGreen
    </div>
    
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="#" class="nav-link active"><i class="fas fa-chart-pie"></i> Dashboard</a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link"><i class="fas fa-file-contract"></i> Status da Conta</a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link"><i class="fas fa-box-open"></i> Meus Produtos</a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link"><i class="fas fa-store"></i> Minha Loja</a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link"><i class="fas fa-cog"></i> Definições</a>
        </li>
    </ul>

    <a href="../../registration/login/logout.php" class="logout-btn">SAIR</a>
</aside>

<main class="main-content">
    <header class="top-bar">
        <div>
            <h1 style="margin:0; font-size: 1.4rem;">Painel de Negócios</h1>
            <p style="color: var(--text-muted); margin: 5px 0 0;">Bem-vindo, <?= htmlspecialchars($user['nome']) ?></p>
        </div>
        
        <div class="profile-section">
            <div style="text-align: right;">
                <span style="display:block; font-weight:bold; font-size:0.9rem;"><?= htmlspecialchars($user['public_id']) ?></span>
                <small style="color:var(--text-muted)"><?= strtoupper($user['business_type']) ?></small>
            </div>
            <?php if ($user['logo_path']): ?>
                <img src="<?= $uploadBase . $user['logo_path'] ?>" class="mini-logo">
            <?php else: ?>
                <div class="mini-logo" style="background:var(--card-bg); display:flex; align-items:center; justify-content:center;"><i class="fas fa-building"></i></div>
            <?php endif; ?>
        </div>
    </header>

    <div class="status-banner">
        <div class="status-info">
            <i class="fas fa-shield-halved" style="font-size: 2rem; color: var(--accent-blue);"></i>
            <div>
                <h4 style="margin:0;">Status de Conformidade Legal</h4>
                <p style="margin:5px 0 0; font-size: 0.85rem; color: var(--text-muted);">Verificação obrigatória VisionGreen</p>
            </div>
        </div>
        <div>
            <?php if($statusDoc === 'aprovado'): ?>
                <span class="badge-status" style="background:#064e3b; color:#4ade80;">CONTA VERIFICADA ✅</span>
            <?php elseif($statusDoc === 'rejeitado'): ?>
                <span class="badge-status" style="background:#7f1d1d; color:#f87171;">DOCUMENTOS REJEITADOS ❌</span>
            <?php else: ?>
                <span class="badge-status" style="background:#451a03; color:#fbbf24;">EM AUDITORIA ⏳</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-header">
                <span>Vendas este mês</span>
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="kpi-value">MT <?= number_format($vendasMes, 2, ',', '.') ?></div>
            <div class="kpi-trend trend-up">
                <i class="fas fa-arrow-up"></i> <?= $crescimentoVendas ?>% <span style="color:var(--text-muted)">vs mês anterior</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-header">
                <span>Produtos Ativos</span>
                <i class="fas fa-tag"></i>
            </div>
            <div class="kpi-value"><?= $produtosAtivos ?></div>
            <div class="kpi-trend trend-up">
                <i class="fas fa-plus"></i> <?= $novosProdutosSemana ?> novos <span style="color:var(--text-muted)">esta semana</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-header">
                <span>Alcance da Marca</span>
                <i class="fas fa-users"></i>
            </div>
            <div class="kpi-value">1,482</div>
            <div class="kpi-trend trend-up">
                <i class="fas fa-chart-line"></i> +4% <span style="color:var(--text-muted)">visitas</span>
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <div class="kpi-card" style="min-height: 200px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h3 style="margin:0;"><i class="fas fa-boxes-stacked"></i> Gestão de Produtos</h3>
                <button class="btn-action">+ Adicionar Novo</button>
            </div>
            <p style="color:var(--text-muted); font-size: 0.9rem;">Gerencie seu inventário, adicione novos produtos ou remova itens descontinuados.</p>
        </div>

        <div class="kpi-card" style="background: linear-gradient(135deg, #1f2937, #111827); border-color: var(--vg-green);">
            <h3 style="margin:0 0 10px 0; color: var(--vg-neon);">Public ID</h3>
            <p style="font-size: 0.8rem; color: var(--text-muted);">Use este código para identificação em transações oficiais.</p>
            <div style="background:var(--bg-deep); padding: 15px; border-radius: 8px; font-family:monospace; font-size: 1.2rem; text-align:center; letter-spacing: 2px;">
                <?= $user['public_id'] ?>
            </div>
        </div>
    </div>

</main>

</body>
</html>