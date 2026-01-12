<?php
    if (!defined('IS_ADMIN_PAGE')) {
        require_once '../../../../registration/includes/db.php';
        session_start();
    }

    // ATIVAR DEBUG (remover em produção)
    $DEBUG_MODE = true;
    if ($DEBUG_MODE) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }

    $adminId = $_SESSION['auth']['user_id'] ?? 0;
    $adminRole = $_SESSION['auth']['role'] ?? 'admin';
    $isSuperAdmin = ($adminRole === 'superadmin');

    if (!$isSuperAdmin) {
        die("Acesso negado. Apenas SuperAdmin pode acessar configurações.");
    }

    /* ================= HELPER FUNCTIONS ================= */
    function getConfig($mysqli, $key, $default = null) {
        $key = $mysqli->real_escape_string($key);
        $result = $mysqli->query("SELECT config_value, config_type FROM form_config WHERE config_key = '$key'");
        if ($result && $row = $result->fetch_assoc()) {
            if ($row['config_type'] === 'boolean') {
                return (bool)(int)$row['config_value'];
            } elseif ($row['config_type'] === 'integer') {
                return (int)$row['config_value'];
            }
            return $row['config_value'];
        }
        return $default;
    }

    function setConfig($mysqli, $key, $value, $adminId) {
        $key = $mysqli->real_escape_string($key);
        $value = $mysqli->real_escape_string($value);
        
        // USAR INSERT ... ON DUPLICATE KEY UPDATE (mais seguro)
        $query = "INSERT INTO form_config (config_key, config_value, updated_by) 
                VALUES ('$key', '$value', $adminId)
                ON DUPLICATE KEY UPDATE config_value = '$value', updated_by = $adminId";
        
        $result = $mysqli->query($query);
        
        if (!$result) {
            throw new Exception("Erro ao salvar config '$key': " . $mysqli->error);
        }
        
        return $result;
    }

    /* ================= CARREGAR CONFIGURAÇÕES ================= */
    $config = [
        'require_tax_id' => getConfig($mysqli, 'require_tax_id', true),
        'require_license' => getConfig($mysqli, 'require_license', true),
        'allow_manual_creation' => getConfig($mysqli, 'allow_manual_creation', true),
        'validate_nif_format' => getConfig($mysqli, 'validate_nif_format', false),
        'tax_id_min_length' => getConfig($mysqli, 'tax_id_min_length', 9),
        'tax_id_max_length' => getConfig($mysqli, 'tax_id_max_length', 14),
        'allow_duplicate_email' => getConfig($mysqli, 'allow_duplicate_email', false),
        'auto_approve' => getConfig($mysqli, 'auto_approve', false),
        'notify_on_create' => getConfig($mysqli, 'notify_on_create', true),
        'notify_on_approve' => getConfig($mysqli, 'notify_on_approve', true),
        'notify_on_reject' => getConfig($mysqli, 'notify_on_reject', true),
        'send_welcome_email' => getConfig($mysqli, 'send_welcome_email', true),
        'reminder_after_days' => getConfig($mysqli, 'reminder_after_days', 7),
        'create_in_platform_x' => getConfig($mysqli, 'create_in_platform_x', false),
        'add_to_crm' => getConfig($mysqli, 'add_to_crm', false),
        'generate_contract' => getConfig($mysqli, 'generate_contract', false),
        'setup_payment' => getConfig($mysqli, 'setup_payment', false)
    ];

    /* ================= PROCESSAR SAVE ================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
        
        if ($DEBUG_MODE) {
            error_log("POST recebido: " . print_r($_POST, true));
        }
        
        try {
            $mysqli->begin_transaction();
            
            // Salvar cada configuração
            setConfig($mysqli, 'require_tax_id', isset($_POST['require_tax_id']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'require_license', isset($_POST['require_license']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'allow_manual_creation', isset($_POST['allow_manual_creation']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'validate_nif_format', isset($_POST['validate_nif_format']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'tax_id_min_length', (string)(int)($_POST['tax_id_min_length'] ?? 9), $adminId);
            setConfig($mysqli, 'tax_id_max_length', (string)(int)($_POST['tax_id_max_length'] ?? 14), $adminId);
            setConfig($mysqli, 'allow_duplicate_email', isset($_POST['allow_duplicate_email']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'auto_approve', isset($_POST['auto_approve']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'notify_on_create', isset($_POST['notify_on_create']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'notify_on_approve', isset($_POST['notify_on_approve']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'notify_on_reject', isset($_POST['notify_on_reject']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'send_welcome_email', isset($_POST['send_welcome_email']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'reminder_after_days', (string)(int)($_POST['reminder_after_days'] ?? 7), $adminId);
            setConfig($mysqli, 'create_in_platform_x', isset($_POST['create_in_platform_x']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'add_to_crm', isset($_POST['add_to_crm']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'generate_contract', isset($_POST['generate_contract']) ? '1' : '0', $adminId);
            setConfig($mysqli, 'setup_payment', isset($_POST['setup_payment']) ? '1' : '0', $adminId);
            
            // Log de auditoria
            $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) 
                        VALUES ($adminId, 'ATUALIZOU_CONFIG_FORMULARIOS', '{$_SERVER['REMOTE_ADDR']}')");
            
            $mysqli->commit();
            $_SESSION['success_msg'] = 'Configurações salvas com sucesso!';
            
            // Recarregar configurações
            $config = [
                'require_tax_id' => getConfig($mysqli, 'require_tax_id', true),
                'require_license' => getConfig($mysqli, 'require_license', true),
                'allow_manual_creation' => getConfig($mysqli, 'allow_manual_creation', true),
                'validate_nif_format' => getConfig($mysqli, 'validate_nif_format', false),
                'tax_id_min_length' => getConfig($mysqli, 'tax_id_min_length', 9),
                'tax_id_max_length' => getConfig($mysqli, 'tax_id_max_length', 14),
                'allow_duplicate_email' => getConfig($mysqli, 'allow_duplicate_email', false),
                'auto_approve' => getConfig($mysqli, 'auto_approve', false),
                'notify_on_create' => getConfig($mysqli, 'notify_on_create', true),
                'notify_on_approve' => getConfig($mysqli, 'notify_on_approve', true),
                'notify_on_reject' => getConfig($mysqli, 'notify_on_reject', true),
                'send_welcome_email' => getConfig($mysqli, 'send_welcome_email', true),
                'reminder_after_days' => getConfig($mysqli, 'reminder_after_days', 7),
                'create_in_platform_x' => getConfig($mysqli, 'create_in_platform_x', false),
                'add_to_crm' => getConfig($mysqli, 'add_to_crm', false),
                'generate_contract' => getConfig($mysqli, 'generate_contract', false),
                'setup_payment' => getConfig($mysqli, 'setup_payment', false)
            ];
            
            if ($DEBUG_MODE) {
                error_log("Configurações salvas com sucesso!");
            }
            
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['error_msg'] = 'Erro ao salvar: ' . $e->getMessage();
            
            if ($DEBUG_MODE) {
                error_log("ERRO no save: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
            }
        }
    }

    /* ================= DEBUG INFO ================= */
    if ($DEBUG_MODE && isset($_GET['debug'])) {
        echo "<pre style='background:#000;color:#0f0;padding:20px;border-radius:10px;'>";
        echo "=== DEBUG INFO ===\n\n";
        echo "Admin ID: $adminId\n";
        echo "Admin Role: $adminRole\n";
        echo "Is SuperAdmin: " . ($isSuperAdmin ? 'YES' : 'NO') . "\n\n";
        
        echo "=== CONFIGURAÇÕES ATUAIS ===\n";
        print_r($config);
        
        echo "\n=== BANCO DE DADOS ===\n";
        $result = $mysqli->query("SELECT * FROM form_config ORDER BY config_key");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                echo "{$row['config_key']} = {$row['config_value']} ({$row['config_type']})\n";
            }
        } else {
            echo "ERRO: " . $mysqli->error . "\n";
        }
        
        echo "\n=== POST DATA ===\n";
        print_r($_POST);
        
        echo "</pre>";
        exit;
    }
?>

<style>
    :root {
        --bg-page: #0d1117;
        --bg-card: #161b22;
        --bg-elevated: #21262d;
        --text-primary: #c9d1d9;
        --text-secondary: #8b949e;
        --text-muted: #6e7681;
        --accent: #238636;
        --accent-hover: #2ea043;
        --border: #30363d;
        --success: #238636;
        --warning: #9e6a03;
        --error: #da3633;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 0;
        background: var(--bg-page);
        color: var(--text-primary);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', Helvetica, Arial, sans-serif;
    }

    .config-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 32px 24px;
    }

    /* ========== HEADER ========== */
    .page-header {
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--border);
    }

    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .header-title {
        font-size: 2rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .header-subtitle {
        color: var(--text-secondary);
        font-size: 0.938rem;
        line-height: 1.5;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid transparent;
        text-decoration: none;
        transition: all 0.15s;
    }

    .btn-primary {
        background: var(--accent);
        color: #fff;
        border-color: var(--accent);
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .btn-secondary {
        background: transparent;
        color: var(--text-secondary);
        border-color: var(--border);
    }

    .btn-secondary:hover {
        background: var(--bg-elevated);
        color: var(--text-primary);
    }

    /* ========== ALERT ========== */
    .alert {
        padding: 16px;
        border-radius: 6px;
        margin-bottom: 24px;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid;
    }

    .alert-success {
        background: rgba(35, 134, 54, 0.15);
        border-color: var(--success);
        color: #7ee787;
    }

    .alert-error {
        background: rgba(218, 54, 51, 0.15);
        border-color: var(--error);
        color: #ff7b72;
    }

    .alert-warning {
        background: rgba(158, 106, 3, 0.15);
        border-color: var(--warning);
        color: #f0c065;
    }

    /* ========== INFO BANNER ========== */
    .info-banner {
        background: rgba(56, 139, 253, 0.15);
        border: 1px solid #388bfd;
        border-radius: 6px;
        padding: 16px;
        margin-bottom: 32px;
        display: flex;
        gap: 16px;
    }

    .info-icon {
        color: #58a6ff;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .info-content {
        flex: 1;
    }

    .info-title {
        font-size: 0.938rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }

    .info-text {
        font-size: 0.875rem;
        color: var(--text-secondary);
        line-height: 1.5;
        margin: 0;
    }

    /* ========== DEBUG LINK ========== */
    .debug-link {
        position: fixed;
        bottom: 90px;
        right: 24px;
        background: rgba(218, 54, 51, 0.2);
        border: 1px solid var(--error);
        color: #ff7b72;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.75rem;
        text-decoration: none;
        z-index: 99;
    }

    .debug-link:hover {
        background: rgba(218, 54, 51, 0.3);
    }

    /* ========== CONFIG GRID ========== */
    .config-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
        gap: 24px;
        margin-bottom: 80px;
    }

    .config-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 24px;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border);
    }

    .section-icon {
        width: 32px;
        height: 32px;
        background: var(--accent);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: #fff;
    }

    .section-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    /* ========== CONFIG ITEMS ========== */
    .config-item {
        display: flex;
        justify-content: space-between;
        align-items: start;
        padding: 16px 0;
        border-bottom: 1px solid var(--border);
    }

    .config-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .config-label {
        flex: 1;
        padding-right: 16px;
    }

    .config-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }

    .config-desc {
        font-size: 0.813rem;
        color: var(--text-secondary);
        line-height: 1.4;
        margin: 0;
    }

    .config-control {
        flex-shrink: 0;
    }

    /* ========== TOGGLE SWITCH ========== */
    .toggle {
        position: relative;
        width: 48px;
        height: 28px;
        cursor: pointer;
        display: inline-block;
    }

    .toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #484f58;
        border-radius: 28px;
        transition: 0.3s;
    }

    .toggle-slider::before {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        left: 4px;
        top: 4px;
        background: #fff;
        border-radius: 50%;
        transition: 0.3s;
    }

    .toggle input:checked + .toggle-slider {
        background: var(--accent);
    }

    .toggle input:checked + .toggle-slider::before {
        transform: translateX(20px);
    }

    /* ========== NUMBER INPUT ========== */
    .number-input {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .number-field {
        width: 70px;
        background: var(--bg-page);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 8px 12px;
        text-align: center;
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .number-field:focus {
        outline: none;
        border-color: var(--accent);
    }

    .number-unit {
        font-size: 0.813rem;
        color: var(--text-muted);
    }

    /* ========== ACTION CARDS ========== */
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 16px;
    }

    .action-card {
        background: var(--bg-elevated);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 16px;
        transition: all 0.2s;
    }

    .action-card:hover {
        border-color: var(--accent);
    }

    .action-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .action-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .action-desc {
        font-size: 0.813rem;
        color: var(--text-secondary);
        line-height: 1.4;
    }

    /* ========== SAVE BUTTON ========== */
    .save-float {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 100;
        display: flex;
        gap: 12px;
    }

    .save-btn {
        background: var(--accent);
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 8px 24px rgba(35, 134, 54, 0.5);
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        font-size: 0.875rem;
    }

    .save-btn:hover {
        background: var(--accent-hover);
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(35, 134, 54, 0.6);
    }

    /* ========== DIVIDER ========== */
    .divider {
        border: none;
        border-top: 1px solid var(--border);
        margin: 16px 0;
    }

    /* ========== RESPONSIVE ========== */
    @media (max-width: 1024px) {
        .config-grid {
            grid-template-columns: 1fr;
        }
        
        .actions-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .config-container {
            padding: 16px;
        }
        
        .header-top {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        
        .save-float {
            left: 16px;
            right: 16px;
            bottom: 16px;
        }
        
        .save-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="config-container">
    <div class="page-header">
        <div class="header-top">
            <h1 class="header-title">⚙️ Configurações de Formulários</h1>
            <a href="javascript:loadContent('modules/forms/form-input')" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
        <p class="header-subtitle">Configure validações, campos obrigatórios e ações automáticas que afetam o formulário de entrada de dados</p>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= $_SESSION['success_msg'] ?></span>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= $_SESSION['error_msg'] ?></span>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <?php if ($DEBUG_MODE): ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-bug"></i>
            <span>Modo DEBUG ativado. <a href="?debug=1" style="color:#f0c065;text-decoration:underline;">Ver detalhes</a></span>
        </div>
    <?php endif; ?>

    <div class="info-banner">
        <div class="info-icon">
            <i class="fa-solid fa-circle-info"></i>
        </div>
        <div class="info-content">
            <h3 class="info-title">🔗 Integração com Formulário de Entrada</h3>
            <p class="info-text">
                Todas as configurações aqui afetam imediatamente o form-input.php. 
                Campos desabilitados ficarão indisponíveis e validações serão aplicadas automaticamente.
            </p>
        </div>
    </div>

    <form method="POST" id="configForm" action="modules/forms/form-config.php">
        <input type="hidden" name="action" value="save_config">

        <div class="config-grid">
            <!-- CAMPOS OBRIGATÓRIOS -->
            <div class="config-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <h3 class="section-title">Campos Obrigatórios</h3>
                </div>

                <div class="config-item">
                    <div class="config-label">
                        <div class="config-name">Campo NIF / Tax ID</div>
                        <div class="config-desc">Se desativado, campo fica desabilitado no formulário</div>
                    </div>
                    <div class="config-control">
                        <label class="toggle">
                            <input type="checkbox" name="require_tax_id" <?= $config['require_tax_id'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-label">
                        <div class="config-name">Upload de Licença</div>
                        <div class="config-desc">Se desativado, upload fica indisponível</div>
                    </div>
                    <div class="config-control">
                        <label class="toggle">
                            <input type="checkbox" name="require_license" <?= $config['require_license'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <hr class="divider">

                <div class="config-item">
                    <div class="config-label">
                        <div class="config-name">🔒 Permitir Criação Manual</div>
                        <div class="config-desc">Se desativado, apenas SuperAdmin pode criar empresas</div>
                    </div>
                    <div class="config-control">
                        <label class="toggle">
                            <input type="checkbox" name="allow_manual_creation" <?= $config['allow_manual_creation'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- VALIDAÇÕES -->
            <div class="config-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h3 class="section-title">Regras de Validação</h3>
                </div>

                <div class="config-item">
                    <div class="config-label">
                        <div class="config-name">Validar Formato de NIF</div>
                        <div class="config-desc">Verifica comprimento mínimo/máximo</div>
                    </div>
                    <div class="config-control">
                        <label class="toggle">
                            <input type="checkbox" name="validate_nif_format" <?= $config['validate_nif_format'] ? 'checked' : '' ?> onchange="toggleNifLimits(this)">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div id="nifLimits" style="display: <?= $config['validate_nif_format'] ? 'block' : 'none' ?>;">
                    <div class="config-item">
                        <div class="config-label">
                            <div class="config-name">Comprimento Mínimo</div>
                            <div class="config-desc">Número mínimo de dígitos</div>
                        </div>
                        <div class="config-control">
                            <div class="number-input">
                                <input type="number" name="tax_id_min_length" class="number-field" value="<?= $config['tax_id_min_length'] ?>" min="1" max="20">
                                <span class="number-unit">dígitos</span>
                            </div>
                        </div>
                    </div>

                    <div class="config-item">
                        <div class="config-label">
                            <div class="config-name">Comprimento Máximo</div>
                            <div class="config-desc">Número máximo de dígitos</div>
                        </div>
                        <div class="config-control">
                            <div class="number-input">
                                <input type="number" name="tax_id_max_length" class="number-field" value="<?= $config['tax_id_max_length'] ?>" min="1" max="20">
                                <span class="number-unit">dígitos</span>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="divider">

                <div class="config-item">
                    <div class="config-label">
                        <div class="config-name">Permitir Email Duplicado</div>
                        <div class="config-desc">Múltiplas empresas com mesmo email</div>
                    </div>
                    <div class="config-control">
                        <label class="toggle">
                            <input type="checkbox" name="allow_duplicate_email" <?= $config['allow_duplicate_email'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- COMPORTAMENTO -->
            <div class="config-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <h3 class="section-title">Comportamento Automático</h3>
                </div>

                <div class="config-item">
                    <div class="config-label">
                        <div class="config-name">Auto Aprovar Novos</div>
                        <div class="config-desc">Aprovar automaticamente ao criar</div>
                    </div>
                    <div class="config-control">
                        <label class="toggle">
                            <input type="checkbox" name="auto_approve" <?= $config['auto_approve'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-label">
                        <div class="config-name">Notificar ao Criar</div>
                        <div class="config-desc">Enviar notificação ao criar empresa</div>
                    </div>
                    <div class="config-control">
                        <label class="toggle">
                            <input type="checkbox" name="notify_on_create" <?= $config['notify_on_create'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-label">
                        <div class="config-name">Notificar ao Aprovar</div>
                        <div class="config-desc">Enviar notificação de aprovação</div>
                    </div>
                    <div class="config-control">
                        <label class="toggle">
                            <input type="checkbox" name="notify_on_approve" <?= $config['notify_on_approve'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-label">
                        <div class="config-name">Notificar ao Rejeitar</div>
                        <div class="config-desc">Enviar notificação com motivo</div>
                    </div>
                    <div class="config-control">
                        <label class="toggle">
                            <input type="checkbox" name="notify_on_reject" <?= $config['notify_on_reject'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-label">
                        <div class="config-name">Email de Boas-Vindas</div>
                        <div class="config-desc">Enviar email automático ao registrar</div>
                    </div>
                    <div class="config-control">
                        <label class="toggle">
                            <input type="checkbox" name="send_welcome_email" <?= $config['send_welcome_email'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-label">
                        <div class="config-name">Lembrete de Documentos</div>
                        <div class="config-desc">Dias após registro sem docs</div>
                    </div>
                    <div class="config-control">
                        <div class="number-input">
                            <input type="number" name="reminder_after_days" class="number-field" value="<?= $config['reminder_after_days'] ?>" min="1" max="30">
                            <span class="number-unit">dias</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AÇÕES AUTOMÁTICAS -->
            <div class="config-section" style="grid-column: 1 / -1;">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                    <h3 class="section-title">Ações Automáticas ao Aprovar</h3>
                </div>

                <div class="actions-grid">
                    <div class="action-card">
                        <div class="action-header">
                            <div class="action-name">🌐 Criar na Plataforma X</div>
                            <label class="toggle">
                                <input type="checkbox" name="create_in_platform_x" <?= $config['create_in_platform_x'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="action-desc">
                            Criar conta automaticamente na Plataforma X via API
                        </div>
                    </div>

                    <div class="action-card">
                        <div class="action-header">
                            <div class="action-name">📊 Adicionar ao CRM</div>
                            <label class="toggle">
                                <input type="checkbox" name="add_to_crm" <?= $config['add_to_crm'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="action-desc">
                            Sincronizar com CRM (Salesforce, HubSpot)
                        </div>
                    </div>

                    <div class="action-card">
                        <div class="action-header">
                            <div class="action-name">📄 Gerar Contrato</div>
                            <label class="toggle">
                                <input type="checkbox" name="generate_contract" <?= $config['generate_contract'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="action-desc">
                            Gerar contrato em PDF automaticamente
                        </div>
                    </div>

                    <div class="action-card">
                        <div class="action-header">
                            <div class="action-name">💳 Configurar Pagamento</div>
                            <label class="toggle">
                                <input type="checkbox" name="setup_payment" <?= $config['setup_payment'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="action-desc">
                            Criar conta (Stripe, PayPal)
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="save-float">
            <button type="submit" class="save-btn">
                <i class="fa-solid fa-floppy-disk"></i>
                Salvar Configurações
            </button>
        </div>
    </form>

    <?php if ($DEBUG_MODE): ?>
        <a href="?debug=1" class="debug-link">
            <i class="fa-solid fa-bug"></i> Debug Info
        </a>
    <?php endif; ?>
</div>

<script>
function toggleNifLimits(checkbox) {
    const limits = document.getElementById('nifLimits');
    limits.style.display = checkbox.checked ? 'block' : 'none';
}

let formChanged = false;
document.getElementById('configForm').addEventListener('change', () => {
    formChanged = true;
});

window.addEventListener('beforeunload', (e) => {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// CORRIGIR ENVIO DO FORMULÁRIO
document.getElementById('configForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    formChanged = false;
    
    console.log('Salvando configurações...');
    
    const formData = new FormData(e.target);
    
    // Debug: mostrar o que está sendo enviado
    console.log('FormData a ser enviado:');
    for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
    }
    
    try {
        const response = await fetch('modules/forms/form-config.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        console.log('Resposta do servidor:', text);
        
        // Recarregar a página para mostrar o resultado
        loadContent('modules/forms/form-config');
        
    } catch (error) {
        console.error('Erro ao salvar:', error);
        alert('Erro ao salvar configurações. Verifique o console.');
    }
});

console.log('✅ Form Config (Debug Mode) loaded!');
</script>