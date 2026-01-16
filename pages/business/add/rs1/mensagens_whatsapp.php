<?php
/**
 * MÓDULO DE MENSAGENS ESTILO WHATSAPP - VisionGreen Pro
 * Design inspirado no WhatsApp com funcionalidades completas
 */

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir conexão com banco de dados
require_once __DIR__ . '/../../../registration/includes/db.php';

// Verificar autenticação
if (empty($_SESSION['auth']['user_id'])) {
    echo "<div style='padding: 40px; text-align: center;'>❌ Acesso negado - Faça login novamente</div>";
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];

// Buscar conversas agrupadas por remetente
$conversations = $mysqli->query("
    SELECT 
        n.sender_id,
        sender.nome as sender_name,
        sender.type as sender_type,
        b.logo_path,
        MAX(n.created_at) as last_message_time,
        (SELECT subject FROM notifications WHERE sender_id = n.sender_id AND receiver_id = $userId ORDER BY created_at DESC LIMIT 1) as last_subject,
        (SELECT message FROM notifications WHERE sender_id = n.sender_id AND receiver_id = $userId ORDER BY created_at DESC LIMIT 1) as last_message,
        COUNT(CASE WHEN n.status = 'unread' THEN 1 END) as unread_count
    FROM notifications n
    LEFT JOIN users sender ON n.sender_id = sender.id
    LEFT JOIN businesses b ON sender.id = b.user_id
    WHERE n.receiver_id = $userId
    GROUP BY n.sender_id, sender.nome, sender.type, b.logo_path
    ORDER BY last_message_time DESC
")->fetch_all(MYSQLI_ASSOC);

// Buscar mensagens do sistema (sender_id NULL)
$system_messages = $mysqli->query("
    SELECT 
        COUNT(*) as total_messages,
        COUNT(CASE WHEN status = 'unread' THEN 1 END) as unread_count,
        MAX(created_at) as last_message_time,
        (SELECT subject FROM notifications WHERE sender_id IS NULL AND receiver_id = $userId ORDER BY created_at DESC LIMIT 1) as last_subject,
        (SELECT message FROM notifications WHERE sender_id IS NULL AND receiver_id = $userId ORDER BY created_at DESC LIMIT 1) as last_message
    FROM notifications
    WHERE receiver_id = $userId AND sender_id IS NULL
")->fetch_assoc();

if ($system_messages['total_messages'] > 0) {
    array_unshift($conversations, [
        'sender_id' => null,
        'sender_name' => 'Sistema VisionGreen',
        'sender_type' => 'admin',
        'logo_path' => null,
        'last_message_time' => $system_messages['last_message_time'],
        'last_subject' => $system_messages['last_subject'],
        'last_message' => $system_messages['last_message'],
        'unread_count' => $system_messages['unread_count']
    ]);
}

// Função auxiliar de tempo
function time_ago($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->d == 0) {
        if ($diff->h > 0) return $diff->h . 'h';
        if ($diff->i > 0) return $diff->i . 'min';
        return 'agora';
    } elseif ($diff->d == 1) {
        return 'ontem';
    } elseif ($diff->d < 7) {
        return $diff->d . 'd';
    } else {
        return $ago->format('d/m/Y');
    }
}

$uploadBase = "../../registration/uploads/business/";
?>

<style>
/* Estilos base WhatsApp */
.whatsapp-container {
    display: flex;
    height: calc(100vh - 200px);
    background: #0a0e17;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
}

/* Lista de conversas (esquerda) */
.conversations-panel {
    width: 400px;
    background: #111b21;
    border-right: 1px solid rgba(255,255,255,0.05);
    display: flex;
    flex-direction: column;
}

.conversations-header {
    padding: 20px;
    background: #1e2a32;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.conversations-header h2 {
    color: #fff;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 15px;
}

.search-box {
    background: #0f1419;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 10px 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-box input {
    background: none;
    border: none;
    color: #fff;
    font-size: 14px;
    outline: none;
    width: 100%;
}

.search-box input::placeholder {
    color: #8b949e;
}

.conversations-list {
    flex: 1;
    overflow-y: auto;
}

.conversation-item {
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    transition: 0.2s;
    border-bottom: 1px solid rgba(255,255,255,0.03);
    position: relative;
}

.conversation-item:hover {
    background: rgba(255,255,255,0.05);
}

.conversation-item.active {
    background: #00a884;
}

.conversation-item.active .conv-name,
.conversation-item.active .conv-last-msg,
.conversation-item.active .conv-time {
    color: #fff !important;
}

.conv-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #00a884;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
    overflow: hidden;
}

.conv-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.conv-info {
    flex: 1;
    min-width: 0;
}

.conv-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.conv-name {
    font-weight: 600;
    color: #e9edef;
    font-size: 15px;
}

.conv-time {
    font-size: 11px;
    color: #8b949e;
}

.conv-last-msg {
    font-size: 13px;
    color: #8b949e;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conv-unread-badge {
    position: absolute;
    right: 20px;
    bottom: 15px;
    background: #00a884;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 12px;
    min-width: 20px;
    text-align: center;
}

/* Painel de chat (direita) */
.chat-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #0a0e17;
}

.chat-header {
    padding: 15px 25px;
    background: #1e2a32;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chat-user-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.chat-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #00a884;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #fff;
    overflow: hidden;
}

.chat-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.chat-user-details h3 {
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 2px;
}

.chat-user-details p {
    color: #8b949e;
    font-size: 12px;
}

.chat-actions {
    display: flex;
    gap: 10px;
}

.chat-action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: 0.2s;
    color: #8b949e;
}

.chat-action-btn:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}

/* Mensagens */
.messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background-image: 
        repeating-linear-gradient(
            45deg,
            transparent,
            transparent 10px,
            rgba(255,255,255,0.01) 10px,
            rgba(255,255,255,0.01) 20px
        );
}

.message-date-separator {
    text-align: center;
    margin: 20px 0;
}

.date-badge {
    display: inline-block;
    background: #1e2a32;
    color: #8b949e;
    font-size: 12px;
    padding: 5px 12px;
    border-radius: 8px;
}

.message-bubble {
    max-width: 70%;
    margin-bottom: 10px;
    animation: messageSlideIn 0.3s ease;
}

@keyframes messageSlideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message-bubble.received {
    margin-right: auto;
}

.message-bubble.sent {
    margin-left: auto;
}

.message-content {
    background: #1e2a32;
    padding: 10px 15px;
    border-radius: 12px;
    position: relative;
}

.message-bubble.sent .message-content {
    background: #005c4b;
}

.message-priority {
    display: inline-block;
    font-size: 9px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 4px;
    margin-bottom: 5px;
    text-transform: uppercase;
}

.priority-critical { background: #ff3232; color: #fff; }
.priority-high { background: #ff6b35; color: #fff; }
.priority-medium { background: #ffc107; color: #000; }
.priority-low { background: #3b82f6; color: #fff; }

.message-subject {
    font-weight: 700;
    font-size: 15px;
    color: #fff;
    margin-bottom: 5px;
}

.message-text {
    color: #e9edef;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 5px;
}

.message-meta {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: #8b949e;
}

.message-bubble.sent .message-meta {
    color: rgba(255,255,255,0.6);
}

/* Estado vazio */
.empty-chat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #8b949e;
    text-align: center;
}

.empty-chat i {
    width: 100px;
    height: 100px;
    color: #8b949e;
    opacity: 0.2;
    margin-bottom: 20px;
}

.empty-chat h3 {
    font-size: 20px;
    color: #fff;
    margin-bottom: 10px;
}

/* Barra de input */
.chat-input-bar {
    padding: 15px 25px;
    background: #1e2a32;
    border-top: 1px solid rgba(255,255,255,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
}

.chat-input {
    flex: 1;
    background: #0f1419;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 12px 15px;
    color: #fff;
    font-size: 14px;
    outline: none;
    resize: none;
    max-height: 100px;
}

.chat-input::placeholder {
    color: #8b949e;
}

.send-btn {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #00a884;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: 0.2s;
    color: #fff;
}

.send-btn:hover {
    background: #00c997;
    transform: scale(1.05);
}

.send-btn:active {
    transform: scale(0.95);
}

/* Scrollbar customizado */
.conversations-list::-webkit-scrollbar,
.messages-container::-webkit-scrollbar {
    width: 6px;
}

.conversations-list::-webkit-scrollbar-track,
.messages-container::-webkit-scrollbar-track {
    background: transparent;
}

.conversations-list::-webkit-scrollbar-thumb,
.messages-container::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
}

.conversations-list::-webkit-scrollbar-thumb:hover,
.messages-container::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.2);
}

/* Sistema de categorias */
.category-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    padding: 3px 8px;
    border-radius: 6px;
    margin-bottom: 5px;
    font-weight: 600;
}

.category-chat { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.category-alert { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
.category-security { background: rgba(255, 50, 50, 0.2); color: #ff3232; }
.category-system_error { background: rgba(255, 107, 53, 0.2); color: #ff6b35; }
.category-audit { background: rgba(139, 148, 158, 0.2); color: #8b949e; }

/* Responsivo */
@media (max-width: 768px) {
    .conversations-panel {
        width: 100%;
        position: absolute;
        z-index: 10;
    }
    
    .chat-panel {
        width: 100%;
        position: absolute;
        z-index: 11;
        left: 100%;
        transition: 0.3s;
    }
    
    .chat-panel.active {
        left: 0;
    }
}
</style>

<div class="whatsapp-container">
    <!-- Lista de Conversas -->
    <div class="conversations-panel">
        <div class="conversations-header">
            <h2>💬 Mensagens</h2>
            <div class="search-box">
                <i data-lucide="search" style="width: 18px; color: #8b949e;"></i>
                <input type="text" id="searchInput" placeholder="Pesquisar conversas...">
            </div>
        </div>
        
        <div class="conversations-list" id="conversationsList">
            <?php if (empty($conversations)): ?>
                <div style="padding: 40px 20px; text-align: center; color: #8b949e;">
                    <i data-lucide="inbox" style="width: 60px; height: 60px; margin: 0 auto 15px; opacity: 0.3;"></i>
                    <p>Nenhuma mensagem ainda</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <div class="conversation-item" 
                         data-sender-id="<?= $conv['sender_id'] ?? 'system' ?>"
                         onclick="openChat(<?= $conv['sender_id'] ?? 'null' ?>, '<?= htmlspecialchars($conv['sender_name']) ?>')">
                        
                        <div class="conv-avatar">
                            <?php if ($conv['logo_path']): ?>
                                <img src="<?= $uploadBase . $conv['logo_path'] ?>" alt="">
                            <?php else: ?>
                                <?= strtoupper(substr($conv['sender_name'], 0, 2)) ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="conv-info">
                            <div class="conv-header">
                                <span class="conv-name"><?= htmlspecialchars($conv['sender_name']) ?></span>
                                <span class="conv-time"><?= time_ago($conv['last_message_time']) ?></span>
                            </div>
                            <div class="conv-last-msg">
                                <?= htmlspecialchars(substr($conv['last_message'], 0, 50)) ?>...
                            </div>
                        </div>
                        
                        <?php if ($conv['unread_count'] > 0): ?>
                            <span class="conv-unread-badge"><?= $conv['unread_count'] ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Painel de Chat -->
    <div class="chat-panel" id="chatPanel">
        <div class="empty-chat">
            <i data-lucide="message-circle"></i>
            <h3>Selecione uma conversa</h3>
            <p>Escolha uma conversa da lista para começar a ler</p>
        </div>
    </div>
</div>

<script>
// Variáveis globais
const userId = <?= $userId ?>;
let currentChatId = null;
let messagesCache = {};

// Dados das conversas (carregados do PHP)
const conversationsData = <?= json_encode($conversations) ?>;

// Buscar mensagens de uma conversa
async function openChat(senderId, senderName) {
    currentChatId = senderId;
    
    // Atualizar UI - marcar conversa ativa
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    const activeConv = document.querySelector(`[data-sender-id="${senderId || 'system'}"]`);
    if (activeConv) activeConv.classList.add('active');
    
    // Mostrar loading
    const chatPanel = document.getElementById('chatPanel');
    chatPanel.innerHTML = '<div class="empty-chat"><i data-lucide="loader" class="spinning"></i><p>Carregando mensagens...</p></div>';
    if (typeof lucide !== 'undefined') lucide.createIcons();
    
    try {
        // Buscar mensagens via AJAX
        const response = await fetch(`actions/get_messages.php?sender_id=${senderId || ''}`);
        const data = await response.json();
        
        if (data.success) {
            messagesCache[senderId] = data.messages;
            renderChat(senderId, senderName, data.messages, data.sender_info);
            
            // Marcar como lidas automaticamente
            if (data.messages.some(m => m.status === 'unread')) {
                markConversationAsRead(senderId);
            }
        } else {
            throw new Error(data.error || 'Erro ao carregar mensagens');
        }
    } catch (error) {
        console.error('Erro:', error);
        chatPanel.innerHTML = `
            <div class="empty-chat">
                <i data-lucide="alert-circle" style="color: #ff3232;"></i>
                <h3>Erro ao carregar</h3>
                <p>${error.message}</p>
            </div>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

// Renderizar o chat
function renderChat(senderId, senderName, messages, senderInfo) {
    const chatPanel = document.getElementById('chatPanel');
    
    const avatar = senderInfo?.logo_path 
        ? `<img src="../../registration/uploads/business/${senderInfo.logo_path}" alt="">`
        : senderName.substring(0, 2).toUpperCase();
    
    let messagesHtml = '';
    let currentDate = '';
    
    messages.forEach(msg => {
        const msgDate = new Date(msg.created_at).toLocaleDateString('pt-BR');
        
        // Separador de data
        if (msgDate !== currentDate) {
            currentDate = msgDate;
            messagesHtml += `
                <div class="message-date-separator">
                    <span class="date-badge">${msgDate}</span>
                </div>
            `;
        }
        
        // Mensagem
        const isReceived = msg.sender_id != null;
        const priorityClass = msg.priority ? `priority-${msg.priority}` : '';
        const categoryClass = msg.category ? `category-${msg.category}` : '';
        
        messagesHtml += `
            <div class="message-bubble ${isReceived ? 'received' : 'sent'}">
                <div class="message-content">
                    ${msg.priority && msg.priority !== 'low' ? `<div class="message-priority ${priorityClass}">${msg.priority}</div>` : ''}
                    ${msg.category ? `<div class="category-badge ${categoryClass}"><i data-lucide="tag" style="width: 12px;"></i> ${msg.category}</div>` : ''}
                    ${msg.subject ? `<div class="message-subject">${escapeHtml(msg.subject)}</div>` : ''}
                    <div class="message-text">${escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>
                    <div class="message-meta">
                        <span>${new Date(msg.created_at).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}</span>
                        ${!isReceived ? '<i data-lucide="check-check" style="width: 16px; color: #00a884;"></i>' : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    chatPanel.innerHTML = `
        <div class="chat-header">
            <div class="chat-user-info">
                <div class="chat-avatar">${avatar}</div>
                <div class="chat-user-details">
                    <h3>${escapeHtml(senderName)}</h3>
                    <p>${messages.length} mensagens</p>
                </div>
            </div>
            <div class="chat-actions">
                <button class="chat-action-btn" onclick="markAllAsRead(${senderId})" title="Marcar todas como lidas">
                    <i data-lucide="check-check" style="width: 18px;"></i>
                </button>
                <button class="chat-action-btn" onclick="archiveConversation(${senderId})" title="Arquivar conversa">
                    <i data-lucide="archive" style="width: 18px;"></i>
                </button>
                <button class="chat-action-btn" onclick="deleteConversation(${senderId})" title="Excluir conversa" style="color: #ff3232;">
                    <i data-lucide="trash-2" style="width: 18px;"></i>
                </button>
            </div>
        </div>
        
        <div class="messages-container" id="messagesContainer">
            ${messagesHtml || '<div class="empty-chat"><p>Sem mensagens</p></div>'}
        </div>
        
        <div class="chat-input-bar">
            <textarea class="chat-input" id="messageInput" placeholder="Digite uma mensagem..." rows="1" 
                      onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault(); sendMessage();}"></textarea>
            <button class="send-btn" onclick="sendMessage()">
                <i data-lucide="send" style="width: 20px;"></i>
            </button>
        </div>
    `;
    
    // Scroll para o final
    setTimeout(() => {
        const container = document.getElementById('messagesContainer');
        if (container) container.scrollTop = container.scrollHeight;
    }, 100);
    
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// Função auxiliar para escapar HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Marcar conversa como lida
async function markConversationAsRead(senderId) {
    try {
        await fetch('actions/mark_conversation_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({sender_id: senderId})
        });
        
        // Atualizar badge
        const badge = document.querySelector(`[data-sender-id="${senderId || 'system'}"] .conv-unread-badge`);
        if (badge) badge.remove();
    } catch (error) {
        console.error('Erro ao marcar como lida:', error);
    }
}

// Marcar todas como lidas
async function markAllAsRead(senderId) {
    if (!confirm('Marcar todas as mensagens desta conversa como lidas?')) return;
    
    try {
        const response = await fetch('actions/mark_conversation_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({sender_id: senderId})
        });
        
        if (response.ok) {
            alert('✅ Mensagens marcadas como lidas!');
            const senderName = document.querySelector(`[data-sender-id="${senderId || 'system'}"] .conv-name`)?.textContent || 'Usuário';
            openChat(senderId, senderName);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao marcar mensagens');
    }
}

// Arquivar conversa
async function archiveConversation(senderId) {
    if (!confirm('Arquivar esta conversa? As mensagens serão ocultadas da lista principal.')) return;
    
    try {
        const response = await fetch('actions/archive_conversation.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({sender_id: senderId})
        });
        
        if (response.ok) {
            alert('✅ Conversa arquivada!');
            location.reload();
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao arquivar');
    }
}

// Excluir conversa
async function deleteConversation(senderId) {
    if (!confirm('⚠️ ATENÇÃO! Excluir TODAS as mensagens desta conversa? Esta ação não pode ser desfeita!')) return;
    
    try {
        const response = await fetch('actions/delete_conversation.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({sender_id: senderId})
        });
        
        if (response.ok) {
            alert('✅ Conversa excluída!');
            location.reload();
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao excluir');
    }
}

// Enviar mensagem (para futuro)
function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input?.value.trim();
    
    if (!message) return;
    
    alert('⚠️ Funcionalidade de envio em desenvolvimento. Por enquanto, este é um sistema de visualização apenas.');
    input.value = '';
}

// Busca em tempo real
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('.conversation-item').forEach(item => {
            const name = item.querySelector('.conv-name')?.textContent.toLowerCase() || '';
            const msg = item.querySelector('.conv-last-msg')?.textContent.toLowerCase() || '';
            item.style.display = (name.includes(search) || msg.includes(search)) ? 'flex' : 'none';
        });
    });
}

// Auto-ajustar altura do textarea
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('chat-input')) {
        e.target.style.height = 'auto';
        e.target.style.height = Math.min(e.target.scrollHeight, 100) + 'px';
    }
});

// Adicionar estilo para spinner
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spinning {
        animation: spin 1s linear infinite;
    }
`;
document.head.appendChild(style);

// Inicializar ícones
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}

console.log('✅ Sistema de mensagens WhatsApp carregado');
console.log('📊 Conversas disponíveis:', conversationsData.length);
</script>
