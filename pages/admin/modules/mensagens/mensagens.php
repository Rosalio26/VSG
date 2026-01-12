<?php
    if (!defined('IS_ADMIN_PAGE')) {
        require_once '../../../../registration/includes/db.php';
        session_start();
    }

    $adminId = $_SESSION['auth']['user_id'] ?? 0;
    $adminRole = $_SESSION['auth']['role'] ?? 'admin';
    $isSuperAdmin = ($adminRole === 'superadmin');

    /* ================= BUSCAR CONVERSAS ================= */
    $chatAtivo = isset($_GET['id']) ? (int)$_GET['id'] : null;

    $queryContatos = "
        SELECT 
            u.id as contato_id, 
            u.nome,
            conv.ultima_msg,
            conv.data_msg,
            conv.nao_lidas
        FROM users u
        INNER JOIN (
            SELECT 
                CASE 
                    WHEN n.sender_id = $adminId THEN n.receiver_id
                    ELSE n.sender_id
                END as user_id,
                MAX(n.created_at) as data_msg,
                SUBSTRING_INDEX(GROUP_CONCAT(n.message ORDER BY n.created_at DESC), ',', 1) as ultima_msg,
                SUM(CASE WHEN n.receiver_id = $adminId AND n.status = 'unread' THEN 1 ELSE 0 END) as nao_lidas
            FROM notifications n
            WHERE n.category = 'chat'
            AND (n.sender_id = $adminId OR n.receiver_id = $adminId)
            GROUP BY user_id
        ) as conv ON conv.user_id = u.id
        ORDER BY conv.data_msg DESC
    ";

    $contatos = $mysqli->query($queryContatos);

    // Buscar informações do usuário ativo
    $contatoInfo = null;
    if ($chatAtivo) {
        $resUser = $mysqli->query("SELECT nome FROM users WHERE id = $chatAtivo");
        $contatoInfo = $resUser->fetch_assoc();
    }
?>

<style>
    :root {
        --bg-sidebar: #0b0f0a;
        --bg-body: #050705;
        --bg-card: #121812;
        --text-main: #a0ac9f;
        --text-title: #ffffff;
        --accent-green: #00ff88;
        --accent-emerald: #00a63d;
        --accent-glow: rgba(0, 255, 136, 0.3);
        --border-color: rgba(0, 255, 136, 0.08);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-10px); }
    }

    @keyframes slideIn {
        from { transform: translateX(-20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .chat-container {
        display: flex;
        height: calc(100vh - 200px);
        background: #0005078a;
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        animation: fadeIn 0.4s ease;
    }

    /* ========== SIDEBAR ========== */
    .sidebar {
        width: 350px;
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        background: var(--bg-card);
    }

    .sidebar-header {
        padding: 15px 25px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(0, 255, 136, 0.02);
    }

    .sidebar-header h2 {
        color: var(--text-title);
        margin: 0;
        font-size: 1.3rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--accent-green), var(--accent-emerald));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .new-chat-btn {
        background: var(--accent-green);
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        cursor: pointer;
        color: #000;
        font-size: 1.1rem;
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .new-chat-btn:hover {
        box-shadow: 0 0 20px var(--accent-glow);
        transform: translateY(-2px);
    }

    .search-box {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
    }

    .search-input {
        width: 100%;
        background: rgba(0, 255, 136, 0.05);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 10px 15px;
        color: var(--text-title);
        font-size: 0.85rem;
        outline: none;
        transition: 0.3s;
    }

    .search-input:focus {
        border-color: var(--accent-green);
        box-shadow: 0 0 15px var(--accent-glow);
    }

    .contacts-list {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
    }

    .contact-item {
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: 0.2s;
        position: relative;
        animation: slideIn 0.3s ease;
        background: rgba(0, 255, 136, 0.02);
        border: 1px solid transparent;
    }

    .contact-item:hover {
        background: rgba(0, 255, 136, 0.05);
        border-color: var(--border-color);
    }

    .contact-item.active {
        background: rgba(0, 255, 136, 0.1);
        border-color: var(--accent-green);
        box-shadow: 0 0 15px var(--accent-glow);
    }

    .contact-avatar {
        width: 45px;
        height: 45px;
        border-radius: 12px;
    }

    .online-status {
        width: 10px;
        height: 10px;
        background: var(--accent-green);
        border-radius: 50%;
        position: absolute;
        bottom: 2px;
        right: 2px;
        border: 2px solid var(--bg-card);
        animation: pulse 2s infinite;
    }

    .unread-badge {
        background: var(--accent-green);
        color: #000;
        font-size: 0.7rem;
        font-weight: 900;
        padding: 3px 8px;
        border-radius: 10px;
        min-width: 20px;
        text-align: center;
    }

    /* ========== CHAT AREA ========== */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #0005078a;
    }

    .chat-header {
        padding: 10px 30px;
        background: var(--bg-card);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
    }

    .chat-user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 12px;
    }

    .action-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-color);
        width: 36px;
        height: 36px;
        border-radius: 10px;
        cursor: pointer;
        color: var(--text-main);
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .action-btn:hover {
        background: rgba(0, 255, 136, 0.1);
        border-color: var(--accent-green);
        color: var(--accent-green);
    }

    /* ========== MESSAGES ========== */
    #chatBox {
        flex: 1;
        padding: 30px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
        scroll-behavior: smooth;
    }

    /* ========== DATE DIVIDER ========== */
    .date-divider {
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 20px 0;
        position: relative;
    }

    .date-divider::before,
    .date-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(90deg, transparent, var(--border-color), transparent);
    }

    .date-divider span {
        padding: 0 15px;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-main);
        background: var(--bg-body);
        letter-spacing: 1px;
    }

    .message-wrapper {
        max-width: 70%;
        display: flex;
        flex-direction: column;
        animation: fadeIn 0.3s ease;
    }

    .message-wrapper.sent {
        align-self: flex-end;
        align-items: flex-end;
    }

    .message-wrapper.received {
        align-self: flex-start;
        align-items: flex-start;
    }

    .message-bubble {
        padding: 12px 18px;
        border-radius: 16px;
        font-size: 0.95rem;
        line-height: 1.5;
        position: relative;
        word-wrap: break-word;
    }

    .message-bubble:hover .delete-msg-btn {
        display: block !important;
    }

    .message-wrapper.sent .message-bubble {
        background: linear-gradient(135deg, var(--accent-green), var(--accent-emerald));
        color: #000;
        border-bottom-right-radius: 4px;
    }

    .message-wrapper.received .message-bubble {
        background: var(--bg-card);
        color: var(--text-title);
        border: 1px solid var(--border-color);
        border-bottom-left-radius: 4px;
    }

    .message-time {
        color: rgba(255, 255, 255, 0.4);
        font-size: 0.7rem;
        margin-top: 4px;
        font-weight: 600;
    }

    .message-status {
        display: inline-block;
        margin-left: 5px;
        font-size: 0.75rem;
    }

    /* ========== INPUT ========== */
    .message-input-area {
        padding: 10px 30px;
        background: var(--bg-card);
        border-top: 1px solid var(--border-color);
    }

    .input-wrapper {
        display: flex;
        gap: 12px;
        background: rgba(0, 255, 136, 0.05);
        padding: 8px 15px;
        border-radius: 25px;
        border: 1px solid var(--border-color);
        align-items: center;
        transition: 0.3s;
    }

    .input-wrapper:focus-within {
        border-color: var(--accent-green);
        box-shadow: 0 0 20px var(--accent-glow);
    }

    .message-input {
        flex: 1;
        background: transparent;
        border: none;
        color: var(--text-title);
        outline: none;
        padding: 8px;
        font-size: 0.95rem;
    }

    .input-btn {
        background: transparent;
        border: none;
        color: var(--text-main);
        cursor: pointer;
        font-size: 1.1rem;
        transition: 0.3s;
        padding: 4px;
    }

    .input-btn:hover {
        color: var(--accent-green);
        transform: scale(1.1);
    }

    .send-btn {
        background: var(--accent-green);
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        cursor: pointer;
        color: #000;
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .send-btn:hover {
        box-shadow: 0 0 20px var(--accent-glow);
        transform: scale(1.05);
    }

    /* ========== CONTEXT MENU ========== */
    .context-menu {
        position: absolute;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 8px;
        display: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        z-index: 1000;
        min-width: 180px;
    }

    .context-menu.active {
        display: block;
    }

    .context-menu-item {
        padding: 10px 15px;
        border-radius: 8px;
        cursor: pointer;
        color: var(--text-title);
        font-size: 0.85rem;
        transition: 0.2s;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .context-menu-item:hover {
        background: rgba(0, 255, 136, 0.1);
    }

    .context-menu-item.danger {
        color: #ff4d4d;
    }

    .context-menu-item.danger:hover {
        background: rgba(255, 77, 77, 0.1);
    }

    /* ========== EMPTY STATE ========== */
    .empty-chat {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: var(--text-main);
        opacity: 0.3;
    }

    .empty-chat i {
        font-size: 5rem;
        margin-bottom: 20px;
    }

    /* ========== SCROLLBAR ========== */
    .contacts-list::-webkit-scrollbar,
    #chatBox::-webkit-scrollbar {
        width: 6px;
    }

    .contacts-list::-webkit-scrollbar-track,
    #chatBox::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.2);
    }

    .contacts-list::-webkit-scrollbar-thumb,
    #chatBox::-webkit-scrollbar-thumb {
        background: var(--accent-green);
        border-radius: 10px;
    }
</style>

<div class="chat-container">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Mensagens</h2>
            <button class="new-chat-btn" title="Nova conversa">
                <i class="fa-solid fa-plus"></i>
            </button>
        </div>

        <div class="search-box">
            <input type="text" id="searchContacts" class="search-input" placeholder="🔍 Buscar conversas...">
        </div>

        <div class="contacts-list" id="contactsList">
            <?php if ($contatos && $contatos->num_rows > 0): ?>
                <?php while ($c = $contatos->fetch_assoc()): ?>
                    <div class="contact-item <?= $chatAtivo == $c['contato_id'] ? 'active' : '' ?>" 
                         data-contact-id="<?= $c['contato_id'] ?>"
                         data-contact-name="<?= strtolower($c['nome']) ?>"
                         onclick="loadContent('modules/mensagens/mensagens?id=<?= $c['contato_id'] ?>')">
                        
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <div style="position: relative;">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($c['nome']) ?>&background=00ff88&color=000&bold=true" class="contact-avatar">
                                <?php if (rand(0,1)): ?><div class="online-status"></div><?php endif; ?>
                            </div>
                            <div style="flex: 1; overflow: hidden;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <strong style="color: var(--text-title); font-size: 0.9rem;"><?= htmlspecialchars($c['nome']) ?></strong>
                                    <small style="color: var(--text-main); font-size: 0.7rem;">
                                        <?= $c['data_msg'] ? date('H:i', strtotime($c['data_msg'])) : '' ?>
                                    </small>
                                </div>
                                <p style="color: var(--text-main); font-size: 0.8rem; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($c['ultima_msg'] ?? 'Inicie uma conversa') ?>
                                </p>
                            </div>
                            <?php if ($c['nao_lidas'] > 0): ?>
                                <span class="unread-badge"><?= $c['nao_lidas'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-chat">
                    <i class="fa-solid fa-inbox"></i>
                    <p>Nenhuma conversa</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CHAT AREA -->
    <div class="chat-main">
        <?php if ($chatAtivo && $contatoInfo): ?>
            <!-- HEADER -->
            <div class="chat-header">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($contatoInfo['nome']) ?>&background=00ff88&color=000&bold=true" class="chat-user-avatar">
                    <div>
                        <h4 style="color: var(--text-title); margin: 0; font-weight: 700;"><?= htmlspecialchars($contatoInfo['nome']) ?></h4>
                        <small style="color: var(--text-main); font-size: 0.75rem;">Online</small>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="action-btn" onclick="markChatAsUnread(<?= $chatAtivo ?>)" title="Marcar como não lida">
                        <i class="fa-solid fa-envelope"></i>
                    </button>
                    <button class="action-btn" onclick="toggleChatOptions()" title="Mais opções">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    
                    <!-- Menu de opções (3 pontos) -->
                    <div id="chatOptionsMenu" style="display: none; position: absolute; right: 30px; top: 70px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 8px; min-width: 200px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); z-index: 1000;">
                        <div class="context-menu-item" onclick="clearChat(<?= $chatAtivo ?>)">
                            <i class="fa-solid fa-broom"></i> Limpar conversa
                        </div>
                        <div class="context-menu-item" onclick="exportChat(<?= $chatAtivo ?>)">
                            <i class="fa-solid fa-download"></i> Exportar chat
                        </div>
                        <div class="context-menu-item danger" onclick="deleteConversation(<?= $chatAtivo ?>)">
                            <i class="fa-solid fa-trash"></i> Excluir conversa
                        </div>
                    </div>
                </div>
            </div>

            <!-- MESSAGES -->
            <div id="chatBox">
                <?php 
                $historico = $mysqli->query("
                    SELECT id, message, sender_id, status, created_at 
                    FROM notifications 
                    WHERE category = 'chat'
                    AND ((sender_id = $adminId AND receiver_id = $chatAtivo) 
                         OR (sender_id = $chatAtivo AND receiver_id = $adminId))
                    ORDER BY created_at ASC
                ");
                
                $currentDateLabel = null;
                
                while ($m = $historico->fetch_assoc()): 
                    $isMe = ($m['sender_id'] == $adminId);
                    
                    // Calcular label da data
                    $msgDate = date('Y-m-d', strtotime($m['created_at']));
                    $hoje = date('Y-m-d');
                    $ontem = date('Y-m-d', strtotime('-1 day'));
                    $semanaAtras = date('Y-m-d', strtotime('-7 days'));
                    
                    // Determinar label
                    if ($msgDate === $hoje) {
                        $dateLabel = 'HOJE';
                    } elseif ($msgDate === $ontem) {
                        $dateLabel = 'ONTEM';
                    } elseif ($msgDate >= $semanaAtras) {
                        // Dia da semana em português
                        $diaSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                        $dateLabel = strtoupper($diaSemana[date('w', strtotime($msgDate))]);
                    } else {
                        // Data completa
                        $dateLabel = date('d/m/Y', strtotime($msgDate));
                    }
                    
                    // Mostrar divisor se mudou a data
                    if ($dateLabel !== $currentDateLabel):
                        $currentDateLabel = $dateLabel;
                ?>
                        <div class="date-divider">
                            <span><?= $dateLabel ?></span>
                        </div>
                <?php endif; ?>
                
                    <div class="message-wrapper <?= $isMe ? 'sent' : 'received' ?>" data-msg-id="<?= $m['id'] ?>">
                        <div class="message-bubble" oncontextmenu="showContextMenu(event, <?= $m['id'] ?>, <?= $isMe ? 'true' : 'false' ?>)">
                            <?= nl2br(htmlspecialchars($m['message'])) ?>
                            <?php if ($isMe): ?>
                                <button onclick="event.stopPropagation(); deleteMessage(<?= $m['id'] ?>)" style="position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.5); border: none; color: #fff; width: 20px; height: 20px; border-radius: 50%; cursor: pointer; display: none; font-size: 0.7rem;" class="delete-msg-btn" title="Excluir">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <small class="message-time">
                            <?= date('H:i', strtotime($m['created_at'])) ?>
                            <?php if ($isMe): ?>
                                <span class="message-status">
                                    <?= $m['status'] === 'read' ? '✓✓' : '✓' ?>
                                </span>
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- INPUT -->
            <div class="message-input-area">
                <div class="input-wrapper">
                    <button class="input-btn" title="Anexar">
                        <i class="fa-solid fa-paperclip"></i>
                    </button>
                    <input type="text" id="fastMsgInput" class="message-input" placeholder="Escreva uma mensagem...">
                    <button class="input-btn" title="Emoji">
                        <i class="fa-solid fa-face-smile"></i>
                    </button>
                    <button class="send-btn" onclick="enviarChatRapido(<?= $chatAtivo ?>)">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>

            <!-- CONTEXT MENU -->
            <div id="contextMenu" class="context-menu">
                <div class="context-menu-item" onclick="copyMessage()">
                    <i class="fa-solid fa-copy"></i> Copiar
                </div>
                <div class="context-menu-item danger" onclick="deleteMessageFromContext()" id="deleteOption" style="display: none;">
                    <i class="fa-solid fa-trash"></i> Excluir
                </div>
            </div>

        <?php else: ?>
            <div class="empty-chat">
                <i class="fa-solid fa-comments"></i>
                <h2 style="color: var(--text-title); margin: 10px 0;">Selecione uma conversa</h2>
                <p>Escolha um contato para começar</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const ADMIN_ID = <?= $adminId ?>;
    const CHAT_ID = <?= $chatAtivo ?? 0 ?>;
    let currentContextMsgId = null;
    let currentContextMsgText = '';
    let isMyMessage = false;
    let lastMessageCount = 0;

    // Enviar mensagem (CORRIGIDO: modules/mensagens/actions/processar_msg.php)
    function enviarChatRapido(toId) {
        const inputElem = document.getElementById('fastMsgInput');
        if(!inputElem) return;
        
        const msg = inputElem.value.trim();
        if(!msg) return;

        console.log('Enviando mensagem para:', toId, 'Mensagem:', msg);
        inputElem.value = '';

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('receiver_id', toId);
        formData.append('subject', 'Mensagem');
        formData.append('message', msg);

        fetch('modules/mensagens/actions/processar_msg.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            console.log('Response status:', res.status);
            return res.text();
        })
        .then(text => {
            console.log('Response text:', text);
            try {
                const data = JSON.parse(text);
                if(data.status === 'success') {
                    atualizarMensagens(toId);
                    showToast('Mensagem enviada!');
                } else {
                    console.error('Erro:', data);
                    inputElem.value = msg;
                    showToast(data.message || 'Erro ao enviar', 'error');
                }
            } catch(e) {
                console.error('Erro ao parsear JSON:', e, 'Text:', text);
                inputElem.value = msg;
                showToast('Erro ao processar resposta', 'error');
            }
        })
        .catch(err => {
            console.error('Erro no fetch:', err);
            inputElem.value = msg;
            showToast('Erro ao enviar mensagem', 'error');
        });
    }

    // Atualizar mensagens (CORRIGIDO)
    function atualizarMensagens(toId) {
        if(!toId) return;
        
        fetch(`modules/mensagens/actions/processar_msg.php?action=fetch_messages&id=${toId}`)
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                const chatBox = document.getElementById('chatBox');
                if(!chatBox) return;
                
                const currentMessages = data.messages.length;
                
                if(currentMessages > lastMessageCount) {
                    const isAtBottom = (chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 100);
                    const newMessages = data.messages.slice(lastMessageCount);
                    
                    let lastDateLabel = null;
                    
                    // Pegar última data label existente
                    const lastDivider = chatBox.querySelector('.date-divider:last-of-type');
                    if(lastDivider) {
                        lastDateLabel = lastDivider.querySelector('span').textContent;
                    }
                    
                    newMessages.forEach(m => {
                        const isMe = (m.sender_id == ADMIN_ID);
                        
                        // Calcular data label
                        const msgDate = new Date(m.created_at);
                        const hoje = new Date();
                        const ontem = new Date(hoje);
                        ontem.setDate(hoje.getDate() - 1);
                        const semanaAtras = new Date(hoje);
                        semanaAtras.setDate(hoje.getDate() - 7);
                        
                        let dateLabel;
                        const msgDateStr = msgDate.toDateString();
                        const hojeStr = hoje.toDateString();
                        const ontemStr = ontem.toDateString();
                        
                        if(msgDateStr === hojeStr) {
                            dateLabel = 'HOJE';
                        } else if(msgDateStr === ontemStr) {
                            dateLabel = 'ONTEM';
                        } else if(msgDate >= semanaAtras) {
                            const diasSemana = ['DOMINGO', 'SEGUNDA-FEIRA', 'TERÇA-FEIRA', 'QUARTA-FEIRA', 'QUINTA-FEIRA', 'SEXTA-FEIRA', 'SÁBADO'];
                            dateLabel = diasSemana[msgDate.getDay()];
                        } else {
                            dateLabel = msgDate.toLocaleDateString('pt-BR');
                        }
                        
                        // Adicionar divisor se mudou a data
                        if(dateLabel !== lastDateLabel) {
                            const divider = document.createElement('div');
                            divider.className = 'date-divider';
                            divider.innerHTML = `<span>${dateLabel}</span>`;
                            chatBox.appendChild(divider);
                            lastDateLabel = dateLabel;
                        }
                        
                        const wrapper = document.createElement('div');
                        wrapper.className = `message-wrapper ${isMe ? 'sent' : 'received'}`;
                        wrapper.dataset.msgId = m.id;
                        
                        const escapedMsg = escapeHtml(m.message).replace(/\n/g, '<br>');
                        
                        wrapper.innerHTML = `
                            <div class="message-bubble" oncontextmenu="showContextMenu(event, ${m.id}, ${isMe})">
                                ${escapedMsg}
                                ${isMe ? `<button onclick="event.stopPropagation(); deleteMessage(${m.id})" style="position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.5); border: none; color: #fff; width: 20px; height: 20px; border-radius: 50%; cursor: pointer; display: none; font-size: 0.7rem;" class="delete-msg-btn" title="Excluir"><i class="fa-solid fa-xmark"></i></button>` : ''}
                            </div>
                            <small class="message-time">
                                ${new Date(m.created_at).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}
                                ${isMe ? `<span class="message-status">${m.status === 'read' ? '✓✓' : '✓'}</span>` : ''}
                            </small>
                        `;
                        
                        chatBox.appendChild(wrapper);
                    });
                    
                    lastMessageCount = currentMessages;
                    if(isAtBottom) chatBox.scrollTop = chatBox.scrollHeight;
                }
            }
        }).catch(err => console.error('Erro:', err));
    }

    // Deletar mensagem (CORRIGIDO)
    function deleteMessage(msgId) {
        if(!confirm('Excluir esta mensagem?')) return;
        
        fetch(`modules/mensagens/actions/processar_msg.php?action=delete_message&msg_id=${msgId}`)
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                const elem = document.querySelector(`[data-msg-id="${msgId}"]`);
                if(elem) {
                    elem.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => elem.remove(), 300);
                }
                lastMessageCount--;
                showToast('Mensagem excluída');
            } else {
                showToast(data.message || 'Erro ao excluir', 'error');
            }
        });
    }

    // Context menu
    function showContextMenu(event, msgId, isMine) {
        event.preventDefault();
        
        const menu = document.getElementById('contextMenu');
        if(!menu) return;
        
        currentContextMsgId = msgId;
        isMyMessage = isMine;
        
        const bubble = event.target.closest('.message-bubble');
        currentContextMsgText = bubble ? bubble.textContent.trim() : '';
        
        const deleteOption = document.getElementById('deleteOption');
        if(deleteOption) deleteOption.style.display = isMine ? 'flex' : 'none';
        
        menu.style.left = event.pageX + 'px';
        menu.style.top = event.pageY + 'px';
        menu.classList.add('active');
        
        setTimeout(() => {
            document.addEventListener('click', () => menu.classList.remove('active'), { once: true });
        }, 10);
    }

    function copyMessage() {
        navigator.clipboard.writeText(currentContextMsgText).then(() => {
            showToast('Mensagem copiada!');
        });
    }

    function deleteMessageFromContext() {
        deleteMessage(currentContextMsgId);
    }

    // Menu de 3 pontos (USA: processar_msg.php)
    function toggleChatOptions() {
        const menu = document.getElementById('chatOptionsMenu');
        if(!menu) return;
        
        const isVisible = menu.style.display === 'block';
        menu.style.display = isVisible ? 'none' : 'block';
        
        if(!isVisible) {
            setTimeout(() => {
                document.addEventListener('click', () => {
                    menu.style.display = 'none';
                }, { once: true });
            }, 10);
        }
    }

    function clearChat(chatId) {
        if(!confirm('Limpar toda a conversa? As mensagens serão apagadas permanentemente.')) return;
        
        const formData = new FormData();
        formData.append('action', 'clear_chat');
        formData.append('user_id', chatId);
        
        fetch('modules/mensagens/actions/processar_msg.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                showToast('Conversa limpa!');
                loadContent('modules/mensagens/mensagens');
            } else {
                showToast(data.message || 'Erro ao limpar', 'error');
            }
        });
    }

    function exportChat(chatId) {
        showToast('Exportando conversa...');
        
        fetch(`modules/mensagens/actions/processar_msg.php?action=export_chat&user_id=${chatId}`)
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                // Criar arquivo para download
                const content = `CONVERSA COM: ${data.user_name}\nDATA DE EXPORTAÇÃO: ${data.export_date}\n\n` +
                    data.messages.map(m => `[${m.timestamp}] ${m.sender}: ${m.message}`).join('\n\n');
                
                const blob = new Blob([content], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `conversa_${data.user_name}_${Date.now()}.txt`;
                a.click();
                URL.revokeObjectURL(url);
                
                showToast('Conversa exportada!');
            } else {
                showToast(data.message || 'Erro ao exportar', 'error');
            }
        });
    }

    function deleteConversation(chatId) {
        if(!confirm('ATENÇÃO: Excluir toda a conversa permanentemente? Esta ação não pode ser desfeita.')) return;
        
        clearChat(chatId); // Usa a mesma função de limpar
    }

    function markChatAsUnread(chatId) {
        fetch(`modules/mensagens/actions/processar_msg.php?action=mark_unread&chat_id=${chatId}`)
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                showToast('Marcado como não lida');
            }
        });
    }

    // Toast notification (ATUALIZADO com erro)
    function showToast(message, type = 'success', duration = 3000) {
        const toast = document.createElement('div');
        toast.textContent = message;
        const bgColor = type === 'success' ? 'var(--accent-green)' : '#ff4d4d';
        const textColor = type === 'success' ? '#000' : '#fff';
        
        toast.style.cssText = `
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: ${bgColor};
            color: ${textColor};
            padding: 15px 25px;
            border-radius: 12px;
            font-weight: 700;
            z-index: 10000;
            animation: fadeIn 0.3s ease;
            box-shadow: 0 5px 20px ${type === 'success' ? 'var(--accent-glow)' : 'rgba(255,77,77,0.3)'};
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Enter para enviar
    const msgInput = document.getElementById('fastMsgInput');
    if(msgInput) {
        msgInput.addEventListener('keypress', function(e) {
            if(e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if(CHAT_ID > 0) enviarChatRapido(CHAT_ID);
            }
        });
    }

    // Busca de contatos
    const searchInput = document.getElementById('searchContacts');
    if(searchInput) {
        searchInput.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            document.querySelectorAll('.contact-item').forEach(contact => {
                const name = contact.dataset.contactName || '';
                contact.style.display = name.includes(search) ? 'block' : 'none';
            });
        });
    }

    // Auto-atualização
    if(CHAT_ID > 0) {
        const chatBox = document.getElementById('chatBox');
        if(chatBox) {
            lastMessageCount = chatBox.querySelectorAll('.message-wrapper').length;
            setTimeout(() => chatBox.scrollTop = chatBox.scrollHeight, 100);
        }
        
        setInterval(() => atualizarMensagens(CHAT_ID), 5000);
    }

    console.log('✅ Chat carregado! API: modules/mensagens/processar_msg.php');
</script>
