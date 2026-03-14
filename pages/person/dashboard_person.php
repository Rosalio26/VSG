<?php
define('REQUIRED_TYPE', 'person');
require_once '../../registration/middleware/middleware_auth.php';
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

if (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    header("Location: ../../pages/admin/dashboard.php");
    exit;
}

if ($_SESSION['auth']['type'] !== 'person') {
    if ($_SESSION['auth']['type'] === 'company') {
        header("Location: ../business/dashboard_business.php");
    } else {
        header("Location: ../../registration/login/login.php?error=acesso_proibido");
    }
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];

$stmt = $mysqli->prepare("
    SELECT nome, apelido, email, telefone, public_id, status, registration_step,
           email_verified_at, created_at, type
    FROM users WHERE id = ? LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../../registration/login/login.php?error=user_not_found");
    exit;
}

if (!$user['email_verified_at']) {
    header("Location: ../../registration/process/verify_email.php");
    exit;
}

if (!$user['public_id']) {
    header("Location: ../../registration/register/gerar_uid.php");
    exit;
}

if ($user['status'] === 'blocked') {
    die('Acesso restrito: Sua conta está bloqueada. Por favor, contacte o suporte técnico.');
}

$displayName   = $user['apelido'] ?: $user['nome'];
$firstName     = explode(' ', $displayName)[0];
$initials      = '';
$nameParts     = explode(' ', $displayName);
$initials      = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
$displayAvatar = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=1a7f37&color=ffffff&bold=true&size=64";

// Greeting by time of day
$hour = (int) date('H');
if ($hour < 12)      $greeting = 'Bom dia';
elseif ($hour < 18)  $greeting = 'Boa tarde';
else                 $greeting = 'Boa noite';

$categoryLabels = [
    'reciclavel'  => ['icon' => 'fa-recycle',      'label' => 'Reciclável'],
    'sustentavel' => ['icon' => 'fa-seedling',     'label' => 'Sustentável'],
    'servico'     => ['icon' => 'fa-screwdriver-wrench', 'label' => 'Serviços'],
    'visiongreen' => ['icon' => 'fa-leaf',         'label' => 'VisionGreen'],
    'ecologico'   => ['icon' => 'fa-earth-africa', 'label' => 'Ecológico'],
    'outro'       => ['icon' => 'fa-box',          'label' => 'Outros'],
];

$priceRanges = [
    ['min' => 0,     'max' => 1000,   'label' => 'Até 1.000 MZN'],
    ['min' => 1000,  'max' => 5000,   'label' => '1.000 – 5.000 MZN'],
    ['min' => 5000,  'max' => 10000,  'label' => '5.000 – 10.000 MZN'],
    ['min' => 10000, 'max' => 999999, 'label' => 'Acima de 10.000 MZN'],
];

$stats = ['mensagens_nao_lidas' => 0, 'pedidos_em_andamento' => 0, 'total_gasto' => 0];

$r = $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id='$userId' AND status='nao_lida'");
if ($r) { $stats['mensagens_nao_lidas'] = (int)$r->fetch_assoc()['total']; $r->close(); }

$r = $mysqli->query("SELECT COUNT(*) as total FROM orders WHERE customer_id='$userId' AND status IN('pendente','confirmado','processando')");
if ($r) { $stats['pedidos_em_andamento'] = (int)$r->fetch_assoc()['total']; $r->close(); }

$r = $mysqli->query("SELECT COALESCE(SUM(total),0) as total FROM orders WHERE customer_id='$userId' AND payment_status='pago'");
if ($r) { $stats['total_gasto'] = (float)$r->fetch_assoc()['total']; $r->close(); }

$r = $mysqli->query("SELECT COUNT(*) as total FROM orders WHERE customer_id='$userId' AND status='entregue'");
$stats['entregues'] = $r ? (int)$r->fetch_assoc()['total'] : 0;
if ($r) $r->close();

$stmt = $mysqli->prepare("
    SELECT o.id, o.order_number, o.order_date, o.status, o.payment_status,
           o.payment_method, o.total, o.currency, o.shipping_address, o.shipping_city,
           o.customer_notes, o.created_at,
           COALESCE(u.nome,'Empresa Desconhecida') as empresa_nome,
           COALESCE((SELECT COUNT(*) FROM order_items WHERE order_id=o.id),0) as items_count,
           COALESCE((SELECT SUM(quantity) FROM order_items WHERE order_id=o.id),0) as total_items
    FROM orders o
    LEFT JOIN users u ON o.company_id=u.id
    WHERE o.customer_id=? AND o.deleted_at IS NULL
    ORDER BY o.order_date DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$statusMap = [
    'pendente'    => ['icon' => 'fa-clock',        'fa_color' => 'var(--warn)',    'label' => 'Pendente',    'class' => 'status-pendente'],
    'confirmado'  => ['icon' => 'fa-circle-check', 'fa_color' => 'var(--link)',    'label' => 'Confirmado',  'class' => 'status-confirmado'],
    'processando' => ['icon' => 'fa-gear',          'fa_color' => 'var(--link)',    'label' => 'Processando', 'class' => 'status-processando'],
    'enviado'     => ['icon' => 'fa-truck-fast',   'fa_color' => 'var(--link)',    'label' => 'Enviado',     'class' => 'status-enviado'],
    'entregue'    => ['icon' => 'fa-circle-check', 'fa_color' => 'var(--primary)', 'label' => 'Entregue',   'class' => 'status-entregue'],
    'cancelado'   => ['icon' => 'fa-circle-xmark', 'fa_color' => 'var(--danger)',  'label' => 'Cancelado',  'class' => 'status-cancelado'],
];

$paymentStatusMap = [
    'pendente'    => ['label' => 'Aguardando'],
    'pago'        => ['label' => 'Pago'],
    'parcial'     => ['label' => 'Parcial'],
    'reembolsado' => ['label' => 'Reembolsado'],
];

$paymentMethodMap = [
    'mpesa'      => ['label' => 'M-Pesa'],
    'emola'      => ['label' => 'E-Mola'],
    'visa'       => ['label' => 'Visa'],
    'mastercard' => ['label' => 'Mastercard'],
    'manual'     => ['label' => 'Manual'],
];

$pedidosStats = [
    'total'        => count($orders),
    'pendentes'    => count(array_filter($orders, fn($o) => $o['status'] === 'pendente')),
    'em_andamento' => count(array_filter($orders, fn($o) => in_array($o['status'], ['confirmado','processando','enviado']))),
    'entregues'    => count(array_filter($orders, fn($o) => $o['status'] === 'entregue')),
];

// Nav items definition
$navItems = [
    ['page' => 'home',          'icon' => 'fa-house',         'label' => 'Início'],
    ['page' => 'produtos',      'icon' => 'fa-store',         'label' => 'Produtos'],
    ['page' => 'carrinho',      'icon' => 'fa-cart-shopping', 'label' => 'Carrinho', 'badge' => 'cart'],
    ['page' => 'meus_pedidos',  'icon' => 'fa-box-open',      'label' => 'Meus Pedidos', 'badge' => $stats['pedidos_em_andamento']],
    ['page' => 'notificacoes',  'icon' => 'fa-bell',          'label' => 'Notificações', 'badge' => $stats['mensagens_nao_lidas']],
    ['page' => 'perfil',        'icon' => 'fa-user',          'label' => 'Perfil'],
    ['page' => 'configuracoes', 'icon' => 'fa-gear',          'label' => 'Configurações'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionGreen | <?= htmlspecialchars($displayName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        /* No-image placeholder */
        .no-image-placeholder {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; width: 100%; height: 100%;
            background: var(--raised); gap: 3px;
        }
        .no-image-placeholder span { font-size: 9.03px; text-transform: uppercase; letter-spacing: .04em; color: var(--txt3); }
        .no-image-placeholder strong { font-size: 11.03px; color: var(--link); }

        /* Badge hide animation */
        .badge.hiding, .mobile-nav-badge.hiding {
            opacity: 0; transform: scale(.8);
            transition: opacity .3s ease, transform .3s ease;
        }

        /* Theme switcher in sidebar footer */
        .theme-switcher {
            display: flex; align-items: center; gap: 6px;
            padding: 6px 10px; margin-bottom: 2px;
        }
        .theme-switcher-label { font-size: 10.03px; color: var(--txt3); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; flex: 1; }
        .theme-dot {
            width: 14px; height: 14px; border-radius: 50%;
            cursor: pointer; border: 2px solid transparent;
            transition: all .15s; flex-shrink: 0;
        }
        .theme-dot:hover, .theme-dot.active { border-color: var(--txt); transform: scale(1.2); }

        /* Greeting banner flex adjustments */
        .greeting-banner { flex-wrap: wrap; }
        @media (max-width: 480px) {
            .greeting-cta { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<div class="loading-bar" id="loadingBar"></div>

<!-- ── SIDEBAR ──────────────────────────── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="brand">
            <div class="logo-icon"><i class="fa-solid fa-leaf"></i></div>
            <div class="logo-text">
                <span class="logo-main">VisionGreen</span>
                <span class="logo-sub">Marketplace Eco</span>
            </div>
        </div>
    </div>

    <!-- User card -->
    <div class="sidebar-user">
        <div class="sidebar-avatar">
            <img src="<?= $displayAvatar ?>" alt="<?= htmlspecialchars($displayName) ?>"
                 onerror="this.style.display='none';this.parentElement.textContent='<?= $initials ?>'">
        </div>
        <div>
            <div class="sidebar-uname"><?= htmlspecialchars($displayName) ?></div>
            <div class="sidebar-urole"><i class="fa-solid fa-circle-check" style="font-size: 8.04px;margin-right:3px"></i>Cliente</div>
        </div>
    </div>

    <div class="filters-container">
        <!-- Main nav -->
        <div class="filter-section">
            <h3>Principal</h3>
            <div class="filter-option active" onclick="navClick('home', this)">
                <i class="fa-solid fa-house"></i>
                <label>Início</label>
            </div>
            <div class="filter-option" onclick="navClick('carrinho', this)">
                <i class="fa-solid fa-cart-shopping"></i>
                <label>Carrinho</label>
                <span class="nav-pip green cart-count-badge" style="display:none">0</span>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <h3>Categorias</h3>
            <?php foreach ($categoryLabels as $value => $data): ?>
            <div class="filter-option" onclick="toggleCategoryFilter('<?= $value ?>', this)">
                <i class="fa-solid <?= $data['icon'] ?>"></i>
                <label><?= $data['label'] ?></label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="filter-section">
            <h3>Preço</h3>
            <?php foreach ($priceRanges as $i => $range): ?>
            <div class="filter-option" onclick="togglePriceFilter('<?= $range['min'] ?>-<?= $range['max'] ?>', this)">
                <i class="fa-solid fa-tag"></i>
                <label><?= $range['label'] ?></label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="filter-section">
            <h3>Disponibilidade</h3>
            <div class="filter-option" onclick="toggleStockFilter(this)">
                <i class="fa-solid fa-circle-check"></i>
                <label>Em Estoque</label>
            </div>
        </div>

        <div class="filter-option" onclick="resetFilters()">
            <i class="fa-solid fa-rotate-right"></i>
            <label>Limpar Filtros</label>
        </div>

        <!-- Account nav -->
        <div class="filter-section">
            <h3>Conta</h3>
            <div class="filter-option" onclick="navClick('meus_pedidos', this)">
                <i class="fa-solid fa-box-open"></i>
                <label>Meus Pedidos</label>
                <?php if ($stats['pedidos_em_andamento'] > 0): ?>
                <span class="nav-pip" id="sidebar-pedidos-pip"><?= $stats['pedidos_em_andamento'] ?></span>
                <?php endif; ?>
            </div>
            <div class="filter-option" onclick="navClick('notificacoes', this)">
                <i class="fa-solid fa-bell"></i>
                <label>Notificações</label>
                <?php if ($stats['mensagens_nao_lidas'] > 0): ?>
                <span class="nav-pip"><?= $stats['mensagens_nao_lidas'] ?></span>
                <?php endif; ?>
            </div>
            <div class="filter-option" onclick="navClick('perfil', this)">
                <i class="fa-solid fa-user"></i>
                <label>Perfil</label>
            </div>
            <div class="filter-option" onclick="navClick('configuracoes', this)">
                <i class="fa-solid fa-gear"></i>
                <label>Configurações</label>
            </div>
        </div>
    </div>

    <div class="sidebar-footer">
        <!-- Theme switcher -->
        <div class="theme-switcher">
            <span class="theme-switcher-label"><i class="fa-solid fa-palette" style="margin-right:4px"></i>Tema</span>
            <div class="theme-dot active" style="background:#1a7f37" onclick="setTheme('', this)" title="Verde"></div>
            <div class="theme-dot" style="background:#b22a6a" onclick="setTheme('theme-pink', this)" title="Rosa"></div>
            <div class="theme-dot" style="background:#0550ae" onclick="setTheme('theme-ocean', this)" title="Azul"></div>
            <div class="theme-dot" style="background:#7d4e00" onclick="setTheme('theme-amber', this)" title="Âmbar"></div>
        </div>

        <a href="javascript:void(0)" onclick="navClick('configuracoes')" class="action-btn">
            <i class="fa-solid fa-gear"></i>
            <span>Configurações</span>
        </a>

        <form method="post" action="../../registration/login/logout.php" style="width:100%">
            <?= csrf_field(); ?>
            <button type="submit" class="action-btn btn-logout" style="width:100%">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Sair da conta</span>
            </button>
        </form>
    </div>
</aside>

<!-- ── MAIN ─────────────────────────────── -->
<main class="main-content">
    <header class="header-main">
        <div class="header-content">
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="col-home-collapse">
                <button class="collapse-btn" onclick="toggleSidebar()" title="Colapsar">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <div class="back-home-btn">
                    <a href="dashboard_person.php" title="Início">
                        <button><i class="fa-solid fa-house"></i></button>
                    </a>
                </div>
            </div>

            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Buscar produtos, pedidos…" autocomplete="off">
            </div>

            <div class="header-actions">
                <button class="icon-btn" onclick="navigateTo('carrinho')" title="Carrinho">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span class="badge cart-badge" style="display:none">0</span>
                </button>

                <button class="icon-btn" onclick="navigateTo('meus_pedidos')" title="Pedidos">
                    <i class="fa-solid fa-box-open"></i>
                    <span class="badge" id="header-pedidos-badge"
                          style="<?= $stats['pedidos_em_andamento'] > 0 ? '' : 'display:none' ?>">
                        <?= $stats['pedidos_em_andamento'] ?>
                    </span>
                </button>

                <button class="icon-btn" onclick="navigateTo('notificacoes')" title="Notificações">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($stats['mensagens_nao_lidas'] > 0): ?>
                        <span class="badge"><?= $stats['mensagens_nao_lidas'] ?></span>
                    <?php endif; ?>
                </button>

                <div class="user-profile" onclick="navigateTo('perfil')">
                    <img src="<?= $displayAvatar ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($firstName) ?></span>
                        <span class="user-role"><i class="fa-solid fa-circle-check" style="font-size: 7.04px;margin-right:2px"></i>Cliente</span>
                    </div>
                    <i class="fa-solid fa-chevron-down" style="font-size: 9.06px;color:var(--txt3)"></i>
                </div>
            </div>
        </div>
    </header>

    <!-- ── CONTENT ─────────────────────── -->
    <div class="content-wrapper">

        <!-- HOME -->
        <div id="content-home" class="dynamic-content active">
            <!-- Greeting banner -->
            <div class="greeting-banner">
                <div>
                    <div class="greeting-hi">
                        <?= $greeting ?>, <?= htmlspecialchars($firstName) ?>
                        <i class="fa-solid fa-hand-wave" style="font-size: 15.09px;color:var(--warn);margin-left:4px"></i>
                    </div>
                    <div class="greeting-sub">
                        <i class="fa-regular fa-calendar"></i>
                        <?= date('l, d \d\e F') ?>
                        <?php if ($stats['pedidos_em_andamento'] > 0): ?>
                        &nbsp;·&nbsp;
                        <i class="fa-solid fa-circle-notch fa-spin" style="font-size: 9.06px;color:var(--warn)"></i>
                        <?= $stats['pedidos_em_andamento'] ?> pedido<?= $stats['pedidos_em_andamento'] > 1 ? 's' : '' ?> em andamento
                        <?php endif; ?>
                    </div>
                </div>
                <button class="greeting-cta" onclick="navigateTo('carrinho')">
                    <i class="fa-solid fa-bolt"></i> Comprar agora
                </button>
            </div>

            <!-- Stats row -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-label">Pedidos activos</div>
                        <div class="stat-icon warn"><i class="fa-solid fa-clock"></i></div>
                    </div>
                    <div class="stat-val" id="stat-pedidos"><?= $stats['pedidos_em_andamento'] ?></div>
                    <div class="stat-meta">
                        <span class="chip y"><i class="fa-solid fa-rotate" style="font-size: 7.04px"></i> Em curso</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-label">Total gasto</div>
                        <div class="stat-icon green"><i class="fa-solid fa-wallet"></i></div>
                    </div>
                    <div class="stat-val"><?= number_format($stats['total_gasto'], 0, ',', '.') ?></div>
                    <div class="stat-meta">
                        <i class="fa-solid fa-circle-info" style="font-size: 9.06px"></i> MZN · histórico
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-label">Entregues</div>
                        <div class="stat-icon blue"><i class="fa-solid fa-circle-check"></i></div>
                    </div>
                    <div class="stat-val"><?= $stats['entregues'] ?></div>
                    <div class="stat-meta">
                        <span class="chip g"><i class="fa-solid fa-arrow-up" style="font-size: 7.04px"></i> Total</span>
                    </div>
                </div>
            </div>

            <!-- Products section header -->
            <div class="section-header">
                <div class="section-title">
                    <i class="fa-solid fa-fire-flame-curved"></i>
                    Produtos em destaque
                </div>
                <div class="section-link" onclick="navigateTo('produtos')">
                    Ver todos <i class="fa-solid fa-arrow-right" style="font-size: 9.06px"></i>
                </div>
            </div>

            <!-- Products grid (loaded via JS) -->
            <div id="productsGrid" class="products-grid">
                <div class="empty-state">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <h3>Carregando produtos…</h3>
                    <p>Aguarde um momento</p>
                </div>
            </div>

            <!-- Recent orders (from PHP data) -->
            <?php if (!empty($orders)): ?>
            <div class="section-header" style="margin-top:4px">
                <div class="section-title">
                    <i class="fa-solid fa-box-open"></i>
                    Últimos pedidos
                </div>
                <div class="section-link" onclick="navigateTo('meus_pedidos')">
                    Ver todos <i class="fa-solid fa-arrow-right" style="font-size: 9.06px"></i>
                </div>
            </div>
            <div class="orders-list">
                <?php foreach (array_slice($orders, 0, 3) as $order):
                    $sm  = $statusMap[$order['status']] ?? $statusMap['pendente'];
                ?>
                <div class="order-item" onclick="navigateTo('meus_pedidos')">
                    <div class="order-icon">
                        <i class="fa-solid <?= $sm['icon'] ?>" style="color:<?= $sm['fa_color'] ?>"></i>
                    </div>
                    <div class="order-info">
                        <div class="order-num"><?= htmlspecialchars($order['order_number'] ?: '#VG-' . $order['id']) ?></div>
                        <div class="order-meta">
                            <i class="fa-regular fa-building"></i>
                            <?= htmlspecialchars($order['empresa_nome']) ?>
                            &nbsp;·&nbsp;
                            <i class="fa-regular fa-calendar"></i>
                            <?= date('d M', strtotime($order['order_date'])) ?>
                        </div>
                    </div>
                    <span class="order-status <?= $sm['class'] ?>">
                        <i class="fa-solid <?= $sm['icon'] ?>"></i>
                        <?= $sm['label'] ?>
                    </span>
                    <div class="order-value">
                        <?= number_format((float)$order['total'], 2, ',', '.') ?> MZN
                        <small><?= $order['total_items'] ?> iten<?= $order['total_items'] != 1 ? 's' : '' ?></small>
                    </div>
                    <i class="fa-solid fa-chevron-right order-chevron"></i>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- MEUS PEDIDOS -->
        <div id="content-meus_pedidos" class="dynamic-content">
            <div class="content-loading active">
                <div class="spinner"></div>
                <h3>Carregando pedidos…</h3>
            </div>
        </div>

        <!-- NOTIFICAÇÕES -->
        <div id="content-notificacoes" class="dynamic-content">
            <div class="content-loading active">
                <div class="spinner"></div>
                <h3>Carregando notificações…</h3>
            </div>
        </div>

        <!-- PERFIL -->
        <div id="content-perfil" class="dynamic-content">
            <div class="content-loading active">
                <div class="spinner"></div>
                <h3>Carregando perfil…</h3>
            </div>
        </div>

        <!-- CONFIGURAÇÕES -->
        <div id="content-configuracoes" class="dynamic-content">
            <div class="content-loading active">
                <div class="spinner"></div>
                <h3>Carregando configurações…</h3>
            </div>
        </div>

        <!-- CARRINHO -->
        <div id="content-carrinho" class="dynamic-content">
            <div class="content-loading active">
                <div class="spinner"></div>
                <h3>Carregando carrinho…</h3>
            </div>
        </div>

        <!-- PRODUTOS (página separada) -->
        <div id="content-produtos" class="dynamic-content">
            <div class="content-loading active">
                <div class="spinner"></div>
                <h3>Carregando produtos…</h3>
            </div>
        </div>

    </div><!-- /content-wrapper -->
</main>

<div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileMenu()"></div>

<!-- ── MOBILE NAV ──────────────────────── -->
<nav class="mobile-nav">
    <div class="mobile-nav-grid">
        <a href="dashboard_person.php" class="mobile-nav-item active" data-page="home">
            <i class="fa-solid fa-house"></i>
            <span class="mobile-nav-label">Início</span>
        </a>
        <button class="mobile-nav-item" onclick="navigateTo('meus_pedidos')" data-page="meus_pedidos">
            <i class="fa-solid fa-box-open"></i>
            <span class="mobile-nav-label">Pedidos</span>
            <span class="mobile-nav-badge" id="mobile-pedidos-badge"
                  style="<?= $stats['pedidos_em_andamento'] > 0 ? '' : 'display:none' ?>">
                <?= $stats['pedidos_em_andamento'] ?>
            </span>
        </button>
        <button class="mobile-nav-item" onclick="toggleMobileMenu()" data-page="filtros">
            <i class="fa-solid fa-sliders"></i>
            <span class="mobile-nav-label">Filtros</span>
        </button>
        <button class="mobile-nav-item" onclick="navigateTo('notificacoes')" data-page="notificacoes">
            <i class="fa-solid fa-bell"></i>
            <span class="mobile-nav-label">Alertas</span>
            <?php if ($stats['mensagens_nao_lidas'] > 0): ?>
                <span class="mobile-nav-badge"><?= $stats['mensagens_nao_lidas'] ?></span>
            <?php endif; ?>
        </button>
        <button class="mobile-nav-item" onclick="navigateTo('perfil')" data-page="perfil">
            <i class="fa-solid fa-user"></i>
            <span class="mobile-nav-label">Perfil</span>
        </button>
    </div>
</nav>

<!-- ── JAVASCRIPT ─────────────────────── -->
<script>
/* ── GlobalModuleManager ────────────────── */
(function () {
    'use strict';
    const hiI = setInterval(function(){}, 0);
    for (let i = 0; i < hiI; i++) clearInterval(i);
    const hiT = setTimeout(function(){}, 0);
    for (let i = 0; i < hiT; i++) clearTimeout(i);

    if (window.GlobalModuleManager) { window.GlobalModuleManager.clearAll(); delete window.GlobalModuleManager; }

    window.GlobalModuleManager = {
        activeIntervals: new Map(),
        activeModules: new Set(),
        currentPage: null,
        isInitialized: false,
        init() { if (this.isInitialized) this.clearAll(); this.isInitialized = true; },
        registerInterval(name, id) {
            if (this.activeIntervals.has(name)) clearInterval(this.activeIntervals.get(name));
            this.activeIntervals.set(name, id);
        },
        clearInterval(name) { if (this.activeIntervals.has(name)) { clearInterval(this.activeIntervals.get(name)); this.activeIntervals.delete(name); } },
        clearAllExcept(except) {
            this.activeIntervals.forEach((id, name) => { if (name !== except) clearInterval(id); });
            if (except && this.activeIntervals.has(except)) { const k = this.activeIntervals.get(except); this.activeIntervals.clear(); this.activeIntervals.set(except, k); } else { this.activeIntervals.clear(); }
        },
        clearAll() { this.activeIntervals.forEach(id => clearInterval(id)); this.activeIntervals.clear(); this.activeModules.clear(); },
        registerModule(n) { this.activeModules.add(n); },
        unregisterModule(n) { this.clearInterval(n); this.activeModules.delete(n); },
        setActivePage(page) {
            if (this.currentPage === page) return;
            this.currentPage = page;
            const map = { home:'dashboard_stats', notificacoes:'NotificationsModule', meus_pedidos:'OrdersModule', carrinho:'CartModule', perfil:null, configuracoes:null, produtos:null };
            const mod = map[page];
            if (mod) this.clearAllExcept(mod); else this.clearAll();
        },
        getStatus() {}
    };
    window.GlobalModuleManager.init();
    window.addEventListener('beforeunload', () => window.GlobalModuleManager.clearAll());
    window.addEventListener('pageshow', e => { if (e.persisted) window.GlobalModuleManager.clearAll(); });
})();

/* ── PHP Data → JS ──────────────────────── */
const userData = <?= json_encode([
    'userId'   => $userId,
    'nome'     => $displayName,
    'firstName'=> $firstName,
    'email'    => $user['email'],
    'publicId' => $user['public_id'],
    'initials' => $initials,
], JSON_UNESCAPED_UNICODE) ?>;

const ordersData       = <?= json_encode($orders,           JSON_UNESCAPED_UNICODE) ?>;
const pedidosStats     = <?= json_encode($pedidosStats,     JSON_UNESCAPED_UNICODE) ?>;
const statusMap        = <?= json_encode($statusMap,        JSON_UNESCAPED_UNICODE) ?>;
const paymentStatusMap = <?= json_encode($paymentStatusMap, JSON_UNESCAPED_UNICODE) ?>;
const paymentMethodMap = <?= json_encode($paymentMethodMap, JSON_UNESCAPED_UNICODE) ?>;

/* ── State ──────────────────────────────── */
let filters = { search: '', categories: [], priceRange: null, inStock: false };
let currentPage = 'home';
const loadedPages = new Set(['home']);

/* ── Theme ──────────────────────────────── */
function setTheme(cls, btn) {
    document.querySelectorAll('.theme-dot').forEach(d => d.classList.remove('active'));
    if (btn) btn.classList.add('active');
    document.body.className = cls || '';
    try { localStorage.setItem('vg_theme', cls || ''); } catch(_) {}
}

/* ── Sidebar / Menu ─────────────────────── */
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    sb.classList.toggle('collapsed');
    try { localStorage.setItem('sidebarCollapsed', sb.classList.contains('collapsed')); } catch(_) {}
}
function toggleMobileMenu() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
    document.getElementById('mobileOverlay').classList.toggle('active');
}
function closeMobileMenu() {
    document.getElementById('sidebar').classList.remove('mobile-open');
    document.getElementById('mobileOverlay').classList.remove('active');
}

/* ── Sidebar nav click (handles both nav + filter clicks) ── */
function navClick(page, el) {
    if (page === 'home') { window.location.href = 'dashboard_person.php'; return; }
    document.querySelectorAll('.filters-container .filter-option[onclick^="navClick"]').forEach(i => i.classList.remove('active'));
    if (el) el.classList.add('active');
    navigateTo(page);
    closeMobileMenu();
}

/* ── Filter toggles ─────────────────────── */
function toggleCategoryFilter(val, el) {
    const isActive = el.classList.contains('active');
    if (isActive) { el.classList.remove('active'); filters.categories = filters.categories.filter(c => c !== val); }
    else { el.classList.add('active'); filters.categories.push(val); }
    if (currentPage !== 'home') navigateTo('home');
    else loadProducts();
}
function togglePriceFilter(val, el) {
    const isActive = el.classList.contains('active');
    document.querySelectorAll('.filters-container .filter-option[onclick^="togglePriceFilter"]').forEach(i => i.classList.remove('active'));
    if (!isActive) { el.classList.add('active'); filters.priceRange = val; }
    else { filters.priceRange = null; }
    if (currentPage !== 'home') navigateTo('home');
    else loadProducts();
}
function toggleStockFilter(el) {
    el.classList.toggle('active');
    filters.inStock = el.classList.contains('active');
    if (currentPage !== 'home') navigateTo('home');
    else loadProducts();
}
function resetFilters() {
    filters = { search: '', categories: [], priceRange: null, inStock: false };
    const si = document.getElementById('searchInput');
    if (si) si.value = '';
    document.querySelectorAll('.filters-container .filter-option').forEach(el => el.classList.remove('active'));
    // re-mark home as active
    const homeItem = document.querySelector('.filters-container .filter-option[onclick*="home"]');
    if (homeItem) homeItem.classList.add('active');
    loadProducts();
}

/* ── Stats polling ──────────────────────── */
async function updateStatsSmooth() {
    try {
        const res  = await fetch('actions/get_stats.php');
        const data = await res.json();
        if (!data.success) return;

        const visto = localStorage.getItem('pedidosVisualizados');
        let count   = data.data.pedidos_em_andamento;
        if (visto && (Date.now() - parseInt(visto)) < 86400000) count = 0;

        const hb = document.getElementById('header-pedidos-badge');
        const mb = document.getElementById('mobile-pedidos-badge');
        const sp = document.getElementById('sidebar-pedidos-pip');
        const sv = document.getElementById('stat-pedidos');

        if (hb) { hb.textContent = count; hb.style.display = count > 0 ? '' : 'none'; }
        if (mb) { mb.textContent = count; mb.style.display = count > 0 ? '' : 'none'; }
        if (sp) { sp.textContent = count; sp.style.display = count > 0 ? '' : 'none'; }
        if (sv) sv.textContent = count;
    } catch (_) {}
}

async function clearOrdersBadge() {
    ['header-pedidos-badge','mobile-pedidos-badge'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hiding');
    });
    setTimeout(() => {
        ['header-pedidos-badge','mobile-pedidos-badge'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.style.display = 'none'; el.textContent = '0'; el.classList.remove('hiding'); }
        });
        const sp = document.getElementById('sidebar-pedidos-pip');
        if (sp) { sp.style.display = 'none'; sp.textContent = '0'; }
    }, 300);
    try {
        await fetch('actions/mark_orders_viewed.php', { method:'POST', headers:{'Content-Type':'application/json'} });
        localStorage.setItem('pedidosVisualizados', Date.now().toString());
    } catch (_) {}
}

/* ── Navigation ─────────────────────────── */
async function navigateTo(page) {
    if (window._isNavigating) return;
    if (page === 'home') { window.location.href = 'dashboard_person.php'; return; }
    if (currentPage === page) return;

    window._isNavigating = true;
    try {
        closeMobileMenu();
        window.GlobalModuleManager.setActivePage(page);

        document.querySelectorAll('.mobile-nav-item').forEach(btn => btn.classList.toggle('active', btn.dataset.page === page));
        document.querySelectorAll('.dynamic-content').forEach(c => c.classList.remove('active'));

        const div = document.getElementById(`content-${page}`);
        if (!div) return;
        div.classList.add('active');
        currentPage = page;
        window.history.pushState({ page }, '', `?page=${page}`);

        if (page === 'meus_pedidos') await clearOrdersBadge();
        if (!loadedPages.has(page)) { await loadPageContent(page); loadedPages.add(page); }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
        setTimeout(() => { window._isNavigating = false; }, 300);
    }
}

async function loadPageContent(page) {
    const div    = document.getElementById(`content-${page}`);
    const loader = div.querySelector('.content-loading');
    if (loader) loader.classList.add('active');
    try {
        const res = await fetch(`pages/${page}.php?t=${Date.now()}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const html = await res.text();
        if (loader) loader.remove();
        div.innerHTML = html;
        div.querySelectorAll('script').forEach(s => {
            const ns = document.createElement('script');
            if (s.src) ns.src = s.src; else ns.textContent = s.textContent;
            ns.async = false;
            document.body.appendChild(ns);
            setTimeout(() => { if (document.body.contains(ns)) document.body.removeChild(ns); }, 100);
        });
    } catch (e) {
        div.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <h3>Erro ao carregar conteúdo</h3>
                <p>Não foi possível carregar a página. Tente novamente.</p>
                <button class="btn-filter-reset" onclick="loadPageContent('${page}')" style="margin-top:12px;justify-content:center">
                    <i class="fa-solid fa-rotate-right"></i> Tentar novamente
                </button>
            </div>`;
    }
}

window.addEventListener('popstate', e => {
    if (!e.state?.page || e.state.page === 'home') { window.location.href = 'dashboard_person.php'; return; }
    navigateTo(e.state.page);
});

/* ── Products ───────────────────────────── */
function trunc(text, max) {
    if (!text) return '';
    return text.length <= max ? text : text.substring(0, max) + '…';
}

function getCatIcon(cat) {
    const m = { reciclavel:'fa-recycle', sustentavel:'fa-seedling', servico:'fa-screwdriver-wrench', visiongreen:'fa-leaf', ecologico:'fa-earth-africa', outro:'fa-box' };
    return m[cat] || 'fa-box';
}
function getCatName(cat) {
    const m = { reciclavel:'Reciclável', sustentavel:'Sustentável', servico:'Serviço', visiongreen:'VisionGreen', ecologico:'Ecológico', outro:'Outros' };
    return m[cat] || cat;
}
function formatPrice(p) {
    return parseFloat(p).toLocaleString('pt-MZ', { minimumFractionDigits:2, maximumFractionDigits:2 });
}
function isNew(createdAt) {
    return Math.floor((Date.now() - new Date(createdAt)) / 86400000) <= 7;
}
function esc(t) {
    return String(t).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

async function loadProducts() {
    const grid   = document.getElementById('productsGrid');
    const loader = document.getElementById('loadingBar');
    if (!grid) return;
    loader.classList.add('active');

    try {
        const params = new URLSearchParams({
            search:      filters.search,
            categories:  filters.categories.join(','),
            price_range: filters.priceRange || '',
            in_stock:    filters.inStock ? '1' : ''
        });
        const res  = await fetch(`actions/get_products.php?${params}`);
        const data = await res.json();

        if (data.success && data.products.length > 0) {
            const favs = JSON.parse(localStorage.getItem('favorites') || '[]');
            grid.innerHTML = data.products.map((p, idx) => {
                const imgSrc = p.imagem ? `../uploads/products/${p.imagem}` : '';
                const fav    = favs.includes(p.id);
                const _new   = isNew(p.created_at);

                let badge = '';
                if (p.stock === 0) badge = '<span class="product-badge out-of-stock"><i class="fa-solid fa-circle-xmark" style="font-size: 8.04px;margin-right:2px"></i>Esgotado</span>';
                else if (p.stock <= p.stock_minimo) badge = '<span class="product-badge stock-low"><i class="fa-solid fa-triangle-exclamation" style="font-size: 8.04px;margin-right:2px"></i>Últimas unidades</span>';
                else if (_new) badge = '<span class="product-badge is-new">NOVO</span>';

                return `
                <div class="product-card" style="animation-delay:${idx * .04}s">
                    <div class="product-company">
                        <i class="fa-solid fa-store"></i>
                        <span>Distribuída por</span>
                        <span>${esc(p.empresa_nome || 'VisionGreen')}</span>
                    </div>
                    <div class="product-image">
                        ${imgSrc
                            ? `<img src="${imgSrc}" alt="${esc(p.nome)}" loading="lazy" onerror="handleImgErr(this,'${esc(p.empresa_nome||'VisionGreen')}')">`
                            : `<i class="fa-solid fa-leaf" style="color:var(--txt3)"></i>`
                        }
                        ${badge}
                        <button class="btn-favorite ${fav ? 'active' : ''}"
                                onclick="event.stopPropagation();toggleFav(${p.id},this)"
                                title="${fav ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}">
                            <i class="fa-${fav ? 'solid' : 'regular'} fa-heart"></i>
                        </button>
                    </div>
                    <div class="product-info">
                        <div class="product-category">
                            <i class="fa-solid ${getCatIcon(p.categoria)}"></i>
                            ${getCatName(p.categoria)}
                        </div>
                        <div class="product-name" title="${esc(p.nome)}">${esc(p.nome)}</div>
                        <div class="product-description">${esc(trunc(p.descricao, 80))}</div>
                        <div class="product-footer">
                            <div class="product-price">${formatPrice(p.preco)}<small>MZN</small></div>
                            <span class="product-stock ${p.stock === 0 ? 'out' : p.stock <= p.stock_minimo ? 'low' : ''}">
                                ${p.stock === 0 ? 'Esgotado' : p.stock <= p.stock_minimo ? `${p.stock} un` : `${p.stock} un`}
                            </span>
                        </div>
                        <div class="product-actions">
                            <button class="btn-add-cart"
                                    onclick="event.stopPropagation();addToCart(${p.id},'${esc(p.nome)}',${p.preco},this)"
                                    ${p.stock === 0 ? 'disabled' : ''}>
                                <i class="fa-solid fa-cart-plus"></i> Adicionar
                            </button>
                            <button class="btn-buy-now"
                                    onclick="event.stopPropagation();buyNow(${p.id})"
                                    ${p.stock === 0 ? 'disabled' : ''}>
                                <i class="fa-solid fa-bolt"></i> Comprar
                            </button>
                        </div>
                    </div>
                </div>`;
            }).join('');

            grid.querySelectorAll('.product-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    if (!e.target.closest('button')) {
                        const id = this.querySelector('.btn-buy-now').getAttribute('onclick').match(/buyNow\((\d+)\)/)[1];
                        viewProduct(id);
                    }
                });
            });
        } else {
            grid.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <h3>Nenhum produto encontrado</h3>
                    <p>Tente ajustar os filtros ou explore outras categorias</p>
                </div>`;
        }
    } catch (e) {
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <h3>Erro ao carregar produtos</h3>
                <p>Por favor, tente novamente mais tarde</p>
                <button class="btn-filter-reset" onclick="loadProducts()" style="margin-top:12px;justify-content:center">
                    <i class="fa-solid fa-rotate-right"></i> Tentar novamente
                </button>
            </div>`;
    } finally {
        loader.classList.remove('active');
    }
}

function handleImgErr(img, company) {
    const c  = img.parentElement;
    const ph = document.createElement('div');
    ph.className = 'no-image-placeholder';
    ph.innerHTML = `<span>Distribuído por:</span><strong>${company}</strong>`;
    img.remove();
    c.prepend(ph);
}

/* ── Cart ───────────────────────────────── */
function addToCart(id, name, price, btn) {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i> Adicionado!';
    btn.style.background = 'var(--primary)';
    btn.style.color = '#fff';
    btn.style.borderColor = 'var(--primary)';

    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const item = cart.find(i => i.id === id);
    if (item) item.quantity++; else cart.push({ id, name, price, quantity: 1, addedAt: new Date().toISOString() });
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartBadge();
    showToast(`<strong>${name}</strong> adicionado ao carrinho!`, 'success');

    setTimeout(() => {
        btn.innerHTML = orig;
        btn.style.background = '';
        btn.style.color = '';
        btn.style.borderColor = '';
    }, 2000);
}

function buyNow(id) {
    showToast('Redirecionando para checkout…', 'info');
    setTimeout(() => { window.location.href = `checkout.php?product=${id}&qty=1`; }, 500);
}

function toggleFav(id, btn) {
    const icon = btn.querySelector('i');
    let favs = JSON.parse(localStorage.getItem('favorites') || '[]');
    if (favs.includes(id)) {
        favs = favs.filter(x => x !== id);
        icon.className = 'fa-regular fa-heart';
        btn.classList.remove('active');
        showToast('Removido dos favoritos', 'warning');
    } else {
        favs.push(id);
        icon.className = 'fa-solid fa-heart';
        btn.classList.add('active');
        showToast('Adicionado aos favoritos!', 'success');
    }
    localStorage.setItem('favorites', JSON.stringify(favs));
}

function viewProduct(id) {
    showToast('Carregando detalhes…', 'info');
    setTimeout(() => { window.location.href = `product_details.php?id=${id}`; }, 300);
}

function updateCartBadge() {
    const cart  = JSON.parse(localStorage.getItem('cart') || '[]');
    const total = cart.reduce((s, i) => s + i.quantity, 0);
    document.querySelectorAll('.cart-badge').forEach(b => { b.textContent = total; b.style.display = total > 0 ? 'flex' : 'none'; });
    document.querySelectorAll('.cart-count-badge').forEach(b => { b.textContent = total; b.style.display = total > 0 ? '' : 'none'; });
}

/* ── Toast ──────────────────────────────── */
function showToast(message, type) {
    const colors = { success:'var(--primary)', error:'var(--danger)', warning:'var(--warn)', info:'var(--link)' };
    const icons  = { success:'fa-check', error:'fa-xmark', warning:'fa-triangle-exclamation', info:'fa-circle-info' };

    const t = document.createElement('div');
    t.className = 'vg-toast';
    t.style.borderLeftColor = colors[type] || colors.info;
    t.style.borderLeftWidth = '3px';
    t.innerHTML = message;

    const dot = document.createElement('div');
    dot.className = 'vg-toast-icon';
    dot.style.background = colors[type] || colors.info;
    dot.innerHTML = `<i class="fa-solid ${icons[type] || icons.info}" style="font-size: 9.06px"></i>`;
    t.appendChild(dot);
    document.body.appendChild(t);

    setTimeout(() => {
        t.style.animation = 'toastOut .25s ease forwards';
        setTimeout(() => t.remove(), 250);
    }, 3000);
}

/* ── Search ─────────────────────────────── */
let searchTimeout;
document.getElementById('searchInput')?.addEventListener('input', function () {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        filters.search = this.value;
        if (currentPage !== 'home') navigateTo('home');
        else loadProducts();
    }, 500);
});

/* ── Init ───────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    if (window._domLoaded) return;
    window._domLoaded = true;

    // Restore theme
    try {
        const saved = localStorage.getItem('vg_theme') || '';
        if (saved) {
            document.body.className = saved;
            const dot = document.querySelector(`.theme-dot[onclick*="${saved}"]`);
            if (dot) { document.querySelectorAll('.theme-dot').forEach(d => d.classList.remove('active')); dot.classList.add('active'); }
        }
    } catch (_) {}

    // Restore sidebar state
    try { if (localStorage.getItem('sidebarCollapsed') === 'true') document.getElementById('sidebar').classList.add('collapsed'); } catch (_) {}

    // Hide orders badge if viewed recently
    try {
        const visto = localStorage.getItem('pedidosVisualizados');
        if (visto && (Date.now() - parseInt(visto)) < 86400000) {
            ['header-pedidos-badge','mobile-pedidos-badge'].forEach(id => {
                const el = document.getElementById(id);
                if (el) { el.style.display = 'none'; el.textContent = '0'; }
            });
            const sp = document.getElementById('sidebar-pedidos-pip');
            if (sp) { sp.style.display = 'none'; }
        }
    } catch (_) {}

    // Load initial page
    const page = new URLSearchParams(window.location.search).get('page') || 'home';
    if (page !== 'home') {
        navigateTo(page);
    } else {
        loadProducts();
        const iv = setInterval(updateStatsSmooth, 5000);
        window.GlobalModuleManager.registerInterval('dashboard_stats', iv);
        window.GlobalModuleManager.registerModule('dashboard_stats');
    }

    updateCartBadge();
});

window.addEventListener('beforeunload', () => window.GlobalModuleManager.clearAll());

// Close mobile menu on outside click
document.addEventListener('click', function (e) {
    const sb  = document.getElementById('sidebar');
    const btn = document.querySelector('.mobile-menu-btn');
    if (sb?.classList.contains('mobile-open') && !sb.contains(e.target) && !btn?.contains(e.target)) closeMobileMenu();
});

// Body scroll lock on mobile menu
new MutationObserver(mutations => {
    mutations.forEach(m => {
        document.body.style.overflow = m.target.classList.contains('mobile-open') ? 'hidden' : '';
    });
}).observe(document.getElementById('sidebar'), { attributes: true, attributeFilter: ['class'] });
</script>
</body>
</html>