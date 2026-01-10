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

$stmt = $mysqli->prepare("
    SELECT u.nome, u.public_id, b.logo_path, b.status_documentos, b.business_type, b.updated_at 
    FROM users u 
    LEFT JOIN businesses b ON u.id = b.user_id 
    WHERE u.id = ? LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$statusDoc = $user['status_documentos'] ?? 'pendente';
$updatedAt = $user['updated_at'];
$uploadBase = "../../registration/uploads/business/";

/* ================= 2. LÓGICA DE PRAZO (48 HORAS) ================= */
$deadlineTimestamp = 0;
if ($statusDoc === 'rejeitado') {
    $dataRejeicao = new DateTime($updatedAt);
    // Adiciona 48 horas à data de atualização
    $dataLimite = clone $dataRejeicao;
    $dataLimite->modify('+48 hours');
    
    $deadlineTimestamp = $dataLimite->getTimestamp();
    $agoraTimestamp = time();

    if ($agoraTimestamp >= $deadlineTimestamp) {
        header("Location: process/reenviar_documentos.php?status=expired");
        exit;
    }
}

/* ================= 3. LÓGICA DE INTERFACE ================= */
if ($statusDoc === 'aprovado') {
    $verifClass = 'verif-checked';
    $verifIcon  = 'check-circle';
    $verifText  = 'VERIFICADO';
    $statusLabel = "Aprovada";
    $statusColor = "var(--vg-neon)";
} elseif ($statusDoc === 'rejeitado') {
    $verifClass = 'verif-rejected'; 
    $verifIcon  = 'alert-circle';
    $verifText  = 'REJEITADO';
    $statusLabel = "Rejeitada";
    $statusColor = "#ff3232";
} else {
    $verifClass = 'verif-pending';
    $verifIcon  = 'clock';
    $verifText  = 'A VERIFICAR';
    $statusLabel = "Pendente";
    $statusColor = "#ffc107";
}

$tipoBus = strtolower($user['business_type'] ?? 'mei');
$permissoes = [
    'mei'  => ['label' => 'Individual (MEI)', 'color' => '#94a3b8', 'features' => ['dashboard', 'produtos', 'vendas', 'configuracoes']],
    'ltda' => ['label' => 'Limitada (LTDA)', 'color' => '#3b82f6', 'features' => ['dashboard', 'produtos', 'vendas', 'funcionarios', 'status', 'mensagens', 'configuracoes']],
    'sa'   => ['label' => 'Anônima (S.A)', 'color' => 'var(--vg-neon)', 'features' => ['dashboard', 'produtos', 'vendas', 'funcionarios', 'status', 'mensagens', 'configuracoes']]
];
$minhaConfig = $permissoes[$tipoBus] ?? $permissoes['mei'];

function canAccess($feature, $config) {
    return in_array($feature, $config['features']);
}

$companyFileName = preg_replace('/[^A-Za-z0-9]/', '_', strtoupper($user['nome']));
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>VisionGreen Pro - Command Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../../assets/style/dashboard_business.css">
    <style>
        .notification-dot { width: 8px; height: 8px; background: #ff3232; border-radius: 50%; display: inline-block; margin-left: auto; box-shadow: 0 0 8px #ff3232; }
        .skeleton { background: linear-gradient(90deg, var(--glass-border) 25%, var(--glass) 50%, var(--glass-border) 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite; border-radius: 10px; }
        @keyframes skeleton-loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        #offline-toast { position: fixed; bottom: 20px; right: 20px; background: #ff3232; color: white; padding: 12px 24px; border-radius: 12px; display: none; z-index: 10000; box-shadow: 0 10px 30px rgba(0,0,0,0.5); align-items: center; gap: 10px; }
        .verif-rejected { background: rgba(255, 50, 50, 0.1); color: #ff3232; border: 1px solid rgba(255, 50, 50, 0.3); }
        
        /* Estilo do Relógio */
        .countdown-timer { font-family: monospace; background: rgba(255, 50, 50, 0.15); padding: 2px 8px; border-radius: 6px; color: #ff3232; font-weight: bold; border: 1px solid rgba(255, 50, 50, 0.3); margin-left: 10px; }
    </style>
</head>
<body data-bus-type="<?= $tipoBus ?>">

<div id="page-loader" style="z-index: 10001;"></div>
<div id="offline-toast"><i data-lucide="wifi-off"></i> Sem conexão com a rede.</div>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="verif-badge <?= $verifClass ?>">
            <i data-lucide="<?= $verifIcon ?>" style="width:12px"></i> <?= $verifText ?>
        </div>
        
        <div class="company-info">
            <div class="logo-box">
                <?php if($user['logo_path']): ?>
                    <img src="<?= $uploadBase . $user['logo_path'] ?>" alt="Logo">
                <?php else: ?>
                    <i data-lucide="building-2" style="color:var(--text-muted); width: 24px; height: 24px;"></i>
                <?php endif; ?>
            </div>
            <div class="name-stack">
                <span class="name"><?= htmlspecialchars($user['nome']) ?></span>
                <span class="id"><?= $user['public_id'] ?></span>
            </div>
        </div>
        
        <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
            <div style="width: 8px; height: 8px; border-radius: 50%; background: <?= $minhaConfig['color'] ?>; box-shadow: 0 0 10px <?= $minhaConfig['color'] ?>;"></div>
            <span style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;"><?= $minhaConfig['label'] ?></span>
        </div>
    </div>

    <div class="nav-content">
        <div class="nav-group-label">Centro de Comando</div>
        <nav id="main-nav">
            <?php if(canAccess('dashboard', $minhaConfig)): ?>
                <a href="javascript:void(0)" onclick="loadPage('dashboard', this)" data-page="dashboard" class="nav-link active"><i data-lucide="layout-grid"></i> Dashboard</a>
            <?php endif; ?>
            <?php if(canAccess('produtos', $minhaConfig)): ?>
                <a href="javascript:void(0)" onclick="loadPage('produtos', this)" data-page="produtos" class="nav-link"><i data-lucide="layers"></i> Meus Produtos</a>
            <?php endif; ?>
            <?php if(canAccess('vendas', $minhaConfig)): ?>
                <a href="javascript:void(0)" onclick="loadPage('vendas', this)" data-page="vendas" class="nav-link"><i data-lucide="bar-chart-horizontal"></i> Painel de Vendas</a>
            <?php endif; ?>
            <?php if(canAccess('funcionarios', $minhaConfig)): ?>
                <a href="javascript:void(0)" onclick="loadPage('funcionarios', this)" data-page="funcionarios" class="nav-link"><i data-lucide="users"></i> Funcionários</a>
            <?php endif; ?>
            <?php if(canAccess('status', $minhaConfig)): ?>
                <a href="javascript:void(0)" onclick="loadPage('status', this)" data-page="status" class="nav-link"><i data-lucide="fingerprint"></i> Status Contas</a>
            <?php endif; ?>
            <?php if(canAccess('mensagens', $minhaConfig)): ?>
                <a href="javascript:void(0)" onclick="loadPage('mensagens', this)" data-page="mensagens" class="nav-link"><i data-lucide="send"></i> Mensagens<span class="notification-dot"></span></a>
            <?php endif; ?>
            <?php if(canAccess('configuracoes', $minhaConfig)): ?>
                <a href="javascript:void(0)" onclick="loadPage('configuracoes', this)" data-page="configuracoes" class="nav-link"><i data-lucide="sliders"></i> Configurações</a>
            <?php endif; ?>
        </nav>
    </div>

    <a href="../../registration/login/logout.php" class="btn-logout"><i data-lucide="power" style="width:18px"></i> FINALIZAR SESSÃO</a>
</aside>

<main class="main" id="dynamic-content"></main>

<script>
    // Variável Global para o Prazo (vinda do PHP)
    const deadline = <?= $deadlineTimestamp ?>;
    let timerInterval = null;

    function initIcons() { if (typeof lucide !== 'undefined') lucide.createIcons(); }
    
    // Lógica do Relógio de Contagem Regressiva
    function updateCountdown() {
        const timerElement = document.getElementById('deadline-clock');
        if (!timerElement || deadline === 0) return;

        const now = Math.floor(Date.now() / 1000);
        const diff = deadline - now;

        if (diff <= 0) {
            window.location.href = "process/reeviar_documentos.php?status=expired";
            return;
        }

        const h = Math.floor(diff / 3600);
        const m = Math.floor((diff % 3600) / 60);
        const s = diff % 60;

        timerElement.innerText = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }

    async function loadPage(page, element = null, isInitial = false) {
        if (timerInterval) clearInterval(timerInterval); // Limpa o timer ao mudar de página
        
        const content = document.getElementById('dynamic-content');
        const loader = document.getElementById('page-loader');
        let targetElement = element || document.querySelector(`[data-page="${page}"]`);
        
        if (!targetElement && !isInitial) { loadPage('dashboard', null, true); return; }
        
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        if (targetElement) targetElement.classList.add('active');
        
        if (!isInitial) {
            localStorage.setItem('vsg_last_page', page);
            const newUrl = window.location.pathname + '?page=' + page;
            window.history.pushState({page: page}, '', newUrl);
        }

        if(loader) loader.style.display = 'block';
        try {
            const response = await fetch(`modules/${page}.php?t=${new Date().getTime()}`);
            if (!response.ok) { 
                if (page === 'dashboard') renderDashboardDefault(); 
                else renderFileNotFound(page);
            } else { content.innerHTML = await response.text(); }
        } catch (error) { renderFileNotFound(page); } finally { 
            if(loader) loader.style.display = 'none'; 
            initIcons();
            // Se carregou o dashboard, inicia o relógio
            if (page === 'dashboard') {
                updateCountdown();
                timerInterval = setInterval(updateCountdown, 1000);
            }
        }
    }

    function renderDashboardDefault() {
        document.getElementById('dynamic-content').innerHTML = `
            <header class="welcome-header">
                <h1>Dashboard <span style="color:var(--vg-neon)"><?= explode(' ', htmlspecialchars($user['nome']))[0] ?></span></h1>
                <p>Resumo operacional para sua empresa <b><?= strtoupper($tipoBus) ?></b>.</p>
            </header>
            <div class="stats-grid" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:20px; margin-bottom:30px;">
                <div class="stat-card" style="background:var(--bg-sidebar); padding:25px; border-radius:24px; border:1px solid var(--glass-border);">
                    <h3 style="font-size:11px; color:var(--text-muted); text-transform:uppercase;">Vendas Totais</h3><div class="value" style="font-size:28px; font-weight:800; margin-top:10px;">MT 0,00</div>
                </div>
                <div class="stat-card" style="background:var(--bg-sidebar); padding:25px; border-radius:24px; border:1px solid var(--glass-border);">
                    <h3 style="font-size:11px; color:var(--text-muted); text-transform:uppercase;">Inventário</h3><div class="value" style="font-size:28px; font-weight:800; margin-top:10px;">0 Itens</div>
                </div>
                <div class="stat-card" style="background:var(--bg-sidebar); padding:25px; border-radius:24px; border:1px solid var(--glass-border);">
                    <h3 style="font-size:11px; color:var(--text-muted); text-transform:uppercase;">Estado da Conta (Documentação)</h3>
                    <div class="value" style="font-size:16px; font-weight:800; color:<?= $statusColor ?>; margin-top:10px; display: flex; align-items: center;">
                        <?= $statusLabel ?> 
                        <?php if ($statusDoc === 'rejeitado'): ?>
                            <span id="deadline-clock" class="countdown-timer">--:--:--</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="file-view-container" style="background:var(--bg-sidebar); border: 1px solid var(--glass-border); border-radius: 24px; padding: 30px;">
                <div style="display:flex; align-items:center; gap:12px; border-bottom:1px solid var(--glass-border); padding-bottom:15px; margin-bottom:25px;">
                    <i data-lucide="file-code" style="color:var(--vg-neon); width:22px;"></i><span style="font-weight:700; font-size:14px;"><?= $companyFileName ?>.SYS</span>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <div style="background:var(--bg-body); padding:20px; border-radius:16px; border-left:4px solid var(--vg-neon);"><p style="font-size:10px; color:var(--text-muted); margin-bottom:8px;">ID DE CONTA</p><code style="color:var(--vg-neon);"><?= $user['public_id'] ?></code></div>
                    <div style="background:var(--bg-body); padding:20px; border-radius:16px; border-left:4px solid var(--accent-blue);"><p style="font-size:10px; color:var(--text-muted); margin-bottom:8px;">LICENÇA OPERACIONAL</p><code style="color:var(--accent-blue);"><?= strtoupper($tipoBus) ?>_ACTIVE</code></div>
                </div>
            </div>`;
        initIcons();
    }

    function renderFileNotFound(fileName) {
        document.getElementById('dynamic-content').innerHTML = `
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:70vh; text-align:center;">
                <div style="position:relative; margin-bottom:30px;"><i data-lucide="file-warning" style="width:100px; height:100px; color:var(--text-muted); opacity:0.1;"></i><i data-lucide="alert-circle" style="width:35px; height:35px; color:#ff3232; position:absolute; top:50%; left:50%; transform:translate(-50%, -50%);"></i></div>
                <h2 style="font-size:24px; font-weight:800; color:#fff; margin-bottom:12px;">Arquivo não localizado</h2>
                <p style="color:var(--text-muted); max-width:450px; line-height:1.6; font-size:15px; margin-bottom:35px;">O sistema tentou aceder ao arquivo <b style="color:var(--accent-blue)">${fileName}</b>.</p>
                <button onclick="loadPage('dashboard')" style="background:var(--vg-green); color:#000; border:none; padding:15px 35px; border-radius:14px; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:12px;"><i data-lucide="refresh-cw" style="width:20px;"></i> Voltar ao dashboard</button>
            </div>`;
        initIcons();
    }

    window.onload = () => {
        const urlParams = new URLSearchParams(window.location.search);
        loadPage(urlParams.get('page') || localStorage.getItem('vsg_last_page') || 'dashboard', null, true);
    };
    window.onpopstate = () => {
        const urlParams = new URLSearchParams(window.location.search);
        loadPage(urlParams.get('page') || 'dashboard', null, true);
    };
</script>
</body>
</html>