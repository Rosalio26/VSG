<?php
define('REQUIRED_TYPE', 'person');
require_once '../../registration/middleware/middleware_auth.php';
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';
require_once 'includes/get_user_stats.php';
require_once 'includes/functions.php';

// Verificar sessão
if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

// Verificar tipo de usuário
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

// Carregar configurações
$config = require_once 'config/constants.php';

// Extrair configurações para variáveis
$categoryLabels = $config['category_labels'];
$priceRanges = $config['price_ranges'];
$statusMap = $config['status_map'];
$paymentStatusMap = $config['payment_status_map'];
$paymentMethodMap = $config['payment_method_map'];

// ID do usuário
$userId = (int) $_SESSION['auth']['user_id'];

// ========================================
// BUSCAR DADOS DO USUÁRIO - QUERY CONSOLIDADA
// ========================================
$stmt = $mysqli->prepare("
    SELECT 
        u.nome, 
        u.apelido, 
        u.email, 
        u.telefone, 
        u.public_id, 
        u.status, 
        u.registration_step, 
        u.email_verified_at, 
        u.created_at, 
        u.type,
        
        -- Contar pedidos do usuário
        (SELECT COUNT(*) FROM orders WHERE customer_id = u.id AND deleted_at IS NULL) as total_orders
        
    FROM users u
    WHERE u.id = ? 
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Validações
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

// Dados do usuário
$displayName = $user['apelido'] ?: $user['nome'];
$displayAvatar = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=00ff88&color=000&bold=true";

// Buscar estatísticas com cache
$stats = getUserStatsWithCache($mysqli, $userId);

// ========================================
// BUSCAR PEDIDOS (apenas se necessário)
// ========================================
$orders = [];
$pedidosStats = [
    'total' => 0,
    'pendentes' => 0,
    'em_andamento' => 0,
    'entregues' => 0
];

// Só buscar pedidos se estiver na página de pedidos
$page = $_GET['page'] ?? 'dashboard';

if ($page === 'pedidos' || $page === 'dashboard') {
    $stmt = $mysqli->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.order_date,
            o.status,
            o.payment_status,
            o.payment_method,
            o.total,
            o.currency,
            o.shipping_address,
            o.shipping_city,
            o.customer_notes,
            o.created_at,
            COALESCE(u.nome, 'Empresa Desconhecida') as empresa_nome,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count,
            (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as total_items
        FROM orders o
        LEFT JOIN users u ON o.company_id = u.id
        WHERE o.customer_id = ? 
        AND o.deleted_at IS NULL
        ORDER BY o.order_date DESC
        LIMIT 50
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Calcular stats dos pedidos
    $pedidosStats = [
        'total' => count($orders),
        'pendentes' => count(array_filter($orders, fn($o) => $o['status'] === 'pendente')),
        'em_andamento' => count(array_filter($orders, fn($o) => in_array($o['status'], ['confirmado', 'processando', 'enviado']))),
        'entregues' => count(array_filter($orders, fn($o) => $o['status'] === 'entregue'))
    ];
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionGreen Marketplace | <?= htmlspecialchars($displayName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/painel_cliente.css">
    <link rel="stylesheet" href="assets/css/notifications.css">
    <link rel="stylesheet" href="assets/css/messages.css">
    <link rel="stylesheet" href="assets/css/dashboard_content.css">
</head>
<body class="body-index">
    <header class="top-header">
        <?php 
        $headerFile = 'includes/header.php';
        if (file_exists($headerFile)) {
            include $headerFile;
        } else {
            echo '<div class="error">Header não encontrado</div>';
        }
        ?>
    </header>

    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-section">
                <div class="sidebar-title">Menu Principal</div>
                <ul class="sidebar-menu">
                    <li class="sidebar-item">
                        <a href="index.php?page=dashboard" class="sidebar-link <?= ($page === 'dashboard') ? 'active' : '' ?>">
                            <i class="fa-solid fa-house"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="index.php?page=favoritos" class="sidebar-link <?= ($page === 'favoritos') ? 'active' : '' ?>">
                            <i class="fa-solid fa-heart"></i>
                            <span>Favoritos</span>
                            <?php if ($stats['favoritos'] > 0): ?>
                                <span class="badge"><?= $stats['favoritos'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="index.php?page=pedidos" class="sidebar-link <?= ($page === 'pedidos') ? 'active' : '' ?>">
                            <i class="fa-solid fa-box-archive"></i>
                            <span>Meus Pedidos</span>
                            <?php if ($stats['pedidos']['pendentes'] > 0): ?>
                                <span class="badge badge-warning"><?= $stats['pedidos']['pendentes'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="index.php?page=cotacoes" class="sidebar-link <?= ($page === 'cotacoes') ? 'active' : '' ?>">
                            <i class="fa-regular fa-file-lines"></i>
                            <span>Cotações</span>
                            <?php if ($stats['cotacoes']['pendentes'] > 0): ?>
                                <span class="badge badge-info"><?= $stats['cotacoes']['pendentes'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="index.php?page=devolucoes" class="sidebar-link <?= ($page === 'devolucoes') ? 'active' : '' ?>">
                            <i class="fa-solid fa-rotate-left"></i>
                            <span>Devoluções</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="index.php?page=pagamentos" class="sidebar-link <?= ($page === 'pagamentos') ? 'active' : '' ?>">
                            <i class="fa-solid fa-credit-card"></i>
                            <span>Pagamentos</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-title">Conta</div>
                <ul class="sidebar-menu">
                    <li class="sidebar-item">
                        <a href="index.php?page=perfil" class="sidebar-link <?= ($page === 'perfil') ? 'active' : '' ?>">
                            <i class="fa-solid fa-user"></i>
                            <span>Meu Perfil</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="index.php?page=configuracoes" class="sidebar-link <?= ($page === 'configuracoes') ? 'active' : '' ?>">
                            <i class="fa-solid fa-gear"></i>
                            <span>Configurações</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        <main class="main-content">
            <?php 
            // Páginas permitidas
            $allowed_pages = [
                'dashboard', 
                'favoritos', 
                'pedidos', 
                'cotacoes', 
                'devolucoes', 
                'pagamentos',
                'perfil',
                'configuracoes'
            ];
            
            // Validar página
            if (!in_array($page, $allowed_pages)) {
                $page = 'dashboard';
            }
            
            // Incluir página com verificação
            $page_file = "pages/{$page}_content.php";
            
            if (file_exists($page_file) && is_readable($page_file)) {
                include $page_file;
            } else {
                // Fallback para dashboard ou página de construção
                if ($page === 'dashboard' && file_exists('pages/dashboard_content.php')) {
                    include 'pages/dashboard_content.php';
                } else {
                    echo '<div style="padding: 60px; text-align: center;">
                            <i class="fa-solid fa-hammer" style="font-size: 72px; color: #d1d5db; margin-bottom: 24px;"></i>
                            <h2 style="color: #111827; margin-bottom: 12px;">Página em Construção</h2>
                            <p style="color: #6b7280; margin-bottom: 24px;">Esta funcionalidade estará disponível em breve.</p>
                            <a href="index.php?page=dashboard" class="btn btn-primary">
                                <i class="fa-solid fa-arrow-left"></i> Voltar ao Dashboard
                            </a>
                          </div>';
                }
            }
            ?>
        </main>
        
        <?php 
        // Incluir modais com verificação
        $modals = ['includes/messages-modal.php', 'includes/notifications_modal.php'];
        foreach ($modals as $modal) {
            if (file_exists($modal) && is_readable($modal)) {
                include $modal;
            }
        }
        ?>
    </div>

    <?php 
    $footerFile = '../../includes/footer.html';
    if (file_exists($footerFile) && is_readable($footerFile)) {
        include $footerFile;
    }
    ?>

    <script src="assets/js/notifications.js"></script>
    <script src="assets/js/messages.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page') || 'dashboard';
            
            // Atualizar menu ativo
            const sidebarLinks = document.querySelectorAll('.sidebar-link');
            sidebarLinks.forEach(link => {
                const href = link.getAttribute('href');
                link.classList.remove('active');
                
                if (href && href.includes(`page=${currentPage}`)) {
                    link.classList.add('active');
                }
            });
            
            // Menu mobile
            const mobileMenuBtn = document.getElementById('mobile-menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (mobileMenuBtn && sidebar) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                });
            }

            // ========================================
            // ATUALIZAÇÃO AUTOMÁTICA DE ESTATÍSTICAS
            // Com debounce e rate limiting
            // ========================================
            let lastUpdate = 0;
            const updateInterval = 30000; // 30 segundos
            
            async function updateStats() {
                const now = Date.now();
                
                // Rate limiting
                if (now - lastUpdate < updateInterval) {
                    return;
                }
                
                lastUpdate = now;
                
                try {
                    const response = await fetch('actions/get_stats_ajax.php');
                    const data = await response.json();
                    
                    if (data.success) {
                        // Atualizar badges de notificações
                        document.querySelectorAll('.notification-badge').forEach(badge => {
                            if (data.data.mensagens_nao_lidas > 0) {
                                badge.textContent = data.data.mensagens_nao_lidas;
                                badge.style.display = '';
                            } else {
                                badge.style.display = 'none';
                            }
                        });

                        // Atualizar contadores nos badges da sidebar
                        updateSidebarBadges(data.data);
                        
                        console.log('Stats atualizadas:', new Date().toLocaleTimeString());
                    }
                } catch (error) {
                    console.error('Erro ao atualizar estatísticas:', error);
                }
            }
            
            function updateSidebarBadges(stats) {
                // Atualizar badge de favoritos
                const favoritosLink = document.querySelector('a[href*="favoritos"]');
                if (favoritosLink && stats.favoritos > 0) {
                    updateBadge(favoritosLink, stats.favoritos);
                }
                
                // Atualizar badge de pedidos pendentes
                const pedidosLink = document.querySelector('a[href*="pedidos"]');
                if (pedidosLink && stats.pedidos_pendentes > 0) {
                    updateBadge(pedidosLink, stats.pedidos_pendentes, 'badge-warning');
                }
            }
            
            function updateBadge(linkElement, count, badgeClass = 'badge') {
                let badge = linkElement.querySelector('.badge');
                if (!badge && count > 0) {
                    badge = document.createElement('span');
                    badge.className = badgeClass;
                    linkElement.appendChild(badge);
                }
                if (badge) {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? '' : 'none';
                }
            }

            // Atualizar a cada 30 segundos
            setInterval(updateStats, updateInterval);

            // Primeira atualização após 5 segundos
            setTimeout(updateStats, 5000);
        });

        document.getElementById('current-year').textContent = new Date().getFullYear();
    </script>
</body>
</html>