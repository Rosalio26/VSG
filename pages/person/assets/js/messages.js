/* =========================================
   SISTEMA DE MENSAGENS - JAVASCRIPT
   ========================================= */

// Variáveis globais
let currentContactId = null;
let currentContactName = '';
let currentContactAvatar = '';
let messageCheckInterval = null;

// ========================================
// ABRIR/FECHAR MODAL
// ========================================
document.getElementById('btn-messages')?.addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('messages-modal').style.display = 'flex';
});

document.getElementById('close-messages-modal')?.addEventListener('click', function() {
    document.getElementById('messages-modal').style.display = 'none';
    stopMessagePolling();
});

// Fechar ao clicar fora
document.getElementById('messages-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
        stopMessagePolling();
    }
});

// ========================================
// SELECIONAR CONVERSA
// ========================================
document.querySelectorAll('.conv-item').forEach(item => {
    item.addEventListener('click', function() {
        // Remover active de todas
        document.querySelectorAll('.conv-item').forEach(i => i.classList.remove('active'));
        
        // Adicionar active na selecionada
        this.classList.add('active');
        
        // Pegar dados do contato
        currentContactId = this.dataset.contactId;
        currentContactName = this.dataset.contactName;
        currentContactAvatar = this.dataset.contactAvatar;
        
        // Abrir chat
        loadConversation(currentContactId);
    });
});

// ========================================
// CARREGAR CONVERSA
// ========================================
function loadConversation(contactId) {
    // Esconder empty state
    document.getElementById('chat-empty').style.display = 'none';
    document.getElementById('chat-active').style.display = 'flex';
    
    // Atualizar header do chat
    document.getElementById('chat-avatar').src = currentContactAvatar;
    document.getElementById('chat-name').textContent = currentContactName;
    
    // Carregar mensagens via AJAX
    fetch(`/ajax/messages.php?action=get_messages&contact_id=${contactId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderMessages(data.messages);
                scrollToBottom();
                startMessagePolling();
            }
        })
        .catch(err => console.error('Erro ao carregar mensagens:', err));
}

// ========================================
// RENDERIZAR MENSAGENS
// ========================================
function renderMessages(messages) {
    const container = document.getElementById('chat-msgs');
    container.innerHTML = '';
    
    messages.forEach(msg => {
        const template = document.getElementById('msg-template');
        const clone = template.content.cloneNode(true);
        
        const msgDiv = clone.querySelector('.chat-msg');
        msgDiv.classList.add(msg.is_sent ? 'sent' : 'received');
        
        clone.querySelector('.msg-text').textContent = msg.message;
        clone.querySelector('.msg-time').textContent = msg.time;
        
        container.appendChild(clone);
    });
}

// ========================================
// ENVIAR MENSAGEM
// ========================================
document.getElementById('btn-send')?.addEventListener('click', function() {
    sendMessage();
});

document.getElementById('msg-input')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

function sendMessage() {
    const input = document.getElementById('msg-input');
    const message = input.value.trim();
    
    if (!message || !currentContactId) return;
    
    // Adicionar mensagem localmente (otimista)
    addMessageToChat(message, true);
    input.value = '';
    
    // Enviar para servidor
    fetch('/ajax/messages.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=send_message&receiver_id=${currentContactId}&message=${encodeURIComponent(message)}`
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            alert('Erro ao enviar mensagem');
        }
    })
    .catch(err => console.error('Erro:', err));
}

// ========================================
// ADICIONAR MENSAGEM AO CHAT
// ========================================
function addMessageToChat(message, isSent) {
    const container = document.getElementById('chat-msgs');
    const template = document.getElementById('msg-template');
    const clone = template.content.cloneNode(true);
    
    const msgDiv = clone.querySelector('.chat-msg');
    msgDiv.classList.add(isSent ? 'sent' : 'received');
    
    clone.querySelector('.msg-text').textContent = message;
    clone.querySelector('.msg-time').textContent = 'Agora';
    
    container.appendChild(clone);
    scrollToBottom();
}

// ========================================
// SCROLL AUTOMÁTICO
// ========================================
function scrollToBottom() {
    const container = document.getElementById('chat-msgs');
    container.scrollTop = container.scrollHeight;
}

// ========================================
// BUSCAR CONVERSAS
// ========================================
document.getElementById('search-conv')?.addEventListener('input', function() {
    const query = this.value.toLowerCase();
    
    document.querySelectorAll('.conv-item').forEach(item => {
        const name = item.dataset.contactName.toLowerCase();
        if (name.includes(query)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
});

// ========================================
// FILTROS
// ========================================
document.querySelectorAll('.msg-filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        // Remover active de todos
        document.querySelectorAll('.msg-filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        
        document.querySelectorAll('.conv-item').forEach(item => {
            if (filter === 'all') {
                item.style.display = 'flex';
            } else if (filter === 'unread') {
                item.style.display = item.classList.contains('unread') ? 'flex' : 'none';
            } else if (filter === 'companies') {
                item.style.display = item.dataset.contactType === 'company' ? 'flex' : 'none';
            }
        });
    });
});

// ========================================
// QUICK REPLIES
// ========================================
document.querySelectorAll('.qr-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const message = this.dataset.msg;
        document.getElementById('msg-input').value = message;
        document.getElementById('msg-input').focus();
    });
});

// ========================================
// POLLING DE MENSAGENS (Verificar novas a cada 5s)
// ========================================
function startMessagePolling() {
    if (messageCheckInterval) return;
    
    messageCheckInterval = setInterval(() => {
        if (currentContactId) {
            checkNewMessages();
        }
    }, 5000);
}

function stopMessagePolling() {
    if (messageCheckInterval) {
        clearInterval(messageCheckInterval);
        messageCheckInterval = null;
    }
}

function checkNewMessages() {
    fetch(`/ajax/messages.php?action=check_new&contact_id=${currentContactId}`)
        .then(res => res.json())
        .then(data => {
            if (data.has_new) {
                // Recarregar mensagens
                loadConversation(currentContactId);
            }
        })
        .catch(err => console.error('Erro ao verificar mensagens:', err));
}

// ========================================
// AUTO-RESIZE TEXTAREA
// ========================================
document.getElementById('msg-input')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

console.log('✅ Sistema de Mensagens carregado');