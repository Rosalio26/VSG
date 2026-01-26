<?php
/* =========================================
   DASHBOARD DO CLIENTE (PESSOA FÍSICA)
   Arquivo: pages/person/pages/dashboard_content.php
   ========================================= */

$userId = $_SESSION['auth']['user_id'];

// 1. ESTATÍSTICAS GERAIS
$stmt = $mysqli->prepare("
    SELECT 
        COUNT(DISTINCT o.id) as total_pedidos,
        COUNT(DISTINCT CASE WHEN o.status IN ('pendente', 'confirmado', 'processando', 'enviado') THEN o.id END) as pedidos_ativos,
        COUNT(DISTINCT CASE WHEN o.status = 'entregue' THEN o.id END) as pedidos_entregues,
        COALESCE(SUM(CASE WHEN o.payment_status = 'pago' THEN o.total ELSE 0 END), 0) as total_gasto,
        (SELECT COUNT(*) FROM notifications WHERE receiver_id = ? AND status = 'nao_lida' AND deleted_at IS NULL) as notificacoes_nao_lidas
    FROM orders o
    WHERE o.customer_id = ?
    AND o.deleted_at IS NULL
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. PEDIDOS RECENTES
$stmt = $mysqli->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.order_date,
        o.status,
        o.payment_status,
        o.total,
        o.currency,
        u.nome as empresa_nome,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count,
        (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as total_items
    FROM orders o
    LEFT JOIN users u ON o.company_id = u.id
    WHERE o.customer_id = ?
    AND o.deleted_at IS NULL
    ORDER BY o.order_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. PRODUTOS MAIS COMPRADOS
$stmt = $mysqli->prepare("
    SELECT 
        p.id,
        p.nome,
        p.imagem,
        p.preco,
        p.currency,
        COUNT(oi.id) as vezes_comprado,
        SUM(oi.quantity) as total_quantidade
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE o.customer_id = ?
    AND o.deleted_at IS NULL
    AND p.deleted_at IS NULL
    GROUP BY p.id, p.nome, p.imagem, p.preco, p.currency
    ORDER BY vezes_comprado DESC
    LIMIT 4
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 4. NOTIFICAÇÕES RECENTES
$stmt = $mysqli->prepare("
    SELECT 
        n.id,
        n.category,
        n.priority,
        n.subject,
        n.message,
        n.created_at,
        n.status,
        DATE_FORMAT(n.created_at, '%d/%m/%Y %H:%i') as formatted_date
    FROM notifications n
    WHERE n.receiver_id = ?
    AND n.deleted_at IS NULL
    ORDER BY n.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recent_notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 5. GASTOS POR MÊS
$stmt = $mysqli->prepare("
    SELECT 
        DATE_FORMAT(order_date, '%Y-%m') as mes,
        DATE_FORMAT(order_date, '%b') as mes_nome,
        SUM(total) as total_gasto,
        COUNT(*) as num_pedidos
    FROM orders
    WHERE customer_id = ?
    AND payment_status = 'pago'
    AND order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    AND deleted_at IS NULL
    GROUP BY DATE_FORMAT(order_date, '%Y-%m'), DATE_FORMAT(order_date, '%b')
    ORDER BY mes ASC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$spending_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 6. CALCULAR CRESCIMENTO
$percentChange = 0;
if (count($spending_data) >= 2) {
    $ultimo_mes = end($spending_data)['total_gasto'];
    $penultimo_mes = prev($spending_data)['total_gasto'];
    
    if ($penultimo_mes > 0) {
        $percentChange = (($ultimo_mes - $penultimo_mes) / $penultimo_mes) * 100;
    }
}

// 7. PRODUTOS RECOMENDADOS (com paginação - 8 por vez)
$offset_produtos = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit_produtos = 8;

// Buscar categorias dos produtos comprados pelo usuário
$stmt = $mysqli->prepare("
    SELECT DISTINCT pr.category_id 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products pr ON oi.product_id = pr.id
    WHERE o.customer_id = ?
    AND o.deleted_at IS NULL
    LIMIT 10
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user_categories_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$user_categories = array_column($user_categories_result, 'category_id');
$stmt->close();

$total_recomendados = 0;
$recommended_products = [];

if (!empty($user_categories)) {
    $placeholders = str_repeat('?,', count($user_categories) - 1) . '?';
    
    // Contar total de produtos recomendados
    $count_query = "
        SELECT COUNT(DISTINCT p.id) as total
        FROM products p
        WHERE p.status = 'ativo'
        AND p.deleted_at IS NULL
        AND p.stock > 0
        AND p.category_id IN ($placeholders)
    ";
    
    $stmt = $mysqli->prepare($count_query);
    $types = str_repeat('i', count($user_categories));
    $stmt->bind_param($types, ...$user_categories);
    $stmt->execute();
    $count_result = $stmt->get_result()->fetch_assoc();
    $total_recomendados = $count_result['total'];
    $stmt->close();
    
    // Buscar produtos com LIMIT e OFFSET
    $recommended_products_query = "
        SELECT 
            p.id,
            p.nome,
            p.imagem,
            p.image_path1,
            p.image_path2,
            p.image_path3,
            p.image_path4,
            p.preco,
            p.currency,
            p.stock,
            c.name as category_name,
            u.nome as company_name,
            (SELECT COUNT(*) FROM order_items WHERE product_id = p.id) as total_vendas,
            COALESCE((SELECT AVG(rating) FROM customer_reviews WHERE product_id = p.id), 4.5) as avg_rating,
            COALESCE((SELECT COUNT(*) FROM customer_reviews WHERE product_id = p.id), 0) as review_count,
            CASE 
                WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 
                ELSE 0 
            END as is_new
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.status = 'ativo'
        AND p.deleted_at IS NULL
        AND p.stock > 0
        AND p.category_id IN ($placeholders)
        ORDER BY total_vendas DESC, p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $mysqli->prepare($recommended_products_query);
    $types = str_repeat('i', count($user_categories)) . 'ii';
    $params = array_merge($user_categories, [$limit_produtos, $offset_produtos]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $recommended_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Se não houver produtos recomendados, pegar os mais vendidos
if (empty($recommended_products) && $offset_produtos === 0) {
    $stmt = $mysqli->prepare("
        SELECT COUNT(DISTINCT p.id) as total
        FROM products p
        WHERE p.status = 'ativo'
        AND p.deleted_at IS NULL
        AND p.stock > 0
    ");
    $stmt->execute();
    $total_recomendados = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $stmt = $mysqli->prepare("
        SELECT 
            p.id,
            p.nome,
            p.imagem,
            p.image_path1,
            p.image_path2,
            p.image_path3,
            p.image_path4,
            p.preco,
            p.currency,
            p.stock,
            c.name as category_name,
            u.nome as company_name,
            COUNT(oi.id) as total_vendas,
            COALESCE((SELECT AVG(rating) FROM customer_reviews WHERE product_id = p.id), 4.5) as avg_rating,
            COALESCE((SELECT COUNT(*) FROM customer_reviews WHERE product_id = p.id), 0) as review_count,
            CASE 
                WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 
                ELSE 0 
            END as is_new
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE p.status = 'ativo'
        AND p.deleted_at IS NULL
        AND p.stock > 0
        GROUP BY p.id
        ORDER BY total_vendas DESC, p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $limit_produtos, $offset_produtos);
    $stmt->execute();
    $recommended_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Verificar se há mais produtos
$produtos_mostrados = $offset_produtos + count($recommended_products);
$has_more_products = $produtos_mostrados < $total_recomendados;
$produtos_restantes = $total_recomendados - $produtos_mostrados;

// 8. DADOS PARA QUICK ACTIONS
// Verificar se tabela favorites existe
$table_check = $mysqli->query("SHOW TABLES LIKE 'favorites'");
$favorites_count = 0;

if ($table_check->num_rows > 0) {
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM favorites WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $favorites_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Contar pedidos em trânsito
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as total
    FROM orders
    WHERE customer_id = ?
    AND status = 'enviado'
    AND deleted_at IS NULL
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$in_transit_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Verificar se tabela quotations existe
$table_check = $mysqli->query("SHOW TABLES LIKE 'quotations'");
$quotations_count = 0;

if ($table_check->num_rows > 0) {
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as total
        FROM quotations
        WHERE customer_id = ?
        AND status = 'pendente'
        AND deleted_at IS NULL
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $quotations_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Buscar últimos 3 produtos comprados (para compra rápida)
$stmt = $mysqli->prepare("
    SELECT DISTINCT
        p.id,
        p.nome,
        p.preco,
        p.currency,
        MAX(o.order_date) as last_purchase
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.customer_id = ?
    AND o.deleted_at IS NULL
    AND p.deleted_at IS NULL
    AND p.status = 'ativo'
    GROUP BY p.id, p.nome, p.preco, p.currency
    ORDER BY last_purchase DESC
    LIMIT 3
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recent_purchased = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>


<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="index.php">Início</a>
    <i class="fa-solid fa-chevron-right"></i>
    <span>Dashboard</span>
</div>

<!-- Page Header -->
<div class="page-header">
    <div class="page-title-section">
        <h1>Olá, <?= htmlspecialchars($displayName) ?> 👋</h1>
        <p class="page-subtitle">Bem-vindo ao seu painel de controle</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="window.location.href='../marketplace/index.php'">
            <i class="fa-solid fa-shopping-cart"></i>
            Ir às Compras
        </button>
    </div>
</div>

<!-- Metrics Grid -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-header">
            <div class="metric-icon green">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <?php if ($percentChange != 0): ?>
            <div class="metric-trend <?= $percentChange > 0 ? 'up' : 'down' ?>">
                <i class="fa-solid fa-arrow-<?= $percentChange > 0 ? 'up' : 'down' ?>"></i>
                <?= number_format(abs($percentChange), 1) ?>%
            </div>
            <?php endif; ?>
        </div>
        <div class="metric-value">
            <?= number_format($stats['total_gasto'] / 1000, 1) ?>K
        </div>
        <div class="metric-label">Total Gasto (MZN)</div>
    </div>

    <div class="metric-card">
        <div class="metric-header">
            <div class="metric-icon blue">
                <i class="fa-solid fa-box"></i>
            </div>
        </div>
        <div class="metric-value"><?= $stats['pedidos_ativos'] ?></div>
        <div class="metric-label">Pedidos Ativos</div>
    </div>

    <div class="metric-card">
        <div class="metric-header">
            <div class="metric-icon orange">
                <i class="fa-solid fa-clock"></i>
            </div>
        </div>
        <div class="metric-value">
            <?= count(array_filter($recent_orders, fn($o) => $o['status'] === 'pendente')) ?>
        </div>
        <div class="metric-label">Aguardando Confirmação</div>
    </div>

    <div class="metric-card">
        <div class="metric-header">
            <div class="metric-icon purple">
                <i class="fa-solid fa-check-circle"></i>
            </div>
        </div>
        <div class="metric-value"><?= $stats['pedidos_entregues'] ?></div>
        <div class="metric-label">Pedidos Entregues</div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="quick-actions-bar">
    <!-- Compra Rápida -->
    <a href="#" class="quick-action-card" onclick="event.preventDefault(); openQuickBuy()">
        <?php if (count($recent_purchased) > 0): ?>
            <div class="quick-action-badge"><?= count($recent_purchased) ?></div>
        <?php endif; ?>
        <div class="quick-action-icon" style="background: var(--light-green, #dcfce7); color: var(--primary-green, #16a34a);">
            <i class="fa-solid fa-bolt"></i>
        </div>
        <div class="quick-action-content">
            <div class="quick-action-title">Compra Rápida</div>
            <div class="quick-action-desc">
                <?php if (count($recent_purchased) > 0): ?>
                    Reordenar <?= count($recent_purchased) ?> produto<?= count($recent_purchased) > 1 ? 's' : '' ?>
                <?php else: ?>
                    Reordenar produtos favoritos
                <?php endif; ?>
            </div>
        </div>
        <i class="fa-solid fa-arrow-right" style="color: var(--primary-green, #16a34a);"></i>
    </a>

    <!-- Solicitar Cotação -->
    <a href="cotacoes.php?nova=1" class="quick-action-card">
        <?php if ($quotations_count > 0): ?>
            <div class="quick-action-badge"><?= $quotations_count ?></div>
        <?php endif; ?>
        <div class="quick-action-icon" style="background: #eff6ff; color: var(--status-info, #3b82f6);">
            <i class="fa-solid fa-file-invoice"></i>
        </div>
        <div class="quick-action-content">
            <div class="quick-action-title">Solicitar Cotação</div>
            <div class="quick-action-desc">
                <?php if ($quotations_count > 0): ?>
                    <?= $quotations_count ?> cotaç<?= $quotations_count > 1 ? 'ões' : 'ão' ?> pendente<?= $quotations_count > 1 ? 's' : '' ?>
                <?php else: ?>
                    Obtenha preços personalizados
                <?php endif; ?>
            </div>
        </div>
        <i class="fa-solid fa-arrow-right" style="color: var(--status-info, #3b82f6);"></i>
    </a>

    <!-- Rastrear Entregas -->
    <a href="pedidos.php?status=enviado" class="quick-action-card">
        <?php if ($in_transit_count > 0): ?>
            <div class="quick-action-badge"><?= $in_transit_count ?></div>
        <?php endif; ?>
        <div class="quick-action-icon" style="background: #f0f9ff; color: #0284c7;">
            <i class="fa-solid fa-truck"></i>
        </div>
        <div class="quick-action-content">
            <div class="quick-action-title">Rastrear Entregas</div>
            <div class="quick-action-desc">
                <?php if ($in_transit_count > 0): ?>
                    <?= $in_transit_count ?> pedido<?= $in_transit_count > 1 ? 's' : '' ?> em trânsito
                <?php else: ?>
                    Nenhum pedido em trânsito
                <?php endif; ?>
            </div>
        </div>
        <i class="fa-solid fa-arrow-right" style="color: #0284c7;"></i>
    </a>

    <!-- Suporte B2B -->
    <a href="suporte.php" class="quick-action-card">
        <div class="quick-action-icon" style="background: #fff7ed; color: var(--status-warning, #f59e0b);">
            <i class="fa-solid fa-headset"></i>
        </div>
        <div class="quick-action-content">
            <div class="quick-action-title">Suporte B2B</div>
            <div class="quick-action-desc">Fale com seu gerente</div>
        </div>
        <i class="fa-solid fa-arrow-right" style="color: var(--status-warning, #f59e0b);"></i>
    </a>
</div>

<!-- Content Grid -->
<div class="content-grid">
    <!-- Left Column -->
    <div>
        <!-- Recent Orders -->
        <div class="card pending-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fa-solid fa-receipt"></i>
                    Pedidos Recentes
                </h2>
                <a href="index.php?page=pedidos" class="card-action">
                    Ver todos
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recent_orders)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-shopping-bag"></i>
                        <p>Você ainda não fez nenhuma compra</p>
                        <button class="btn btn-primary" onclick="window.location.href='../../marketplace.php'">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            Explorar Produtos
                        </button>
                    </div>
                <?php else: ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Produto(s)</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): 
                                $status = getStatusInfo($order['status']);
                                $orderDate = date('d/m/Y', strtotime($order['order_date']));
                                
                                // Buscar itens do pedido (primeiro item para preview)
                                $stmt = $mysqli->prepare("
                                    SELECT 
                                        oi.product_id,
                                        oi.product_name,
                                        oi.product_image,
                                        oi.quantity,
                                        p.imagem,
                                        p.image_path1,
                                        u.nome as company_name
                                    FROM order_items oi
                                    LEFT JOIN products p ON oi.product_id = p.id
                                    LEFT JOIN users u ON p.user_id = u.id
                                    WHERE oi.order_id = ?
                                    LIMIT 1
                                ");
                                $stmt->bind_param("i", $order['id']);
                                $stmt->execute();
                                $firstItem = $stmt->get_result()->fetch_assoc();
                                $stmt->close();
                                
                                // Preparar dados do produto
                                if ($firstItem) {
                                    $productData = [
                                        'imagem' => $firstItem['imagem'] ?? $firstItem['product_image'],
                                        'image_path1' => $firstItem['image_path1'] ?? null,
                                        'company_name' => $firstItem['company_name'] ?? 'Fornecedor',
                                        'nome' => $firstItem['product_name']
                                    ];
                                    $productImage = getProductImage($productData);
                                }
                            ?>
                                <tr onclick="window.location.href='pedido_detalhes.php?id=<?= $order['id'] ?>'" style="cursor: pointer;">
                                    <td data-label="Pedido">
                                        <a href="pedido_detalhes.php?id=<?= $order['id'] ?>" class="order-id">
                                            #<?= htmlspecialchars($order['order_number']) ?>
                                        </a>
                                    </td>
                                    
                                    <td data-label="Produto(s)">
                                        <?php if ($firstItem): ?>
                                            <div class="product-cell">
                                                <div class="product-image-wrapper">
                                                    <img src="<?= htmlspecialchars($productImage) ?>" 
                                                        alt="<?= htmlspecialchars($firstItem['product_name']) ?>"
                                                        onerror="this.src='<?= generateCompanyAvatar($firstItem['company_name'] ?? 'Fornecedor', 100) ?>'">
                                                    <?php if ($order['items_count'] > 1): ?>
                                                        <div class="product-count-badge">
                                                            +<?= $order['items_count'] - 1 ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="product-details">
                                                    <p class="product-title">
                                                        <?= htmlspecialchars($firstItem['product_name']) ?>
                                                    </p>
                                                    <p class="product-quantity">
                                                        <?= (int)$firstItem['quantity'] ?> unidade<?= $firstItem['quantity'] != 1 ? 's' : '' ?>
                                                        <?php if ($order['items_count'] > 1): ?>
                                                            <span class="product-extra-count">
                                                                +<?= $order['items_count'] - 1 ?> produto<?= ($order['items_count'] - 1) != 1 ? 's' : '' ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td data-label="Data"><?= $orderDate ?></td>
                                    
                                    <td data-label="Status">
                                        <div style="display: flex; flex-direction: column; gap: 6px;">
                                            <!-- Status do Pedido -->
                                            <span class="status-badge <?= $status['class'] ?>">
                                                <span class="status-indicator"></span>
                                                <?= htmlspecialchars($status['label']) ?>
                                            </span>
                                            
                                            <!-- Status de Pagamento -->
                                            <?php 
                                            $paymentStatus = getPaymentStatusInfo($order['payment_status']);
                                            ?>
                                            <span class="status-badge status-payment <?= $paymentStatus['class'] ?>">
                                                <i class="fa-solid <?= $paymentStatus['icon'] ?>" style="font-size: 10px;"></i>
                                                <?= htmlspecialchars($paymentStatus['label']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td data-label="Total">
                                        <strong style="color: var(--gray-900); font-size: 15px;">
                                            <?= strtoupper($order['currency']) ?> 
                                            <?= number_format($order['total'], 2, ',', '.') ?>
                                        </strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Products (se houver) -->
        <?php if (!empty($top_products)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fa-solid fa-star"></i>
                    Seus Produtos Favoritos
                </h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                    <?php foreach ($top_products as $product): ?>
                        <div style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 12px; transition: all 0.2s; cursor: pointer;" 
                             onmouseover="this.style.borderColor='var(--primary)'; this.style.boxShadow='var(--shadow-sm)'"
                             onmouseout="this.style.borderColor='var(--gray-200)'; this.style.boxShadow='none'"
                             onclick="window.location.href='../marketplace/produto.php?id=<?= $product['id'] ?>'">
                            <img src="<?= htmlspecialchars($product['imagem'] ?: 'assets/img/placeholder.jpg') ?>" 
                                 alt="<?= htmlspecialchars($product['nome']) ?>"
                                 style="width: 100%; height: 120px; object-fit: cover; border-radius: 6px; margin-bottom: 8px;">
                            <h4 style="font-size: 14px; font-weight: 700; color: var(--gray-900); margin: 0 0 4px 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($product['nome']) ?>
                            </h4>
                            <p style="font-size: 12px; color: var(--gray-600); margin: 0 0 8px 0;">
                                Comprado <?= $product['vezes_comprado'] ?>x (<?= $product['total_quantidade'] ?> un)
                            </p>
                            <div style="font-size: 16px; font-weight: 700; color: var(--primary);">
                                <?= strtoupper($product['currency']) ?> 
                                <?= number_format($product['preco'], 2, ',', '.') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Column -->
    <div>
        <!-- Activity Feed -->
        <div class="card notification-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fa-solid fa-bell"></i>
                    Notificações
                </h2>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recent_notifications)): ?>
                    <div class="empty-state" style="padding: 40px 20px;">
                        <i class="fa-solid fa-inbox" style="font-size: 48px;"></i>
                        <p style="font-size: 14px; margin: 0;">Nenhuma notificação recente</p>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($recent_notifications as $notif): 
                            $activityClass = match($notif['category'] ?? 'sistema') {
                                'compra_pendente', 'compra_confirmada', 'novo_pedido' => 'order',
                                'pagamento', 'pagamento_manual' => 'payment',
                                'estoque_baixo' => 'shipment',
                                default => 'system'
                            };
                            
                            $icon = match($notif['category'] ?? 'sistema') {
                                'compra_pendente', 'compra_confirmada', 'novo_pedido' => 'fa-box',
                                'pagamento', 'pagamento_manual' => 'fa-credit-card',
                                'estoque_baixo' => 'fa-exclamation-triangle',
                                default => 'fa-bell'
                            };
                            
                            $time = strtotime($notif['created_at']);
                            $diff = time() - $time;
                            
                            if ($diff < 3600) {
                                $timeText = 'Há ' . floor($diff / 60) . 'min';
                            } elseif ($diff < 86400) {
                                $timeText = 'Há ' . floor($diff / 3600) . 'h';
                            } elseif ($diff < 172800) {
                                $timeText = 'Ontem';
                            } else {
                                $timeText = floor($diff / 86400) . 'd atrás';
                            }
                            
                            $isUnread = $notif['status'] === 'nao_lida';
                        ?>
                            <div class="activity-item">
                                <div class="activity-icon <?= $activityClass ?>">
                                    <i class="fa-solid <?= $icon ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">
                                        <?= htmlspecialchars($notif['message']) ?>
                                    </p>
                                    <p class="activity-time"><?= $timeText ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="card resumo-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fa-solid fa-chart-simple"></i>
                    Resumo Rápido
                </h2>
            </div>
            <div class="card-body">
                <div class="quick-stats">
                    <div class="stat-item">
                        <div class="stat-info">
                            <div class="stat-icon-small" style="background: #E0F2FE; color: #0EA5E9;">
                                <i class="fa-solid fa-truck-fast"></i>
                            </div>
                            <div class="stat-details">
                                <h4>Em Trânsito</h4>
                                <p>Pedidos a caminho</p>
                            </div>
                        </div>
                        <div class="stat-value">
                            <?= count(array_filter($recent_orders, fn($o) => $o['status'] === 'enviado')) ?>
                        </div>
                    </div>

                    <div class="stat-item">
                        <div class="stat-info">
                            <div class="stat-icon-small" style="background: #FEF3C7; color: #F59E0B;">
                                <i class="fa-solid fa-hourglass-half"></i>
                            </div>
                            <div class="stat-details">
                                <h4>Aguardando</h4>
                                <p>Confirmação pendente</p>
                            </div>
                        </div>
                        <div class="stat-value">
                            <?= count(array_filter($recent_orders, fn($o) => $o['status'] === 'pendente')) ?>
                        </div>
                    </div>

                    <div class="stat-item">
                        <div class="stat-info">
                            <div class="stat-icon-small" style="background: #DCFCE7; color: #16A34A;">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div class="stat-details">
                                <h4>Entregues</h4>
                                <p>Pedidos completos</p>
                            </div>
                        </div>
                        <div class="stat-value"><?= $stats['pedidos_entregues'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recommended Products Section -->
<?php if (!empty($recommended_products)): ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa-solid fa-sparkles"></i>
            Recomendados para Você
        </h2>
        <a href="../marketplace/index.php" class="card-action">
            Ver todos os produtos
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
    <div class="card-body">
        <div class="products-grid">
            <?php foreach ($recommended_products as $product): 
                // Calcular badge
                $badge = null;
                $badgeClass = '';
                
                if ($product['is_new'] == 1) {
                    $badge = '✨ Novo';
                    $badgeClass = 'new';
                } elseif ($product['total_vendas'] > 50) {
                    $badge = '🔥 Mais Vendido';
                    $badgeClass = 'hot';
                } elseif ($product['category_name'] && (
                    stripos($product['category_name'], 'eco') !== false || 
                    stripos($product['category_name'], 'sustenta') !== false ||
                    stripos($product['category_name'], 'organic') !== false
                )) {
                    $badge = '🌿 Ecológico';
                    $badgeClass = 'eco';
                }
                
                // Calcular estrelas
                $rating = round($product['avg_rating'] ?? 4.5);
                $reviewCount = $product['review_count'] ?? 0;
                
                // ✅ USAR A FUNÇÃO PARA PEGAR A MELHOR IMAGEM
                $productImage = getProductImage($product);
            ?>
                <div class="product-card-mini" onclick="window.location.href='../marketplace/produto.php?id=<?= $product['id'] ?>'">
                    <?php if ($badge): ?>
                        <div class="product-badge <?= $badgeClass ?>"><?= $badge ?></div>
                    <?php endif; ?>
                    
                    <img src="<?= htmlspecialchars($productImage) ?>" 
                         alt="<?= htmlspecialchars($product['nome']) ?>" 
                         class="product-card-img"
                         onerror="this.onerror=null; this.src='assets/img/placeholder-product.jpg'">
                    
                    <div class="product-card-content">
                        <div class="product-category">
                            <?= htmlspecialchars($product['category_name'] ?: 'Produtos') ?>
                        </div>
                        
                        <h3 class="product-card-title">
                            <?= htmlspecialchars($product['nome']) ?>
                        </h3>
                        
                        <div class="product-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $rating): ?>
                                    <i class="fa-solid fa-star"></i>
                                <?php elseif ($i - $rating < 1): ?>
                                    <i class="fa-solid fa-star-half-stroke"></i>
                                <?php else: ?>
                                    <i class="fa-regular fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <span class="rating-count">(<?= $reviewCount ?>)</span>
                        </div>
                        
                        <div class="product-meta-info">
                            <span>
                                <i class="fa-solid fa-box"></i> 
                                <?= $product['stock'] > 100 ? '100+' : $product['stock'] ?> em estoque
                            </span>
                            <span>
                                <i class="fa-solid fa-building"></i> 
                                <?= htmlspecialchars(substr($product['company_name'] ?: 'Fornecedor', 0, 15)) ?>
                            </span>
                        </div>
                        
                        <div class="product-price-section">
                            <div class="product-price">
                                <span class="price-label">Preço</span>
                                <span class="price-value">
                                    <?= strtoupper($product['currency']) ?> 
                                    <?= number_format($product['preco'], 2, ',', '.') ?>
                                </span>
                                <span class="price-unit">/unidade</span>
                            </div>
                            <button class="btn-add-cart" onclick="event.stopPropagation(); adicionarAoCarrinho(<?= $product['id'] ?>)">
                                <i class="fa-solid fa-cart-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function exportarDados() {
    if (confirm('Deseja exportar seus dados de pedidos para PDF?')) {
        window.location.href = 'exportar_pedidos.php';
    }
}
</script>

<script>
function adicionarAoCarrinho(productId) {
    // Implementar lógica de adicionar ao carrinho
    alert('Produto adicionado ao carrinho! (ID: ' + productId + ')');
    
    // Redirecionar para página do produto
    window.location.href = '../marketplace/produto.php?id=' + productId;
}

// Modal de Compra Rápida
function openQuickBuy() {
    const recentProducts = <?= json_encode($recent_purchased) ?>;
    
    if (recentProducts.length === 0) {
        alert('Você ainda não comprou nenhum produto. Explore nosso marketplace!');
        window.location.href = '../marketplace/index.php';
        return;
    }
    
    // Criar HTML do modal
    let modalHTML = `
        <div id="quickBuyModal" style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        " onclick="closeQuickBuy(event)">
            <div style="
                background: white;
                border-radius: 16px;
                padding: 32px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
            " onclick="event.stopPropagation()">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h2 style="margin: 0; font-size: 24px; color: #111827;">
                        <i class="fa-solid fa-bolt" style="color: #16a34a;"></i>
                        Compra Rápida
                    </h2>
                    <button onclick="closeQuickBuy()" style="
                        background: none;
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                        color: #6b7280;
                        padding: 0;
                        width: 32px;
                        height: 32px;
                    ">×</button>
                </div>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    Selecione os produtos que deseja reordenar:
                </p>
                <div style="display: flex; flex-direction: column; gap: 12px;">
    `;
    
    recentProducts.forEach(product => {
        modalHTML += `
            <div style="
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                transition: all 0.2s;
            " onmouseover="this.style.borderColor='#16a34a'" onmouseout="this.style.borderColor='#e5e7eb'">
                <div>
                    <h3 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 700; color: #111827;">
                        ${product.nome}
                    </h3>
                    <p style="margin: 0; font-size: 13px; color: #6b7280;">
                        Última compra: ${new Date(product.last_purchase).toLocaleDateString('pt-BR')}
                    </p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 16px; font-weight: 700; color: #16a34a; margin-bottom: 8px;">
                        ${product.currency} ${parseFloat(product.preco).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                    </div>
                    <button onclick="window.location.href='index.php?page=carrinho&id=<?= $product['id'] ?>'" style="
                        background: #16a34a;
                        color: white;
                        border: none;
                        padding: 8px 16px;
                        border-radius: 6px;
                        font-size: 13px;
                        font-weight: 600;
                        cursor: pointer;
                    ">
                        <i class="fa-solid fa-cart-plus"></i> Adicionar
                    </button>
                </div>
            </div>
        `;
    });
    
    modalHTML += `
                </div>
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                    <button onclick="window.location.href='../../../marketplace.php'" style="
                        width: 100%;
                        background: white;
                        color: #16a34a;
                        border: 2px solid #16a34a;
                        padding: 12px;
                        border-radius: 8px;
                        font-size: 14px;
                        font-weight: 600;
                        cursor: pointer;
                    ">
                        Ver Todos os Produtos
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Adicionar ao body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.body.style.overflow = 'hidden';
}

function closeQuickBuy(event) {
    if (!event || event.target.id === 'quickBuyModal') {
        const modal = document.getElementById('quickBuyModal');
        if (modal) {
            modal.remove();
            document.body.style.overflow = 'auto';
        }
    }
}

// Adicionar ao carrinho
function adicionarAoCarrinho(productId) {
    // TODO: Implementar lógica real de adicionar ao carrinho
    alert('Produto adicionado ao carrinho! (ID: ' + productId + ')');
    window.location.href = 'dashboard_person.php?page=carrinho&id=' + productId;
}
</script>