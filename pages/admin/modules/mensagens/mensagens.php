<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - SISTEMA DE MENSAGENS (COMPLETO)
 * Módulo: modules/mensagens/mensagens.php
 * Descrição: Chat em tempo real com UI GitHub Dark
 * Proteção: Role-based access
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= BUSCAR CONVERSAS (ROLE-BASED) ================= */
$chatAtivo = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Admin não vê conversas com SuperAdmins
if ($isSuperAdmin) {
    $queryContatos = "
        SELECT 
            u.id as contato_id, 
            u.nome,
            u.email,
            u.role,
            u.last_activity,
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
        WHERE u.deleted_at IS NULL
        ORDER BY conv.data_msg DESC
    ";
} else {
    $queryContatos = "
        SELECT 
            u.id as contato_id, 
            u.nome,
            u.email,
            u.role,
            u.last_activity,
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
        WHERE u.deleted_at IS NULL
        AND u.role != 'superadmin'
        ORDER BY conv.data_msg DESC
    ";
}

$contatos = $mysqli->query($queryContatos);

// Buscar informações do usuário ativo
$contatoInfo = null;
if ($chatAtivo) {
    $resUser = $mysqli->query("SELECT nome, email, role, last_activity FROM users WHERE id = $chatAtivo AND deleted_at IS NULL");
    $contatoInfo = $resUser ? $resUser->fetch_assoc() : null;
    
    // Verificar se Admin pode ver este contato
    if (!$isSuperAdmin && $contatoInfo && $contatoInfo['role'] === 'superadmin') {
        $contatoInfo = null; // Bloqueia acesso
        $chatAtivo = null;
    }
}

// Estatísticas
$total_conversas = $contatos ? $contatos->num_rows : 0;
$total_nao_lidas = 0;
if ($contatos) {
    $contatos->data_seek(0);
    while ($c = $contatos->fetch_assoc()) {
        $total_nao_lidas += $c['nao_lidas'];
    }
    $contatos->data_seek(0);
}
?>

<!-- HEADER -->
<div style="margin-bottom: 24px;">
    <h1 style="color: var(--text-title); font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">
        <i class="fa-solid fa-comments" style="color: var(--accent);"></i>
        Mensagens
        <?php if (!$isSuperAdmin): ?>
            <span class="badge info" style="margin-left: 12px; font-size: 0.8rem;">
                <i class="fa-solid fa-info-circle"></i>
                Visualização Limitada
            </span>
        <?php endif; ?>
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.938rem;">
        Sistema de mensagens em tempo real
    </p>
</div>

<div class="chat-container">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Conversas</h2>
            <div class="sidebar-stats">
                <div class="sidebar-stat">
                    <i class="fa-solid fa-message"></i>
                    <strong><?= $total_conversas ?></strong> conversas
                </div>
                <?php if ($total_nao_lidas > 0): ?>
                    <div class="sidebar-stat">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <strong><?= $total_nao_lidas ?></strong> não lidas
                    </div>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary" 
                    onclick="loadContent('modules/mensagens/nova_msg')" 
                    style="margin-top: 12px; width: 100%;"
                    title="Nova conversa">
                <i class="fa-solid fa-plus"></i>
                Nova Conversa
            </button>
        </div>

        <div class="search-box">
            <input type="text" id="searchContacts" class="search-input" placeholder="Buscar conversas...">
        </div>

        <div class="contacts-list" id="contactsList">
            <?php if ($contatos && $contatos->num_rows > 0): ?>
                <?php while ($c = $contatos->fetch_assoc()): 
                    $isOnline = ($c['last_activity'] && $c['last_activity'] > (time() - 900)); // 15 min
                ?>
                    <div class="contact-item <?= $chatAtivo == $c['contato_id'] ? 'active' : '' ?>" 
                         data-contact-id="<?= $c['contato_id'] ?>"
                         data-contact-name="<?= strtolower($c['nome']) ?>"
                         onclick="loadContent('modules/mensagens/mensagens?id=<?= $c['contato_id'] ?>')">
                        
                        <?php if ($isOnline): ?>
                            <div class="online-indicator"></div>
                        <?php endif; ?>
                        
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($c['nome']) ?>&background=238636&color=fff&bold=true&size=44" 
                             class="contact-avatar" 
                             alt="<?= htmlspecialchars($c['nome']) ?>">
                        
                        <div class="contact-info">
                            <div class="contact-name">
                                <?= htmlspecialchars($c['nome']) ?>
                                <?php if ($isSuperAdmin && $c['role']): ?>
                                    <span class="badge <?= $c['role'] === 'superadmin' ? 'error' : ($c['role'] === 'admin' ? 'info' : 'neutral') ?> contact-role-badge">
                                        <?= strtoupper($c['role']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="contact-preview">
                                <?= htmlspecialchars($c['ultima_msg'] ?? 'Inicie uma conversa') ?>
                            </div>
                        </div>
                        
                        <div class="contact-meta">
                            <span class="contact-time">
                                <?= $c['data_msg'] ? date('H:i', strtotime($c['data_msg'])) : '' ?>
                            </span>
                            <?php if ($c['nao_lidas'] > 0): ?>
                                <span class="unread-badge"><?= $c['nao_lidas'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-chat">
                    <i class="fa-solid fa-inbox"></i>
                    <h3>Nenhuma conversa</h3>
                    <p>Suas mensagens aparecerão aqui</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CHAT AREA -->
    <div class="chat-main">
        <?php if ($chatAtivo && $contatoInfo): 
            $isOnline = ($contatoInfo['last_activity'] && $contatoInfo['last_activity'] > (time() - 900));
        ?>
            <!-- HEADER -->
            <div class="chat-header">
                <div class="chat-user-info">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($contatoInfo['nome']) ?>&background=238636&color=fff&bold=true&size=44" 
                         class="chat-user-avatar" 
                         alt="<?= htmlspecialchars($contatoInfo['nome']) ?>">
                    <div>
                        <div class="chat-user-name">
                            <?= htmlspecialchars($contatoInfo['nome']) ?>
                            <?php if ($isSuperAdmin && $contatoInfo['role']): ?>
                                <span class="badge <?= $contatoInfo['role'] === 'superadmin' ? 'error' : ($contatoInfo['role'] === 'admin' ? 'info' : 'neutral') ?>" style="font-size: 0.7rem; margin-left: 6px;">
                                    <?= strtoupper($contatoInfo['role']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="chat-user-status">
                            <?php if ($isOnline): ?>
                                <i class="fa-solid fa-circle" style="color: var(--accent); font-size: 0.5rem;"></i>
                                Online
                            <?php else: ?>
                                <i class="fa-solid fa-circle" style="color: var(--text-muted); font-size: 0.5rem;"></i>
                                Offline
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="action-btn" onclick="markChatAsUnread(<?= $chatAtivo ?>)" title="Marcar como não lida">
                        <i class="fa-solid fa-envelope"></i>
                    </button>
                    <button class="action-btn" onclick="toggleChatOptions(event)" title="Mais opções">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                </div>
                
                <!-- Options Menu -->
                <div id="chatOptionsMenu" class="options-menu">
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
                
                if ($historico && $historico->num_rows > 0):
                    $currentDateLabel = null;
                    
                    while ($m = $historico->fetch_assoc()): 
                        $isMe = ($m['sender_id'] == $adminId);
                        
                        // Date divider logic
                        $msgDate = date('Y-m-d', strtotime($m['created_at']));
                        $hoje = date('Y-m-d');
                        $ontem = date('Y-m-d', strtotime('-1 day'));
                        
                        if ($msgDate === $hoje) {
                            $dateLabel = 'HOJE';
                        } elseif ($msgDate === $ontem) {
                            $dateLabel = 'ONTEM';
                        } else {
                            $dateLabel = date('d/m/Y', strtotime($msgDate));
                        }
                        
                        if ($dateLabel !== $currentDateLabel):
                            $currentDateLabel = $dateLabel;
                ?>
                            <div class="date-divider">
                                <span><?= $dateLabel ?></span>
                            </div>
                <?php endif; ?>
                
                        <div class="message-wrapper <?= $isMe ? 'sent' : 'received' ?>" data-msg-id="<?= $m['id'] ?>">
                            <div class="message-bubble" oncontextmenu="showContextMenu(event, <?= $m['id'] ?>, <?= $isMe ? 'true' : 'false' ?>, '<?= htmlspecialchars(addslashes($m['message']), ENT_QUOTES) ?>')">
                                <?= nl2br(htmlspecialchars($m['message'])) ?>
                                <?php if ($isMe): ?>
                                    <button onclick="event.stopPropagation(); deleteMessage(<?= $m['id'] ?>)" class="delete-msg-btn" title="Excluir">
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
                <?php else: ?>
                    <div class="empty-chat">
                        <i class="fa-solid fa-message"></i>
                        <h3>Nenhuma mensagem ainda</h3>
                        <p>Inicie a conversa enviando uma mensagem</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- INPUT -->
            <div class="message-input-area">
                <div class="input-wrapper">
                    <button class="input-btn" title="Anexar arquivo" onclick="showToast('Recurso em desenvolvimento', 'error')">
                        <i class="fa-solid fa-paperclip"></i>
                    </button>
                    <input type="text" id="fastMsgInput" class="message-input" placeholder="Digite uma mensagem..." autocomplete="off">
                    <button class="input-btn" title="Emoji" onclick="showToast('Recurso em desenvolvimento', 'error')">
                        <i class="fa-solid fa-face-smile"></i>
                    </button>
                    <button class="send-btn" id="sendBtn" onclick="enviarChatRapido(<?= $chatAtivo ?>)">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>

        <?php else: ?>
            <div class="empty-chat">
                <i class="fa-solid fa-comments"></i>
                <h3>Selecione uma conversa</h3>
                <p>Escolha um contato para começar a conversar</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Context Menu -->
<div id="contextMenu" class="context-menu">
    <div class="context-menu-item" onclick="copyMessage()">
        <i class="fa-solid fa-copy"></i> Copiar mensagem
    </div>
    <div class="context-menu-item danger" onclick="deleteMessageFromContext()" id="deleteOption" style="display: none;">
        <i class="fa-solid fa-trash"></i> Excluir mensagem
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const ADMIN_ID = <?= $adminId ?>;
    const CHAT_ID = <?= $chatAtivo ?? 0 ?>;
    let currentContextMsgId = null;
    let currentContextMsgText = '';
    let isMyMessage = false;
    let lastMessageCount = 0;

    // ========== ENVIAR MENSAGEM ==========
    function enviarChatRapido(toId) {
        const inputElem = document.getElementById('fastMsgInput');
        const sendBtn = document.getElementById('sendBtn');
        if (!inputElem || !sendBtn) return;
        
        const msg = inputElem.value.trim();
        if (!msg) return;

        // Disable input
        inputElem.disabled = true;
        sendBtn.disabled = true;
        
        const originalMsg = msg;
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
        .then(res => res.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.status === 'success') {
                    // ADICIONAR MENSAGEM IMEDIATAMENTE (otimistic update)
                    adicionarMensagemLocal(msg, true, data.msg_id);
                    
                    // ATUALIZAR LISTA DE CONVERSAS
                    atualizarListaConversas();
                    
                    // ATUALIZAR MENSAGENS DO SERVIDOR (confirmação)
                    setTimeout(() => atualizarMensagens(toId), 500);
                    
                    showToast('Mensagem enviada!', 'success');
                } else {
                    inputElem.value = originalMsg;
                    showToast(data.message || 'Erro ao enviar', 'error');
                }
            } catch(e) {
                console.error('Parse error:', e, 'Response:', text);
                inputElem.value = originalMsg;
                showToast('Erro ao processar resposta', 'error');
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            inputElem.value = originalMsg;
            showToast('Erro ao enviar mensagem', 'error');
        })
        .finally(() => {
            inputElem.disabled = false;
            sendBtn.disabled = false;
            inputElem.focus();
        });
    }
    window.enviarChatRapido = enviarChatRapido;

    // ========== ADICIONAR MENSAGEM LOCAL (OPTIMISTIC UPDATE) ==========
    function adicionarMensagemLocal(message, isSent, msgId) {
        const chatBox = document.getElementById('chatBox');
        if (!chatBox) return;
        
        // Verificar se precisa adicionar divisor de data (HOJE)
        let lastDivider = chatBox.querySelector('.date-divider:last-of-type span');
        if (!lastDivider || lastDivider.textContent !== 'HOJE') {
            const divider = document.createElement('div');
            divider.className = 'date-divider';
            divider.innerHTML = '<span>HOJE</span>';
            chatBox.appendChild(divider);
        }
        
        const wrapper = document.createElement('div');
        wrapper.className = `message-wrapper ${isSent ? 'sent' : 'received'}`;
        wrapper.dataset.msgId = msgId || 'temp-' + Date.now();
        
        const now = new Date();
        const timeStr = now.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        const escapedMsg = escapeHtml(message).replace(/\n/g, '<br>');
        
        wrapper.innerHTML = `
            <div class="message-bubble">
                ${escapedMsg}
                ${isSent ? `<button onclick="event.stopPropagation(); deleteMessage(${msgId || 0})" class="delete-msg-btn" title="Excluir"><i class="fa-solid fa-xmark"></i></button>` : ''}
            </div>
            <small class="message-time">
                ${timeStr}
                ${isSent ? '<span class="message-status">✓</span>' : ''}
            </small>
        `;
        
        chatBox.appendChild(wrapper);
        
        // Scroll para o fim
        chatBox.scrollTop = chatBox.scrollHeight;
        
        // Incrementar contador
        lastMessageCount++;
        
        // Animação
        wrapper.style.animation = 'fadeIn 0.3s ease';
    }

    // ========== ATUALIZAR LISTA DE CONVERSAS ==========
    function atualizarListaConversas() {
        fetch('modules/mensagens/actions/processar_msg.php?action=get_conversations')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.conversations) {
                atualizarSidebarConversas(data.conversations);
            }
        })
        .catch(err => console.error('Erro ao atualizar conversas:', err));
    }

    // ========== ATUALIZAR SIDEBAR CONVERSAS ==========
    function atualizarSidebarConversas(conversations) {
        const contactsList = document.getElementById('contactsList');
        if (!contactsList) return;
        
        // Manter conversa ativa
        const activeId = CHAT_ID;
        
        // Limpar e recriar
        contactsList.innerHTML = '';
        
        if (conversations.length === 0) {
            contactsList.innerHTML = `
                <div class="empty-chat">
                    <i class="fa-solid fa-inbox"></i>
                    <h3>Nenhuma conversa</h3>
                    <p>Suas mensagens aparecerão aqui</p>
                </div>
            `;
            return;
        }
        
        conversations.forEach(conv => {
            const isOnline = conv.is_online || false;
            const isActive = (conv.contato_id == activeId);
            
            const contactDiv = document.createElement('div');
            contactDiv.className = `contact-item ${isActive ? 'active' : ''}`;
            contactDiv.dataset.contactId = conv.contato_id;
            contactDiv.dataset.contactName = conv.nome.toLowerCase();
            contactDiv.onclick = () => loadContent(`modules/mensagens/mensagens?id=${conv.contato_id}`);
            
            contactDiv.innerHTML = `
                ${isOnline ? '<div class="online-indicator"></div>' : ''}
                <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(conv.nome)}&background=238636&color=fff&bold=true&size=44" 
                     class="contact-avatar" 
                     alt="${escapeHtml(conv.nome)}">
                <div class="contact-info">
                    <div class="contact-name">
                        ${escapeHtml(conv.nome)}
                        ${conv.role_badge ? `<span class="badge ${conv.role_badge.class} contact-role-badge">${conv.role_badge.text}</span>` : ''}
                    </div>
                    <div class="contact-preview">
                        ${escapeHtml(conv.ultima_msg || 'Inicie uma conversa')}
                    </div>
                </div>
                <div class="contact-meta">
                    <span class="contact-time">
                        ${conv.data_msg ? formatTime(conv.data_msg) : ''}
                    </span>
                    ${conv.nao_lidas > 0 ? `<span class="unread-badge">${conv.nao_lidas}</span>` : ''}
                </div>
            `;
            
            contactsList.appendChild(contactDiv);
        });
    }

    function formatTime(datetime) {
        const date = new Date(datetime);
        const today = new Date();
        const isToday = date.toDateString() === today.toDateString();
        
        if (isToday) {
            return date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        } else {
            return date.toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'});
        }
    }

    // ========== ATUALIZAR MENSAGENS ==========
    function atualizarMensagens(toId) {
        if (!toId) return;
        
        fetch(`modules/mensagens/actions/processar_msg.php?action=fetch_messages&id=${toId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const chatBox = document.getElementById('chatBox');
                if (!chatBox) return;
                
                const currentMessages = data.messages.length;
                
                if (currentMessages > lastMessageCount) {
                    const isAtBottom = (chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 100);
                    const newMessages = data.messages.slice(lastMessageCount);
                    
                    let lastDateLabel = chatBox.querySelector('.date-divider:last-of-type span')?.textContent;
                    
                    newMessages.forEach(m => {
                        const isMe = (m.sender_id == ADMIN_ID);
                        const msgDate = new Date(m.created_at);
                        const hoje = new Date();
                        const ontem = new Date(hoje);
                        ontem.setDate(hoje.getDate() - 1);
                        
                        let dateLabel;
                        if (msgDate.toDateString() === hoje.toDateString()) {
                            dateLabel = 'HOJE';
                        } else if (msgDate.toDateString() === ontem.toDateString()) {
                            dateLabel = 'ONTEM';
                        } else {
                            dateLabel = msgDate.toLocaleDateString('pt-BR');
                        }
                        
                        if (dateLabel !== lastDateLabel) {
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
                        const escapedForAttr = m.message.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        
                        wrapper.innerHTML = `
                            <div class="message-bubble" oncontextmenu="showContextMenu(event, ${m.id}, ${isMe}, '${escapedForAttr}')">
                                ${escapedMsg}
                                ${isMe ? `<button onclick="event.stopPropagation(); deleteMessage(${m.id})" class="delete-msg-btn" title="Excluir"><i class="fa-solid fa-xmark"></i></button>` : ''}
                            </div>
                            <small class="message-time">
                                ${new Date(m.created_at).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}
                                ${isMe ? `<span class="message-status">${m.status === 'read' ? '✓✓' : '✓'}</span>` : ''}
                            </small>
                        `;
                        
                        chatBox.appendChild(wrapper);
                    });
                    
                    lastMessageCount = currentMessages;
                    if (isAtBottom) chatBox.scrollTop = chatBox.scrollHeight;
                }
            }
        }).catch(err => console.error('Update error:', err));
    }

    // ========== DELETAR MENSAGEM ==========
    function deleteMessage(msgId) {
        if (!confirm('Excluir esta mensagem?')) return;
        
        fetch(`modules/mensagens/actions/processar_msg.php?action=delete_message&msg_id=${msgId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const elem = document.querySelector(`[data-msg-id="${msgId}"]`);
                if (elem) {
                    elem.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => elem.remove(), 300);
                }
                lastMessageCount--;
                
                // Atualizar lista de conversas (última mensagem pode ter mudado)
                setTimeout(() => atualizarListaConversas(), 500);
                
                showToast('Mensagem excluída', 'success');
            } else {
                showToast(data.message || 'Erro ao excluir', 'error');
            }
        });
    }
    window.deleteMessage = deleteMessage;

    // ========== CONTEXT MENU ==========
    function showContextMenu(event, msgId, isMine, msgText) {
        event.preventDefault();
        
        const menu = document.getElementById('contextMenu');
        if (!menu) return;
        
        currentContextMsgId = msgId;
        isMyMessage = isMine;
        currentContextMsgText = msgText || event.target.textContent.trim();
        
        const deleteOption = document.getElementById('deleteOption');
        if (deleteOption) deleteOption.style.display = isMine ? 'flex' : 'none';
        
        menu.style.left = event.pageX + 'px';
        menu.style.top = event.pageY + 'px';
        menu.classList.add('active');
        
        setTimeout(() => {
            document.addEventListener('click', () => menu.classList.remove('active'), { once: true });
        }, 10);
    }
    window.showContextMenu = showContextMenu;

    function copyMessage() {
        navigator.clipboard.writeText(currentContextMsgText).then(() => {
            showToast('Mensagem copiada!', 'success');
        });
    }
    window.copyMessage = copyMessage;

    function deleteMessageFromContext() {
        deleteMessage(currentContextMsgId);
    }
    window.deleteMessageFromContext = deleteMessageFromContext;

    // ========== OPTIONS MENU ==========
    function toggleChatOptions(event) {
        event.stopPropagation();
        const menu = document.getElementById('chatOptionsMenu');
        if (!menu) return;
        
        const isActive = menu.classList.contains('active');
        menu.classList.toggle('active');
        
        if (!isActive) {
            setTimeout(() => {
                document.addEventListener('click', () => {
                    menu.classList.remove('active');
                }, { once: true });
            }, 10);
        }
    }
    window.toggleChatOptions = toggleChatOptions;

    function clearChat(chatId) {
        if (!confirm('Limpar toda a conversa? As mensagens serão apagadas permanentemente.')) return;
        
        const formData = new FormData();
        formData.append('action', 'clear_chat');
        formData.append('user_id', chatId);
        
        fetch('modules/mensagens/actions/processar_msg.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Conversa limpa!', 'success');
                setTimeout(() => loadContent('modules/mensagens/mensagens'), 500);
            } else {
                showToast(data.message || 'Erro ao limpar', 'error');
            }
        });
    }
    window.clearChat = clearChat;

    function exportChat(chatId) {
        showToast('Exportando conversa...', 'success');
        
        fetch(`modules/mensagens/actions/processar_msg.php?action=export_chat&user_id=${chatId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const content = `CONVERSA COM: ${data.user_name}\nDATA DE EXPORTAÇÃO: ${data.export_date}\n\n` +
                    data.messages.map(m => `[${m.timestamp}] ${m.sender}: ${m.message}`).join('\n\n');
                
                const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `conversa_${data.user_name}_${Date.now()}.txt`;
                a.click();
                URL.revokeObjectURL(url);
                
                showToast('Conversa exportada!', 'success');
            } else {
                showToast(data.message || 'Erro ao exportar', 'error');
            }
        });
    }
    window.exportChat = exportChat;

    function deleteConversation(chatId) {
        if (!confirm('ATENÇÃO: Excluir toda a conversa permanentemente? Esta ação não pode ser desfeita.')) return;
        clearChat(chatId);
    }
    window.deleteConversation = deleteConversation;

    function markChatAsUnread(chatId) {
        fetch(`modules/mensagens/actions/processar_msg.php?action=mark_unread&chat_id=${chatId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Marcado como não lida', 'success');
            }
        });
    }
    window.markChatAsUnread = markChatAsUnread;

    // ========== TOAST ==========
    function showToast(message, type = 'success', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    window.showToast = showToast;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ========== EVENT LISTENERS ==========
    const msgInput = document.getElementById('fastMsgInput');
    if (msgInput) {
        msgInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (CHAT_ID > 0) enviarChatRapido(CHAT_ID);
            }
        });
    }

    const searchInput = document.getElementById('searchContacts');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            document.querySelectorAll('.contact-item').forEach(contact => {
                const name = contact.dataset.contactName || '';
                contact.style.display = name.includes(search) ? 'flex' : 'none';
            });
        });
    }

    // ========== AUTO-UPDATE ==========
    if (CHAT_ID > 0) {
        const chatBox = document.getElementById('chatBox');
        if (chatBox) {
            lastMessageCount = chatBox.querySelectorAll('.message-wrapper').length;
            setTimeout(() => chatBox.scrollTop = chatBox.scrollHeight, 100);
        }
        
        // Atualizar mensagens a cada 3 segundos (mais rápido)
        setInterval(() => atualizarMensagens(CHAT_ID), 3000);
    }
    
    // Atualizar lista de conversas a cada 10 segundos
    setInterval(() => atualizarListaConversas(), 10000);

    console.log('✅ Sistema de mensagens carregado!');
    console.log('🔄 Auto-refresh: Mensagens (3s), Conversas (10s)');
    <?php if (!$isSuperAdmin): ?>
    console.log('ℹ️ Modo Admin: Conversas com SuperAdmins ocultas');
    <?php else: ?>
    console.log('👑 Modo SuperAdmin: Todas as conversas visíveis');
    <?php endif; ?>
})();
</script>
