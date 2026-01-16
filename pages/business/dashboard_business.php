<?php
/**
 * ================================================================================
 * VISIONGREEN - COMPANY DASHBOARD
 * Arquivo: company/index.php
 * Descrição: Dashboard principal para empresas (MEI, LTDA, S.A)
 * Tema: GitHub Dark (igual ao dashboard admin)
 * ================================================================================
 */

define('REQUIRED_TYPE', 'company');
define('REQUIRE_APPROVED_DOCS', false);

session_start();
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';
require_once '../../registration/middleware/middleware_auth.php';

/* ================= VALIDAÇÃO DE SEGURANÇA ================= */
if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];

/* ================= BUSCAR DADOS DO USUÁRIO ================= */
$stmt = $mysqli->prepare("
    SELECT u.*, 
           b.id as business_id, 
           b.logo_path, 
           b.status_documentos, 
           b.business_type, 
           b.updated_at, 
           b.motivo_rejeicao, 
           b.tax_id,
           b.description,
           b.country,
           b.region,
           b.city
    FROM users u 
    LEFT JOIN businesses b ON u.id = b.user_id 
    WHERE u.id = ? LIMIT 1
");

if (!$stmt) {
    die("Erro na preparação: " . $mysqli->error);
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: ../../registration/login/login.php");
    exit;
}

/* ================= VALIDAR LOCKDOWN ================= */
if ($user['is_in_lockdown']) {
    header("Location: process/blocked.php");
    exit;
}

/* ================= DADOS BÁSICOS ================= */
$statusDoc = $user['status_documentos'] ?? 'pendente';
$updatedAt = $user['updated_at'];
$uploadBase = "../../registration/uploads/business/";

/* ================= BUSCAR ASSINATURA ATIVA ================= */
$stmt = $mysqli->prepare("
    SELECT sp.*, 
           us.id as subscription_id,
           us.status as sub_status, 
           us.start_date, 
           us.end_date, 
           us.next_billing_date, 
           us.auto_renew, 
           us.mrr,
           DATEDIFF(us.end_date, NOW()) as dias_restantes
    FROM user_subscriptions us
    INNER JOIN subscription_plans sp ON us.plan_id = sp.id
    WHERE us.user_id = ? AND us.status IN ('active', 'trial')
    ORDER BY us.created_at DESC
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $subscription = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $subscription = null;
}

/* ================= CALCULAR ESTATÍSTICAS ================= */
$stats = [
    'vendas_mes' => 0,
    'vendas_total' => 0,
    'transacoes_mes' => 0,
    'transacoes_total' => 0,
    'produtos_comprados' => 0,
    'produtos_pendentes' => 0,
    'mensagens_nao_lidas' => 0,
    'mensagens_total' => 0,
    'dias_ativo' => 0,
    'taxa_conversao' => 0,
    'ticket_medio' => 0
];

// Vendas este mês
$result = $mysqli->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM transactions 
    WHERE user_id = '$userId' 
    AND MONTH(transaction_date) = MONTH(NOW()) 
    AND YEAR(transaction_date) = YEAR(NOW()) 
    AND status = 'completed'
");
if ($result) {
    $stats['vendas_mes'] = (float)$result->fetch_assoc()['total'];
    $result->close();
}

// Vendas totais
$result = $mysqli->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM transactions 
    WHERE user_id = '$userId' 
    AND status = 'completed'
");
if ($result) {
    $stats['vendas_total'] = (float)$result->fetch_assoc()['total'];
    $result->close();
}

// Transações este mês
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM transactions 
    WHERE user_id = '$userId' 
    AND MONTH(transaction_date) = MONTH(NOW()) 
    AND YEAR(transaction_date) = YEAR(NOW())
");
if ($result) {
    $stats['transacoes_mes'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Total de transações
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM transactions 
    WHERE user_id = '$userId' 
    AND status = 'completed'
");
if ($result) {
    $stats['transacoes_total'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Produtos comprados
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM product_purchases 
    WHERE user_id = '$userId' 
    AND status = 'completed'
");
if ($result) {
    $stats['produtos_comprados'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Produtos pendentes
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM product_purchases 
    WHERE user_id = '$userId' 
    AND status = 'pending'
");
if ($result) {
    $stats['produtos_pendentes'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Mensagens não lidas
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE receiver_id = '$userId' 
    AND status = 'unread'
");
if ($result) {
    $stats['mensagens_nao_lidas'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Total de mensagens
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE receiver_id = '$userId'
");
if ($result) {
    $stats['mensagens_total'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Dias ativo
$result = $mysqli->query("
    SELECT DATEDIFF(NOW(), created_at) as dias 
    FROM users 
    WHERE id = '$userId'
");
if ($result) {
    $stats['dias_ativo'] = (int)$result->fetch_assoc()['dias'];
    $result->close();
}

// Calcular taxa de conversão e ticket médio
$stats['taxa_conversao'] = $stats['transacoes_total'] > 0 ? round(($stats['transacoes_total'] / max($stats['mensagens_total'], 1)) * 100, 1) : 0;
$stats['ticket_medio'] = $stats['transacoes_total'] > 0 ? round($stats['vendas_total'] / $stats['transacoes_total'], 2) : 0;

/* ================= BUSCAR HEALTH SCORE ================= */
$healthScore = null;
$result = $mysqli->query("SELECT * FROM company_health_score WHERE user_id = '$userId'");
if ($result && $result->num_rows > 0) {
    $healthScore = $result->fetch_assoc();
    $result->close();
}

/* ================= LÓGICA DE PRAZO 48H ================= */
$deadlineTimestamp = 0;
$showRejectionAlert = false;

if ($statusDoc === 'rejeitado') {
    $showRejectionAlert = true;
    try {
        $dataRejeicao = new DateTime($updatedAt);
        $dataLimite = clone $dataRejeicao;
        $dataLimite->modify('+48 hours');
        $deadlineTimestamp = $dataLimite->getTimestamp();
    } catch (Exception $e) {
        error_log("Erro ao calcular deadline: " . $e->getMessage());
    }
}

/* ================= LÓGICA DE INTERFACE ================= */
$verifConfig = [
    'aprovado' => [
        'class' => 'verif-checked',
        'icon' => 'fa-check-circle',
        'text' => 'VERIFICADO',
        'label' => 'Aprovada',
        'color' => '#00ff88'
    ],
    'rejeitado' => [
        'class' => 'verif-rejected',
        'icon' => 'fa-exclamation-triangle',
        'text' => 'REJEITADO',
        'label' => 'Rejeitada',
        'color' => '#ff4d4d'
    ],
    'pendente' => [
        'class' => 'verif-pending',
        'icon' => 'fa-clock',
        'text' => 'A VERIFICAR',
        'label' => 'Pendente',
        'color' => '#ffc107'
    ]
];

$verifClass = $verifConfig[$statusDoc]['class'] ?? $verifConfig['pendente']['class'];
$verifIcon = $verifConfig[$statusDoc]['icon'] ?? $verifConfig['pendente']['icon'];
$verifText = $verifConfig[$statusDoc]['text'] ?? $verifConfig['pendente']['text'];
$statusLabel = $verifConfig[$statusDoc]['label'] ?? $verifConfig['pendente']['label'];
$statusColor = $verifConfig[$statusDoc]['color'] ?? $verifConfig['pendente']['color'];

/* ================= CONFIGURAÇÃO DE PERMISSÕES ================= */
$tipoBus = strtolower($user['business_type'] ?? 'mei');
$permissoes = [
    'mei'  => [
        'label' => 'Individual (MEI)', 
        'color' => '#8b949e', 
        'features' => ['dashboard', 'produtos', 'vendas', 'configuracoes']
    ],
    'ltda' => [
        'label' => 'Limitada (LTDA)', 
        'color' => '#4da3ff', 
        'features' => ['dashboard', 'produtos', 'vendas', 'funcionarios', 'assinatura', 'mensagens', 'configuracoes']
    ],
    'sa'   => [
        'label' => 'Anônima (S.A)', 
        'color' => '#00ff88', 
        'features' => ['dashboard', 'produtos', 'vendas', 'funcionarios', 'assinatura', 'mensagens', 'relatorios', 'configuracoes']
    ]
];
$minhaConfig = $permissoes[$tipoBus] ?? $permissoes['mei'];

function canAccess($feature, $config) {
    return in_array($feature, $config['features']);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionGreen | <?= htmlspecialchars($user['nome']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ==================== VARIÁVEIS GITHUB DARK ==================== */
        :root {
            --bg-body: #0d1117;
            --bg-sidebar: #161b22;
            --bg-card: #161b22;
            --border-color: #30363d;
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --accent-green: #00ff88;
            --accent-blue: #4da3ff;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
        }

        #masterBody.sidebar-is-collapsed .sidebar {
            width: 80px;
        }

        #masterBody.sidebar-is-collapsed .main-wrapper {
            margin-left: 80px;
        }

        #masterBody.sidebar-is-collapsed .logo-text,
        #masterBody.sidebar-is-collapsed .nav-item span,
        #masterBody.sidebar-is-collapsed .nav-label,
        #masterBody.sidebar-is-collapsed .notification-badge {
            display: none;
        }

        #masterBody.sidebar-is-collapsed .collapse-btn i {
            transform: rotate(180deg);
        }

        /* ==================== SIDEBAR ==================== */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transition: width 0.3s ease;
            z-index: 100;
            overflow: hidden;
        }

        .collapse-btn {
            position: absolute;
            right: -15px;
            top: 20px;
            width: 30px;
            height: 30px;
            background: var(--bg-sidebar);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 101;
            transition: all 0.3s ease;
        }

        .collapse-btn:hover {
            background: var(--accent-green);
            border-color: var(--accent-green);
            color: #000;
        }

        .collapse-btn i {
            transition: transform 0.3s ease;
        }

        .header-section {
            padding: 30px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .brand-area {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: var(--accent-green);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 20px;
            flex-shrink: 0;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            white-space: nowrap;
        }

        .logo-main {
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .company-badge {
            font-size: 9px;
            font-weight: 800;
            padding: 4px 8px;
            border-radius: 6px;
            margin-top: 6px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .verif-checked {
            background: rgba(0, 255, 136, 0.1);
            color: var(--accent-green);
            border: 1px solid rgba(0, 255, 136, 0.3);
        }

        .verif-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .verif-rejected {
            background: rgba(255, 77, 77, 0.1);
            color: #ff4d4d;
            border: 1px solid rgba(255, 77, 77, 0.3);
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .company-info-widget {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .company-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #000;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .company-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .company-details {
            flex: 1;
            min-width: 0;
        }

        .company-name {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .company-id {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            font-weight: 600;
            color: var(--accent-green);
            background: rgba(0, 255, 136, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 4px;
        }

        .nav-menu {
            flex: 1;
            overflow-y: auto;
            padding: 20px 0;
        }

        .nav-label {
            padding: 10px 20px 8px;
            font-size: 11px;
            font-weight: 800;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            border-left: 3px solid transparent;
            white-space: nowrap;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
        }

        .nav-item.active {
            background: rgba(0, 255, 136, 0.1);
            color: var(--accent-green);
            border-left-color: var(--accent-green);
        }

        .nav-icon-box {
            width: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-badge {
            margin-left: auto;
            background: #ff4d4d;
            color: white;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        .sidebar-footer-fixed {
            border-top: 1px solid var(--border-color);
            padding: 20px;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            background: rgba(255, 77, 77, 0.1);
            border: 1px solid rgba(255, 77, 77, 0.3);
            border-radius: 10px;
            color: #ff4d4d;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #ff4d4d;
            color: #fff;
        }

        /* ==================== MAIN CONTENT ==================== */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .header-section-main {
            background: var(--bg-sidebar);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 30px;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
        }

        .header-left {
            flex: 1;
        }

        .breadcrumb-area {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .b-current {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
        }

        .search-container {
            position: relative;
            flex: 0 1 400px;
        }

        .search-container input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
        }

        .search-container input:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 3px rgba(0, 255, 136, 0.1);
        }

        .search-container i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .system-ping {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
        }

        .ping-dot {
            width: 8px;
            height: 8px;
            background: var(--accent-green);
            border-radius: 50%;
            animation: ping 2s infinite;
        }

        @keyframes ping {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .icon-action-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .icon-action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-green);
        }

        .badge-dot {
            position: absolute;
            top: -3px;
            right: -3px;
            width: 10px;
            height: 10px;
            background: #ff4d4d;
            border: 2px solid var(--bg-sidebar);
            border-radius: 50%;
        }

        .master-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }

        .master-info {
            text-align: right;
        }

        .master-name {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
        }

        .avatar-box img {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        /* ==================== CONTENT AREA ==================== */
        .main-wrapper-content {
            flex: 1;
            padding: 30px;
        }

        /* ==================== REJECTION ALERT ==================== */
        .rejection-alert-banner {
            background: rgba(255, 77, 77, 0.1);
            border: 1px solid rgba(255, 77, 77, 0.3);
            border-radius: 12px;
            padding: 20px 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .rejection-alert-banner h3 {
            font-size: 16px;
            font-weight: 800;
            color: #ff4d4d;
            margin-bottom: 8px;
        }

        .rejection-alert-banner p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .countdown-timer {
            font-family: 'Courier New', monospace;
            font-size: 32px;
            font-weight: 800;
            color: #ff4d4d;
            background: rgba(255, 77, 77, 0.2);
            padding: 15px 25px;
            border-radius: 10px;
            border: 1px solid rgba(255, 77, 77, 0.4);
            letter-spacing: 2px;
        }

        .btn-reenviar {
            padding: 12px 24px;
            background: #ff4d4d;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-reenviar:hover {
            background: #ff3232;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 77, 77, 0.3);
        }

        /* ==================== LOADING BAR ==================== */
        #page-loader {
            display: none;
            position: fixed;
            top: 0;
            right: 0;
            width: calc(100% - var(--sidebar-width));
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--accent-green), transparent);
            z-index: 1000;
            animation: loadingBar 1.5s infinite linear;
        }

        @keyframes loadingBar {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
    </style>
</head>
<body id="masterBody">

<div id="page-loader"></div>

<!-- ==================== SIDEBAR ==================== -->
<aside class="sidebar">
    <div class="collapse-btn" onclick="toggleSidebar()">
        <i class="fa-solid fa-chevron-left"></i>
    </div>

    <div class="header-section">
        <div class="brand-area">
            <div class="logo-icon">
                <i class="fa-solid fa-leaf"></i>
            </div>
            <div class="logo-text">
                <span class="logo-main">VISIONGREEN</span>
                <span class="company-badge <?= $verifClass ?>">
                    <i class="fa-solid <?= $verifIcon ?>"></i> <?= $verifText ?>
                </span>
            </div>
        </div>

        <div class="company-info-widget">
            <div class="company-avatar">
                <?php if($user['logo_path']): ?>
                    <img src="<?= $uploadBase . htmlspecialchars($user['logo_path']) ?>" alt="Logo">
                <?php else: ?>
                    <i class="fa-solid fa-building" style="color: var(--text-secondary);"></i>
                <?php endif; ?>
            </div>
            <div class="company-details">
                <div class="company-name"><?= htmlspecialchars($user['nome']) ?></div>
                <span class="company-id"><?= htmlspecialchars($user['public_id']) ?></span>
            </div>
        </div>
    </div>

    <div class="nav-menu">
        <div class="nav-label">Centro de Comando</div>
        
        <?php if(canAccess('dashboard', $minhaConfig)): ?>
            <a href="javascript:void(0)" onclick="loadContent('modules/dashboard/dashboard', this)" class="nav-item active">
                <div class="nav-icon-box"><i class="fa-solid fa-gauge-high"></i></div>
                <span>Dashboard</span>
            </a>
        <?php endif; ?>

        <?php if(canAccess('produtos', $minhaConfig)): ?>
            <a href="javascript:void(0)" onclick="loadContent('modules/produtos/produtos', this)" class="nav-item">
                <div class="nav-icon-box"><i class="fa-solid fa-box"></i></div>
                <span>Meus Produtos</span>
                <?php if($stats['produtos_pendentes'] > 0): ?>
                    <span class="notification-badge"><?= $stats['produtos_pendentes'] ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if(canAccess('vendas', $minhaConfig)): ?>
            <a href="javascript:void(0)" onclick="loadContent('modules/vendas/vendas', this)" class="nav-item">
                <div class="nav-icon-box"><i class="fa-solid fa-chart-line"></i></div>
                <span>Vendas</span>
            </a>
        <?php endif; ?>

        <?php if(canAccess('funcionarios', $minhaConfig)): ?>
            <a href="javascript:void(0)" onclick="loadContent('modules/funcionarios/funcionarios', this)" class="nav-item">
                <div class="nav-icon-box"><i class="fa-solid fa-users"></i></div>
                <span>Funcionários</span>
            </a>
        <?php endif; ?>

        <?php if(canAccess('assinatura', $minhaConfig)): ?>
            <a href="javascript:void(0)" onclick="loadContent('modules/assinatura/assinatura', this)" class="nav-item">
                <div class="nav-icon-box"><i class="fa-solid fa-credit-card"></i></div>
                <span>Assinatura</span>
                <?php if($subscription && $subscription['dias_restantes'] && $subscription['dias_restantes'] <= 7): ?>
                    <span class="notification-badge">!</span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if(canAccess('mensagens', $minhaConfig)): ?>
            <a href="javascript:void(0)" onclick="loadContent('modules/mensagens/mensagens', this)" class="nav-item">
                <div class="nav-icon-box"><i class="fa-solid fa-comments"></i></div>
                <span>Mensagens</span>
                <?php if($stats['mensagens_nao_lidas'] > 0): ?>
                    <span class="notification-badge"><?= $stats['mensagens_nao_lidas'] ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if(canAccess('relatorios', $minhaConfig)): ?>
            <a href="javascript:void(0)" onclick="loadContent('modules/relatorios/relatorios', this)" class="nav-item">
                <div class="nav-icon-box"><i class="fa-solid fa-file-invoice"></i></div>
                <span>Relatórios</span>
            </a>
        <?php endif; ?>

        <div class="nav-label">Sistema</div>
        
        <?php if(canAccess('configuracoes', $minhaConfig)): ?>
            <a href="javascript:void(0)" onclick="loadContent('modules/configuracoes/configuracoes', this)" class="nav-item">
                <div class="nav-icon-box"><i class="fa-solid fa-sliders"></i></div>
                <span>Configurações</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer-fixed">
        <a href="../../registration/login/logout.php" class="logout-btn">
            <i class="fa-solid fa-power-off"></i>
            <span>Finalizar Sessão</span>
        </a>
    </div>
</aside>

<!-- ==================== MAIN CONTENT ==================== -->
<main class="main-wrapper">
    <header class="header-section-main">
        <div class="header-content">
            <div class="header-left">
                <div class="breadcrumb-area">
                    <span id="parent-name">Centro de Comando</span>
                    <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
                </div>
                <div class="b-current" id="current-page-title">Dashboard</div>
            </div>

            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="mainSearchInput" placeholder="Pesquisar transações, produtos..." autocomplete="off">
            </div>

            <div class="header-right">
                <div class="system-ping">
                    <div class="ping-dot"></div>
                    <span>ONLINE</span>
                </div>

                <div class="icon-action-btn" title="Notificações">
                    <i class="fa-solid fa-bell"></i>
                    <?php if($stats['mensagens_nao_lidas'] > 0): ?>
                        <span class="badge-dot"></span>
                    <?php endif; ?>
                </div>

                <div class="master-profile">
                    <div class="master-info">
                        <span class="master-name"><?= htmlspecialchars($user['nome']) ?></span>
                        <span style="font-size: 10px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;"><?= $minhaConfig['label'] ?></span>
                    </div>
                    <div class="avatar-box">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['nome']) ?>&background=00ff88&color=000&bold=true" alt="Avatar">
                    </div>
                </div>
            </div>
        </div>
    </header>

    <?php if($showRejectionAlert): ?>
    <div class="main-wrapper-content">
        <div class="rejection-alert-banner">
            <div style="flex: 1;">
                <h3><i class="fa-solid fa-triangle-exclamation"></i> Documentos Rejeitados - Prazo Expirando!</h3>
                <p><strong>Motivo:</strong> <?= htmlspecialchars($user['motivo_rejeicao'] ?? 'Não especificado') ?></p>
            </div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <span id="deadline-clock" class="countdown-timer">--:--:--</span>
                <button onclick="window.location.href='process/reenviar_documentos.php'" class="btn-reenviar">
                    <i class="fa-solid fa-upload"></i>
                    Reenviar
                </button>
            </div>
        </div>
    <?php else: ?>
    <div class="main-wrapper-content">
    <?php endif; ?>

        <div id="content-area">
            <!-- Conteúdo dinâmico será carregado aqui -->
        </div>
    </div>
</main>

<script>
const userData = <?= json_encode([
    'userId' => $userId,
    'nome' => $user['nome'],
    'publicId' => $user['public_id'],
    'taxId' => $user['tax_id'] ?? 'N/A',
    'businessType' => $tipoBus,
    'statusDoc' => $statusDoc,
    'stats' => $stats,
    'subscription' => $subscription,
    'healthScore' => $healthScore
]) ?>;

const deadline = <?= $deadlineTimestamp ?>;

function toggleSidebar() {
    document.getElementById('masterBody').classList.toggle('sidebar-is-collapsed');
}

// Countdown para documentos rejeitados
if (deadline > 0) {
    function updateCountdown() {
        const timerElement = document.getElementById('deadline-clock');
        if (!timerElement) return;

        const now = Math.floor(Date.now() / 1000);
        const diff = deadline - now;

        if (diff <= 0) {
            timerElement.innerText = "EXPIRADO!";
            timerElement.style.background = "rgba(255, 50, 50, 0.4)";
            return;
        }

        const h = Math.floor(diff / 3600);
        const m = Math.floor((diff % 3600) / 60);
        const s = diff % 60;

        timerElement.innerText = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        
        if (diff < 3600) {
            timerElement.style.animation = "pulse-red 1s infinite";
        }
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
}

// ==================== SISTEMA DE NAVEGAÇÃO (IGUAL AO ADMIN) ====================
let contentCache = new Map();
let isLoading = false;

async function loadContent(pageName, element = null) {
    if (isLoading) return;
    
    const contentArea = document.getElementById('content-area');
    const loader = document.getElementById('page-loader');
    
    if (!contentArea) return;

    isLoading = true;
    
    // Limpar query strings e extensões
    const parts = pageName.split('?');
    const cleanName = parts[0].replace(/\.(php|html)$/, "");
    const queryString = parts[1] ? '?' + parts[1] : "";
    const fullUrl = cleanName + queryString;

    if (!element) {
        element = document.querySelector(`[onclick*="'${cleanName}'"]`);
    }

    try {
        let html;
        
        // Cache
        if (contentCache.has(fullUrl)) {
            html = contentCache.get(fullUrl);
        } else {
            // Tentar .php primeiro
            let response = await fetch(cleanName + '.php' + queryString, { cache: 'no-cache' });
            
            // Se falhar, tentar .html
            if (!response.ok) {
                response = await fetch(cleanName + '.html' + queryString, { cache: 'no-cache' });
            }

            if (!response.ok) {
                // Se ainda falhar, mostrar erro
                throw new Error('Módulo não encontrado: ' + cleanName);
            } else {
                html = await response.text();
                
                // Limitar cache
                if (contentCache.size > 20) {
                    const firstKey = contentCache.keys().next().value;
                    contentCache.delete(firstKey);
                }
                contentCache.set(fullUrl, html);
            }
        }
        
        contentArea.innerHTML = html;

        // Executar scripts inline (com verificação)
        const existingScripts = new Set();
        contentArea.querySelectorAll('script').forEach(oldScript => {
            const scriptContent = oldScript.innerHTML.trim();
            
            // Evitar reexecutar scripts vazios
            if (!scriptContent) return;
            
            // Criar hash simples do conteúdo para detectar duplicatas
            const scriptHash = scriptContent.substring(0, 100);
            
            // Se já foi executado, pular
            if (existingScripts.has(scriptHash)) {
                console.log('⚠️ Script duplicado detectado, pulando...');
                return;
            }
            
            existingScripts.add(scriptHash);
            
            try {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.appendChild(document.createTextNode(scriptContent));
                oldScript.parentNode.replaceChild(newScript, oldScript);
            } catch (error) {
                console.error('Erro ao executar script:', error);
            }
        });

        // Atualizar navegação ativa
        if(element && element.classList) {
            document.querySelectorAll('.nav-item').forEach(btn => {
                if(btn && btn.classList) btn.classList.remove('active');
            });
            
            element.classList.add('active');
            
            // Atualizar breadcrumb
            const labelSpan = element.querySelector('span');
            if(labelSpan) {
                document.getElementById('current-page-title').innerText = labelSpan.innerText;
            }
        }

        // Atualizar URL
        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=' + cleanName + queryString;
        window.history.pushState({ path: newUrl }, '', newUrl);

    } catch (error) {
        console.error('Erro ao carregar:', error);
        contentArea.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px; text-align: center;">
                <i class="fa-solid fa-exclamation-triangle" style="font-size: 64px; color: #ff4d4d; margin-bottom: 20px; opacity: 0.5;"></i>
                <h2 style="font-size: 24px; font-weight: 800; color: #fff; margin-bottom: 10px;">Módulo não encontrado</h2>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">O módulo "<strong>${pageName}</strong>" não está disponível.</p>
                <button onclick="loadContent('modules/dashboard/dashboard')" style="padding: 12px 24px; background: var(--accent-green); color: #000; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-home"></i> Voltar ao Dashboard
                </button>
            </div>
        `;
    } finally {
        if(loader) loader.style.display = 'none';
        isLoading = false;
    }
}

// Carregar página inicial
window.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const page = urlParams.get('page') || 'modules/dashboard/dashboard';
    loadContent(page, null);
});

// Navegação com botão voltar
window.onpopstate = () => {
    const urlParams = new URLSearchParams(window.location.search);
    loadContent(urlParams.get('page') || 'modules/dashboard/dashboard', null);
};

// Loading no loader
if(document.getElementById('page-loader')) {
    document.getElementById('page-loader').style.display = 'block';
}

console.log('✅ VisionGreen Business Dashboard carregado - User:', userData.publicId);
</script>
</body>
</html>