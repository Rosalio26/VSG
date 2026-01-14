<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - NOVA MENSAGEM
 * Módulo: modules/mensagens/nova_msg.php
 * Descrição: Interface para iniciar nova conversa
 * Carrega dentro do container do chat (substitui área de mensagens)
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= BUSCAR USUÁRIOS DISPONÍVEIS (ROLE-BASED) ================= */
if ($isSuperAdmin) {
    // SuperAdmin vê todos os usuários
    $queryUsuarios = "
        SELECT 
            u.id,
            u.nome,
            u.email,
            u.type,
            u.role,
            u.last_activity,
            u.created_at
        FROM users u
        WHERE u.id != $adminId
        AND u.deleted_at IS NULL
        ORDER BY u.nome ASC
    ";
} else {
    // Admin não vê SuperAdmins
    $queryUsuarios = "
        SELECT 
            u.id,
            u.nome,
            u.email,
            u.type,
            u.role,
            u.last_activity,
            u.created_at
        FROM users u
        WHERE u.id != $adminId
        AND u.deleted_at IS NULL
        AND u.role != 'superadmin'
        ORDER BY u.nome ASC
    ";
}

$usuarios = $mysqli->query($queryUsuarios);

// Estatísticas
$total_usuarios = $usuarios ? $usuarios->num_rows : 0;
$usuarios_online = 0;
if ($usuarios) {
    $usuarios->data_seek(0);
    while ($u = $usuarios->fetch_assoc()) {
        if ($u['last_activity'] && $u['last_activity'] > (time() - 900)) {
            $usuarios_online++;
        }
    }
    $usuarios->data_seek(0);
}
?>

<style>
.new-message-container {
    height: 100%;
    display: flex;
    flex-direction: column;
    background: var(--bg-card);
    animation: fadeIn 0.3s ease;
}

.new-message-header {
    padding: 20px 24px;
    background: var(--bg-elevated);
    border-bottom: 1px solid var(--border);
}

.new-message-header h2 {
    color: var(--text-title);
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.new-message-header p {
    color: var(--text-secondary);
    font-size: 0.875rem;
    margin: 0;
}

.new-message-stats {
    display: flex;
    gap: 20px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.813rem;
    color: var(--text-secondary);
}

.stat-item i {
    color: var(--accent);
}

.stat-item strong {
    color: var(--text-primary);
    font-weight: 700;
}

.search-section {
    padding: 20px 24px;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
}

.search-wrapper {
    position: relative;
}

.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 1rem;
}

.search-input-large {
    width: 100%;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 14px 12px 42px;
    color: var(--text-primary);
    font-size: 0.938rem;
    outline: none;
    transition: 0.2s;
}

.search-input-large:focus {
    border-color: var(--accent);
    background: var(--bg-card);
}

.filter-tabs {
    display: flex;
    gap: 8px;
    margin-top: 16px;
    flex-wrap: wrap;
}

.filter-tab {
    background: transparent;
    border: 1px solid var(--border);
    padding: 8px 16px;
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 0.813rem;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}

.filter-tab:hover {
    background: var(--bg-elevated);
    color: var(--text-primary);
}

.filter-tab.active {
    background: var(--accent);
    color: var(--bg-card);
    border-color: var(--accent);
}

.users-list {
    flex: 1;
    overflow-y: auto;
    padding: 16px 24px;
}

.user-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 16px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-bottom: 12px;
    cursor: pointer;
    transition: 0.2s;
    animation: slideIn 0.3s ease;
}

.user-card:hover {
    background: var(--bg-card);
    border-color: var(--accent);
    transform: translateX(4px);
}

.user-avatar-large {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    border: 2px solid var(--border);
    flex-shrink: 0;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    color: var(--text-title);
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.user-email {
    color: var(--text-secondary);
    font-size: 0.813rem;
    margin-bottom: 6px;
}

.user-meta {
    display: flex;
    gap: 12px;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.user-meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
}

.user-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: flex-end;
}

.online-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    color: var(--accent);
    font-weight: 600;
}

.online-dot {
    width: 8px;
    height: 8px;
    background: var(--accent);
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.start-chat-btn {
    background: var(--accent);
    border: none;
    padding: 8px 20px;
    border-radius: 8px;
    color: var(--bg-card);
    font-size: 0.875rem;
    font-weight: 700;
    cursor: pointer;
    transition: 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.start-chat-btn:hover {
    transform: scale(1.05);
}

.role-badge-large {
    font-size: 0.688rem;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 700;
}

.type-badge {
    background: rgba(56, 139, 253, 0.15);
    color: #388bfd;
    font-size: 0.688rem;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 700;
}

.empty-state-new {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-state-new i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state-new h3 {
    color: var(--text-secondary);
    font-size: 1.25rem;
    margin: 0 0 8px 0;
}

.empty-state-new p {
    font-size: 0.938rem;
    margin: 0;
}

@keyframes slideIn {
    from { transform: translateX(-20px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<div class="new-message-container">
    <!-- HEADER -->
    <div class="new-message-header">
        <h2>
            <i class="fa-solid fa-paper-plane" style="color: var(--accent);"></i>
            Nova Mensagem
            <?php if (!$isSuperAdmin): ?>
                <span class="badge info" style="font-size: 0.7rem; margin-left: 8px;">
                    <i class="fa-solid fa-info-circle"></i>
                    Visualização Limitada
                </span>
            <?php endif; ?>
        </h2>
        <p>Selecione um usuário para iniciar uma conversa</p>
        
        <div class="new-message-stats">
            <div class="stat-item">
                <i class="fa-solid fa-users"></i>
                <strong><?= $total_usuarios ?></strong> usuários disponíveis
            </div>
            <div class="stat-item">
                <i class="fa-solid fa-wifi"></i>
                <strong><?= $usuarios_online ?></strong> online agora
            </div>
        </div>
    </div>

    <!-- SEARCH -->
    <div class="search-section">
        <div class="search-wrapper">
            <i class="fa-solid fa-search search-icon"></i>
            <input type="text" 
                   id="searchUsers" 
                   class="search-input-large" 
                   placeholder="Buscar por nome ou email..."
                   autocomplete="off">
        </div>
        
        <div class="filter-tabs">
            <button class="filter-tab active" onclick="filterUsers('all')">
                <i class="fa-solid fa-users"></i>
                Todos
            </button>
            <button class="filter-tab" onclick="filterUsers('online')">
                <i class="fa-solid fa-wifi"></i>
                Online
            </button>
            <button class="filter-tab" onclick="filterUsers('person')">
                <i class="fa-solid fa-user"></i>
                Pessoas
            </button>
            <button class="filter-tab" onclick="filterUsers('company')">
                <i class="fa-solid fa-building"></i>
                Empresas
            </button>
            <?php if ($isSuperAdmin): ?>
            <button class="filter-tab" onclick="filterUsers('admin')">
                <i class="fa-solid fa-user-shield"></i>
                Admins
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- USERS LIST -->
    <div class="users-list" id="usersList">
        <?php if ($usuarios && $usuarios->num_rows > 0): ?>
            <?php while ($user = $usuarios->fetch_assoc()): 
                $isOnline = ($user['last_activity'] && $user['last_activity'] > (time() - 900));
                $userType = $user['type'] ?? 'person';
                $userRole = $user['role'] ?? null;
            ?>
                <div class="user-card" 
                     data-user-id="<?= $user['id'] ?>"
                     data-user-name="<?= strtolower($user['nome'] . ' ' . $user['email']) ?>"
                     data-user-online="<?= $isOnline ? '1' : '0' ?>"
                     data-user-type="<?= $userType ?>"
                     data-user-role="<?= $userRole ?? '' ?>"
                     onclick="startChat(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['nome'])) ?>')">
                    
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['nome']) ?>&background=238636&color=fff&bold=true&size=52" 
                         class="user-avatar-large" 
                         alt="<?= htmlspecialchars($user['nome']) ?>">
                    
                    <div class="user-info">
                        <div class="user-name">
                            <?= htmlspecialchars($user['nome']) ?>
                            
                            <?php if ($isSuperAdmin && $userRole): ?>
                                <span class="badge <?= $userRole === 'superadmin' ? 'error' : ($userRole === 'admin' ? 'info' : 'neutral') ?> role-badge-large">
                                    <?= strtoupper($userRole) ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($userType === 'company'): ?>
                                <span class="type-badge">
                                    <i class="fa-solid fa-building"></i>
                                    EMPRESA
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="user-email">
                            <i class="fa-solid fa-envelope"></i>
                            <?= htmlspecialchars($user['email']) ?>
                        </div>
                        
                        <div class="user-meta">
                            <div class="user-meta-item">
                                <i class="fa-solid fa-calendar"></i>
                                Cadastrado em <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                            </div>
                            <?php if ($isOnline): ?>
                                <div class="user-meta-item">
                                    <div class="online-dot"></div>
                                    Online agora
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="user-actions">
                        <button class="start-chat-btn" onclick="event.stopPropagation(); startChat(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['nome'])) ?>')">
                            <i class="fa-solid fa-comment"></i>
                            Iniciar conversa
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state-new">
                <i class="fa-solid fa-users-slash"></i>
                <h3>Nenhum usuário disponível</h3>
                <p>Não há usuários para iniciar conversas no momento</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    let currentFilter = 'all';
    
    // ========== INICIAR CONVERSA ==========
    window.startChat = function(userId, userName) {
        console.log('Iniciando conversa com:', userId, userName);
        
        // Mostrar loading
        const btn = event.target.closest('.start-chat-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Carregando...';
        }
        
        // Carregar conversa
        loadContent('modules/mensagens/mensagens?id=' + userId);
    };
    
    // ========== BUSCA DE USUÁRIOS ==========
    const searchInput = document.getElementById('searchUsers');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            filterUsersBySearch(search);
        });
    }
    
    function filterUsersBySearch(search) {
        document.querySelectorAll('.user-card').forEach(card => {
            const name = card.dataset.userName || '';
            const matchesSearch = name.includes(search);
            const matchesFilter = checkFilterMatch(card);
            
            card.style.display = (matchesSearch && matchesFilter) ? 'flex' : 'none';
        });
        
        checkEmptyState();
    }
    
    // ========== FILTROS ==========
    window.filterUsers = function(filter) {
        currentFilter = filter;
        
        // Update active tab
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.target.closest('.filter-tab').classList.add('active');
        
        // Apply filter
        const search = searchInput ? searchInput.value.toLowerCase() : '';
        document.querySelectorAll('.user-card').forEach(card => {
            const matchesSearch = search ? (card.dataset.userName || '').includes(search) : true;
            const matchesFilter = checkFilterMatch(card);
            
            card.style.display = (matchesSearch && matchesFilter) ? 'flex' : 'none';
        });
        
        checkEmptyState();
    };
    
    function checkFilterMatch(card) {
        switch (currentFilter) {
            case 'all':
                return true;
            case 'online':
                return card.dataset.userOnline === '1';
            case 'person':
                return card.dataset.userType === 'person';
            case 'company':
                return card.dataset.userType === 'company';
            case 'admin':
                return card.dataset.userRole === 'admin' || card.dataset.userRole === 'superadmin';
            default:
                return true;
        }
    }
    
    function checkEmptyState() {
        const usersList = document.getElementById('usersList');
        if (!usersList) return;
        
        const visibleCards = Array.from(document.querySelectorAll('.user-card'))
            .filter(card => card.style.display !== 'none');
        
        let emptyState = usersList.querySelector('.empty-state-new');
        
        if (visibleCards.length === 0) {
            if (!emptyState) {
                emptyState = document.createElement('div');
                emptyState.className = 'empty-state-new';
                emptyState.innerHTML = `
                    <i class="fa-solid fa-user-slash"></i>
                    <h3>Nenhum usuário encontrado</h3>
                    <p>Tente ajustar os filtros ou a busca</p>
                `;
                usersList.appendChild(emptyState);
            }
        } else {
            if (emptyState) {
                emptyState.remove();
            }
        }
    }
    
    console.log('✅ Nova mensagem carregada');
    console.log('📊 Total de usuários: <?= $total_usuarios ?>');
    console.log('🟢 Usuários online: <?= $usuarios_online ?>');
    <?php if (!$isSuperAdmin): ?>
    console.log('ℹ️ Modo Admin: SuperAdmins ocultos');
    <?php else: ?>
    console.log('👑 Modo SuperAdmin: Todos visíveis');
    <?php endif; ?>
})();
</script>
