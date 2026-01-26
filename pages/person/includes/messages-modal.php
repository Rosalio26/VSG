<!-- =========================================
     MODAL DE MENSAGENS - ESTILO ALIBABA
     ========================================= -->

<?php
$user_id = $_SESSION['auth']['user_id'] ?? 0;
$conversations = [];
$total_unread = 0;

if ($user_id > 0) {
    $stmt = $mysqli->prepare("
        SELECT 
            CASE 
                WHEN n.sender_id = ? THEN n.receiver_id 
                ELSE n.sender_id 
            END as contact_id,
            u.nome as contact_name,
            u.apelido as contact_surname,
            u.type as contact_type,
            MAX(n.created_at) as last_message_date,
            (SELECT message FROM notifications 
             WHERE (sender_id = contact_id AND receiver_id = ?) 
                OR (sender_id = ? AND receiver_id = contact_id)
             ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM notifications 
             WHERE sender_id = contact_id 
               AND receiver_id = ? 
               AND status = 'nao_lida'
               AND deleted_at IS NULL) as unread_count
        FROM notifications n
        LEFT JOIN users u ON (
            CASE 
                WHEN n.sender_id = ? THEN n.receiver_id 
                ELSE n.sender_id 
            END = u.id
        )
        WHERE (n.sender_id = ? OR n.receiver_id = ?)
          AND n.deleted_at IS NULL
          AND n.reply_to IS NOT NULL
        GROUP BY contact_id, u.nome, u.apelido, u.type
        ORDER BY last_message_date DESC
        LIMIT 20
    ");
    $stmt->bind_param("iiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    $total_unread = (int)$result->fetch_assoc()['total'];
    $stmt->close();
}
?>

<!-- Modal de Mensagens -->
<div id="messages-modal" class="messages-modal-overlay" style="display: none;">
    <div class="messages-modal-container">
        
        <!-- SIDEBAR DE CONVERSAS -->
        <div class="messages-sidebar">
            <!-- Header -->
            <div class="messages-sidebar-header">
                <h3>
                    <i class="fa-regular fa-comment-dots"></i>
                    Mensagens
                    <?php if ($total_unread > 0): ?>
                        <span class="badge-count-msg"><?= $total_unread ?></span>
                    <?php endif; ?>
                </h3>
                <button class="btn-icon" id="close-messages-modal">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <!-- Search -->
            <div class="messages-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Buscar conversas..." id="search-conv">
            </div>

            <!-- Filtros -->
            <div class="messages-filters">
                <button class="msg-filter-btn active" data-filter="all">
                    Todas
                </button>
                <button class="msg-filter-btn" data-filter="unread">
                    Não Lidas (<?= $total_unread ?>)
                </button>
                <button class="msg-filter-btn" data-filter="companies">
                    Empresas
                </button>
            </div>

            <!-- Lista -->
            <div class="conversations-list">
                <?php if (empty($conversations)): ?>
                    <div class="empty-conv">
                        <i class="fa-regular fa-comment-slash"></i>
                        <p>Nenhuma conversa</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $c): ?>
                        <?php 
                        $name = $c['contact_surname'] ? $c['contact_name'] . ' ' . $c['contact_surname'] : $c['contact_name'];
                        $avatar = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=00b96b&color=fff";
                        ?>
                        <div class="conv-item <?= $c['unread_count'] > 0 ? 'unread' : '' ?>" 
                             data-contact-id="<?= $c['contact_id'] ?>"
                             data-contact-name="<?= htmlspecialchars($name) ?>"
                             data-contact-avatar="<?= $avatar ?>">
                            
                            <div class="conv-avatar">
                                <img src="<?= $avatar ?>" alt="">
                                <?php if ($c['unread_count'] > 0): ?>
                                    <span class="unread-dot"><?= $c['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="conv-info">
                                <div class="conv-top">
                                    <h4><?= htmlspecialchars($name) ?></h4>
                                    <span class="conv-time">
                                        <?= date('H:i', strtotime($c['last_message_date'])) ?>
                                    </span>
                                </div>
                                <p class="conv-preview">
                                    <?= htmlspecialchars(substr($c['last_message'], 0, 40)) ?>
                                    <?= strlen($c['last_message']) > 40 ? '...' : '' ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- CHAT AREA -->
        <div class="messages-chat">
            <!-- Empty State -->
            <div class="chat-empty" id="chat-empty">
                <i class="fa-regular fa-comments"></i>
                <h3>Selecione uma conversa</h3>
                <p>Escolha um contato para começar</p>
            </div>

            <!-- Chat Active -->
            <div class="chat-active" id="chat-active" style="display: none;">
                <!-- Header -->
                <div class="chat-header">
                    <div class="chat-user">
                        <img src="" alt="" id="chat-avatar">
                        <div>
                            <h4 id="chat-name">Contato</h4>
                            <span class="chat-status">
                                <i class="fa-solid fa-circle"></i> Online
                            </span>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <button class="btn-icon">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        <button class="btn-icon">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div class="chat-messages" id="chat-msgs">
                    <!-- Carregado via JS -->
                </div>

                <!-- Input -->
                <div class="chat-input-wrapper">
                    <button class="btn-icon">
                        <i class="fa-solid fa-paperclip"></i>
                    </button>
                    
                    <textarea 
                        id="msg-input" 
                        placeholder="Digite sua mensagem..."
                        rows="1"></textarea>
                    
                    <button class="btn-icon">
                        <i class="fa-regular fa-face-smile"></i>
                    </button>
                    
                    <button class="btn-send-msg" id="btn-send">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>

                <!-- Quick Replies -->
                <div class="quick-replies">
                    <button class="qr-btn" data-msg="Olá! Gostaria de mais informações.">
                        💬 Informações
                    </button>
                    <button class="qr-btn" data-msg="Qual o prazo de entrega?">
                        🚚 Prazo
                    </button>
                    <button class="qr-btn" data-msg="Qual a quantidade mínima?">
                        📦 MOQ
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="msg-template">
    <div class="chat-msg">
        <div class="msg-bubble">
            <p class="msg-text"></p>
            <span class="msg-time"></span>
        </div>
    </div>
</template>