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

$displayName = $user['apelido'] ?: $user['nome'];
$displayAvatar = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=00ff88&color=000&bold=true";

// Categorias conforme nova lógica
$categoryLabels = [
    'reciclavel' => ['icon' => '♻️', 'label' => 'Reciclável'],
    'sustentavel' => ['icon' => '🌿', 'label' => 'Sustentável'],
    'servico' => ['icon' => '🛠️', 'label' => 'Serviços'],
    'visiongreen' => ['icon' => '🌱', 'label' => 'VisionGreen'],
    'ecologico' => ['icon' => '🌍', 'label' => 'Ecológico'],
    'outro' => ['icon' => '📦', 'label' => 'Outros']
];

// Faixas de preço
$priceRanges = [
    ['min' => 0, 'max' => 1000, 'label' => 'Até 1.000 MZN'],
    ['min' => 1000, 'max' => 5000, 'label' => '1.000 - 5.000 MZN'],
    ['min' => 5000, 'max' => 10000, 'label' => '5.000 - 10.000 MZN'],
    ['min' => 10000, 'max' => 999999, 'label' => 'Acima de 10.000 MZN']
];

// Estatísticas
$stats = [
    'mensagens_nao_lidas' => 0,
    'pedidos_em_andamento' => 0,
    'total_gasto' => 0
];

$result = $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = '$userId' AND status = 'nao_lida'");
if ($result) {
    $stats['mensagens_nao_lidas'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

$result = $mysqli->query("SELECT COUNT(*) as total FROM orders WHERE customer_id = '$userId' AND status IN ('pendente', 'confirmado', 'processando')");
if ($result) {
    $stats['pedidos_em_andamento'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

$result = $mysqli->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE customer_id = '$userId' AND payment_status = 'pago'");
if ($result) {
    $stats['total_gasto'] = (float)$result->fetch_assoc()['total'];
    $result->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionGreen Marketplace | <?= htmlspecialchars($displayName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --primary-light: #34d399;
            --dark-bg: #0f172a;
            --darker-bg: #020617;
            --card-bg: #1e293b;
            --border: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --accent: #06b6d4;
            --danger: #ef4444;
            --warning: #f59e0b;
            --sidebar-width: 300px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--darker-bg);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark-bg);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: var(--darker-bg);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .filter-section h3,
        .sidebar.collapsed .filter-option label,
        .sidebar.collapsed .btn-filter-reset span,
        .sidebar.collapsed .settings-btn span,
        .sidebar.collapsed .logout-btn span {
            display: none;
        }

        .sidebar.collapsed .collapse-btn i {
            transform: rotate(180deg);
        }

        /* Header Sidebar */
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
            position: relative;
        }

        .collapse-btn {
            position: absolute;
            right: -15px;
            top: 24px;
            width: 30px;
            height: 30px;
            background: var(--primary);
            border: 2px solid var(--dark-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #000;
            font-weight: bold;
        }

        .collapse-btn:hover {
            background: var(--primary-light);
            transform: scale(1.1);
        }

        .collapse-btn i {
            transition: transform 0.3s ease;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-main {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .logo-sub {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Filters */
        .filters-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .filter-section {
            margin-bottom: 28px;
        }

        .filter-section h3 {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-section h3::before {
            content: '';
            width: 3px;
            height: 12px;
            background: var(--primary);
            border-radius: 2px;
        }

        /* Custom Checkbox Moderno */
        .filter-option {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            margin-bottom: 6px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .filter-option:hover {
            background: rgba(16, 185, 129, 0.1);
        }

        .filter-option input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            width: 0;
            height: 0;
        }

        .checkbox-custom {
            width: 20px;
            height: 20px;
            background: var(--darker-bg);
            border: 2px solid var(--border);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            position: relative;
        }

        .filter-option input:checked ~ .checkbox-custom {
            background: var(--primary);
            border-color: var(--primary);
            transform: scale(1.1);
        }

        .checkbox-custom::after {
            content: '✓';
            color: #000;
            font-size: 14px;
            font-weight: bold;
            opacity: 0;
            transform: scale(0) rotate(-45deg);
            transition: all 0.2s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .filter-option input:checked ~ .checkbox-custom::after {
            opacity: 1;
            transform: scale(1) rotate(0deg);
        }

        .filter-option label {
            font-size: 14px;
            cursor: pointer;
            margin-left: 12px;
            flex: 1;
            user-select: none;
            color: var(--text-primary);
            font-weight: 500;
        }

        /* Radio Button Moderno */
        .radio-custom {
            width: 20px;
            height: 20px;
            background: var(--darker-bg);
            border: 2px solid var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .filter-option input:checked ~ .radio-custom {
            border-color: var(--primary);
            background: var(--primary);
        }

        .radio-custom::after {
            content: '';
            width: 8px;
            height: 8px;
            background: #000;
            border-radius: 50%;
            opacity: 0;
            transform: scale(0);
            transition: all 0.2s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .filter-option input:checked ~ .radio-custom::after {
            opacity: 1;
            transform: scale(1);
        }

        /* Botão Reset */
        .btn-filter-reset {
            width: 100%;
            padding: 12px;
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid rgba(239, 68, 68, 0.3);
            color: var(--danger);
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-filter-reset:hover {
            background: var(--danger);
            color: #fff;
            transform: translateY(-2px);
        }

        /* Sidebar Footer */
        .sidebar-footer {
            border-top: 1px solid var(--border);
            padding: 20px;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .stat-mini {
            background: var(--darker-bg);
            padding: 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
        }

        .stat-mini-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
            display: block;
        }

        .stat-mini-label {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .sidebar.collapsed .stats-mini {
            grid-template-columns: 1fr;
        }

        .sidebar.collapsed .stat-mini-label {
            display: none;
        }

        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            width: 100%;
            margin-bottom: 8px;
        }

        .btn-settings {
            background: rgba(6, 182, 212, 0.1);
            border: 2px solid rgba(6, 182, 212, 0.3);
            color: var(--accent);
        }

        .btn-settings:hover {
            background: var(--accent);
            color: #000;
            transform: translateY(-2px);
        }

        .btn-logout {
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid rgba(239, 68, 68, 0.3);
            color: var(--danger);
        }

        .btn-logout:hover {
            background: var(--danger);
            color: #fff;
            transform: translateY(-2px);
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar.collapsed + .main-content {
            margin-left: 80px;
        }

        /* Header Principal */
        .header-main {
            background: var(--dark-bg);
            border-bottom: 1px solid var(--border);
            padding: 20px 32px;
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(10px);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .search-box {
            flex: 1;
            max-width: 600px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 14px 20px 14px 48px;
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--darker-bg);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 16px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .icon-btn {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: var(--card-bg);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .icon-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: #000;
            transform: translateY(-2px);
        }

        .icon-btn .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 20px;
            height: 20px;
            background: var(--danger);
            border: 2px solid var(--dark-bg);
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 14px;
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            border-color: var(--primary);
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            display: block;
        }

        .user-role {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 2px solid var(--border);
        }

        /* Products Grid */
        .content-wrapper {
            flex: 1;
            padding: 32px;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        .product-card {
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-8px);
            border-color: var(--primary);
            box-shadow: 0 20px 40px rgba(16, 185, 129, 0.2);
        }

        .product-image {
            width: 100%;
            height: 220px;
            background: var(--darker-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image i {
            font-size: 48px;
            color: var(--text-secondary);
            opacity: 0.3;
        }

        .product-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 6px 12px;
            background: var(--primary);
            color: #000;
            font-size: 11px;
            font-weight: 800;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-info {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-category {
            font-size: 12px;
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-name {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 12px;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }

        .product-price {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
        }

        .product-stock {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .product-stock.low {
            color: var(--warning);
        }

        .product-stock.out {
            color: var(--danger);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 64px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        /* Loading */
        .loading-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
            z-index: 9999;
        }

        .loading-bar.active {
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% {
                transform: scaleX(0);
            }
            50% {
                transform: scaleX(0.7);
            }
            100% {
                transform: scaleX(1);
            }
        }

        /* Mobile Bottom Navigation */
        .mobile-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--dark-bg);
            border-top: 2px solid var(--border);
            padding: 12px 20px 20px;
            z-index: 999;
            backdrop-filter: blur(10px);
        }

        .mobile-nav-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            max-width: 500px;
            margin: 0 auto;
        }

        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 10px 8px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            border-radius: 12px;
        }

        .mobile-nav-item:active {
            transform: scale(0.95);
        }

        .mobile-nav-item.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary);
        }

        .mobile-nav-item i {
            font-size: 10px;
            transition: all 0.3s ease;
        }

        .mobile-nav-item.active i {
            transform: scale(1.2);
        }

        .mobile-nav-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .mobile-nav-badge {
            position: absolute;
            top: 6px;
            right: 18px;
            min-width: 18px;
            height: 18px;
            background: var(--danger);
            border: 2px solid var(--dark-bg);
            border-radius: 10px;
            font-size: 10px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-btn {
            display: none;
            width: 44px;
            height: 44px;
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .mobile-menu-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: #000;
        }

        /* Mobile Overlay */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 99;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .mobile-overlay.active {
            opacity: 1;
        }

        /* Content Area para navegação dinâmica */
        .dynamic-content {
            display: none;
        }

        .dynamic-content.active {
            display: block;
        }

        /* Loading Spinner */
        .content-loading {
            display: none;
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
        }

        .content-loading.active {
            display: block;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            :root {
                --sidebar-width: 0;
            }

            .sidebar {
                transform: translateX(-100%);
                box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding-bottom: 80px;
            }

            .content-wrapper {
                padding: 20px;
            }

            .products-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .header-content {
                flex-wrap: wrap;
            }

            .search-box {
                order: 3;
                max-width: 100%;
                flex-basis: 100%;
            }

            .user-info {
                display: none;
            }

            .mobile-nav {
                display: block;
            }

            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .mobile-overlay {
                display: block;
            }

            .stats-mini {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="loading-bar" id="loadingBar"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <button class="collapse-btn" onclick="toggleSidebar()" title="Colapsar Sidebar">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="brand">
                <div class="logo-icon">
                    <i class="fa-solid fa-leaf"></i>
                </div>
                <div class="logo-text">
                    <span class="logo-main">VISIONGREEN</span>
                    <span class="logo-sub">Marketplace Eco</span>
                </div>
            </div>
        </div>

        <div class="filters-container">
            <div class="filter-section">
                <h3>Categorias</h3>
                <?php foreach ($categoryLabels as $value => $data): ?>
                <div class="filter-option">
                    <input type="checkbox" id="cat_<?= $value ?>" class="category-filter" value="<?= $value ?>">
                    <span class="checkbox-custom"></span>
                    <label for="cat_<?= $value ?>"><?= $data['icon'] ?> <?= $data['label'] ?></label>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="filter-section">
                <h3>Faixa de Preço</h3>
                <?php foreach ($priceRanges as $index => $range): ?>
                <div class="filter-option">
                    <input type="radio" name="price_range" id="price_<?= $index ?>" class="price-filter" 
                           value="<?= $range['min'] ?>-<?= $range['max'] ?>">
                    <span class="radio-custom"></span>
                    <label for="price_<?= $index ?>"><?= $range['label'] ?></label>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="filter-section">
                <h3>Disponibilidade</h3>
                <div class="filter-option">
                    <input type="checkbox" id="in_stock" class="stock-filter" value="1">
                    <span class="checkbox-custom"></span>
                    <label for="in_stock">✓ Em Estoque</label>
                </div>
            </div>

            <button class="btn-filter-reset" onclick="resetFilters()">
                <i class="fa-solid fa-rotate-right"></i>
                <span>Limpar Filtros</span>
            </button>
        </div>

        <div class="sidebar-footer">
            <div class="stats-mini">
                <div class="stat-mini">
                    <span class="stat-mini-value"><?= $stats['pedidos_em_andamento'] ?></span>
                    <span class="stat-mini-label">Pedidos</span>
                </div>
                <div class="stat-mini">
                    <span class="stat-mini-value"><?= number_format($stats['total_gasto'], 0) ?></span>
                    <span class="stat-mini-label">Total Gasto</span>
                </div>
            </div>

            <a href="javascript:void(0)" onclick="navigateTo('configuracoes')" class="action-btn btn-settings">
                <i class="fa-solid fa-gear"></i>
                <span>Configurações</span>
            </a>

            <form method="post" action="../../registration/login/logout.php">
                <?= csrf_field(); ?>
                <button type="submit" class="action-btn btn-logout">
                    <i class="fa-solid fa-power-off"></i>
                    <span>Sair</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="header-main">
            <div class="header-content">
                <button class="mobile-menu-btn" onclick="toggleMobileMenu()" title="Menu">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Buscar produtos ecológicos..." autocomplete="off">
                </div>

                <div class="header-actions">
                    <button class="icon-btn" onclick="navigateTo('meus_pedidos')" title="Meus Pedidos">
                        <i class="fa-solid fa-shopping-bag"></i>
                        <?php if($stats['pedidos_em_andamento'] > 0): ?>
                            <span class="badge"><?= $stats['pedidos_em_andamento'] ?></span>
                        <?php endif; ?>
                    </button>

                    <button class="icon-btn" onclick="navigateTo('notificacoes')" title="Notificações">
                        <i class="fa-solid fa-bell"></i>
                        <?php if($stats['mensagens_nao_lidas'] > 0): ?>
                            <span class="badge"><?= $stats['mensagens_nao_lidas'] ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="user-profile" onclick="navigateTo('perfil')">
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($displayName) ?></span>
                            <span class="user-role">Cliente</span>
                        </div>
                        <img src="<?= $displayAvatar ?>" alt="Avatar" class="user-avatar">
                    </div>
                </div>
            </div>
        </header>

        <div class="content-wrapper">
            <!-- Conteúdo Home (Produtos) -->
            <div id="content-home" class="dynamic-content active">
                <div id="productsGrid" class="products-grid">
                    <div class="empty-state">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        <h3>Carregando produtos...</h3>
                        <p>Aguarde um momento</p>
                    </div>
                </div>
            </div>

            <!-- Conteúdo Meus Pedidos -->
            <div id="content-meus_pedidos" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando pedidos...</h3>
                </div>
            </div>

            <!-- Conteúdo Notificações -->
            <div id="content-notificacoes" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando notificações...</h3>
                </div>
            </div>

            <!-- Conteúdo Perfil -->
            <div id="content-perfil" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando perfil...</h3>
                </div>
            </div>

            <!-- Conteúdo Configurações -->
            <div id="content-configuracoes" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando configurações...</h3>
                </div>
            </div>
        </div>
    </main>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileMenu()"></div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav">
        <div class="mobile-nav-grid">
            <button class="mobile-nav-item active" onclick="navigateTo('home')" data-page="home">
                <i class="fa-solid fa-house"></i>
                <span class="mobile-nav-label">Início</span>
            </button>

            <button class="mobile-nav-item" onclick="navigateTo('meus_pedidos')" data-page="meus_pedidos">
                <i class="fa-solid fa-shopping-bag"></i>
                <span class="mobile-nav-label">Pedidos</span>
                <?php if($stats['pedidos_em_andamento'] > 0): ?>
                    <span class="mobile-nav-badge"><?= $stats['pedidos_em_andamento'] ?></span>
                <?php endif; ?>
            </button>

            <button class="mobile-nav-item" onclick="toggleMobileMenu()" data-page="filters">
                <i class="fa-solid fa-filter"></i>
                <span class="mobile-nav-label">Filtros</span>
            </button>

            <button class="mobile-nav-item" onclick="navigateTo('notificacoes')" data-page="notificacoes">
                <i class="fa-solid fa-bell"></i>
                <span class="mobile-nav-label">Alertas</span>
                <?php if($stats['mensagens_nao_lidas'] > 0): ?>
                    <span class="mobile-nav-badge"><?= $stats['mensagens_nao_lidas'] ?></span>
                <?php endif; ?>
            </button>

            <button class="mobile-nav-item" onclick="navigateTo('perfil')" data-page="perfil">
                <i class="fa-solid fa-user"></i>
                <span class="mobile-nav-label">Perfil</span>
            </button>
        </div>
    </nav>

    <script>
    const userData = <?= json_encode([
        'userId' => $userId,
        'nome' => $displayName,
        'email' => $user['email'],
        'publicId' => $user['public_id']
    ], JSON_UNESCAPED_UNICODE) ?>;

    let filters = {
        search: '',
        categories: [],
        priceRange: null,
        inStock: false
    };

    let currentPage = 'home';
    const loadedPages = new Set(['home']);

    // Toggle Sidebar
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', document.getElementById('sidebar').classList.contains('collapsed'));
    }

    // Mobile Menu
    function toggleMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
    }

    function closeMobileMenu() {
        document.getElementById('sidebar').classList.remove('mobile-open');
        document.getElementById('mobileOverlay').classList.remove('active');
    }

    // Sistema de Navegação Dinâmica
    async function navigateTo(page) {
        if (currentPage === page && page === 'home') return;

        closeMobileMenu();
        
        // Atualizar estado ativo nos botões mobile
        document.querySelectorAll('.mobile-nav-item').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.page === page) {
                btn.classList.add('active');
            }
        });

        // Esconder conteúdo atual
        document.querySelectorAll('.dynamic-content').forEach(content => {
            content.classList.remove('active');
        });

        // Mostrar novo conteúdo
        const contentDiv = document.getElementById(`content-${page}`);
        if (!contentDiv) return;

        contentDiv.classList.add('active');
        currentPage = page;

        // Atualizar URL sem recarregar
        window.history.pushState({ page }, '', `?page=${page}`);

        // Carregar conteúdo se necessário
        if (!loadedPages.has(page)) {
            await loadPageContent(page);
            loadedPages.add(page);
        }

        // Scroll para o topo
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Carregar conteúdo de páginas
    async function loadPageContent(page) {
        const contentDiv = document.getElementById(`content-${page}`);
        const loader = contentDiv.querySelector('.content-loading');
        
        if (loader) loader.classList.add('active');

        try {
            const response = await fetch(`pages/${page}.php`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const html = await response.text();
            
            // Remover loader e inserir conteúdo
            if (loader) loader.remove();
            contentDiv.innerHTML = html;

            // Executar scripts da página carregada
            const scripts = contentDiv.querySelectorAll('script');
            scripts.forEach(script => {
                const newScript = document.createElement('script');
                newScript.textContent = script.textContent;
                document.body.appendChild(newScript);
                document.body.removeChild(newScript);
            });

        } catch (error) {
            console.error(`Erro ao carregar ${page}:`, error);
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <h3>Erro ao carregar conteúdo</h3>
                    <p>Não foi possível carregar a página. Tente novamente.</p>
                    <button class="btn-filter-reset" onclick="navigateTo('home')" style="max-width: 200px; margin: 20px auto 0;">
                        <i class="fa-solid fa-house"></i>
                        <span>Voltar ao Início</span>
                    </button>
                </div>
            `;
        }
    }

    // Gerenciar histórico do navegador
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.page) {
            navigateTo(event.state.page);
        } else {
            navigateTo('home');
        }
    });

    // Carregar produtos
    async function loadProducts() {
        const grid = document.getElementById('productsGrid');
        const loader = document.getElementById('loadingBar');
        
        if (!grid) return;
        
        loader.classList.add('active');
        
        try {
            const params = new URLSearchParams({
                search: filters.search,
                categories: filters.categories.join(','),
                price_range: filters.priceRange || '',
                in_stock: filters.inStock ? '1' : ''
            });

            const response = await fetch(`actions/get_products.php?${params}`);
            const data = await response.json();

            if (data.success && data.products.length > 0) {
                grid.innerHTML = data.products.map(p => {
                    const imageSrc = p.imagem ? 
                        `../${p.imagem}` : '';
                    
                    const stockBadge = p.stock <= 0 ? 
                        '<span class="product-badge" style="background: var(--danger);">Esgotado</span>' :
                        (p.stock <= p.stock_minimo ? 
                            '<span class="product-badge">Últimas Unidades</span>' : '');

                    return `
                        <a href="javascript:void(0)" onclick="viewProduct(${p.id})" class="product-card">
                            <div class="product-image">
                                ${imageSrc ? 
                                    `<img src="${imageSrc}" alt="${p.nome}" loading="lazy">` :
                                    '<i class="fa-solid fa-leaf"></i>'
                                }
                                ${stockBadge}
                            </div>
                            <div class="product-info">
                                <div class="product-category">${getCategoryName(p.categoria)}</div>
                                <div class="product-name">${p.nome}</div>
                                <div class="product-footer">
                                    <div class="product-price">${parseFloat(p.preco).toFixed(2)} MZN</div>
                                    <div class="product-stock ${p.stock === 0 ? 'out' : (p.stock <= p.stock_minimo ? 'low' : '')}">
                                        ${p.stock === 0 ? 'Esgotado' : 
                                          p.stock <= p.stock_minimo ? 'Estoque Baixo' : 
                                          `${p.stock} un`}
                                    </div>
                                </div>
                            </div>
                        </a>
                    `;
                }).join('');
            } else {
                grid.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        <h3>Nenhum produto encontrado</h3>
                        <p>Tente ajustar os filtros de pesquisa</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Erro:', error);
            grid.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <h3>Erro ao carregar produtos</h3>
                    <p>Tente novamente mais tarde</p>
                </div>
            `;
        } finally {
            loader.classList.remove('active');
        }
    }

    function getCategoryName(cat) {
        const names = {
            'reciclavel': '♻️ Reciclável',
            'sustentavel': '🌿 Sustentável',
            'servico': '🛠️ Serviço',
            'visiongreen': '🌱 VisionGreen',
            'ecologico': '🌍 Ecológico',
            'outro': '📦 Outros'
        };
        return names[cat] || cat;
    }

    // Ver detalhes do produto
    function viewProduct(productId) {
        // Carregar modal ou página de detalhes
        window.location.href = `product_details.php?id=${productId}`;
    }

    // Event Listeners para Filtros
    document.querySelectorAll('.category-filter').forEach(el => {
        el.addEventListener('change', function() {
            if (this.checked) {
                filters.categories.push(this.value);
            } else {
                filters.categories = filters.categories.filter(c => c !== this.value);
            }
            loadProducts();
        });
    });

    document.querySelectorAll('.price-filter').forEach(el => {
        el.addEventListener('change', function() {
            filters.priceRange = this.value;
            loadProducts();
        });
    });

    document.querySelectorAll('.stock-filter').forEach(el => {
        el.addEventListener('change', function() {
            filters.inStock = this.checked;
            loadProducts();
        });
    });

    // Busca com debounce
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            filters.search = this.value;
            loadProducts();
        }, 500);
    });

    function resetFilters() {
        filters = {
            search: '',
            categories: [],
            priceRange: null,
            inStock: false
        };
        
        document.getElementById('searchInput').value = '';
        document.querySelectorAll('.category-filter, .price-filter, .stock-filter').forEach(el => el.checked = false);
        loadProducts();
    }

    // Inicialização
    document.addEventListener('DOMContentLoaded', function() {
        // Restaurar estado do sidebar
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            document.getElementById('sidebar').classList.add('collapsed');
        }

        // Verificar página inicial via URL
        const urlParams = new URLSearchParams(window.location.search);
        const initialPage = urlParams.get('page') || 'home';
        
        if (initialPage !== 'home') {
            navigateTo(initialPage);
        } else {
            loadProducts();
        }

        console.log('✅ VisionGreen Marketplace -', userData.nome);
    });

    // Fechar menu mobile ao clicar fora
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.mobile-menu-btn');
        
        if (sidebar.classList.contains('mobile-open') && 
            !sidebar.contains(event.target) && 
            !menuBtn.contains(event.target)) {
            closeMobileMenu();
        }
    });

    // Prevenir scroll do body quando sidebar mobile está aberto
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.classList.contains('mobile-open')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });
    });

    observer.observe(document.getElementById('sidebar'), {
        attributes: true,
        attributeFilter: ['class']
    });
    </script>
</body>
</html>