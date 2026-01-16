<?php
/**
 * ================================================================================
 * VISIONGREEN - GESTÃO DE AUDITORES
 * Módulo: modules/auditor/auditores.php
 * Descrição: Painel de gestão de auditores (Superadmin only) - VERSÃO COMPLETA
 * Versão: 2.0
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

// ==================== VERIFICAÇÃO DE SEGURANÇA ====================
$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';

if ($adminRole !== 'superadmin') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Acesso negado. Apenas superadministradores podem acessar esta página.'
    ]);
    exit;
}

// ==================== FUNÇÃO DE VALIDAÇÃO CSRF ====================
function csrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ==================== PROCESSAR AÇÕES (POST) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['status' => 'error', 'message' => 'Ação inválida'];
    
    try {
        // ========== CRIAR NOVO AUDITOR ==========
        if ($action === 'criar' && isset($_POST['nome'], $_POST['email'])) {
            $nome = trim($_POST['nome']);
            $email = trim($_POST['email']);
            
            // Validação
            if (empty($nome) || empty($email)) {
                throw new Exception('Nome e email são obrigatórios.');
            }
            
            if (strlen($nome) < 3) {
                throw new Exception('Nome deve ter no mínimo 3 caracteres.');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido.');
            }
            
            // Verificar duplicação de email
            $checkStmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                throw new Exception('Este email já está registrado no sistema.');
            }
            $checkStmt->close();
            
            // Gerar credenciais
            $tempPassword = bin2hex(random_bytes(8)); // 16 caracteres hexadecimais
            $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);
            
            // Gerar Public ID único (8 dígitos + A)
            $publicId = str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT) . 'A';
            
            // Gerar Secure ID (V0000-S0000-G0000)
            $n1 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $n2 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $n3 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $secureId = "V{$n1}-S{$n2}-G{$n3}";
            $secureIdHash = password_hash($secureId, PASSWORD_BCRYPT);
            
            // Email corporativo
            $apelido = strtolower(explode('@', $email)[0]);
            $emailCorporativo = $apelido . '.admin@vsg.com';
            
            // Inserir usuário
            $stmt = $mysqli->prepare("
                INSERT INTO users 
                (public_id, type, role, nome, email, email_corporativo, telefone, password_hash, secure_id_hash, status, email_verified_at, created_at, updated_at) 
                VALUES (?, 'admin', 'admin', ?, ?, ?, '00000000000', ?, ?, 'active', NOW(), NOW(), NOW())
            ");
            
            if (!$stmt) {
                throw new Exception('Erro ao preparar statement: ' . $mysqli->error);
            }
            
            $stmt->bind_param("sssss", $publicId, $nome, $email, $emailCorporativo, $passwordHash);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao criar auditor: ' . $stmt->error);
            }
            
            $newAuditorId = $mysqli->insert_id;
            $stmt->close();
            
            // Registrar auditoria
            $auditStmt = $mysqli->prepare("
                INSERT INTO admin_audit_logs (admin_id, action, ip_address) 
                VALUES (?, ?, ?)
            ");
            $action_log = "CREATED_AUDITOR_" . $publicId;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $auditStmt->bind_param("iss", $adminId, $action_log, $ip);
            $auditStmt->execute();
            $auditStmt->close();
            
            $response = [
                'status' => 'success',
                'message' => 'Auditor criado com sucesso!',
                'auditor_id' => $newAuditorId,
                'temp_password' => $tempPassword,
                'secure_id' => $secureId,
                'public_id' => $publicId,
                'email' => $email,
                'email_corporativo' => $emailCorporativo
            ];
        }
        
        // ========== EDITAR AUDITOR ==========
        else if ($action === 'editar' && isset($_POST['auditor_id'])) {
            $auditorId = (int)$_POST['auditor_id'];
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            // Validação
            if (empty($nome) || empty($email)) {
                throw new Exception('Nome e email são obrigatórios.');
            }
            
            if (strlen($nome) < 3) {
                throw new Exception('Nome deve ter no mínimo 3 caracteres.');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido.');
            }
            
            // Verificar email duplicado
            $checkStmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL");
            $checkStmt->bind_param("si", $email, $auditorId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                throw new Exception('Este email já está em uso.');
            }
            $checkStmt->close();
            
            // Atualizar
            $stmt = $mysqli->prepare("UPDATE users SET nome = ?, email = ?, updated_at = NOW() WHERE id = ? AND role = 'admin' AND deleted_at IS NULL");
            
            if (!$stmt) {
                throw new Exception('Erro ao preparar statement: ' . $mysqli->error);
            }
            
            $stmt->bind_param("ssi", $nome, $email, $auditorId);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao atualizar auditor: ' . $stmt->error);
            }
            
            $stmt->close();
            
            // Auditoria
            $auditStmt = $mysqli->prepare("
                INSERT INTO admin_audit_logs (admin_id, action, ip_address, details) 
                VALUES (?, ?, ?, ?)
            ");
            $action_log = "UPDATED_AUDITOR_" . $auditorId;
            $details = json_encode(['nome' => $nome, 'email' => $email]);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $auditStmt->bind_param("isss", $adminId, $action_log, $ip, $details);
            $auditStmt->execute();
            $auditStmt->close();
            
            $response = [
                'status' => 'success',
                'message' => 'Auditor atualizado com sucesso!'
            ];
        }
        
        // ========== DELETAR AUDITOR ==========
        else if ($action === 'deletar' && isset($_POST['auditor_id'])) {
            $auditorId = (int)$_POST['auditor_id'];
            
            // Proteger contra auto-exclusão
            if ($auditorId == $adminId) {
                throw new Exception('Você não pode deletar sua própria conta!');
            }
            
            // Verificar existência
            $checkStmt = $mysqli->prepare("SELECT nome FROM users WHERE id = ? AND role = 'admin'");
            $checkStmt->bind_param("i", $auditorId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Auditor não encontrado.');
            }
            
            $auditorData = $result->fetch_assoc();
            $checkStmt->close();
            
            // Soft delete
            $stmt = $mysqli->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $auditorId);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao deletar auditor: ' . $stmt->error);
            }
            
            $stmt->close();
            
            // Auditoria
            $auditStmt = $mysqli->prepare("
                INSERT INTO admin_audit_logs (admin_id, action, ip_address, details) 
                VALUES (?, ?, ?, ?)
            ");
            $action_log = "DELETED_AUDITOR_" . $auditorId;
            $details = json_encode(['auditor_name' => $auditorData['nome']]);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $auditStmt->bind_param("isss", $adminId, $action_log, $ip, $details);
            $auditStmt->execute();
            $auditStmt->close();
            
            $response = [
                'status' => 'success',
                'message' => 'Auditor deletado com sucesso!'
            ];
        }
        
        // ========== RESETAR SENHA ==========
        else if ($action === 'resetar_senha' && isset($_POST['auditor_id'])) {
            $auditorId = (int)$_POST['auditor_id'];
            
            // Verificar existência
            $checkStmt = $mysqli->prepare("SELECT nome, email FROM users WHERE id = ? AND role = 'admin'");
            $checkStmt->bind_param("i", $auditorId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Auditor não encontrado.');
            }
            
            $auditorData = $result->fetch_assoc();
            $checkStmt->close();
            
            // Gerar nova senha
            $tempPassword = bin2hex(random_bytes(8));
            $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);
            
            // Atualizar
            $stmt = $mysqli->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $passwordHash, $auditorId);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao resetar senha: ' . $stmt->error);
            }
            
            $stmt->close();
            
            // Auditoria
            $auditStmt = $mysqli->prepare("
                INSERT INTO admin_audit_logs (admin_id, action, ip_address, details) 
                VALUES (?, ?, ?, ?)
            ");
            $action_log = "RESET_PASSWORD_" . $auditorId;
            $details = json_encode(['auditor' => $auditorData['nome'], 'email' => $auditorData['email']]);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $auditStmt->bind_param("isss", $adminId, $action_log, $ip, $details);
            $auditStmt->execute();
            $auditStmt->close();
            
            $response = [
                'status' => 'success',
                'message' => 'Senha resetada com sucesso!',
                'new_password' => $tempPassword,
                'auditor_name' => $auditorData['nome'],
                'auditor_email' => $auditorData['email']
            ];
        }
        
    } catch (Exception $e) {
        $response = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
    
    echo json_encode($response);
    exit;
}

/* ==================== BUSCAR DADOS ==================== */
$searchTerm = $_GET['search'] ?? '';
$orderBy = $_GET['order'] ?? 'recent';
$page = (int)($_GET['page'] ?? 1);
$perPage = 10;

// Construir WHERE clause
$whereConditions = ["type = 'admin'", "role = 'admin'", "deleted_at IS NULL"];

if (!empty($searchTerm)) {
    $searchEscaped = $mysqli->real_escape_string($searchTerm);
    $whereConditions[] = "(nome LIKE '%$searchEscaped%' OR email LIKE '%$searchEscaped%' OR public_id LIKE '%$searchEscaped%')";
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// ORDER BY
$orderClause = match($orderBy) {
    'name' => 'ORDER BY nome ASC',
    'oldest' => 'ORDER BY created_at ASC',
    default => 'ORDER BY created_at DESC'
};

// Contar total
$countResult = $mysqli->query("SELECT COUNT(*) as total FROM users $whereClause");
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $perPage);

if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

// Buscar auditores
$stmt = $mysqli->prepare("
    SELECT 
        id,
        public_id,
        nome,
        email,
        email_corporativo,
        role,
        status,
        created_at,
        password_changed_at,
        last_activity
    FROM users
    WHERE type = 'admin' AND role = 'admin' AND deleted_at IS NULL
    " . (isset($searchEscaped) ? "AND (nome LIKE '%$searchEscaped%' OR email LIKE '%$searchEscaped%' OR public_id LIKE '%$searchEscaped%')" : "") . "
    $orderClause
    LIMIT $offset, $perPage
");

$stmt->execute();
$result = $stmt->get_result();
$auditores = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Buscar estatísticas
$statsStmt = $mysqli->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as ativos,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins
    FROM users
    WHERE type = 'admin' AND role = 'admin' AND deleted_at IS NULL
");
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();
$statsStmt->close();

// Buscar últimas atividades
$recentStmt = $mysqli->prepare("
    SELECT id, nome, created_at FROM users 
    WHERE type = 'admin' AND role = 'admin' AND deleted_at IS NULL
    ORDER BY created_at DESC LIMIT 5
");
$recentStmt->execute();
$recentAuditores = $recentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentStmt->close();
?>

<!-- ==================== HEADER ==================== -->
<div style="margin-bottom: 32px;">
    <h1 style="color: var(--text-title); font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">
        <i class="fa-solid fa-user-shield" style="color: var(--accent);"></i>
        Gestão de Auditores
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.938rem;">
        Gerencie os usuários administradores e auditores do sistema
    </p>
</div>

<!-- ==================== ESTATÍSTICAS ==================== -->
<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-label">Total de Auditores</div>
        <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-user-shield"></i>
            Cadastrados
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="stat-label">Auditores Ativos</div>
        <div class="stat-value" style="color: var(--accent);"><?= $stats['ativos'] ?? 0 ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-check"></i>
            Em Funcionamento
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="stat-label">Últimas Adições</div>
        <div class="stat-value"><?= count($recentAuditores) ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-history"></i>
            Últimos 5 registros
        </div>
    </div>
</div>

<!-- ==================== FILTROS E AÇÕES ==================== -->
<div class="card mb-3">
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 16px; align-items: end;">
            <div class="form-field">
                <label class="form-label">Pesquisar</label>
                <input type="text" id="searchInput" class="form-input" placeholder="Nome, email ou UID..." value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
            
            <div class="form-field">
                <label class="form-label">Ordenar por</label>
                <select id="orderSelect" class="form-select" >
                    <option value="recent" <?= $orderBy === 'recent' ? 'selected' : '' ?>>Mais Recentes</option>
                    <option value="oldest" <?= $orderBy === 'oldest' ? 'selected' : '' ?>>Mais Antigos</option>
                    <option value="name" <?= $orderBy === 'name' ? 'selected' : '' ?>>Nome (A-Z)</option>
                </select>
            </div>
            
            <button class="btn btn-primary" onclick="window.location.href='dashboard_register_admin.php'">
                <i class="fa-solid fa-plus"></i>
                Novo Auditor
            </button>
        </div>
    </div>
</div>

<!-- ==================== TABELA DE AUDITORES ==================== -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-table"></i>
            Auditores
            <span class="badge info" style="margin-left: 8px;"><?= count($auditores) ?> / <?= $totalRecords ?></span>
        </h3>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <?php if (count($auditores) > 0): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>UID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Email Corporativo</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th>Última Atividade</th>
                            <th style="text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditores as $auditor): ?>
                            <tr>
                                <td>
                                    <code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 6px; font-size: 0.875rem; font-weight: 700; color: var(--accent);">
                                        <?= htmlspecialchars($auditor['public_id']) ?>
                                    </code>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-primary);">
                                        <?= htmlspecialchars($auditor['nome']) ?>
                                    </div>
                                </td>
                                <td>
                                    <code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem;">
                                        <?= htmlspecialchars($auditor['email']) ?>
                                    </code>
                                </td>
                                <td>
                                    <code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; color: var(--accent);">
                                        <?= htmlspecialchars($auditor['email_corporativo']) ?>
                                    </code>
                                </td>
                                <td>
                                    <span class="badge <?= $auditor['status'] === 'active' ? 'success' : 'error' ?>">
                                        <?= strtoupper($auditor['status']) ?>
                                    </span>
                                </td>
                                <td style="color: var(--text-secondary); font-size: 0.875rem;">
                                    <?= date('d/m/Y H:i', strtotime($auditor['created_at'])) ?>
                                </td>
                                <td style="color: var(--text-secondary); font-size: 0.875rem;">
                                    <?= $auditor['last_activity'] ? date('d/m/Y H:i', strtotime($auditor['last_activity'])) : 'Nunca' ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                                        <button class="btn btn-sm btn-ghost" 
                                                onclick="abrirModalEditar(<?= $auditor['id'] ?>, '<?= htmlspecialchars(addslashes($auditor['nome'])) ?>', '<?= htmlspecialchars($auditor['email']) ?>')" 
                                                title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        
                                        <button class="btn btn-sm btn-ghost" 
                                                onclick="resetarSenha(<?= $auditor['id'] ?>, '<?= htmlspecialchars($auditor['nome']) ?>')" 
                                                title="Resetar Senha">
                                            <i class="fa-solid fa-key"></i>
                                        </button>
                                        
                                        <?php if ($auditor['id'] != $_SESSION['auth']['user_id']): ?>
                                            <button class="btn btn-sm btn-error" 
                                                    onclick="deletarAuditor(<?= $auditor['id'] ?>, '<?= htmlspecialchars($auditor['nome']) ?>')" 
                                                    title="Deletar">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINAÇÃO -->
            <?php if ($totalPages > 1): ?>
            <div style="padding: 16px; text-align: center; border-top: 1px solid var(--border);">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1" class="page-btn">Primeira</a>
                        <a href="?page=<?= $page - 1 ?>" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    <?php endif; ?>
                    
                    <span class="page-info">Página <?= $page ?> de <?= $totalPages ?></span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                        <a href="?page=<?= $totalPages ?>" class="page-btn">Última</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="empty-title">Nenhum auditor encontrado</div>
                <div class="empty-description">
                    <?= !empty($searchTerm) ? 'Nenhum resultado para sua busca. Tente outros termos.' : 'Comece criando seu primeiro auditor clicando no botão acima.' ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== MODAL: EDITAR AUDITOR ==================== -->
<div id="editarModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-user-pen"></i>
                Editar Auditor
            </h3>
            <button class="modal-close" onclick="fecharModal('editarModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="editarForm" onsubmit="return enviarFormulario(event, 'editar')">
                <input type="hidden" name="auditor_id" id="editarId">
                
                <div class="form-field">
                    <label class="form-label">Nome Completo *</label>
                    <input type="text" name="nome" id="editarNome" class="form-input" required minlength="3">
                </div>
                
                <div class="form-field">
                    <label class="form-label">Email Pessoal *</label>
                    <input type="email" name="email" id="editarEmail" class="form-input" required>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-ghost" onclick="fecharModal('editarModal')">
                        <i class="fa-solid fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i>
                        Atualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL: NOVA SENHA ==================== -->
<div id="senhaModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-key"></i>
                <span id="senhaModalTitle">Nova Senha Gerada</span>
            </h3>
            <button class="modal-close" onclick="fecharModal('senhaModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div style="background: var(--bg-elevated); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <p style="color: var(--text-secondary); font-size: 0.813rem; margin: 0 0 8px 0;">
                    <strong>Auditor:</strong> <span id="senhaModalAuditor"></span>
                </p>
                <p style="color: var(--text-secondary); font-size: 0.813rem; margin: 0;">
                    <strong>Email:</strong> <span id="senhaModalEmail"></span>
                </p>
            </div>
            
            <div style="background: #000; border: 2px solid var(--accent); border-radius: 12px; padding: 24px; margin-bottom: 20px; text-align: center;">
                <div style="color: var(--accent); font-size: 0.75rem; font-weight: 600; margin-bottom: 10px; text-transform: uppercase;">NOVA SENHA</div>
                <div style="color: var(--accent); font-size: 1.5rem; font-weight: 800; font-family: 'Courier New', monospace;" id="novaSenha"></div>
            </div>
            
            <p style="color: var(--text-secondary); margin-bottom: 20px; font-size: 0.875rem;">
                <i class="fa-solid fa-triangle-exclamation" style="color: var(--accent);"></i>
                Copie a senha acima e compartilhe com segurança. O usuário poderá alterá-la no próximo login.
            </p>
            
            <div style="display: flex; gap: 12px;">
                <button class="btn btn-primary" style="flex: 1;" onclick="copiarSenha()">
                    <i class="fa-solid fa-copy"></i>
                    Copiar Senha
                </button>
                <button class="btn btn-ghost" style="flex: 1;" onclick="fecharModal('senhaModal')">
                    <i class="fa-solid fa-times"></i>
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(1, 4, 9, 0.8);
        backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 20px;
    }
    
    .modal-overlay.active {
        display: flex;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<script>
    // ========== FUNÇÃO DE BUSCA ==========
    let searchTimeout;
    
    function getTableCard() {
        const cards = document.querySelectorAll('.card');
        return cards[cards.length - 1]; // Último card (tabela de auditores)
    }
    
    document.getElementById('searchInput').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const search = e.target.value.trim();
        
        searchTimeout = setTimeout(() => {
            const order = document.getElementById('orderSelect').value;
            
            if (search === '') {
                loadTableData(order, 1);
            } else {
                fetchSearchResults(search, order, 1);
            }
        }, 500);
    });
    
    document.getElementById('orderSelect').addEventListener('change', function() {
        const search = document.getElementById('searchInput').value.trim();
        const order = this.value;
        
        if (search === '') {
            loadTableData(order, 1);
        } else {
            fetchSearchResults(search, order, 1);
        }
    });
    
    function fetchSearchResults(search, order, page) {
        const url = 'modules/auditor/aud_search.php?search=' + encodeURIComponent(search) + '&order=' + order + '&page=' + page;
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    renderSearchResults(data);
                } else {
                    renderEmptyState('Erro na busca: ' + data.message);
                }
            })
            .catch(err => {
                console.error('Erro na busca:', err);
                renderEmptyState('Erro ao buscar auditores');
            });
    }
    
    function loadTableData(order, page) {
        const url = window.location.pathname + '?order=' + order + '&page=' + page;
        
        fetch(url)
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const tableCard = getTableCard();
                const newTableCard = doc.querySelectorAll('.card')[doc.querySelectorAll('.card').length - 1];
                const newContent = newTableCard.querySelector('.card-body');
                
                if (newContent && tableCard) {
                    tableCard.querySelector('.card-body').innerHTML = newContent.innerHTML;
                }
            })
            .catch(err => console.error('Erro ao carregar dados:', err));
    }
    
    function renderEmptyState(message) {
        const html = `
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fa-solid fa-search"></i>
                </div>
                <div class="empty-title">Nenhum auditor encontrado</div>
                <div class="empty-description">${message}</div>
            </div>
        `;
        updateTableContent(html);
    }
    
    function renderSearchResults(data) {
        if (data.results.length === 0) {
            renderEmptyState('Nenhum resultado para: <strong>' + data.search + '</strong>');
            return;
        }
        
        let html = `
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>UID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Email Corporativo</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th>Última Atividade</th>
                            <th style="text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.results.forEach(auditor => {
            const statusClass = auditor.status === 'active' ? 'success' : 'error';
            const statusText = auditor.status.toUpperCase();
            
            html += `
                <tr>
                    <td><code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 6px; font-size: 0.875rem; font-weight: 700; color: var(--accent);">${auditor.public_id}</code></td>
                    <td><div style="font-weight: 700; color: var(--text-primary);">${auditor.nome}</div></td>
                    <td><code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem;">${auditor.email}</code></td>
                    <td><code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; color: var(--accent);">${auditor.email_corporativo}</code></td>
                    <td><span class="badge ${statusClass}">${statusText}</span></td>
                    <td style="color: var(--text-secondary); font-size: 0.875rem;">${auditor.created_at}</td>
                    <td style="color: var(--text-secondary); font-size: 0.875rem;">${auditor.last_activity}</td>
                    <td>
                        <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                            <button class="btn btn-sm btn-ghost" onclick="abrirModalEditar(${auditor.id}, '${auditor.nome.replace(/'/g, "\\'")}', '${auditor.email}')" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="btn btn-sm btn-ghost" onclick="resetarSenha(${auditor.id}, '${auditor.nome.replace(/'/g, "\\'")}')" title="Resetar Senha">
                                <i class="fa-solid fa-key"></i>
                            </button>
                            <button class="btn btn-sm btn-error" onclick="deletarAuditor(${auditor.id}, '${auditor.nome.replace(/'/g, "\\'")}')" title="Deletar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
        
        if (data.pages > 1) {
            html += `
                <div style="padding: 16px; text-align: center; border-top: 1px solid var(--border);">
                    <div class="pagination">
                        <span class="page-info">Página ${data.page} de ${data.pages}</span>
                    </div>
                </div>
            `;
        }
        
        updateTableContent(html);
    }
    
    function updateTableContent(html) {
        const tableCard = getTableCard();
        const cardBody = tableCard ? tableCard.querySelector('.card-body') : null;
        if (cardBody) {
            cardBody.innerHTML = html;
        }
    }
    
    
    // ========== MODAIS ==========
    function abrirModalEditar(id, nome, email) {
        document.getElementById('editarId').value = id;
        document.getElementById('editarNome').value = nome;
        document.getElementById('editarEmail').value = email;
        document.getElementById('editarModal').classList.add('active');
    }
    
    function fecharModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }
    
    // Fechar modal ao clicar fora
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });
    
    // ========== ENVIO DE FORMULÁRIO ==========
    function enviarFormulario(event, acao) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', acao);
        
        const btn = form.querySelector('[type="submit"]');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processando...';
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                
                if (acao === 'editar') {
                    fecharModal('editarModal');
                } else {
                    fecharModal(form.id);
                }
                
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Erro desconhecido', 'error');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            showToast('Erro ao processar solicitação', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
        
        return false;
    }
    
    // ========== AÇÕES ==========
    function resetarSenha(auditorId, nome) {
        if (!confirm(`Resetar senha de "${nome}"?\n\nUma nova senha será gerada.`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'resetar_senha');
        formData.append('auditor_id', auditorId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('senhaModalTitle').textContent = 'Senha Resetada';
                document.getElementById('senhaModalAuditor').textContent = data.auditor_name;
                document.getElementById('senhaModalEmail').textContent = data.auditor_email;
                document.getElementById('novaSenha').textContent = data.new_password;
                document.getElementById('senhaModal').classList.add('active');
                showToast('Senha resetada!', 'success');
            } else {
                showToast(data.message || 'Erro ao resetar senha', 'error');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            showToast('Erro ao resetar senha', 'error');
        });
    }
    
    function deletarAuditor(auditorId, nome) {
        if (!confirm(`⚠️ Deletar "${nome}"?\n\nEsta ação não pode ser desfeita.`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'deletar');
        formData.append('auditor_id', auditorId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Erro ao deletar auditor', 'error');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            showToast('Erro ao deletar auditor', 'error');
        });
    }
    
    function copiarSenha() {
        const senha = document.getElementById('novaSenha').textContent;
        navigator.clipboard.writeText(senha).then(() => {
            showToast('Senha copiada para a área de transferência!', 'success');
        }).catch(() => {
            showToast('Erro ao copiar senha', 'error');
        });
    }
    
    // ========== TOAST ==========
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: ${type === 'success' ? 'var(--accent)' : '#ff4d4d'};
            color: ${type === 'success' ? '#000' : '#fff'};
            padding: 14px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            z-index: 10001;
            animation: fadeIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
        `;
        toast.innerHTML = `<i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>