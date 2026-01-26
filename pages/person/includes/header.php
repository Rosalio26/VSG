<?php
$notif_count = 0;
$total_unread = 0;
$notifications = [];

if (isset($_SESSION['auth']['user_id'])) {
    $user_id = (int)$_SESSION['auth']['user_id'];
    
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as total 
        FROM notifications 
        WHERE receiver_id = ? 
        AND status = 'nao_lida' 
        AND deleted_at IS NULL
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $notif_count = (int)$result->fetch_assoc()['total'];
    }
    $stmt->close();
    
    $stmt = $mysqli->prepare("
        SELECT n.*, 
        o.order_number,
        p.nome as product_name,
        DATE_FORMAT(n.created_at, '%d/%m/%Y %H:%i') as formatted_date
        FROM notifications n
        LEFT JOIN orders o ON n.related_order_id = o.id
        LEFT JOIN products p ON n.related_product_id = p.id
        WHERE n.receiver_id = ? 
        AND n.deleted_at IS NULL
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as total 
        FROM notifications 
        WHERE receiver_id = ? 
        AND status = 'nao_lida' 
        AND reply_to IS NOT NULL
        AND deleted_at IS NULL
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $total_unread = (int)$result->fetch_assoc()['total'];
    }
    $stmt->close();
}
?>

<div class="top-header-content">
    <div class="logo-section">
        <a href="index.php?page=dashboard" class="logo">
            VSG<span class="logo-accent">•</span> 
            <span class="title-panel">painel do cliente</span>
        </a>
    </div>

    <div class="header-search">
        <div class="search-wrapper">
            <input type="text" class="search-input" placeholder="Buscar produtos...">
            <button class="search-btn">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </div>
    </div>

    <div class="header-actions">
        <a href="#" class="header-action-btn" id="btn-notifications">
            <i class="fa-regular fa-bell"></i>
            <?php if ($notif_count > 0): ?>
                <span class="notification-badge"><?= $notif_count ?></span>
            <?php endif; ?>
        </a>

        <a href="#" class="header-action-btn" id="btn-messages">
            <i class="fa-regular fa-comment-dots"></i>
            <?php if ($total_unread > 0): ?>
                <span class="notification-badge"><?= $total_unread ?></span>
            <?php endif; ?>
        </a>

        <a href="#" class="app-circle-btn">
            <div class="cnt-circle-app">
                <div class="line-1-app circle-app"></div>
                <div class="line-1-app circle-app"></div>
                <div class="line-1-app circle-app"></div>
            </div>
            <div class="cnt-circle-app">
                <div class="line-2-app circle-app"></div>
                <div class="line-2-app circle-app"></div>
                <div class="line-2-app circle-app"></div>
            </div>
            <div class="cnt-circle-app">
                <div class="line-3-app circle-app"></div>
                <div class="line-3-app circle-app"></div>
                <div class="line-3-app circle-app"></div>
            </div>
        </a>

        <div class="user-menu">
            <img src="<?= $displayAvatar ?>" alt="Avatar" class="user-avatar">
        </div>
    </div>
</div>