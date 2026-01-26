// Abrir/Fechar Modal
document.getElementById('btn-notifications').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('notifications-modal').style.display = 'flex';
});

document.getElementById('close-modal').addEventListener('click', function() {
    document.getElementById('notifications-modal').style.display = 'none';
});

// Fechar ao clicar fora
document.getElementById('notifications-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});

// Marcar todas como lidas
document.getElementById('mark-all-read').addEventListener('click', function() {
    if (!confirm('Marcar todas as notificações como lidas?')) return;
    
    fetch('/ajax/notifications.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mark_all_read'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Remover badge de não lidas
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            // Atualizar contador
            updateNotificationCount();
            
            alert(`${data.count} notificações marcadas como lidas!`);
        }
    });
});

// Ver notificação completa
function viewNotification(id) {
    fetch(`/ajax/notifications.php?action=get_notification&notif_id=${id}`)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const notif = data.data;
            
            // Verificar se tem opção de responder
            const hasReply = ['novo_pedido', 'pagamento_manual'].includes(notif.category);
            
            if (hasReply) {
                // Abrir no sistema de mensagens
                window.location.href = `/messages.php?notif_id=${id}`;
            } else {
                // Mostrar modal de detalhes
                showNotificationDetail(notif);
            }
            
            // Marcar como lida
            markAsRead(id);
        }
    });
}

// Marcar como lida
function markAsRead(id) {
    fetch('/ajax/notifications.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=mark_as_read&notif_id=${id}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Remover classe unread
            const item = document.querySelector(`.notification-item[data-id="${id}"]`);
            if (item) item.classList.remove('unread');
            
            // Atualizar contador
            updateNotificationCount();
        }
    });
}

// Deletar notificação
function deleteNotification(id) {
    if (!confirm('Deseja realmente deletar esta notificação?')) return;
    
    fetch('/ajax/notifications.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete&notif_id=${id}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Remover item da lista
            const item = document.querySelector(`.notification-item[data-id="${id}"]`);
            if (item) item.remove();
            
            // Atualizar contador
            updateNotificationCount();
        }
    });
}

// Atualizar contador
function updateNotificationCount() {
    fetch('/ajax/notifications.php?action=get_count')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector('.notification-badge');
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count;
                } else {
                    // Criar badge se não existir
                    const btn = document.getElementById('btn-notifications');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.textContent = data.count;
                    btn.appendChild(newBadge);
                }
            } else {
                // Remover badge se zero
                if (badge) badge.remove();
            }
        }
    });
}

// Filtrar por categoria
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const filter = this.dataset.filter;
        
        // Atualizar aba ativa
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        // Filtrar notificações
        document.querySelectorAll('.notification-item').forEach(item => {
            if (filter === 'all' || item.dataset.category === filter) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });
});

// Atualizar contador a cada 30 segundos
setInterval(updateNotificationCount, 30000);