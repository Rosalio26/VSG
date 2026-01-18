<?php
// Este arquivo deve estar em: pages/configuracoes.php
// Retorna apenas o HTML do conteúdo (sem header/footer)
?>
<div style="max-width: 900px; margin: 0 auto;">
    <!-- Header da Página -->
    <div style="margin-bottom: 32px;">
        <h1 style="font-size: 32px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">
            <i class="fa-solid fa-gear"></i> Configurações
        </h1>
        <p style="color: var(--text-secondary); font-size: 16px;">
            Gerencie suas preferências e configurações da conta
        </p>
    </div>

    <!-- Tabs de Configuração -->
    <div style="display: flex; gap: 12px; margin-bottom: 32px; border-bottom: 2px solid var(--border); overflow-x: auto; padding-bottom: 0;">
        <button class="config-tab active" onclick="switchConfigTab('perfil')" data-tab="perfil">
            <i class="fa-solid fa-user"></i> Perfil
        </button>
        <button class="config-tab" onclick="switchConfigTab('seguranca')" data-tab="seguranca">
            <i class="fa-solid fa-lock"></i> Segurança
        </button>
        <button class="config-tab" onclick="switchConfigTab('notificacoes')" data-tab="notificacoes">
            <i class="fa-solid fa-bell"></i> Notificações
        </button>
        <button class="config-tab" onclick="switchConfigTab('privacidade')" data-tab="privacidade">
            <i class="fa-solid fa-shield"></i> Privacidade
        </button>
    </div>

    <!-- Conteúdo das Tabs -->
    <div id="config-content">
        <!-- Tab Perfil -->
        <div id="tab-perfil" class="config-tab-content active">
            <div class="config-card">
                <div class="config-card-header">
                    <h3><i class="fa-solid fa-user-circle"></i> Informações Pessoais</h3>
                    <p>Atualize seus dados pessoais</p>
                </div>
                <form id="formPerfil" class="config-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nome *</label>
                            <input type="text" name="nome" id="nome" required>
                        </div>
                        <div class="form-group">
                            <label>Apelido</label>
                            <input type="text" name="apelido" id="apelido">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" id="email" required disabled>
                            <small>Email não pode ser alterado</small>
                        </div>
                        <div class="form-group">
                            <label>Telefone *</label>
                            <input type="tel" name="telefone" id="telefone" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>ID Público</label>
                            <input type="text" name="public_id" id="public_id" disabled>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab Segurança -->
        <div id="tab-seguranca" class="config-tab-content">
            <div class="config-card">
                <div class="config-card-header">
                    <h3><i class="fa-solid fa-key"></i> Alterar Senha</h3>
                    <p>Mantenha sua conta segura</p>
                </div>
                <form id="formSenha" class="config-form">
                    <div class="form-group">
                        <label>Senha Atual *</label>
                        <input type="password" name="senha_atual" required>
                    </div>
                    <div class="form-group">
                        <label>Nova Senha *</label>
                        <input type="password" name="nova_senha" required minlength="8">
                        <small>Mínimo 8 caracteres</small>
                    </div>
                    <div class="form-group">
                        <label>Confirmar Nova Senha *</label>
                        <input type="password" name="confirmar_senha" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-lock"></i> Alterar Senha
                        </button>
                    </div>
                </form>
            </div>

            <div class="config-card" style="margin-top: 24px;">
                <div class="config-card-header">
                    <h3><i class="fa-solid fa-clock"></i> Sessões Ativas</h3>
                    <p>Gerencie seus dispositivos conectados</p>
                </div>
                <div class="session-list">
                    <div class="session-item">
                        <div class="session-icon">
                            <i class="fa-solid fa-laptop"></i>
                        </div>
                        <div class="session-info">
                            <strong>Dispositivo Atual</strong>
                            <p>Última atividade: Agora</p>
                        </div>
                        <span class="session-badge">Ativo</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Notificações -->
        <div id="tab-notificacoes" class="config-tab-content">
            <div class="config-card">
                <div class="config-card-header">
                    <h3><i class="fa-solid fa-bell"></i> Preferências de Notificação</h3>
                    <p>Escolha como deseja receber notificações</p>
                </div>
                <form id="formNotificacoes" class="config-form">
                    <div class="notification-item">
                        <div class="notification-info">
                            <strong>Notificações por Email</strong>
                            <p>Receba atualizações importantes por email</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_notifications" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="notification-item">
                        <div class="notification-info">
                            <strong>Novos Produtos</strong>
                            <p>Alertas quando novos produtos forem adicionados</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="new_products" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="notification-item">
                        <div class="notification-info">
                            <strong>Status de Pedidos</strong>
                            <p>Atualizações sobre seus pedidos</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="order_status" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="notification-item">
                        <div class="notification-info">
                            <strong>Promoções e Ofertas</strong>
                            <p>Receba ofertas especiais e descontos</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="promotions">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-save"></i> Salvar Preferências
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab Privacidade -->
        <div id="tab-privacidade" class="config-tab-content">
            <div class="config-card">
                <div class="config-card-header">
                    <h3><i class="fa-solid fa-shield-halved"></i> Configurações de Privacidade</h3>
                    <p>Controle suas informações pessoais</p>
                </div>
                <div class="privacy-section">
                    <div class="privacy-item">
                        <div class="privacy-icon">
                            <i class="fa-solid fa-eye"></i>
                        </div>
                        <div class="privacy-info">
                            <strong>Perfil Público</strong>
                            <p>Seu perfil está visível apenas para você</p>
                        </div>
                    </div>

                    <div class="privacy-item">
                        <div class="privacy-icon">
                            <i class="fa-solid fa-database"></i>
                        </div>
                        <div class="privacy-info">
                            <strong>Dados Pessoais</strong>
                            <p>Baixe uma cópia dos seus dados</p>
                        </div>
                        <button class="btn-outline-small">
                            <i class="fa-solid fa-download"></i> Baixar
                        </button>
                    </div>

                    <div class="privacy-item danger">
                        <div class="privacy-icon">
                            <i class="fa-solid fa-trash"></i>
                        </div>
                        <div class="privacy-info">
                            <strong>Excluir Conta</strong>
                            <p>Remover permanentemente sua conta e dados</p>
                        </div>
                        <button class="btn-danger-small">
                            <i class="fa-solid fa-trash"></i> Excluir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Tabs de Configuração */
.config-tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.config-tab:hover {
    color: var(--primary);
}

.config-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.config-tab i {
    margin-right: 8px;
}

/* Conteúdo das Tabs */
.config-tab-content {
    display: none;
}

.config-tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Cards de Configuração */
.config-card {
    background: var(--card-bg);
    border: 2px solid var(--border);
    border-radius: 16px;
    padding: 28px;
}

.config-card-header {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.config-card-header h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 6px;
}

.config-card-header h3 i {
    color: var(--primary);
    margin-right: 10px;
}

.config-card-header p {
    color: var(--text-secondary);
    font-size: 14px;
}

/* Formulários */
.config-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

.form-group input {
    padding: 12px 16px;
    background: var(--darker-bg);
    border: 2px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 14px;
    font-family: inherit;
    transition: all 0.3s ease;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--dark-bg);
}

.form-group input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.form-group small {
    font-size: 12px;
    color: var(--text-secondary);
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 12px;
}

.btn-primary {
    padding: 12px 24px;
    background: var(--primary);
    color: #000;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
}

/* Notificações */
.notification-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: var(--darker-bg);
    border-radius: 10px;
    margin-bottom: 12px;
}

.notification-info strong {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.notification-info p {
    font-size: 13px;
    color: var(--text-secondary);
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--border);
    transition: 0.3s;
    border-radius: 28px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background: var(--dark-bg);
    transition: 0.3s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background: var(--primary);
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(24px);
    background: #000;
}

/* Sessões */
.session-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.session-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: var(--darker-bg);
    border-radius: 10px;
}

.session-icon {
    width: 48px;
    height: 48px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 20px;
}

.session-info {
    flex: 1;
}

.session-info strong {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.session-info p {
    font-size: 13px;
    color: var(--text-secondary);
}

.session-badge {
    padding: 6px 12px;
    background: rgba(16, 185, 129, 0.1);
    color: var(--primary);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

/* Privacidade */
.privacy-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.privacy-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: var(--darker-bg);
    border-radius: 10px;
    border: 2px solid var(--border);
}

.privacy-item.danger {
    border-color: var(--danger);
    background: rgba(239, 68, 68, 0.05);
}

.privacy-icon {
    width: 48px;
    height: 48px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 20px;
    flex-shrink: 0;
}

.privacy-item.danger .privacy-icon {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.privacy-info {
    flex: 1;
}

.privacy-info strong {
    display: block;
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.privacy-info p {
    font-size: 13px;
    color: var(--text-secondary);
}

.btn-outline-small,
.btn-danger-small {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-outline-small {
    background: transparent;
    border: 2px solid var(--border);
    color: var(--text-primary);
}

.btn-outline-small:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.btn-danger-small {
    background: rgba(239, 68, 68, 0.1);
    border: 2px solid var(--danger);
    color: var(--danger);
}

.btn-danger-small:hover {
    background: var(--danger);
    color: #fff;
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .config-card {
        padding: 20px;
    }
    
    .notification-item,
    .privacy-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
}
</style>

<script>
// Mudar tabs
function switchConfigTab(tabName) {
    // Remover active de todas as tabs
    document.querySelectorAll('.config-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('.config-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Ativar tab selecionada
    document.querySelector(`.config-tab[data-tab="${tabName}"]`).classList.add('active');
    document.getElementById(`tab-${tabName}`).classList.add('active');
}

// Carregar dados do usuário
fetch('actions/get_user_data.php')
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('nome').value = data.user.nome || '';
            document.getElementById('apelido').value = data.user.apelido || '';
            document.getElementById('email').value = data.user.email || '';
            document.getElementById('telefone').value = data.user.telefone || '';
            document.getElementById('public_id').value = data.user.public_id || '';
        }
    })
    .catch(err => console.error('Erro ao carregar dados:', err));

// Salvar perfil
document.getElementById('formPerfil').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('actions/update_profile.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Perfil atualizado com sucesso!');
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao atualizar perfil');
    }
});

// Alterar senha
document.getElementById('formSenha').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    if (formData.get('nova_senha') !== formData.get('confirmar_senha')) {
        alert('❌ As senhas não coincidem');
        return;
    }
    
    try {
        const response = await fetch('actions/change_password.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Senha alterada com sucesso!');
            this.reset();
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao alterar senha');
    }
});

// Salvar notificações
document.getElementById('formNotificacoes').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('actions/update_notifications.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Preferências salvas com sucesso!');
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao salvar preferências');
    }
});
</script>