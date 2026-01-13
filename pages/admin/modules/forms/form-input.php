<?php
if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= CARREGAR CONFIGURAÇÕES DO BANCO ================= */
function getConfig($mysqli, $key, $default = null) {
    $result = $mysqli->query("SELECT config_value, config_type FROM form_config WHERE config_key = '$key'");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['config_type'] === 'boolean' ? (bool)$row['config_value'] : $row['config_value'];
    }
    return $default;
}

$config = [
    'require_tax_id' => getConfig($mysqli, 'require_tax_id', true),
    'require_license' => getConfig($mysqli, 'require_license', true),
    'allow_manual_creation' => getConfig($mysqli, 'allow_manual_creation', true),
    'validate_nif_format' => getConfig($mysqli, 'validate_nif_format', false),
    'tax_id_min_length' => getConfig($mysqli, 'tax_id_min_length', 9),
    'tax_id_max_length' => getConfig($mysqli, 'tax_id_max_length', 14),
    'auto_approve' => getConfig($mysqli, 'auto_approve', false),
    'notify_on_create' => getConfig($mysqli, 'notify_on_create', true)
];

// Se configuração desabilita criação manual
if (!$config['allow_manual_creation'] && !$isSuperAdmin) {
    $_SESSION['error_msg'] = 'Criação manual de empresas está desabilitada.';
    header('Location: ?page=forms');
    exit;
}

/* ================= PROCESSAR AÇÕES ================= */
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        try {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $taxId = trim($_POST['tax_id'] ?? '');
            $status = $_POST['status_documentos'] ?? 'pendente';
            $motivo = trim($_POST['motivo_rejeicao'] ?? '');
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
            
            // Validações baseadas em config
            if (empty($nome) || empty($email)) {
                throw new Exception('Nome e email são obrigatórios');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
            
            if ($config['require_tax_id'] && empty($taxId)) {
                throw new Exception('NIF é obrigatório segundo as configurações');
            }
            
            if ($config['validate_nif_format'] && !empty($taxId)) {
                if (strlen($taxId) < $config['tax_id_min_length'] || strlen($taxId) > $config['tax_id_max_length']) {
                    throw new Exception("NIF deve ter entre {$config['tax_id_min_length']} e {$config['tax_id_max_length']} dígitos");
                }
            }
            
            // Upload de licença
            $licensePath = '';
            if ($userId) {
                $existingData = $mysqli->query("SELECT license_path FROM businesses WHERE user_id = $userId")->fetch_assoc();
                $licensePath = $existingData['license_path'] ?? '';
            }
            
            if (isset($_FILES['license']) && $_FILES['license']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../../../../registration/uploads/licenses/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['license']['name']));
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['license']['tmp_name'], $targetPath)) {
                    $licensePath = 'uploads/licenses/' . $fileName;
                }
            }
            
            if ($config['require_license'] && empty($licensePath)) {
                throw new Exception('Licença comercial é obrigatória segundo as configurações');
            }
            
            // Escapar dados
            $nomeEsc = $mysqli->real_escape_string($nome);
            $emailEsc = $mysqli->real_escape_string($email);
            $taxIdEsc = $mysqli->real_escape_string($taxId);
            $licenseEsc = $mysqli->real_escape_string($licensePath);
            $motivoEsc = $mysqli->real_escape_string($motivo);
            
            if ($userId) {
                // ATUALIZAR
                $mysqli->query("UPDATE users SET nome = '$nomeEsc', email = '$emailEsc' WHERE id = $userId");
                
                if (!empty($password)) {
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $hashedPassEsc = $mysqli->real_escape_string($hashedPass);
                    $mysqli->query("UPDATE users SET password = '$hashedPassEsc' WHERE id = $userId");
                }
                
                $checkBiz = $mysqli->query("SELECT user_id FROM businesses WHERE user_id = $userId");
                if ($checkBiz->num_rows > 0) {
                    $mysqli->query("UPDATE businesses SET tax_id = '$taxIdEsc', license_path = '$licenseEsc', status_documentos = '$status', motivo_rejeicao = '$motivoEsc', updated_at = NOW() WHERE user_id = $userId");
                } else {
                    $mysqli->query("INSERT INTO businesses (user_id, tax_id, license_path, status_documentos, motivo_rejeicao, updated_at) VALUES ($userId, '$taxIdEsc', '$licenseEsc', '$status', '$motivoEsc', NOW())");
                }
                
                $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ($adminId, 'EDITOU_EMPRESA_$userId', '{$_SERVER['REMOTE_ADDR']}')");
                $_SESSION['success_msg'] = 'Empresa atualizada com sucesso!';
                
            } else {
                // CRIAR NOVA
                $checkEmail = $mysqli->query("SELECT id FROM users WHERE email = '$emailEsc'");
                if ($checkEmail->num_rows > 0) {
                    throw new Exception('Email já cadastrado');
                }
                
                $hashedPass = password_hash($password ?: 'Mudar@123', PASSWORD_DEFAULT);
                $hashedPassEsc = $mysqli->real_escape_string($hashedPass);
                
                $mysqli->query("INSERT INTO users (nome, email, password, type, role, created_at) VALUES ('$nomeEsc', '$emailEsc', '$hashedPassEsc', 'company', 'user', NOW())");
                $newUserId = $mysqli->insert_id;
                
                // Auto aprovar se configurado
                $autoStatus = $config['auto_approve'] ? 'aprovado' : $status;
                
                $mysqli->query("INSERT INTO businesses (user_id, tax_id, license_path, status_documentos, motivo_rejeicao, updated_at) VALUES ($newUserId, '$taxIdEsc', '$licenseEsc', '$autoStatus', '$motivoEsc', NOW())");
                
                // Notificar se configurado
                if ($config['notify_on_create']) {
                    $mysqli->query("INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status) VALUES ($adminId, $newUserId, 'alert', 'high', 'Conta Criada', 'Sua conta foi criada por um administrador. Bem-vindo!', 'unread')");
                }
                
                $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ($adminId, 'CRIOU_EMPRESA_$newUserId', '{$_SERVER['REMOTE_ADDR']}')");
                $_SESSION['success_msg'] = 'Empresa criada com sucesso!';
            }
            
            header('Location: ?reload=1');
            exit;
            
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }
    
    // Deletar empresa
    if ($action === 'delete' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        
        $mysqli->query("DELETE FROM businesses WHERE user_id = $userId");
        $mysqli->query("DELETE FROM notifications WHERE sender_id = $userId OR receiver_id = $userId");
        $mysqli->query("DELETE FROM users WHERE id = $userId");
        
        $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ($adminId, 'DELETOU_EMPRESA_$userId', '{$_SERVER['REMOTE_ADDR']}')");
        $_SESSION['success_msg'] = 'Empresa deletada.';
        header('Location: ?reload=1');
        exit;
    }
}

/* ================= BUSCAR DADOS ================= */
// Para edição
$editData = null;
if ($editId) {
    $query = $mysqli->query("SELECT u.*, b.tax_id, b.license_path, b.status_documentos, b.motivo_rejeicao FROM users u LEFT JOIN businesses b ON u.id = b.user_id WHERE u.id = $editId AND u.type = 'company'");
    $editData = $query->fetch_assoc();
}

// Lista de empresas (com filtro)
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'todos';

$whereClause = "WHERE u.type = 'company'";
if ($statusFilter !== 'todos') {
    $whereClause .= " AND b.status_documentos = '$statusFilter'";
}
if (!empty($searchTerm)) {
    $searchEsc = $mysqli->real_escape_string($searchTerm);
    $whereClause .= " AND (u.nome LIKE '%$searchEsc%' OR u.email LIKE '%$searchEsc%' OR b.tax_id LIKE '%$searchEsc%')";
}

$companies = $mysqli->query("
    SELECT u.id, u.nome, u.email, u.created_at, 
           b.tax_id, b.status_documentos, b.license_path
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    $whereClause
    ORDER BY u.created_at DESC
    LIMIT 50
");
?>

<style>
:root {
    --bg-main: #0a0f0a;
    --bg-card: #12151a;
    --bg-input: #1a1f24;
    --text-primary: #e8e9ea;
    --text-secondary: #9ca3af;
    --text-muted: #6b7280;
    --accent: #10b981;
    --accent-hover: #059669;
    --border: rgba(255, 255, 255, 0.06);
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.form-input-container {
    padding: 24px;
    min-height: 100vh;
    background: var(--bg-main);
}

/* ========== HEADER ========== */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.header-left h1 {
    font-size: 1.875rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.header-left p {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.header-actions {
    display: flex;
    gap: 12px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.btn-primary {
    background: var(--accent);
    color: #fff;
}

.btn-primary:hover {
    background: var(--accent-hover);
}

.btn-secondary {
    background: var(--bg-card);
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--bg-input);
    color: var(--text-primary);
}

/* ========== LAYOUT ========== */
.content-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 24px;
}

.main-column {
    min-width: 0;
}

.side-column {
    position: sticky;
    top: 24px;
    height: fit-content;
}

/* ========== FORMULÁRIO ========== */
.form-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}

.form-card.collapsed {
    padding: 16px 24px;
}

.form-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.form-card-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
}

.collapse-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 4px;
    transition: all 0.2s;
}

.collapse-btn:hover {
    color: var(--text-primary);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.form-grid.full {
    grid-template-columns: 1fr;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-label {
    font-size: 0.813rem;
    font-weight: 500;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-label .required {
    color: var(--error);
}

.form-label .optional {
    color: var(--text-muted);
    font-weight: 400;
    text-transform: none;
}

.form-input,
.form-select,
.form-textarea {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 10px 12px;
    font-size: 0.875rem;
    color: var(--text-primary);
    outline: none;
    transition: all 0.2s;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    border-color: var(--accent);
}

.form-input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

.form-help {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* File Upload */
.file-upload-zone {
    border: 2px dashed var(--border);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.file-upload-zone:hover {
    border-color: var(--accent);
    background: var(--bg-input);
}

.file-upload-zone input {
    display: none;
}

.file-icon {
    font-size: 2rem;
    color: var(--text-muted);
    margin-bottom: 8px;
}

.file-text {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.current-file {
    margin-top: 12px;
    padding: 10px;
    background: var(--bg-input);
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
}

.current-file a {
    color: var(--accent);
    text-decoration: none;
}

/* ========== LISTA DE EMPRESAS ========== */
.companies-list {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.list-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.list-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.search-box {
    display: flex;
    gap: 8px;
}

.search-input {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.813rem;
    color: var(--text-primary);
    outline: none;
    width: 200px;
}

.filter-select {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.813rem;
    color: var(--text-primary);
    outline: none;
}

.company-item {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    transition: all 0.2s;
    cursor: pointer;
}

.company-item:hover {
    background: var(--bg-input);
}

.company-item:last-child {
    border-bottom: none;
}

.company-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 8px;
}

.company-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.company-email {
    color: var(--text-secondary);
    font-size: 0.75rem;
}

.company-meta {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-top: 8px;
    flex-wrap: wrap;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.688rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pendente {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-aprovado {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-rejeitado {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.company-actions {
    display: flex;
    gap: 6px;
}

.action-btn {
    padding: 6px 10px;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 4px;
    color: var(--text-secondary);
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}

.action-btn:hover {
    background: var(--bg-input);
    color: var(--text-primary);
}

.action-btn.edit:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.action-btn.delete:hover {
    border-color: var(--error);
    color: var(--error);
}

/* ========== CONFIG INFO ========== */
.config-info {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
}

.config-title {
    font-size: 0.938rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 16px;
}

.config-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}

.config-item:last-child {
    border-bottom: none;
}

.config-label {
    font-size: 0.813rem;
    color: var(--text-secondary);
}

.config-value {
    font-size: 0.813rem;
    font-weight: 500;
    color: var(--text-primary);
}

.config-value.enabled {
    color: var(--success);
}

.config-value.disabled {
    color: var(--text-muted);
}

/* ========== ALERTS ========== */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 12px;
    opacity: 0.3;
}

@media (max-width: 1024px) {
    .content-layout {
        grid-template-columns: 1fr;
    }
    
    .side-column {
        position: relative;
        top: 0;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="form-input-container">
    <!-- HEADER -->
    <div class="page-header">
        <div class="header-left">
            <h1><?= $editId ? '✏️ Editar Empresa' : '📝 Gestão de Empresas' ?></h1>
            <p><?= $editId ? 'Atualize os dados da empresa' : 'Crie e gerencie empresas cadastradas' ?></p>
        </div>
        <div class="header-actions">
            <?php if ($editId): ?>
                <a href="javascript:loadContent('modules/dashboard/form-input')" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </a>
            <?php else: ?>
                <a href="javascript:loadContent('modules/dashboard/form-config')" class="btn btn-secondary">
                    <i class="fa-solid fa-gear"></i> Configurações
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <?= $_SESSION['success_msg'] ?>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (isset($errorMsg)): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <div class="content-layout">
        <!-- COLUNA PRINCIPAL -->
        <div class="main-column">
            <?php if ($action === 'create' || $editId): ?>
                <!-- FORMULÁRIO -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <?php if ($editId): ?>
                        <input type="hidden" name="user_id" value="<?= $editId ?>">
                    <?php endif; ?>

                    <!-- INFORMAÇÕES BÁSICAS -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <h3 class="form-card-title">Informações Básicas</h3>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Nome da Empresa <span class="required">*</span></label>
                                <input type="text" name="nome" class="form-input" 
                                       value="<?= htmlspecialchars($editData['nome'] ?? '') ?>" 
                                       required placeholder="Ex: VisionGreen Lda">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email <span class="required">*</span></label>
                                <input type="email" name="email" class="form-input" 
                                       value="<?= htmlspecialchars($editData['email'] ?? '') ?>" 
                                       required placeholder="empresa@exemplo.com">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    Senha 
                                    <?= $editId ? '<span class="optional">(opcional)</span>' : '<span class="required">*</span>' ?>
                                </label>
                                <input type="password" name="password" class="form-input" 
                                       <?= $editId ? '' : 'required' ?>
                                       placeholder="<?= $editId ? 'Deixe vazio para manter' : 'Mínimo 8 caracteres' ?>">
                                <small class="form-help">
                                    <?= $editId ? 'Preencha apenas para alterar' : 'Senha padrão será gerada se vazio' ?>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    NIF / Tax ID 
                                    <?= $config['require_tax_id'] ? '<span class="required">*</span>' : '<span class="optional">(opcional)</span>' ?>
                                </label>
                                <input type="text" name="tax_id" class="form-input" 
                                       value="<?= htmlspecialchars($editData['tax_id'] ?? '') ?>" 
                                       <?= $config['require_tax_id'] ? 'required' : '' ?>
                                       <?= !$config['require_tax_id'] ? 'disabled' : '' ?>
                                       placeholder="<?= $config['validate_nif_format'] ? "{$config['tax_id_min_length']}-{$config['tax_id_max_length']} dígitos" : 'Ex: 123456789' ?>">
                                <?php if (!$config['require_tax_id']): ?>
                                    <small class="form-help">Campo desabilitado nas configurações</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- DOCUMENTOS -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <h3 class="form-card-title">Documentos</h3>
                        </div>
                        
                        <div class="form-grid full">
                            <div class="form-group">
                                <label class="form-label">
                                    Licença Comercial 
                                    <?= $config['require_license'] ? '<span class="required">*</span>' : '<span class="optional">(opcional)</span>' ?>
                                </label>
                                
                                <?php if ($config['require_license']): ?>
                                    <label class="file-upload-zone" for="licenseFile">
                                        <input type="file" name="license" id="licenseFile" accept=".pdf,.jpg,.jpeg,.png">
                                        <div class="file-icon">
                                            <i class="fa-solid fa-cloud-arrow-up"></i>
                                        </div>
                                        <div class="file-text">
                                            Clique para escolher arquivo (PDF, JPG, PNG)
                                        </div>
                                    </label>
                                    
                                    <?php if ($editId && !empty($editData['license_path'])): ?>
                                        <div class="current-file">
                                            <i class="fa-solid fa-file-pdf"></i>
                                            <span>Arquivo atual:</span>
                                            <a href="../../../../<?= htmlspecialchars($editData['license_path']) ?>" target="_blank">Ver documento</a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small class="form-help">Campo desabilitado nas configurações</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- STATUS -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <h3 class="form-card-title">Status</h3>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Status dos Documentos</label>
                                <select name="status_documentos" class="form-select" id="statusSelect" onchange="toggleMotivo()">
                                    <option value="pendente" <?= ($editData['status_documentos'] ?? 'pendente') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="aprovado" <?= ($editData['status_documentos'] ?? '') === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                                    <option value="rejeitado" <?= ($editData['status_documentos'] ?? '') === 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid full" id="motivoGroup" style="display: <?= ($editData['status_documentos'] ?? '') === 'rejeitado' ? 'block' : 'none' ?>;">
                            <div class="form-group">
                                <label class="form-label">Motivo da Rejeição</label>
                                <textarea name="motivo_rejeicao" class="form-textarea" placeholder="Descreva o motivo..."><?= htmlspecialchars($editData['motivo_rejeicao'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <?= $editId ? 'Salvar Alterações' : 'Criar Empresa' ?>
                        </button>
                        <a href="javascript:loadContent('modules/dashboard/form-input')" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            <?php else: ?>
                <!-- LISTA DE EMPRESAS -->
                <div class="companies-list">
                    <div class="list-header">
                        <h3 class="list-title">Empresas Cadastradas (<?= $companies->num_rows ?>)</h3>
                        <div class="search-box">
                            <form id="searchForm" style="display: flex; gap: 8px;">
                                <input type="text" name="search" id="searchInput" class="search-input" placeholder="Buscar..." value="<?= htmlspecialchars($searchTerm) ?>">
                                <select name="status" id="statusFilter" class="filter-select">
                                    <option value="todos">Todos</option>
                                    <option value="pendente" <?= $statusFilter === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                                    <option value="aprovado" <?= $statusFilter === 'aprovado' ? 'selected' : '' ?>>Aprovados</option>
                                    <option value="rejeitado" <?= $statusFilter === 'rejeitado' ? 'selected' : '' ?>>Rejeitados</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    
                    <?php if ($companies->num_rows > 0): ?>
                        <?php while ($comp = $companies->fetch_assoc()): ?>
                            <div class="company-item">
                                <div class="company-header">
                                    <div>
                                        <div class="company-name"><?= htmlspecialchars($comp['nome']) ?></div>
                                        <div class="company-email"><?= htmlspecialchars($comp['email']) ?></div>
                                    </div>
                                    <div class="company-actions">
                                        <button class="action-btn edit" onclick="loadContent('modules/dashboard/form-input?action=create&edit=<?= $comp['id'] ?>')">
                                            <i class="fa-solid fa-pen"></i> Editar
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Deletar esta empresa?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= $comp['id'] ?>">
                                            <button type="submit" class="action-btn delete">
                                                <i class="fa-solid fa-trash"></i> Deletar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="company-meta">
                                    <span class="status-badge status-<?= $comp['status_documentos'] ?>">
                                        <?= strtoupper($comp['status_documentos'] ?? 'pendente') ?>
                                    </span>
                                    <?php if (!empty($comp['tax_id'])): ?>
                                        <span class="meta-item">
                                            <i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($comp['tax_id']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="meta-item">
                                        <i class="fa-solid fa-calendar"></i> <?= date('d/m/Y', strtotime($comp['created_at'])) ?>
                                    </span>
                                    <?php if (!empty($comp['license_path'])): ?>
                                        <span class="meta-item">
                                            <i class="fa-solid fa-file-pdf"></i> Licença anexada
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-folder-open"></i>
                            <p>Nenhuma empresa encontrada</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- COLUNA LATERAL -->
        <div class="side-column">
            <!-- CONFIG INFO -->
            <div class="config-info">
                <h4 class="config-title">⚙️ Configurações Ativas</h4>
                
                <div class="config-item">
                    <span class="config-label">NIF Obrigatório</span>
                    <span class="config-value <?= $config['require_tax_id'] ? 'enabled' : 'disabled' ?>">
                        <?= $config['require_tax_id'] ? 'Sim' : 'Não' ?>
                    </span>
                </div>
                
                <div class="config-item">
                    <span class="config-label">Licença Obrigatória</span>
                    <span class="config-value <?= $config['require_license'] ? 'enabled' : 'disabled' ?>">
                        <?= $config['require_license'] ? 'Sim' : 'Não' ?>
                    </span>
                </div>
                
                <div class="config-item">
                    <span class="config-label">Validar Formato NIF</span>
                    <span class="config-value <?= $config['validate_nif_format'] ? 'enabled' : 'disabled' ?>">
                        <?= $config['validate_nif_format'] ? 'Sim' : 'Não' ?>
                    </span>
                </div>
                
                <div class="config-item">
                    <span class="config-label">Auto Aprovar</span>
                    <span class="config-value <?= $config['auto_approve'] ? 'enabled' : 'disabled' ?>">
                        <?= $config['auto_approve'] ? 'Sim' : 'Não' ?>
                    </span>
                </div>
                
                <div class="config-item">
                    <span class="config-label">Notificar ao Criar</span>
                    <span class="config-value <?= $config['notify_on_create'] ? 'enabled' : 'disabled' ?>">
                        <?= $config['notify_on_create'] ? 'Sim' : 'Não' ?>
                    </span>
                </div>
                
                <a href="javascript:loadContent('modules/dashboard/form-config')" class="btn btn-secondary" style="width: 100%; margin-top: 16px; justify-content: center;">
                    <i class="fa-solid fa-gear"></i> Editar Configurações
                </a>
            </div>

            <?php if (!$editId && !($action === 'create')): ?>
                <!-- AÇÕES RÁPIDAS -->
                <div class="config-info" style="margin-top: 16px;">
                    <h4 class="config-title">⚡ Ações Rápidas</h4>
                    <a href="javascript:loadContent('modules/dashboard/form-input?action=create')" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-plus"></i> Nova Empresa
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleMotivo() {
    const status = document.getElementById('statusSelect').value;
    const motivoGroup = document.getElementById('motivoGroup');
    motivoGroup.style.display = status === 'rejeitado' ? 'block' : 'none';
}

document.getElementById('licenseFile')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file && file.size > 5 * 1024 * 1024) {
        alert('Arquivo muito grande! Máximo 5MB.');
        e.target.value = '';
    }
});

// ========== BUSCA E FILTRO AJAX ==========
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');

if (searchInput && statusFilter) {
    let searchTimeout;
    
    // Busca com delay
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 500);
    });
    
    // Filtro imediato
    statusFilter.addEventListener('change', function() {
        applyFilters();
    });
    
    function applyFilters() {
        const search = searchInput.value;
        const status = statusFilter.value;
        
        console.log('🔍 Aplicando filtros:', { search, status });
        
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (status && status !== 'todos') params.append('status', status);
        
        const newUrl = 'modules/forms/form-input' + (params.toString() ? '?' + params.toString() : '');
        loadContent(newUrl);
    }
}

// ========== FORM CREATE/EDIT AJAX ==========
const mainForm = document.querySelector('form[method="POST"][enctype="multipart/form-data"]');

if (mainForm && !mainForm.dataset.listenerAttached) {
    mainForm.dataset.listenerAttached = 'true';
    
    mainForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        console.log('💾 Salvando empresa...');
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('modules/forms/form-input-save.php', {
                method: 'POST',
                body: formData
            });
            
            const contentType = response.headers.get('content-type');
            
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('❌ Resposta não é JSON:', text.substring(0, 500));
                throw new Error('Servidor não retornou JSON.');
            }
            
            const result = await response.json();
            console.log('✅ Resposta:', result);
            
            if (result.success) {
                showNotification('success', result.message);
                
                // Voltar para lista após 1.5s
                setTimeout(() => {
                    loadContent('modules/forms/form-input');
                }, 1500);
            } else {
                showNotification('error', result.message);
            }
            
        } catch (error) {
            console.error('❌ Erro:', error);
            showNotification('error', 'Erro: ' + error.message);
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
}

// ========== DELETE AJAX ==========
document.querySelectorAll('form[method="POST"]:not([enctype])').forEach(form => {
    if (form.querySelector('input[name="action"][value="delete"]') && !form.dataset.listenerAttached) {
        form.dataset.listenerAttached = 'true';
        
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!confirm('Deletar esta empresa?')) return;
            
            console.log('🗑️ Deletando empresa...');
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('modules/forms/form-input-save.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                console.log('✅ Resposta:', result);
                
                if (result.success) {
                    showNotification('success', result.message);
                    
                    // Recarregar lista após 1s
                    setTimeout(() => {
                        loadContent('modules/forms/form-input');
                    }, 1000);
                } else {
                    showNotification('error', result.message);
                }
                
            } catch (error) {
                console.error('❌ Erro:', error);
                showNotification('error', 'Erro ao deletar.');
            }
        });
    }
});

// ========== SISTEMA DE NOTIFICAÇÕES ==========
function showNotification(type, message) {
    console.log(`🔔 [${type}] ${message}`);
    
    const oldNotifications = document.querySelectorAll('.notification-toast');
    oldNotifications.forEach(notif => notif.remove());
    
    const notification = document.createElement('div');
    notification.className = 'notification-toast';
    
    const icon = type === 'success' 
        ? '<i class="fa-solid fa-circle-check"></i>' 
        : '<i class="fa-solid fa-triangle-exclamation"></i>';
    
    const bgColor = type === 'success' ? '#238636' : '#da3633';
    
    notification.innerHTML = `
        <div style="
            display: flex;
            align-items: center;
            gap: 12px;
            background: ${bgColor};
            color: #fff;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            font-size: 0.875rem;
            font-weight: 500;
            animation: slideInRight 0.3s ease;
        ">
            <div style="font-size: 1.25rem;">${icon}</div>
            <div style="flex: 1;">${message}</div>
            <button onclick="this.parentElement.parentElement.remove()" style="
                background: rgba(255,255,255,0.2);
                border: none;
                color: #fff;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 0.75rem;
            ">×</button>
        </div>
    `;
    
    notification.style.cssText = 'position: fixed; top: 24px; right: 24px; z-index: 10000; min-width: 300px; max-width: 500px;';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Adicionar animações CSS
if (!document.getElementById('notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes slideOutRight {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100px); }
        }
    `;
    document.head.appendChild(style);
}

console.log('✅ Form Input (Expanded + AJAX) loaded!');
</script>