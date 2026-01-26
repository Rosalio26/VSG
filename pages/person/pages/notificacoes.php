<div class="page-container">
    <div class="page-header notification-header">
        <div class="page-header-content">
            <div class="page-title-group">
                <div class="notification-icon-wrapper">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div>
                    <h1>Notificações</h1>
                    <p class="page-subtitle">Mensagens e atualizações</p>
                </div>
            </div>
            
            <div class="header-stats">
                <div class="stat-pill">
                    <span class="stat-value" id="stat-total-notif">0</span>
                    <span class="stat-label">Total</span>
                </div>
                <div class="stat-pill warning">
                    <span class="stat-value" id="stat-nao-lidas">0</span>
                    <span class="stat-label">Não Lidas</span>
                </div>
                <div class="stat-pill success">
                    <span class="stat-value" id="stat-lidas">0</span>
                    <span class="stat-label">Lidas</span>
                </div>
            </div>
        </div>
    </div>

    <div class="filters-bar notification-filters">
        <div class="filter-group">
            <button class="filter-chip active" data-filter="all" onclick="NotificationsModule.filterNotifications('all', this)">
                <i class="fa-solid fa-list"></i> Todas
            </button>
            <button class="filter-chip" data-filter="nao_lida" onclick="NotificationsModule.filterNotifications('nao_lida', this)">
                <i class="fa-solid fa-circle"></i> Não Lidas
            </button>
            <button class="filter-chip" data-filter="lida" onclick="NotificationsModule.filterNotifications('lida', this)">
                <i class="fa-solid fa-check-double"></i> Lidas
            </button>
            <button class="filter-chip" data-filter="compra_confirmada" onclick="NotificationsModule.filterNotifications('compra_confirmada', this)">
                🛒 Compras
            </button>
            <button class="filter-chip" data-filter="pagamento" onclick="NotificationsModule.filterNotifications('pagamento', this)">
                💰 Pagamentos
            </button>
        </div>
        
        <div style="display: flex; gap: 8px;">
            <button class="btn-action-primary" onclick="NotificationsModule.markAllAsRead()">
                <i class="fa-solid fa-check-double"></i>
                Marcar Todas como Lidas
            </button>
        </div>
    </div>

    <div class="notifications-list" id="notificationsList">
        <div class="empty-state">
            <i class="fa-solid fa-spinner fa-spin"></i>
            <h3>Carregando notificações...</h3>
            <p>Aguarde um momento</p>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalNotification">
    <div class="modal-container notification-modal">
        <div class="modal-header notification-modal-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="notification-avatar-modal" id="modalAvatar">💬</div>
                <div>
                    <h2 class="modal-title" id="modalNotificationTitle">Mensagem</h2>
                    <p class="modal-subtitle" id="modalNotificationSubtitle">VisionGreen</p>
                </div>
            </div>
            <button class="modal-close" onclick="NotificationsModule.closeNotificationModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body notification-modal-body" id="modalNotificationContent"></div>
        <div class="modal-footer notification-modal-footer">
            <button class="btn-action-primary" onclick="NotificationsModule.closeNotificationModal()">
                <i class="fa-solid fa-check"></i>
                Fechar
            </button>
        </div>
    </div>
</div>

<style>
:root {
    --notif-primary: #00ff88;
    --notif-primary-dark: #00cc6a;
    --notif-primary-light: #e7fff4;
    --notif-bg: #0a0f0d;
    --notif-surface: #0f1612;
    --notif-surface-light: #1a231d;
    --notif-border: #243329;
    --notif-text: #e9f5ed;
    --notif-text-secondary: #8a9e8f;
    --notif-bubble-sent: #004d3d;
    --notif-bubble-received: #1d2822;
    --notif-unread: #00ff88;
    --notif-shadow: rgba(0, 0, 0, 0.4);
}

.notification-header {
    background: linear-gradient(135deg, var(--notif-surface) 0%, var(--notif-bg) 100%);
    border-bottom: 1px solid var(--notif-border);
}

.notification-icon-wrapper {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--notif-primary) 0%, var(--notif-primary-dark) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0, 255, 136, 0.3);
}

.notification-icon-wrapper i {
    font-size: 28px;
    color: #000;
}

.notification-filters {
    background: var(--notif-surface);
    border-bottom: 1px solid var(--notif-border);
}

.filter-chip {
    background: var(--notif-surface-light);
    border: 1px solid var(--notif-border);
    color: var(--notif-text-secondary);
    transition: all 0.2s ease;
}

.filter-chip:hover {
    background: var(--notif-bubble-received);
    border-color: var(--notif-primary);
    color: var(--notif-text);
}

.filter-chip.active {
    background: var(--notif-primary);
    border-color: var(--notif-primary);
    color: #000;
    font-weight: 600;
}

.btn-action-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--notif-surface-light);
    border: 1px solid var(--notif-border);
    border-radius: 8px;
    color: var(--notif-text);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    height: 36px;
}

.btn-action-primary:hover {
    background: var(--notif-bubble-received);
    border-color: var(--notif-primary);
    transform: translateY(-1px);
}

.notifications-list {
    background: var(--notif-bg);
    padding: 8px;
    max-height: calc(100vh - 280px);
    overflow-y: auto;
}

.notification-card {
    background: var(--notif-surface);
    border: none;
    border-left: 4px solid transparent;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    box-shadow: 0 1px 2px var(--notif-shadow);
}

.notification-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: transparent;
    transition: all 0.2s;
    border-radius: 12px 0 0 12px;
}

.notification-card:hover {
    background: var(--notif-surface-light);
    transform: translateX(4px);
    box-shadow: 0 4px 12px var(--notif-shadow);
}

.notification-card:hover::before {
    background: var(--notif-primary);
}

.notification-card.unread {
    background: var(--notif-surface-light);
    border-left-color: var(--notif-unread);
}

.notification-card.unread::before {
    background: var(--notif-unread);
    box-shadow: 0 0 8px rgba(0, 255, 136, 0.4);
}

.notification-card.unread::after {
    content: '';
    position: absolute;
    right: 16px;
    top: 16px;
    width: 24px;
    height: 24px;
    background: var(--notif-unread);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    color: #000;
    box-shadow: 0 2px 8px rgba(0, 255, 136, 0.4);
    animation: pulseNotif 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulseNotif {
    0%, 100% { 
        transform: scale(1);
        opacity: 1;
    }
    50% { 
        transform: scale(1.1);
        opacity: 0.8;
    }
}

.notification-icon {
    width: 52px;
    height: 52px;
    min-width: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    position: relative;
    transition: transform 0.2s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.notification-card:hover .notification-icon {
    transform: scale(1.05);
}

.notification-icon.compra_confirmada { 
    background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%);
    color: #000;
}

.notification-icon.compra_pendente {
    background: linear-gradient(135deg, #FFA726 0%, #FF9800 100%);
    color: white;
}

.notification-icon.pagamento { 
    background: linear-gradient(135deg, #FFD54F 0%, #FFC107 100%);
    color: #1a231d;
}

.notification-icon.pagamento_manual {
    background: linear-gradient(135deg, #66BB6A 0%, #4CAF50 100%);
    color: white;
}

.notification-icon.sistema { 
    background: linear-gradient(135deg, #42A5F5 0%, #2196F3 100%);
    color: white;
}

.notification-icon.importante { 
    background: linear-gradient(135deg, #EF5350 0%, #F44336 100%);
    color: white;
}

.notification-icon.alerta {
    background: linear-gradient(135deg, #FFEE58 0%, #FDD835 100%);
    color: #1a231d;
}

.notification-content {
    flex: 1;
    min-width: 0;
    padding-right: 32px;
}

.notification-header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px;
    gap: 12px;
}

.notification-sender {
    font-weight: 600;
    color: var(--notif-text);
    font-size: 16px;
}

.notification-card.unread .notification-sender {
    color: var(--notif-primary);
}

.notification-date {
    font-size: 12px;
    color: var(--notif-text-secondary);
    white-space: nowrap;
}

.notification-subject {
    font-size: 14px;
    font-weight: 500;
    color: var(--notif-text);
    margin-bottom: 4px;
    line-height: 1.4;
}

.notification-message {
    font-size: 13px;
    color: var(--notif-text-secondary);
    line-height: 1.5;
    max-height: 40px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.notification-ticks {
    display: inline-flex;
    gap: 2px;
    margin-left: 4px;
    color: var(--notif-text-secondary);
    font-size: 14px;
}

.notification-card.unread .notification-ticks {
    color: var(--notif-text-secondary);
}

.modal-overlay {
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
}

.notification-modal {
    background: var(--notif-surface);
    border: 1px solid var(--notif-border);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
    border-radius: 16px;
    overflow: hidden;
}

.notification-modal-header {
    background: var(--notif-surface-light);
    border-bottom: 1px solid var(--notif-border);
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.notification-avatar-modal {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--notif-primary) 0%, var(--notif-primary-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 2px 8px rgba(0, 255, 136, 0.3);
}

.modal-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--notif-text);
    margin: 0;
}

.modal-subtitle {
    font-size: 13px;
    color: var(--notif-text-secondary);
    margin: 2px 0 0 0;
}

.modal-close {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: transparent;
    border: none;
    color: var(--notif-text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--notif-text);
    transform: rotate(90deg);
}

.notification-modal-body {
    padding: 24px;
    background: var(--notif-bg);
    max-height: 70vh;
    overflow-y: auto;
}

.message-bubble {
    background: var(--notif-bubble-received);
    border-radius: 12px;
    padding: 12px 16px;
    margin: 16px 0;
    position: relative;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.message-bubble::before {
    content: '';
    position: absolute;
    left: -8px;
    top: 12px;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 0 8px 8px 0;
    border-color: transparent var(--notif-bubble-received) transparent transparent;
}

.message-content {
    color: var(--notif-text);
    font-size: 14px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.message-time {
    display: flex;
    align-items: center;
    gap: 4px;
    justify-content: flex-end;
    margin-top: 8px;
    font-size: 11px;
    color: var(--notif-text-secondary);
}

.message-info {
    background: var(--notif-surface-light);
    border: 1px solid var(--notif-border);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
}

.message-info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--notif-border);
}

.message-info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.message-info-label {
    color: var(--notif-text-secondary);
    font-size: 13px;
}

.message-info-value {
    color: var(--notif-text);
    font-weight: 600;
    font-size: 13px;
}

.notification-attachment {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--notif-surface-light);
    border: 1px solid var(--notif-border);
    border-radius: 12px;
    margin-top: 16px;
    transition: all 0.2s;
    text-decoration: none;
}

.notification-attachment:hover {
    background: var(--notif-bubble-received);
    border-color: var(--notif-primary);
    transform: translateX(4px);
}

.notification-attachment-icon {
    width: 48px;
    height: 48px;
    background: var(--notif-primary);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #000;
    font-size: 20px;
}

.notification-attachment-info {
    flex: 1;
}

.notification-attachment-name {
    color: var(--notif-text);
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 2px;
}

.notification-attachment-size {
    color: var(--notif-text-secondary);
    font-size: 12px;
}

.notification-attachment-download {
    color: var(--notif-primary);
    font-size: 20px;
}

.notification-modal-footer {
    background: var(--notif-surface-light);
    border-top: 1px solid var(--notif-border);
    padding: 16px 20px;
}

.empty-state {
    background: var(--notif-surface);
    border: 2px dashed var(--notif-border);
    border-radius: 16px;
    padding: 64px 32px;
    text-align: center;
}

.empty-state i {
    font-size: 64px;
    color: var(--notif-text-secondary);
    margin-bottom: 16px;
    opacity: 0.6;
}

.empty-state i.fa-spin {
    color: var(--notif-primary);
    opacity: 1;
}

.empty-state h3 {
    color: var(--notif-text);
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--notif-text-secondary);
    font-size: 14px;
}

.notifications-list::-webkit-scrollbar,
.notification-modal-body::-webkit-scrollbar {
    width: 8px;
}

.notifications-list::-webkit-scrollbar-track,
.notification-modal-body::-webkit-scrollbar-track {
    background: var(--notif-bg);
}

.notifications-list::-webkit-scrollbar-thumb,
.notification-modal-body::-webkit-scrollbar-thumb {
    background: var(--notif-border);
    border-radius: 4px;
}

.notifications-list::-webkit-scrollbar-thumb:hover,
.notification-modal-body::-webkit-scrollbar-thumb:hover {
    background: var(--notif-surface-light);
}

@keyframes slideInNotif {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.notification-card {
    animation: slideInNotif 0.3s cubic-bezier(0.4, 0, 0.2, 1) backwards;
}

.notification-card:nth-child(1) { animation-delay: 0.05s; }
.notification-card:nth-child(2) { animation-delay: 0.1s; }
.notification-card:nth-child(3) { animation-delay: 0.15s; }
.notification-card:nth-child(4) { animation-delay: 0.2s; }
.notification-card:nth-child(5) { animation-delay: 0.25s; }

@media (max-width: 768px) {
    .notification-card {
        padding: 12px;
        gap: 10px;
    }
    
    .notification-icon {
        width: 44px;
        height: 44px;
        min-width: 44px;
        font-size: 20px;
    }
    
    .notification-sender {
        font-size: 15px;
    }
    
    .notification-subject {
        font-size: 13px;
    }
    
    .notification-message {
        font-size: 12px;
    }
    
    .notifications-list {
        padding: 4px;
    }
    
    .notification-modal {
        margin: 16px;
        max-width: calc(100% - 32px);
    }
    
    .message-bubble {
        padding: 10px 14px;
    }
}
</style>

<script>
(function() {
    'use strict';
    
    if (window.NotificationsModule) {
        return;
    }
    
    const state = {
        allNotifications: [],
        currentFilter: 'all',
        updateInterval: null,
        lastUpdate: 0
    };

    async function loadNotifications(silent = false) {
        try {
            const response = await fetch('actions/get_notifications.php');
            const data = await response.json();
            
            if (data.success) {
                const hasChanges = JSON.stringify(state.allNotifications) !== JSON.stringify(data.notifications);
                state.allNotifications = data.notifications;
                
                if (!silent || hasChanges) {
                    renderNotifications();
                    updateNotificationStats();
                }
                
                if (!silent) {
                    clearNotificationBadges();
                }
                
                state.lastUpdate = Date.now();
            } else if (!silent) {
                showError('Erro ao carregar notificações');
            }
        } catch (error) {
            if (!silent) {
                console.error('Erro ao carregar notificações:', error);
                showError('Erro de conexão. Verifique sua internet.');
            }
        }
    }

    function renderNotifications() {
        const container = document.getElementById('notificationsList');
        if (!container) return;
        
        if (!state.allNotifications || state.allNotifications.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-bell"></i>
                    <h3>Nenhuma notificação</h3>
                    <p>Você está em dia com suas mensagens</p>
                </div>
            `;
            return;
        }
        
        let filtered = state.allNotifications;
        
        if (state.currentFilter === 'nao_lida') {
            filtered = state.allNotifications.filter(n => n.status === 'nao_lida');
        } else if (state.currentFilter === 'lida') {
            filtered = state.allNotifications.filter(n => n.status === 'lida');
        } else if (state.currentFilter !== 'all') {
            filtered = state.allNotifications.filter(n => n.category === state.currentFilter);
        }
        
        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-filter"></i>
                    <h3>Nenhuma notificação encontrada</h3>
                    <p>Não há notificações com o filtro selecionado</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = filtered.map(notif => renderNotificationCard(notif)).join('');
    }

    function renderNotificationCard(notif) {
        const isUnread = notif.status === 'nao_lida';
        const iconClass = notif.category || 'sistema';
        
        const iconMap = {
            'compra_confirmada': '🛒',
            'compra_pendente': '⏳',
            'pagamento': '💰',
            'pagamento_manual': '💵',
            'sistema': '🔔',
            'importante': '⚠️',
            'alerta': '⚡'
        };
        
        const icon = iconMap[notif.category] || '💬';
        
        const ticks = isUnread 
            ? '<span class="notification-ticks"><i class="fa-solid fa-check"></i></span>'
            : '<span class="notification-ticks"><i class="fa-solid fa-check-double"></i></span>';
        
        return `
            <div class="notification-card ${isUnread ? 'unread' : ''}" 
                 data-id="${notif.id}" 
                 data-status="${notif.status}"
                 data-category="${notif.category || ''}"
                 onclick="NotificationsModule.openNotification(${notif.id})">
                <div class="notification-icon ${iconClass}">${icon}</div>
                <div class="notification-content">
                    <div class="notification-header-content">
                        <span class="notification-sender">${escapeHtml(notif.sender_name || 'VisionGreen')}</span>
                        <span class="notification-date">${formatDate(notif.created_at)}</span>
                    </div>
                    <div class="notification-subject">${escapeHtml(notif.subject)}</div>
                    <div class="notification-message">
                        ${ticks}
                        ${escapeHtml(notif.message)}
                    </div>
                </div>
            </div>
        `;
    }

    function updateNotificationStats() {
        const total = state.allNotifications.length;
        const naoLidas = state.allNotifications.filter(n => n.status === 'nao_lida').length;
        const lidas = state.allNotifications.filter(n => n.status === 'lida').length;
        
        const statTotal = document.getElementById('stat-total-notif');
        const statNaoLidas = document.getElementById('stat-nao-lidas');
        const statLidas = document.getElementById('stat-lidas');
        
        if (statTotal && statTotal.textContent !== total.toString()) {
            statTotal.textContent = total;
        }
        if (statNaoLidas && statNaoLidas.textContent !== naoLidas.toString()) {
            statNaoLidas.textContent = naoLidas;
        }
        if (statLidas && statLidas.textContent !== lidas.toString()) {
            statLidas.textContent = lidas;
        }
    }

    function filterNotifications(filter, button) {
        state.currentFilter = filter;
        
        document.querySelectorAll('.filter-chip').forEach(chip => {
            chip.classList.remove('active');
        });
        
        if (button) {
            button.classList.add('active');
        }
        
        renderNotifications();
    }

    async function openNotification(id) {
        const notif = state.allNotifications.find(n => n.id === id);
        if (!notif) return;
        
        if (notif.status === 'nao_lida') {
            await markAsRead(id);
        }
        
        const modal = document.getElementById('modalNotification');
        const title = document.getElementById('modalNotificationTitle');
        const subtitle = document.getElementById('modalNotificationSubtitle');
        const content = document.getElementById('modalNotificationContent');
        const avatar = document.getElementById('modalAvatar');
        
        if (!modal || !title || !subtitle || !content || !avatar) return;
        
        const iconMap = {
            'compra_confirmada': '🛒',
            'compra_pendente': '⏳',
            'pagamento': '💰',
            'pagamento_manual': '💵',
            'sistema': '🔔',
            'importante': '⚠️',
            'alerta': '⚡'
        };
        avatar.textContent = iconMap[notif.category] || '💬';
        
        title.textContent = notif.subject;
        subtitle.textContent = notif.sender_name || 'VisionGreen';
        
        let attachmentHtml = '';
        if (notif.attachment_url) {
            const fileName = notif.attachment_url.split('/').pop();
            const fileSize = '245 KB';
            attachmentHtml = `
                <a href="${escapeHtml(notif.attachment_url)}" download class="notification-attachment" target="_blank">
                    <div class="notification-attachment-icon">
                        <i class="fa-solid fa-file-pdf"></i>
                    </div>
                    <div class="notification-attachment-info">
                        <div class="notification-attachment-name">${escapeHtml(fileName)}</div>
                        <div class="notification-attachment-size">${fileSize}</div>
                    </div>
                    <i class="fa-solid fa-download notification-attachment-download"></i>
                </a>
            `;
        }
        
        content.innerHTML = `
            <div class="message-info">
                <div class="message-info-row">
                    <span class="message-info-label">De:</span>
                    <span class="message-info-value">${escapeHtml(notif.sender_name || 'VisionGreen')}</span>
                </div>
                <div class="message-info-row">
                    <span class="message-info-label">Data:</span>
                    <span class="message-info-value">${formatDate(notif.created_at)}</span>
                </div>
                ${notif.related_order_number ? `
                <div class="message-info-row">
                    <span class="message-info-label">Pedido:</span>
                    <span class="message-info-value">#${escapeHtml(notif.related_order_number)}</span>
                </div>
                ` : ''}
            </div>
            
            <div class="message-bubble">
                <div class="message-content">${escapeHtml(notif.message)}</div>
                <div class="message-time">
                    ${formatTime(notif.created_at)}
                    <i class="fa-solid fa-check-double" style="color: var(--notif-primary);"></i>
                </div>
            </div>
            
            ${attachmentHtml}
        `;
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeNotificationModal() {
        const modal = document.getElementById('modalNotification');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    async function markAsRead(id) {
        try {
            const response = await fetch('actions/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: id })
            });
            
            const data = await response.json();
            
            if (data.success) {
                const notif = state.allNotifications.find(n => n.id === id);
                if (notif) {
                    notif.status = 'lida';
                }
                renderNotifications();
                updateNotificationStats();
            }
        } catch (error) {
            console.error('Erro ao marcar como lida:', error);
        }
    }

    async function markAllAsRead() {
        const unreadCount = state.allNotifications.filter(n => n.status === 'nao_lida').length;
        
        if (unreadCount === 0) {
            if (typeof showToast === 'function') {
                showToast('ℹ️ Não há notificações não lidas', 'info');
            }
            return;
        }
        
        if (!confirm(`Marcar ${unreadCount} mensagem${unreadCount > 1 ? 'ns' : ''} como lida${unreadCount > 1 ? 's' : ''}?`)) {
            return;
        }
        
        try {
            const response = await fetch('actions/mark_all_notifications_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            const data = await response.json();
            
            if (data.success) {
                state.allNotifications.forEach(n => n.status = 'lida');
                renderNotifications();
                updateNotificationStats();
                if (typeof showToast === 'function') {
                    showToast('✅ Todas as mensagens foram marcadas como lidas', 'success');
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast('❌ Erro ao marcar mensagens', 'error');
                }
            }
        } catch (error) {
            console.error('Erro ao marcar todas como lidas:', error);
            if (typeof showToast === 'function') {
                showToast('❌ Erro de conexão', 'error');
            }
        }
    }

    function clearNotificationBadges() {
        const badges = document.querySelectorAll('.icon-btn .badge, .mobile-nav-badge');
        badges.forEach(badge => {
            const parent = badge.closest('[data-page="notificacoes"], .icon-btn');
            if (parent) {
                const icon = badge.previousElementSibling || badge.closest('.icon-btn')?.querySelector('.fa-bell');
                if (icon && (icon.classList.contains('fa-bell') || parent.dataset.page === 'notificacoes')) {
                    badge.style.display = 'none';
                    badge.textContent = '0';
                }
            }
        });
        
        localStorage.setItem('notificacoesVisualizadas', Date.now().toString());
    }

    function showError(message) {
        const container = document.getElementById('notificationsList');
        if (!container) return;
        
        container.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <h3>Erro ao Carregar</h3>
                <p>${escapeHtml(message)}</p>
                <button class="btn-action-primary" onclick="NotificationsModule.loadNotifications()" style="margin-top: 24px;">
                    <i class="fa-solid fa-rotate-right"></i>
                    Tentar Novamente
                </button>
            </div>
        `;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        
        try {
            const date = new Date(dateStr);
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            
            if (dateOnly.getTime() === today.getTime()) {
                return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            } else if (dateOnly.getTime() === yesterday.getTime()) {
                return 'Ontem';
            } else if (date.getFullYear() === now.getFullYear()) {
                return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            } else {
                return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' });
            }
        } catch (error) {
            return '';
        }
    }

    function formatTime(dateStr) {
        if (!dateStr) return '';
        
        try {
            const date = new Date(dateStr);
            return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        } catch (error) {
            return '';
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function setupEventListeners() {
        const overlay = document.getElementById('modalNotification');
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeNotificationModal();
                }
            });
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeNotificationModal();
            }
        });
    }

    function startAutoUpdate() {
        if (state.updateInterval) {
            clearInterval(state.updateInterval);
        }
        
        state.updateInterval = setInterval(() => {
            loadNotifications(true);
        }, 1000);
    }

    function stopAutoUpdate() {
        if (state.updateInterval) {
            clearInterval(state.updateInterval);
            state.updateInterval = null;
        }
    }

    window.NotificationsModule = {
        loadNotifications: (silent) => loadNotifications(silent || false),
        filterNotifications,
        openNotification,
        closeNotificationModal,
        markAllAsRead,
        state
    };

    setTimeout(() => {
        setupEventListeners();
        loadNotifications();
        startAutoUpdate();
    }, 100);

    window.addEventListener('beforeunload', stopAutoUpdate);
})();
</script>