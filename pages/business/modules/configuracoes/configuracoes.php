<?php
/**
 * ================================================================================
 * VISIONGREEN - CONFIGURAÇÕES DO SISTEMA
 * Módulo: system/settings.php
 * Descrição: Painel de configurações gerais, conta e sistema
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    require_once '../../../../registration/includes/login_tracker.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

// Processar atualizações de configuração
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Validar CSRF
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }

    // Ação: Atualizar dados pessoais
    if ($action === 'update_profile') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($nome) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Preenchimentos obrigatórios']);
            exit;
        }

        $stmt = $mysqli->prepare("UPDATE users SET nome = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nome, $email, $adminId);
        
        if ($stmt->execute()) {
            $_SESSION['auth']['nome'] = $nome;
            echo json_encode(['success' => true, 'message' => 'Perfil atualizado com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar perfil']);
        }
        exit;
    }

    // Ação: Alterar senha
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) {
            echo json_encode(['success' => false, 'message' => 'Senha deve ter no mínimo 8 caracteres']);
            exit;
        }

        if ($new !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'Senhas não correspondem']);
            exit;
        }

        // Verificar senha atual
        $stmt = $mysqli->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!password_verify($current, $result['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Senha atual incorreta']);
            exit;
        }

        // Atualizar senha
        $newHash = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $mysqli->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $newHash, $adminId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao alterar senha']);
        }
        exit;
    }

    // Ação: Atualizar configurações do sistema (Superadmin)
    if ($action === 'update_system_config' && $isSuperAdmin) {
        $config_key = $_POST['config_key'] ?? '';
        $config_value = $_POST['config_value'] ?? '';

        $stmt = $mysqli->prepare("
            INSERT INTO form_config (config_key, config_value, config_type)
            VALUES (?, ?, 'string')
            ON DUPLICATE KEY UPDATE config_value = ?
        ");
        $stmt->bind_param("sss", $config_key, $config_value, $config_value);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Configuração salva com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar configuração']);
        }
        exit;
    }

    // Ação: Habilitar/Desabilitar 2FA
    if ($action === 'toggle_2fa') {
        $enabled = $_POST['enabled'] === '1' ? 1 : 0;
        
        $stmt = $mysqli->prepare("UPDATE users SET two_fa_enabled = ? WHERE id = ?");
        $stmt->bind_param("ii", $enabled, $adminId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => '2FA ' . ($enabled ? 'ativado' : 'desativado')]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar 2FA']);
        }
        exit;
    }
}

// Buscar dados do usuário
$stmt = $mysqli->prepare("
    SELECT id, nome, email, email_corporativo, status, created_at, password_changed_at 
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

// Buscar configurações do sistema
$systemConfigs = [];
if ($isSuperAdmin) {
    $stmt = $mysqli->prepare("SELECT config_key, config_value FROM form_config LIMIT 50");
    $stmt->execute();
    $systemConfigs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Buscar logins recentes (últimos 5)
$loginHistory = getLoginHistory($mysqli, $adminId, 5);

// Gerar CSRF token se não existir
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Determinar qual aba deve ser carregada via GET parameter
$activeTab = $_GET['tab'] ?? 'account';
?>

<!-- PAGE HEADER -->
<div style="margin-bottom: 40px;">
    <h1 style="color: #fff; margin: 0 0 8px 0; font-size: 2rem;">
        <i class="fa-solid fa-sliders"></i>
        Configurações
    </h1>
    <p style="color: #888; font-size: 0.938rem; margin: 0;">
        Gerencie suas preferências pessoais e configurações do sistema
    </p>
</div>

<!-- TABS NAVIGATION -->
<div style="border-bottom: 1px solid var(--border); margin-bottom: 30px;">
    <div style="display: flex; gap: 4px;">
        <button class="settings-tab <?= $activeTab === 'account' ? 'active' : '' ?>" onclick="switchTab('account', this)">
            <i class="fa-solid fa-user-gear"></i>
            Minha Conta
        </button>
        <button class="settings-tab <?= $activeTab === 'security' ? 'active' : '' ?>" onclick="switchTab('security', this)">
            <i class="fa-solid fa-shield-halved"></i>
            Segurança
        </button>
        <button class="settings-tab <?= $activeTab === 'preferences' ? 'active' : '' ?>" onclick="switchTab('preferences', this)">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            Preferências
        </button>
        <?php if ($isSuperAdmin): ?>
        <button class="settings-tab <?= $activeTab === 'system' ? 'active' : '' ?>" onclick="switchTab('system', this)">
            <i class="fa-solid fa-server"></i>
            Sistema
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- TAB 1: MINHA CONTA -->
<div id="tab-account" class="settings-content" <?= $activeTab !== 'account' ? 'style="display: none;"' : '' ?>>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;">
        
        <!-- Informações Pessoais -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-id-card"></i>
                    Informações Pessoais
                </h3>
            </div>
            <div class="card-body">
                <form id="profileForm">
                    <div class="form-group">
                        <label class="form-label">Nome Completo</label>
                        <input type="text" class="form-input" id="nome" name="nome" value="<?= htmlspecialchars($userData['nome']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Pessoal</label>
                        <input type="email" class="form-input" id="email" name="email" value="<?= htmlspecialchars($userData['email']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Corporativo</label>
                        <input type="email" class="form-input" disabled value="<?= htmlspecialchars($userData['email_corporativo'] ?? '-') ?>">
                        <small style="color: #666; font-size: 0.75rem;">Não pode ser alterado</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div style="padding: 10px 12px; background: var(--bg-elevated); border-radius: 8px; color: #ccc;">
                            <span class="badge <?= $userData['status'] === 'active' ? 'success' : 'warning' ?>">
                                <?= ucfirst($userData['status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Membro Desde</label>
                        <div style="padding: 10px 12px; background: var(--bg-elevated); border-radius: 8px; color: #999;">
                            <?= date('d/m/Y H:i', strtotime($userData['created_at'])) ?>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" onclick="updateProfile()">
                        <i class="fa-solid fa-save"></i>
                        Salvar Alterações
                    </button>
                </form>
            </div>
        </div>

        <!-- Informações da Conta -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-circle-info"></i>
                    Detalhes da Conta
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div style="padding: 16px; background: rgba(35,134,54,0.1); border-radius: 8px;">
                        <p style="color: #999; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; margin: 0 0 8px 0;">Cargo</p>
                        <p style="color: #fff; margin: 0; font-size: 1rem; font-weight: 700;">
                            <?= strtoupper($adminRole) ?>
                        </p>
                    </div>

                    <div style="padding: 16px; background: rgba(56,139,253,0.1); border-radius: 8px;">
                        <p style="color: #999; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; margin: 0 0 8px 0;">Tipo de Conta</p>
                        <p style="color: #fff; margin: 0; font-size: 1rem; font-weight: 700;">
                            Administrador <?= $isSuperAdmin ? '(Super)' : '' ?>
                        </p>
                    </div>

                    <div style="padding: 16px; background: rgba(158,106,3,0.1); border-radius: 8px;">
                        <p style="color: #999; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; margin: 0 0 8px 0;">Última Senha Alterada</p>
                        <p style="color: #fff; margin: 0; font-size: 1rem; font-weight: 700;">
                            <?= date('d/m/Y', strtotime($userData['password_changed_at'])) ?>
                        </p>
                    </div>

                    <div style="padding: 16px; background: rgba(0,0,0,0.3); border-radius: 8px; border: 1px solid var(--border);">
                        <p style="color: #ccc; margin: 0; font-size: 0.875rem;">
                            <strong>Timeout de Sessão:</strong><br>
                            <?= $isSuperAdmin ? '1 hora' : '24 horas' ?> de inatividade
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: SEGURANÇA -->
<div id="tab-security" class="settings-content" <?= $activeTab !== 'security' ? 'style="display: none;"' : '' ?>>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;">
        
        <!-- Alterar Senha -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-key"></i>
                    Alterar Senha
                </h3>
            </div>
            <div class="card-body">
                <form id="passwordForm">
                    <div class="form-group">
                        <label class="form-label">Senha Atual</label>
                        <input type="password" class="form-input" id="current_password" placeholder="Digite sua senha atual">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nova Senha</label>
                        <input type="password" class="form-input" id="new_password" placeholder="Mínimo 8 caracteres">
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirmar Senha</label>
                        <input type="password" class="form-input" id="confirm_password" placeholder="Confirme a nova senha">
                    </div>

                    <div style="background: rgba(248,81,73,0.1); border-left: 3px solid #f85149; padding: 12px; border-radius: 6px; margin-bottom: 16px;">
                        <p style="color: #f85149; font-size: 0.75rem; margin: 0; font-weight: 600;">⚠️ Importante</p>
                        <p style="color: #999; font-size: 0.75rem; margin: 4px 0 0 0;">
                            Escolha uma senha forte com letras, números e caracteres especiais.
                        </p>
                    </div>

                    <button type="button" class="btn btn-primary" onclick="changePassword()">
                        <i class="fa-solid fa-lock"></i>
                        Alterar Senha
                    </button>
                </form>
            </div>
        </div>

        <!-- Segurança da Conta -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-shield-check"></i>
                    Opções de Segurança
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    
                    <!-- Autenticação de Dois Fatores -->
                    <div style="padding: 16px; background: rgba(0,0,0,0.3); border-radius: 8px; border: 1px solid var(--border);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="color: #fff; margin: 0 0 4px 0;">
                                    <i class="fa-solid fa-mobile-screen-button"></i>
                                    Autenticação de Dois Fatores
                                </h4>
                                <p style="color: #666; margin: 0; font-size: 0.813rem;">
                                    Ativar 2FA para maior segurança
                                </p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" id="twoFAToggle" onchange="toggle2FA()">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Sessões Ativas -->
                    <div style="padding: 16px; background: rgba(0,0,0,0.3); border-radius: 8px; border: 1px solid var(--border);">
                        <h4 style="color: #fff; margin: 0 0 12px 0;">
                            <i class="fa-solid fa-link"></i>
                            Sessão Atual
                        </h4>
                        <?php
                            $currentBrowser = detectBrowser($_SERVER['HTTP_USER_AGENT'] ?? '');
                            $currentOS = detectOS($_SERVER['HTTP_USER_AGENT'] ?? '');
                            $currentIP = getClientIP();
                        ?>
                        <div style="padding: 10px; background: rgba(35,134,54,0.1); border-left: 3px solid var(--accent); border-radius: 6px; margin-bottom: 12px;">
                            <div style="color: #00ff88; font-weight: 600; font-size: 0.75rem; margin-bottom: 4px;">
                                <i class="fa-solid <?= $currentBrowser['icon'] ?>" style="color: <?= $currentBrowser['color'] ?>; margin-right: 4px;"></i>
                                <?= $currentBrowser['name'] ?> em 
                                <i class="fa-solid <?= $currentOS['icon'] ?>" style="color: <?= $currentOS['color'] ?>; margin-left: 4px; margin-right: 4px;"></i>
                                <?= $currentOS['name'] ?>
                            </div>
                            <div style="color: #666; font-size: 0.75rem;">
                                IP: <code style="background: rgba(0,0,0,0.3); padding: 2px 4px; border-radius: 3px;"><?= htmlspecialchars($currentIP) ?></code>
                            </div>
                        </div>
                        <p style="color: #999; font-size: 0.813rem; margin: 0 0 12px 0;">
                            Você tem 1 sessão ativa no momento.
                        </p>
                        <button class="btn btn-ghost" style="width: 100%; justify-content: center;">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            Encerrar Todas as Sessões
                        </button>
                    </div>

                    <!-- Logins Recentes -->
                    <div style="padding: 16px; background: rgba(0,0,0,0.3); border-radius: 8px; border: 1px solid var(--border);">
                        <h4 style="color: #fff; margin: 0 0 12px 0;">
                            <i class="fa-solid fa-history"></i>
                            Logins Recentes
                        </h4>
                        <div style="display: flex; flex-direction: column; gap: 8px; max-height: 300px; overflow-y: auto;">
                            <?php if (!empty($loginHistory)): ?>
                                <?php foreach ($loginHistory as $login): 
                                    $browser = detectBrowser($login['user_agent']);
                                    $os = detectOS($login['user_agent']);
                                    $loginTime = strtotime($login['created_at']);
                                    $timeAgo = date('d/m/Y H:i', $loginTime);
                                    $ipAddress = htmlspecialchars($login['ip_address']);
                                ?>
                                <div style="padding: 10px; background: rgba(35,134,54,0.1); border-left: 3px solid var(--accent); border-radius: 6px; font-size: 0.75rem;">
                                    <div style="color: #00ff88; font-weight: 600;">
                                        <i class="fa-solid <?= $browser['icon'] ?>" style="color: <?= $browser['color'] ?>; margin-right: 4px;"></i>
                                        <?= $browser['name'] ?> - 
                                        <i class="fa-solid <?= $os['icon'] ?>" style="color: <?= $os['color'] ?>; margin-left: 4px; margin-right: 4px;"></i>
                                        <?= $os['name'] ?>
                                    </div>
                                    <div style="color: #666; margin-top: 4px;">
                                        <?= $timeAgo ?> | IP: <code style="background: rgba(0,0,0,0.3); padding: 2px 4px; border-radius: 3px;"><?= $ipAddress ?></code>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding: 12px; background: rgba(248,81,73,0.1); border-left: 3px solid #f85149; border-radius: 6px; color: #f85149; font-size: 0.75rem;">
                                    <i class="fa-solid fa-info-circle"></i> Nenhum login registrado ainda
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TAB 3: PREFERÊNCIAS -->
<div id="tab-preferences" class="settings-content" <?= $activeTab !== 'preferences' ? 'style="display: none;"' : '' ?>>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;">
        
        <!-- Preferências de Interface -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-eye"></i>
                    Interface
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    
                    <div>
                        <label class="form-label">Tema</label>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                            <div style="padding: 12px; background: rgba(35,134,54,0.1); border: 2px solid var(--accent); border-radius: 8px; cursor: pointer;">
                                <i class="fa-solid fa-sun"></i>
                                <p style="color: #ccc; margin: 4px 0 0 0; font-size: 0.75rem;">Claro</p>
                            </div>
                            <div style="padding: 12px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 8px; cursor: pointer;">
                                <i class="fa-solid fa-moon"></i>
                                <p style="color: #999; margin: 4px 0 0 0; font-size: 0.75rem;">Escuro</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Tema de Cor</label>
                        <select class="form-select">
                            <option>Verde (Padrão)</option>
                            <option>Azul</option>
                            <option>Roxo</option>
                            <option>Laranja</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Tamanho da Fonte</label>
                        <select class="form-select">
                            <option>Pequeno</option>
                            <option selected>Normal</option>
                            <option>Grande</option>
                            <option>Muito Grande</option>
                        </select>
                    </div>

                    <button class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-save"></i>
                        Salvar Preferências
                    </button>
                </div>
            </div>
        </div>

        <!-- Notificações -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-bell"></i>
                    Notificações
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border);">
                        <div>
                            <p style="color: #ccc; margin: 0; font-weight: 600;">Email de Alertas</p>
                            <small style="color: #666;">Receba alertas críticos por email</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border);">
                        <div>
                            <p style="color: #ccc; margin: 0; font-weight: 600;">Notificações no Navegador</p>
                            <small style="color: #666;">Popup quando há novas mensagens</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border);">
                        <div>
                            <p style="color: #ccc; margin: 0; font-weight: 600;">Notificações de Segurança</p>
                            <small style="color: #666;">Avisos de tentativas de login</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0;">
                        <div>
                            <p style="color: #ccc; margin: 0; font-weight: 600;">Resumo Semanal</p>
                            <small style="color: #666;">Email com dados da semana</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TAB 4: SISTEMA (SUPERADMIN) -->
<?php if ($isSuperAdmin): ?>
<div id="tab-system" class="settings-content" <?= $activeTab !== 'system' ? 'style="display: none;"' : '' ?>>
    <div style="display: grid; gap: 30px;">
        
        <!-- Configurações Gerais do Sistema -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-cogs"></i>
                    Configurações Gerais
                </h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
                    
                    <div>
                        <label class="form-label">Nome da Empresa</label>
                        <input type="text" class="form-input" placeholder="VisionGreen" value="VisionGreen">
                    </div>

                    <div>
                        <label class="form-label">Email de Suporte</label>
                        <input type="email" class="form-input" placeholder="support@visiongreen.com" value="support@visiongreen.com">
                    </div>

                    <div>
                        <label class="form-label">Telefone de Suporte</label>
                        <input type="tel" class="form-input" placeholder="+258 (21) 123-4567" value="+258 (21) 123-4567">
                    </div>

                    <div>
                        <label class="form-label">Moeda Padrão</label>
                        <select class="form-select">
                            <option selected>MZN (Meticais)</option>
                            <option>USD (Dólar)</option>
                            <option>EUR (Euro)</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Fuso Horário</label>
                        <select class="form-select">
                            <option selected>Africa/Maputo (UTC+2)</option>
                            <option>UTC</option>
                            <option>Europe/London</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Idioma</label>
                        <select class="form-select">
                            <option selected>Português</option>
                            <option>Inglês</option>
                            <option>Espanhol</option>
                        </select>
                    </div>
                </div>

                <button class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fa-solid fa-save"></i>
                    Salvar Configurações
                </button>
            </div>
        </div>

        <!-- Limites do Sistema -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-gauge-high"></i>
                    Limites e Quotas
                </h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    
                    <div>
                        <label class="form-label">Armazenamento Máximo por Usuário (GB)</label>
                        <input type="number" class="form-input" value="10" min="1">
                    </div>

                    <div>
                        <label class="form-label">Tentativas de Login Máximas</label>
                        <input type="number" class="form-input" value="5" min="1">
                    </div>

                    <div>
                        <label class="form-label">Timeout de Sessão Admin (minutos)</label>
                        <input type="number" class="form-input" value="60" min="10">
                    </div>

                    <div>
                        <label class="form-label">Timeout de Sessão Superadmin (minutos)</label>
                        <input type="number" class="form-input" value="60" min="10">
                    </div>

                    <div>
                        <label class="form-label">Tamanho Máximo de Upload (MB)</label>
                        <input type="number" class="form-input" value="50" min="1">
                    </div>

                    <div>
                        <label class="form-label">Limite de Requisições por Minuto</label>
                        <input type="number" class="form-input" value="100" min="10">
                    </div>
                </div>

                <button class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fa-solid fa-save"></i>
                    Salvar Limites
                </button>
            </div>
        </div>

        <!-- Segurança do Sistema -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-shield-halved"></i>
                    Segurança do Sistema
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border);">
                        <div>
                            <p style="color: #ccc; margin: 0; font-weight: 600;">Modo Manutenção</p>
                            <small style="color: #666;">Desativar acesso temporário</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border);">
                        <div>
                            <p style="color: #ccc; margin: 0; font-weight: 600;">Exigir 2FA para Admins</p>
                            <small style="color: #666;">Obrigatório autenticação de dois fatores</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border);">
                        <div>
                            <p style="color: #ccc; margin: 0; font-weight: 600;">Habilitar HTTPS Obrigatório</p>
                            <small style="color: #666;">Redirecionar HTTP para HTTPS</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0;">
                        <div>
                            <p style="color: #ccc; margin: 0; font-weight: 600;">Bloquear IPs Suspeitos</p>
                            <small style="color: #666;">Bloqueio automático de múltiplas tentativas</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <button class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fa-solid fa-save"></i>
                        Salvar Segurança
                    </button>
                </div>
            </div>
        </div>

        <!-- Manutenção do Sistema -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-wrench"></i>
                    Manutenção
                </h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    
                    <button class="btn btn-ghost" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-broom"></i>
                        Limpar Cache
                    </button>

                    <button class="btn btn-ghost" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-database"></i>
                        Otimizar Banco de Dados
                    </button>

                    <button class="btn btn-ghost" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-download"></i>
                        Fazer Backup
                    </button>

                    <button class="btn btn-ghost" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-sync"></i>
                        Sincronizar Dados
                    </button>
                </div>

                <div style="margin-top: 20px; padding: 16px; background: rgba(0,0,0,0.3); border-radius: 8px; border-left: 3px solid #d29922;">
                    <p style="color: #d29922; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; margin: 0 0 8px 0;">⚠️ Atenção</p>
                    <p style="color: #999; margin: 0; font-size: 0.875rem;">
                        Estas operações podem levar alguns minutos. Não feche a página durante a execução.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    .settings-tab {
        padding: 12px 20px;
        background: transparent;
        border: none;
        color: var(--text-secondary);
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        position: relative;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .settings-tab:hover {
        color: var(--text-primary);
    }

    .settings-tab.active {
        color: var(--accent);
    }

    .settings-tab.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--accent);
    }

    .settings-content {
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        background: rgba(0,0,0,0.2);
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-body {
        padding: 24px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group:last-child {
        margin-bottom: 0;
    }

    .form-label {
        display: block;
        color: var(--text-secondary);
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .form-input,
    .form-select {
        width: 100%;
        padding: 10px 12px;
        background: var(--bg-elevated);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .form-input:focus,
    .form-select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(35, 134, 54, 0.1);
    }

    .form-input:disabled {
        background: rgba(0,0,0,0.2);
        color: #666;
        cursor: not-allowed;
    }

    .btn {
        padding: 10px 16px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.875rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: var(--accent);
        color: #000;
    }

    .btn-primary:hover {
        background: #00e080;
    }

    .btn-ghost {
        background: transparent;
        color: var(--text-secondary);
        border: 1px solid var(--border);
    }

    .btn-ghost:hover {
        background: rgba(255,255,255,0.05);
        color: var(--text-primary);
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .badge.success {
        background: rgba(35, 134, 54, 0.15);
        color: #3fb950;
    }

    .badge.warning {
        background: rgba(158, 106, 3, 0.15);
        color: #d29922;
    }

    .password-strength {
        height: 4px;
        background: var(--border);
        border-radius: 2px;
        margin-top: 8px;
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
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
        background-color: #555;
        transition: 0.3s;
        border-radius: 24px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
    }

    input:checked + .toggle-slider {
        background-color: var(--accent);
    }

    input:checked + .toggle-slider:before {
        transform: translateX(26px);
    }
</style>

<script>
    function switchTab(tabName, element) {
        // Esconder todas as abas
        document.querySelectorAll('.settings-content').forEach(tab => {
            tab.style.display = 'none';
        });
        
        // Remover classe active de todos os botões
        document.querySelectorAll('.settings-tab').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Mostrar aba selecionada
        document.getElementById('tab-' + tabName).style.display = 'block';
        element.classList.add('active');
    }

    function updateProfile() {
        const nome = document.getElementById('nome').value;
        const email = document.getElementById('email').value;

        fetch('system/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=update_profile&nome=' + encodeURIComponent(nome) + '&email=' + encodeURIComponent(email) + 
                  '&csrf=<?= $_SESSION['csrf_token'] ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('✓ Perfil atualizado com sucesso', 'success');
            } else {
                showToast('✗ ' + data.message, 'error');
            }
        });
    }

    function changePassword() {
        const current = document.getElementById('current_password').value;
        const newPass = document.getElementById('new_password').value;
        const confirm = document.getElementById('confirm_password').value;

        if (!current || !newPass || !confirm) {
            showToast('✗ Preencha todos os campos', 'error');
            return;
        }

        fetch('system/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=change_password&current_password=' + encodeURIComponent(current) + 
                  '&new_password=' + encodeURIComponent(newPass) + 
                  '&confirm_password=' + encodeURIComponent(confirm) + 
                  '&csrf=<?= $_SESSION['csrf_token'] ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('✓ Senha alterada com sucesso', 'success');
                document.getElementById('passwordForm').reset();
            } else {
                showToast('✗ ' + data.message, 'error');
            }
        });
    }

    function toggle2FA() {
        const enabled = document.getElementById('twoFAToggle').checked ? '1' : '0';

        fetch('system/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=toggle_2fa&enabled=' + enabled + '&csrf=<?= $_SESSION['csrf_token'] ?>'
        })
        .then(r => r.json())
        .then(data => {
            showToast(data.success ? '✓ ' + data.message : '✗ ' + data.message);
        });
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 16px 20px;
            background: ${type === 'success' ? 'rgba(35,134,54,0.9)' : 'rgba(248,81,73,0.9)'};
            color: #fff;
            border-radius: 8px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Monitorar força da senha
    document.getElementById('new_password')?.addEventListener('input', function(e) {
        const password = e.target.value;
        const strength = document.getElementById('passwordStrength');
        
        let score = 0;
        if (password.length >= 8) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;

        const colors = ['#555', '#f85149', '#d29922', '#58a6ff', '#00ff88'];
        strength.style.background = colors[score];
    });
</script>