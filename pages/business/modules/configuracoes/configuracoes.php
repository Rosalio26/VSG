<?php
/**
 * ================================================================================
 * VISIONGREEN - MÓDULO DE CONFIGURAÇÕES
 * Arquivo: pages/business/modules/configuracoes/configuracoes.php
 * Descrição: Configurações da empresa e gerenciamento de permissões de funcionários
 * ATUALIZADO: Sistema completo de controle de acesso
 * ================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação - APENAS GESTORES
$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isManager = isset($_SESSION['auth']['user_id']);

if (!$isManager) {
    echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">
        <i class="fa-solid fa-lock" style="font-size: 48px; margin-bottom: 16px;"></i>
        <h3>Acesso Restrito</h3>
        <p>Apenas gestores podem acessar as configurações.</p>
    </div>';
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];
$userName = $_SESSION['auth']['nome'];

// Incluir DB
$db_paths = [
    __DIR__ . '/../../../../registration/includes/db.php',
    dirname(dirname(dirname(__FILE__))) . '/registration/includes/db.php'
];

$db_connected = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected || !isset($mysqli)) {
    echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">Erro de Conexão</div>';
    exit;
}

// Buscar dados da empresa
$stmt = $mysqli->prepare("
    SELECT u.*, b.* 
    FROM users u 
    LEFT JOIN businesses b ON u.id = b.user_id 
    WHERE u.id = ? 
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Buscar funcionários
$stmt = $mysqli->prepare("
    SELECT 
        e.*,
        u.email as email_pessoal,
        u.type as user_type
    FROM employees e
    INNER JOIN users u ON e.user_employee_id = u.id
    WHERE e.user_id = ?
    AND e.is_active = 1
    ORDER BY e.created_at DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Módulos disponíveis para controle de permissões
$available_modules = [
    'dashboard' => [
        'name' => 'Dashboard',
        'icon' => 'fa-gauge-high',
        'description' => 'Visão geral e estatísticas',
        'permissions' => ['can_view']
    ],
    'produtos' => [
        'name' => 'Produtos',
        'icon' => 'fa-box',
        'description' => 'Gerenciamento de produtos',
        'permissions' => ['can_view', 'can_create', 'can_edit', 'can_delete']
    ],
    'vendas' => [
        'name' => 'Vendas',
        'icon' => 'fa-chart-line',
        'description' => 'Histórico e análise de vendas',
        'permissions' => ['can_view', 'can_edit']
    ],
    'funcionarios' => [
        'name' => 'Funcionários',
        'icon' => 'fa-users',
        'description' => 'Gerenciamento de equipe',
        'permissions' => ['can_view', 'can_create', 'can_edit']
    ],
    'mensagens' => [
        'name' => 'Mensagens',
        'icon' => 'fa-comments',
        'description' => 'Sistema de mensagens',
        'permissions' => ['can_view', 'can_create']
    ],
    'relatorios' => [
        'name' => 'Relatórios',
        'icon' => 'fa-file-invoice',
        'description' => 'Geração de relatórios',
        'permissions' => ['can_view', 'can_create']
    ],
    'assinatura' => [
        'name' => 'Assinatura',
        'icon' => 'fa-credit-card',
        'description' => 'Planos e pagamentos',
        'permissions' => ['can_view']
    ],
    'configuracoes' => [
        'name' => 'Configurações',
        'icon' => 'fa-sliders',
        'description' => 'Configurações do sistema',
        'permissions' => ['can_view']
    ]
];
?>

<style>
/* ==================== GitHub Dark Theme ==================== */
:root {
    --gh-bg-primary: #0d1117;
    --gh-bg-secondary: #161b22;
    --gh-bg-tertiary: #21262d;
    --gh-border: #30363d;
    --gh-border-hover: #8b949e;
    --gh-text: #c9d1d9;
    --gh-text-secondary: #8b949e;
    --gh-text-muted: #6e7681;
    --gh-accent-green: #238636;
    --gh-accent-green-bright: #2ea043;
    --gh-accent-blue: #1f6feb;
    --gh-accent-yellow: #d29922;
    --gh-accent-red: #da3633;
}

.config-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 16px;
}

/* Header */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gh-border);
}

.page-title {
    font-size: 24px;
    font-weight: 600;
    color: var(--gh-text);
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-title i {
    color: var(--gh-accent-green-bright);
}

/* Tabs */
.config-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--gh-border);
    padding-bottom: 0;
}

.tab-btn {
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--gh-text-secondary);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tab-btn:hover {
    color: var(--gh-text);
    background: var(--gh-bg-tertiary);
}

.tab-btn.active {
    color: var(--gh-accent-green-bright);
    border-bottom-color: var(--gh-accent-green-bright);
}

.tab-content {
    display: none;
    animation: fadeIn 0.3s ease;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Cards */
.config-card {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gh-border);
}

.card-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--gh-text);
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-description {
    font-size: 14px;
    color: var(--gh-text-secondary);
    margin-top: 4px;
}

/* Form Elements */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--gh-text-secondary);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 10px 12px;
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    color: var(--gh-text);
    font-size: 14px;
    font-family: inherit;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--gh-accent-blue);
    box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.1);
}

.form-textarea {
    resize: vertical;
    min-height: 100px;
}

.form-switch {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: var(--gh-bg-tertiary);
    border: 1px solid var(--gh-border);
    border-radius: 8px;
    margin-bottom: 12px;
}

.switch-info {
    flex: 1;
}

.switch-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--gh-text);
    margin-bottom: 4px;
}

.switch-description {
    font-size: 12px;
    color: var(--gh-text-secondary);
}

.toggle-switch {
    position: relative;
    width: 48px;
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
    background-color: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    transition: 0.3s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
    background-color: var(--gh-text-secondary);
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: var(--gh-accent-green);
    border-color: var(--gh-accent-green);
}

input:checked + .toggle-slider:before {
    transform: translateX(24px);
    background-color: #fff;
}

/* Buttons */
.btn {
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid;
}

.btn-primary {
    background: var(--gh-accent-green);
    border-color: rgba(240, 246, 252, 0.1);
    color: #fff;
}

.btn-primary:hover {
    background: var(--gh-accent-green-bright);
}

.btn-secondary {
    background: var(--gh-bg-tertiary);
    border-color: var(--gh-border);
    color: var(--gh-text);
}

.btn-secondary:hover {
    background: var(--gh-bg-primary);
    border-color: var(--gh-border-hover);
}

.btn-danger {
    background: var(--gh-accent-red);
    border-color: rgba(240, 246, 252, 0.1);
    color: #fff;
}

.btn-danger:hover {
    background: #b62324;
}

/* Employees Table */
.employees-table {
    width: 100%;
    border-collapse: collapse;
}

.employees-table thead {
    background: var(--gh-bg-primary);
}

.employees-table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-text-secondary);
    text-transform: uppercase;
    border-bottom: 1px solid var(--gh-border);
}

.employees-table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gh-border);
    font-size: 14px;
    color: var(--gh-text);
}

.employees-table tbody tr:hover {
    background: var(--gh-bg-primary);
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid;
}

.badge-success {
    background: rgba(46, 160, 67, 0.1);
    border-color: rgba(46, 160, 67, 0.4);
    color: #3fb950;
}

.badge-warning {
    background: rgba(210, 153, 34, 0.1);
    border-color: rgba(210, 153, 34, 0.4);
    color: #d29922;
}

.badge-info {
    background: rgba(31, 111, 235, 0.1);
    border-color: rgba(31, 111, 235, 0.4);
    color: var(--gh-accent-blue);
}

/* Permissions Grid */
.permissions-grid {
    display: grid;
    gap: 16px;
}

.module-permission {
    background: var(--gh-bg-tertiary);
    border: 1px solid var(--gh-border);
    border-radius: 8px;
    padding: 16px;
}

.module-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--gh-border);
}

.module-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.module-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: rgba(46, 160, 67, 0.1);
    color: var(--gh-accent-green-bright);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.module-details h4 {
    font-size: 15px;
    font-weight: 600;
    color: var(--gh-text);
    margin-bottom: 4px;
}

.module-details p {
    font-size: 12px;
    color: var(--gh-text-secondary);
}

.permissions-checkboxes {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.permission-check {
    display: flex;
    align-items: center;
    gap: 8px;
}

.permission-check input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--gh-accent-green);
}

.permission-check label {
    font-size: 13px;
    color: var(--gh-text);
    cursor: pointer;
}

/* Modal */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(1, 4, 9, 0.85);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay.active {
    display: flex;
}

.modal-container {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 12px;
    max-width: 800px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--gh-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--gh-text);
}

.modal-close {
    background: none;
    border: none;
    color: var(--gh-text-secondary);
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--gh-bg-tertiary);
    color: var(--gh-text);
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--gh-border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* Alert */
.alert {
    padding: 12px 16px;
    border: 1px solid;
    border-radius: 6px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    animation: slideIn 0.2s ease;
}

.alert-success {
    background: rgba(46, 160, 67, 0.1);
    border-color: rgba(46, 160, 67, 0.4);
    color: #3fb950;
}

.alert-error {
    background: rgba(248, 81, 73, 0.1);
    border-color: rgba(248, 81, 73, 0.4);
    color: #f85149;
}

.alert-info {
    background: rgba(31, 111, 235, 0.1);
    border-color: rgba(31, 111, 235, 0.4);
    color: var(--gh-accent-blue);
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .permissions-checkboxes {
        flex-direction: column;
    }
}
</style>

<div class="config-container">
    <div id="alert-container"></div>

    <!-- Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-sliders"></i>
            Configurações
        </h1>
    </div>

    <!-- Tabs -->
    <div class="config-tabs">
        <button class="tab-btn active" onclick="switchTab('geral')">
            <i class="fa-solid fa-building"></i>
            Dados da Empresa
        </button>
        <button class="tab-btn" onclick="switchTab('permissoes')">
            <i class="fa-solid fa-user-shield"></i>
            Permissões de Funcionários
        </button>
        <button class="tab-btn" onclick="switchTab('notificacoes')">
            <i class="fa-solid fa-bell"></i>
            Notificações
        </button>
        <button class="tab-btn" onclick="switchTab('seguranca')">
            <i class="fa-solid fa-lock"></i>
            Segurança
        </button>
    </div>

    <!-- Tab: Dados da Empresa -->
    <div class="tab-content active" id="tab-geral">
        <div class="config-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <i class="fa-solid fa-building"></i>
                        Informações da Empresa
                    </h3>
                    <p class="card-description">Atualize os dados básicos da sua empresa</p>
                </div>
            </div>

            <form id="formEmpresa">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nome da Empresa *</label>
                        <input type="text" class="form-input" id="empresaNome" value="<?= htmlspecialchars($company['nome']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-input" id="empresaEmail" value="<?= htmlspecialchars($company['email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Telefone</label>
                        <input type="tel" class="form-input" id="empresaTelefone" value="<?= htmlspecialchars($company['telefone'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tipo de Negócio</label>
                        <select class="form-select" id="empresaTipo" disabled>
                            <option value="mei" <?= $company['business_type'] === 'mei' ? 'selected' : '' ?>>MEI</option>
                            <option value="ltda" <?= $company['business_type'] === 'ltda' ? 'selected' : '' ?>>LTDA</option>
                            <option value="sa" <?= $company['business_type'] === 'sa' ? 'selected' : '' ?>>S.A</option>
                        </select>
                        <p style="font-size: 12px; color: var(--gh-text-secondary); margin-top: 4px;">
                            <i class="fa-solid fa-info-circle"></i> Contate o suporte para alterar
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-textarea" id="empresaDescricao"><?= htmlspecialchars($company['description'] ?? '') ?></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="resetFormEmpresa()">
                        <i class="fa-solid fa-rotate-left"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i>
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab: Permissões de Funcionários -->
    <div class="tab-content" id="tab-permissoes">
        <div class="config-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <i class="fa-solid fa-user-shield"></i>
                        Controle de Permissões
                    </h3>
                    <p class="card-description">Gerencie o que cada funcionário pode acessar e fazer</p>
                </div>
            </div>

            <?php if (empty($employees)): ?>
                <div style="padding: 60px 20px; text-align: center; color: var(--gh-text-secondary);">
                    <i class="fa-solid fa-users" style="font-size: 64px; opacity: 0.3; margin-bottom: 16px;"></i>
                    <h3 style="font-size: 18px; color: var(--gh-text); margin-bottom: 8px;">Nenhum funcionário cadastrado</h3>
                    <p>Adicione funcionários para configurar suas permissões</p>
                </div>
            <?php else: ?>
                <table class="employees-table">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Cargo</th>
                            <th>Email Corporativo</th>
                            <th>Status</th>
                            <th style="text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($emp['nome']) ?></div>
                                    <div style="font-size: 12px; color: var(--gh-text-secondary);">ID: #<?= $emp['id'] ?></div>
                                </td>
                                <td><?= htmlspecialchars($emp['cargo']) ?></td>
                                <td><?= htmlspecialchars($emp['email_company']) ?></td>
                                <td>
                                    <?php if ($emp['status'] === 'ativo' && $emp['pode_acessar_sistema']): ?>
                                        <span class="badge badge-success">
                                            <i class="fa-solid fa-check"></i> Ativo
                                        </span>
                                    <?php elseif ($emp['status'] === 'ativo'): ?>
                                        <span class="badge badge-warning">
                                            <i class="fa-solid fa-clock"></i> Sem Acesso
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">
                                            <i class="fa-solid fa-pause"></i> Inativo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <button class="btn btn-secondary" onclick="openPermissionsModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['nome'])) ?>')">
                                        <i class="fa-solid fa-shield"></i>
                                        Permissões
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Notificações -->
    <div class="tab-content" id="tab-notificacoes">
        <div class="config-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <i class="fa-solid fa-bell"></i>
                        Preferências de Notificações
                    </h3>
                    <p class="card-description">Configure como e quando deseja receber notificações</p>
                </div>
            </div>

            <div class="form-switch">
                <div class="switch-info">
                    <div class="switch-label">Notificações por Email</div>
                    <div class="switch-description">Receber alertas importantes por email</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="notif-email" checked>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="form-switch">
                <div class="switch-info">
                    <div class="switch-label">Alertas de Vendas</div>
                    <div class="switch-description">Notificar sobre novas vendas e pagamentos</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="notif-vendas" checked>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="form-switch">
                <div class="switch-info">
                    <div class="switch-label">Atualizações de Funcionários</div>
                    <div class="switch-description">Notificar sobre ações de funcionários</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="notif-funcionarios" checked>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="form-switch">
                <div class="switch-info">
                    <div class="switch-label">Relatórios Semanais</div>
                    <div class="switch-description">Receber resumo semanal de atividades</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="notif-relatorios">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                <button class="btn btn-primary" onclick="salvarNotificacoes()">
                    <i class="fa-solid fa-save"></i>
                    Salvar Preferências
                </button>
            </div>
        </div>
    </div>

    <!-- Tab: Segurança -->
    <div class="tab-content" id="tab-seguranca">
        <div class="config-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <i class="fa-solid fa-lock"></i>
                        Segurança da Conta
                    </h3>
                    <p class="card-description">Proteja sua conta e dados sensíveis</p>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="fa-solid fa-info-circle"></i>
                <span>Última alteração de senha: <?= date('d/m/Y H:i', strtotime($company['updated_at'])) ?></span>
            </div>

            <form id="formSenha">
                <div class="form-group">
                    <label class="form-label">Senha Atual *</label>
                    <input type="password" class="form-input" id="senhaAtual" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nova Senha *</label>
                        <input type="password" class="form-input" id="senhaNova" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirmar Nova Senha *</label>
                        <input type="password" class="form-input" id="senhaConfirm" required>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-key"></i>
                        Alterar Senha
                    </button>
                </div>
            </form>
        </div>

        <div class="config-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <i class="fa-solid fa-shield-halved"></i>
                        Sessões Ativas
                    </h3>
                    <p class="card-description">Gerencie dispositivos conectados à sua conta</p>
                </div>
            </div>

            <div style="padding: 40px 20px; text-align: center; color: var(--gh-text-secondary);">
                <i class="fa-solid fa-desktop" style="font-size: 48px; opacity: 0.3; margin-bottom: 12px;"></i>
                <p>Apenas este dispositivo está conectado</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Permissões -->
<div class="modal-overlay" id="permissionsModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">
                <i class="fa-solid fa-user-shield"></i>
                Gerenciar Permissões
            </h3>
            <button class="modal-close" onclick="closePermissionsModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="alert alert-info">
                <i class="fa-solid fa-info-circle"></i>
                <span>Configure o que este funcionário pode visualizar e fazer no sistema</span>
            </div>

            <input type="hidden" id="permissionEmployeeId">
            
            <div class="permissions-grid" id="permissionsGrid">
                <!-- Será preenchido via JavaScript -->
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closePermissionsModal()">
                <i class="fa-solid fa-times"></i>
                Cancelar
            </button>
            <button class="btn btn-primary" onclick="savePermissions()">
                <i class="fa-solid fa-save"></i>
                Salvar Permissões
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const userId = <?= $userId ?>;
    const availableModules = <?= json_encode($available_modules, JSON_UNESCAPED_UNICODE) ?>;

    // Switch Tabs
    window.switchTab = function(tabName) {
        // Remove active from all tabs
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

        // Add active to selected tab
        event.target.closest('.tab-btn').classList.add('active');
        document.getElementById(`tab-${tabName}`).classList.add('active');
    };

    // Form Empresa
    const formEmpresa = document.getElementById('formEmpresa');
    if (formEmpresa) {
        formEmpresa.addEventListener('submit', async (e) => {
            e.preventDefault();

            const data = {
                nome: document.getElementById('empresaNome').value,
                email: document.getElementById('empresaEmail').value,
                telefone: document.getElementById('empresaTelefone').value,
                description: document.getElementById('empresaDescricao').value
            };

            try {
                const response = await fetch('modules/configuracoes/actions/atualizar_empresa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);

                if (result.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                showAlert('error', 'Erro ao salvar: ' + error.message);
            }
        });
    }

    window.resetFormEmpresa = function() {
        formEmpresa.reset();
    };

    // Form Senha
    const formSenha = document.getElementById('formSenha');
    if (formSenha) {
        formSenha.addEventListener('submit', async (e) => {
            e.preventDefault();

            const senhaAtual = document.getElementById('senhaAtual').value;
            const senhaNova = document.getElementById('senhaNova').value;
            const senhaConfirm = document.getElementById('senhaConfirm').value;

            if (senhaNova !== senhaConfirm) {
                showAlert('error', 'As senhas não coincidem');
                return;
            }

            if (senhaNova.length < 8) {
                showAlert('error', 'A senha deve ter no mínimo 8 caracteres');
                return;
            }

            try {
                const response = await fetch('modules/configuracoes/actions/alterar_senha.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        senha_atual: senhaAtual,
                        senha_nova: senhaNova
                    })
                });

                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);

                if (result.success) {
                    formSenha.reset();
                }
            } catch (error) {
                showAlert('error', 'Erro ao alterar senha: ' + error.message);
            }
        });
    }

    // Notificações
    window.salvarNotificacoes = async function() {
        const config = {
            email: document.getElementById('notif-email').checked,
            vendas: document.getElementById('notif-vendas').checked,
            funcionarios: document.getElementById('notif-funcionarios').checked,
            relatorios: document.getElementById('notif-relatorios').checked
        };

        try {
            const response = await fetch('modules/configuracoes/actions/salvar_notificacoes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(config)
            });

            const result = await response.json();
            showAlert(result.success ? 'success' : 'error', result.message);
        } catch (error) {
            showAlert('error', 'Erro ao salvar: ' + error.message);
        }
    };

    // Permissões Modal
    window.openPermissionsModal = async function(employeeId, employeeName) {
        document.getElementById('permissionEmployeeId').value = employeeId;
        document.getElementById('modalTitle').innerHTML = `
            <i class="fa-solid fa-user-shield"></i>
            Permissões: ${employeeName}
        `;

        try {
            const response = await fetch(`modules/configuracoes/actions/buscar_permissoes.php?employee_id=${employeeId}`);
            const data = await response.json();

            if (data.success) {
                renderPermissionsGrid(data.permissions);
                document.getElementById('permissionsModal').classList.add('active');
            } else {
                showAlert('error', 'Erro ao carregar permissões');
            }
        } catch (error) {
            showAlert('error', 'Erro: ' + error.message);
        }
    };

    function renderPermissionsGrid(currentPermissions) {
        const grid = document.getElementById('permissionsGrid');
        grid.innerHTML = '';

        Object.keys(availableModules).forEach(moduleKey => {
            const module = availableModules[moduleKey];
            const current = currentPermissions[moduleKey] || {};

            const moduleDiv = document.createElement('div');
            moduleDiv.className = 'module-permission';
            moduleDiv.innerHTML = `
                <div class="module-header">
                    <div class="module-info">
                        <div class="module-icon">
                            <i class="fa-solid ${module.icon}"></i>
                        </div>
                        <div class="module-details">
                            <h4>${module.name}</h4>
                            <p>${module.description}</p>
                        </div>
                    </div>
                </div>
                <div class="permissions-checkboxes">
                    ${module.permissions.map(perm => {
                        const permName = perm.replace('can_', '');
                        const labels = {
                            view: 'Visualizar',
                            create: 'Criar',
                            edit: 'Editar',
                            delete: 'Deletar'
                        };
                        const isChecked = current[perm] == 1 ? 'checked' : '';
                        return `
                            <div class="permission-check">
                                <input type="checkbox" 
                                    id="perm_${moduleKey}_${perm}" 
                                    data-module="${moduleKey}" 
                                    data-permission="${perm}"
                                    ${isChecked}>
                                <label for="perm_${moduleKey}_${perm}">${labels[permName] || permName}</label>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
            grid.appendChild(moduleDiv);
        });
    }

    window.closePermissionsModal = function() {
        document.getElementById('permissionsModal').classList.remove('active');
    };

    window.savePermissions = async function() {
        const employeeId = document.getElementById('permissionEmployeeId').value;
        const permissions = {};

        document.querySelectorAll('#permissionsGrid input[type="checkbox"]').forEach(checkbox => {
            const module = checkbox.dataset.module;
            const permission = checkbox.dataset.permission;
            
            if (!permissions[module]) {
                permissions[module] = {};
            }
            permissions[module][permission] = checkbox.checked ? 1 : 0;
        });

        try {
            const response = await fetch('modules/configuracoes/actions/salvar_permissoes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    employee_id: employeeId,
                    permissions: permissions
                })
            });

            const result = await response.json();
            showAlert(result.success ? 'success' : 'error', result.message);

            if (result.success) {
                closePermissionsModal();
            }
        } catch (error) {
            showAlert('error', 'Erro ao salvar: ' + error.message);
        }
    };

    // Alertas
    function showAlert(type, message) {
        const container = document.getElementById('alert-container');
        if (!container) return;

        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <i class="fa-solid fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation' : 'info'}-circle"></i>
            <span>${message}</span>
        `;

        container.appendChild(alert);

        setTimeout(() => {
            alert.style.animation = 'slideIn 0.2s ease reverse';
            setTimeout(() => alert.remove(), 200);
        }, 3000);
    }

    // Fechar modal clicando fora
    document.getElementById('permissionsModal').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) {
            closePermissionsModal();
        }
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closePermissionsModal();
        }
    });

    console.log('✅ Módulo de Configurações carregado - User ID:', userId);
})();
</script>