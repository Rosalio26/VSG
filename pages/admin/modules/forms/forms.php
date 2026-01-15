<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - GESTÃO DE FORMULÁRIOS
 * Módulo: modules/forms/forms.php
 * Descrição: Gerenciamento completo de documentos empresariais
 * Versão: 2.2 - Exportação dentro do dashboard
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

require_once '../../includes/audit_helper.php';

/* ================= PROCESSAR AÇÕES ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['status' => 'error', 'message' => 'Ação inválida'];
    
    try {
        // Aprovar documento
        if ($action === 'aprovar' && isset($_POST['business_id'])) {
            $businessId = (int)$_POST['business_id'];
            
            $stmt = $mysqli->prepare("
                UPDATE businesses 
                SET status_documentos = 'aprovado', 
                    motivo_rejeicao = NULL, 
                    updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $businessId);
            $stmt->execute();
            
            $userStmt = $mysqli->prepare("SELECT nome FROM users WHERE id = ?");
            $userStmt->bind_param("i", $businessId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            $userInfo = $userResult->fetch_assoc();
            $userStmt->close();
            
            $notifStmt = $mysqli->prepare("
                INSERT INTO notifications 
                (sender_id, receiver_id, category, priority, subject, message, status, created_at) 
                VALUES (?, ?, 'alert', 'high', 'Documentos Aprovados', 'Seus documentos foram aprovados! Você já pode usar a plataforma.', 'unread', NOW())
            ");
            $notifStmt->bind_param("ii", $adminId, $businessId);
            $notifStmt->execute();
            $notifStmt->close();
            
            auditDocApprove($mysqli, $adminId, $businessId, $businessId);
            
            $response = [
                'status' => 'success',
                'message' => 'Documentos aprovados com sucesso!',
                'business_name' => $userInfo['nome'] ?? 'Empresa'
            ];
        }
        
        // Rejeitar documento
        if ($action === 'rejeitar' && isset($_POST['business_id'], $_POST['motivo'])) {
            $businessId = (int)$_POST['business_id'];
            $motivo = trim($_POST['motivo']);
            
            if (empty($motivo)) {
                throw new Exception('Motivo da rejeição é obrigatório');
            }
            
            $stmt = $mysqli->prepare("
                UPDATE businesses 
                SET status_documentos = 'rejeitado', 
                    motivo_rejeicao = ?, 
                    updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->bind_param("si", $motivo, $businessId);
            $stmt->execute();
            
            $userStmt = $mysqli->prepare("SELECT nome FROM users WHERE id = ?");
            $userStmt->bind_param("i", $businessId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            $userInfo = $userResult->fetch_assoc();
            $userStmt->close();
            
            $message = "Seus documentos foram rejeitados. Motivo: " . $motivo;
            $notifStmt = $mysqli->prepare("
                INSERT INTO notifications 
                (sender_id, receiver_id, category, priority, subject, message, status, created_at) 
                VALUES (?, ?, 'alert', 'high', 'Documentos Rejeitados', ?, 'unread', NOW())
            ");
            $notifStmt->bind_param("iis", $adminId, $businessId, $message);
            $notifStmt->execute();
            $notifStmt->close();
            
            auditDocReject($mysqli, $adminId, $businessId, $businessId, $motivo);
            
            $response = [
                'status' => 'success',
                'message' => 'Documentos rejeitados',
                'business_name' => $userInfo['nome'] ?? 'Empresa',
                'motivo' => $motivo
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

/* ================= BUSCAR DADOS ================= */
$statusFilter = $_GET['status'] ?? 'todos';
$searchTerm = $_GET['search'] ?? '';
$orderBy = $_GET['order'] ?? 'recent';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$whereConditions = ["u.type = 'company'", "u.deleted_at IS NULL"];

if ($statusFilter !== 'todos') {
    $whereConditions[] = "b.status_documentos = '" . $mysqli->real_escape_string($statusFilter) . "'";
}

if (!empty($searchTerm)) {
    $searchEscaped = $mysqli->real_escape_string($searchTerm);
    $whereConditions[] = "(u.nome LIKE '%$searchEscaped%' OR u.email LIKE '%$searchEscaped%' OR b.tax_id LIKE '%$searchEscaped%')";
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

$orderClause = match($orderBy) {
    'oldest' => 'ORDER BY u.created_at ASC',
    'name' => 'ORDER BY u.nome ASC',
    'status' => 'ORDER BY b.status_documentos ASC, u.created_at DESC',
    default => 'ORDER BY u.created_at DESC'
};

$query = "
    SELECT 
        u.id,
        u.nome,
        u.email,
        u.created_at,
        b.tax_id,
        b.license_path,
        b.status_documentos,
        b.motivo_rejeicao,
        b.updated_at,
        DATEDIFF(NOW(), u.created_at) as dias_pendente
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    $whereClause
    $orderClause
    LIMIT $perPage OFFSET $offset
";

$result = $mysqli->query($query);

$totalQuery = "
    SELECT COUNT(*) as total 
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    $whereClause
";
$totalResult = $mysqli->query($totalQuery);
$totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
$totalPages = ceil($totalRecords / $perPage);

// Estatísticas
$statsPendente = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'pendente'
    AND u.deleted_at IS NULL
")->fetch_assoc()['total'] ?? 0;

$statsAprovado = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'aprovado'
    AND u.deleted_at IS NULL
")->fetch_assoc()['total'] ?? 0;

$statsRejeitado = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'rejeitado'
    AND u.deleted_at IS NULL
")->fetch_assoc()['total'] ?? 0;

$statsTotal = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM users 
    WHERE type = 'company'
    AND deleted_at IS NULL
")->fetch_assoc()['total'] ?? 0;

$statsUrgente = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'pendente'
    AND DATEDIFF(NOW(), u.created_at) > 7
    AND u.deleted_at IS NULL
")->fetch_assoc()['total'] ?? 0;
?>

<!-- HEADER -->
<div style="margin-bottom: 32px;">
    <h1 style="color: var(--text-title); font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">
        <i class="fa-solid fa-file-signature" style="color: var(--accent);"></i>
        Gestão de Formulários
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.938rem;">
        Gerenciamento completo de documentos empresariais
    </p>
</div>

<!-- ESTATÍSTICAS -->
<div class="stats-grid mb-3">
    <div class="stat-card" onclick="aplicarFiltroCard('todos')" style="cursor: pointer;">
        <div class="stat-icon">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="stat-label">Total de Empresas</div>
        <div class="stat-value"><?= number_format($statsTotal, 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-briefcase"></i>
            Cadastradas
        </div>
    </div>
    
    <div class="stat-card" onclick="aplicarFiltroCard('pendente')" style="cursor: pointer;">
        <div class="stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="stat-label">Pendentes</div>
        <div class="stat-value" style="color: #d29922;"><?= $statsPendente ?></div>
        <div class="stat-change <?= $statsUrgente > 0 ? 'negative' : 'neutral' ?>">
            <i class="fa-solid fa-<?= $statsUrgente > 0 ? 'exclamation-triangle' : 'clock' ?>"></i>
            <?= $statsUrgente ?> urgentes
        </div>
    </div>
    
    <div class="stat-card" onclick="aplicarFiltroCard('aprovado')" style="cursor: pointer;">
        <div class="stat-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="stat-label">Aprovados</div>
        <div class="stat-value" style="color: var(--accent);"><?= $statsAprovado ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-check"></i>
            Ativos
        </div>
    </div>
    
    <div class="stat-card" onclick="aplicarFiltroCard('rejeitado')" style="cursor: pointer;">
        <div class="stat-icon">
            <i class="fa-solid fa-times-circle"></i>
        </div>
        <div class="stat-label">Rejeitados</div>
        <div class="stat-value" style="color: #f85149;"><?= $statsRejeitado ?></div>
        <div class="stat-change negative">
            <i class="fa-solid fa-ban"></i>
            Bloqueados
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" id="filterForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
            
            <div class="form-field">
                <label class="form-label">Status</label>
                <select name="status" id="statusSelect" class="form-select">
                    <option value="todos" <?= $statusFilter === 'todos' ? 'selected' : '' ?>>Todos os status</option>
                    <option value="pendente" <?= $statusFilter === 'pendente' ? 'selected' : '' ?>>⏳ Pendentes</option>
                    <option value="aprovado" <?= $statusFilter === 'aprovado' ? 'selected' : '' ?>>✓ Aprovados</option>
                    <option value="rejeitado" <?= $statusFilter === 'rejeitado' ? 'selected' : '' ?>>✗ Rejeitados</option>
                </select>
            </div>

            <div class="form-field">
                <label class="form-label">Ordenar por</label>
                <select name="order" id="orderSelect" class="form-select">
                    <option value="recent" <?= $orderBy === 'recent' ? 'selected' : '' ?>>Mais Recentes</option>
                    <option value="oldest" <?= $orderBy === 'oldest' ? 'selected' : '' ?>>Mais Antigos</option>
                    <option value="name" <?= $orderBy === 'name' ? 'selected' : '' ?>>Nome (A-Z)</option>
                    <option value="status" <?= $orderBy === 'status' ? 'selected' : '' ?>>Status</option>
                </select>
            </div>

            <div class="form-field">
                <label class="form-label">Buscar</label>
                <input type="text" 
                       name="search" 
                       id="searchInput"
                       class="form-input" 
                       placeholder="Nome, email ou NIF..." 
                       value="<?= htmlspecialchars($searchTerm) ?>">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-primary" onclick="aplicarFiltros()">
                    <i class="fa-solid fa-search"></i>
                    Buscar
                </button>
                <?php if ($statusFilter !== 'todos' || !empty($searchTerm) || $orderBy !== 'recent'): ?>
                <button type="button" class="btn btn-ghost" onclick="limparFiltros()">
                    <i class="fa-solid fa-times"></i>
                    Limpar
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABELA -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title">
            <i class="fa-solid fa-table"></i>
            Formulários
            <span class="badge info" style="margin-left: 8px;"><?= $totalRecords ?></span>
        </h3>
        <div style="display: flex; gap: 8px;">
            <button class="btn btn-sm btn-secondary" onclick="abrirModalExportacao()">
                <i class="fa-solid fa-download"></i>
                Exportar
            </button>
        </div>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>NIF</th>
                            <th>Status</th>
                            <th>Registrado há</th>
                            <th>Última Atualização</th>
                            <th style="text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                                        <?= htmlspecialchars($row['nome']) ?>
                                    </div>
                                    <div style="font-size: 0.813rem; color: var(--text-secondary);">
                                        <i class="fa-solid fa-envelope"></i>
                                        <?= htmlspecialchars($row['email']) ?>
                                    </div>
                                </td>
                                <td>
                                    <code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 6px; font-size: 0.875rem;">
                                        <?= htmlspecialchars($row['tax_id'] ?? 'N/A') ?>
                                    </code>
                                </td>
                                <td>
                                    <?php
                                    $status = $row['status_documentos'] ?? 'pendente';
                                    $badgeClass = match($status) {
                                        'aprovado' => 'success',
                                        'rejeitado' => 'error',
                                        default => 'warning'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= strtoupper($status) ?>
                                    </span>
                                    <?php if ($status === 'pendente' && $row['dias_pendente'] > 7): ?>
                                        <span class="badge error" style="margin-left: 4px; animation: pulse 2s infinite;">
                                            <i class="fa-solid fa-exclamation-triangle"></i>
                                            URGENTE
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--text-secondary); font-size: 0.875rem;">
                                    <?= $row['dias_pendente'] ?> dias
                                    <br>
                                    <small style="color: var(--text-muted); font-size: 0.75rem;">
                                        <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                                    </small>
                                </td>
                                <td style="color: var(--text-secondary); font-size: 0.875rem;">
                                    <?= $row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-' ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                                        <button class="btn btn-sm btn-ghost" 
                                                onclick="viewDetails(<?= $row['id'] ?>)" 
                                                title="Ver Detalhes">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        
                                        <?php if ($row['status_documentos'] === 'pendente'): ?>
                                            <button class="btn btn-sm btn-success" 
                                                    onclick="aprovarDocumento(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nome'])) ?>')" 
                                                    title="Aprovar">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            
                                            <button class="btn btn-sm btn-error" 
                                                    onclick="showRejectModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nome'])) ?>')" 
                                                    title="Rejeitar">
                                                <i class="fa-solid fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINAÇÃO -->
            <?php if ($totalPages > 1): ?>
            <div style="padding: 20px; border-top: 1px solid var(--border); display: flex; justify-content: center; gap: 8px;">
                <?php if ($page > 1): ?>
                    <button class="btn btn-sm btn-ghost" onclick="irParaPagina(<?= $page - 1 ?>)">
                        <i class="fa-solid fa-chevron-left"></i>
                        Anterior
                    </button>
                <?php endif; ?>
                
                <span style="color: var(--text-secondary); padding: 8px 12px;">
                    Página <?= $page ?> de <?= $totalPages ?>
                </span>
                
                <?php if ($page < $totalPages): ?>
                    <button class="btn btn-sm btn-ghost" onclick="irParaPagina(<?= $page + 1 ?>)">
                        Próxima
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <div class="empty-title">Nenhum formulário encontrado</div>
                <div class="empty-description">
                    <?php if (!empty($searchTerm) || $statusFilter !== 'todos'): ?>
                        Tente ajustar os filtros ou realizar uma nova busca
                    <?php else: ?>
                        Não há empresas cadastradas no momento
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL DE EXPORTAÇÃO -->
<div id="exportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-download"></i>
                Exportar Dados
            </h3>
            <button class="modal-close" onclick="closeModal('exportModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 24px;">
                <p style="color: var(--text-secondary); margin-bottom: 16px;">
                    Selecione o formato desejado para exportação dos dados filtrados.
                </p>
                
                <div class="form-field">
                    <label class="form-label">Formato de Exportação</label>
                    <select id="exportFormat" class="form-select">
                        <option value="csv">CSV (Excel)</option>
                        <option value="json">JSON</option>
                        <option value="xlsx">Excel (.xlsx)</option>
                    </select>
                </div>
                
                <div class="form-field">
                    <label class="form-label">Incluir</label>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="includeStats" checked>
                            <span style="color: var(--text-primary);">Estatísticas resumidas</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="includeFilters" checked>
                            <span style="color: var(--text-primary);">Filtros aplicados</span>
                        </label>
                    </div>
                </div>
                
                <div style="background: var(--bg-elevated); padding: 16px; border-radius: 8px; margin-top: 16px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: var(--text-secondary);">Registros a exportar:</span>
                        <span style="color: var(--accent); font-weight: 700;"><?= $totalRecords ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-secondary);">Filtros ativos:</span>
                        <span style="color: var(--text-primary); font-weight: 700;">
                            <?= $statusFilter !== 'todos' ? 'Status' : '' ?>
                            <?= !empty($searchTerm) ? ($statusFilter !== 'todos' ? ', Busca' : 'Busca') : '' ?>
                            <?= $statusFilter === 'todos' && empty($searchTerm) ? 'Nenhum' : '' ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-ghost" onclick="closeModal('exportModal')">
                    <i class="fa-solid fa-times"></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="iniciarExportacao()">
                    <i class="fa-solid fa-download"></i>
                    Exportar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE PROGRESSO -->
<div id="progressModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-body" style="text-align: center; padding: 40px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 3rem; color: var(--accent); margin-bottom: 20px;"></i>
            <h3 style="color: var(--text-title); margin-bottom: 8px;">Exportando Dados</h3>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">Por favor, aguarde...</p>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 12px;" id="progressText">
                Preparando exportação...
            </p>
        </div>
    </div>
</div>

<!-- MODAL DE DETALHES -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-file-alt"></i>
                Detalhes do Formulário
            </h3>
            <button class="modal-close" onclick="closeModal('detailsModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="detailsContent">
            <div style="text-align: center; padding: 40px;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--accent);"></i>
                <p style="color: var(--text-secondary); margin-top: 16px;">Carregando...</p>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE REJEIÇÃO -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-ban"></i>
                Rejeitar Documentos
            </h3>
            <button class="modal-close" onclick="closeModal('rejectModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="rejectForm" onsubmit="return submitReject(event)">
                <input type="hidden" id="rejectBusinessId">
                
                <div class="form-field">
                    <label class="form-label">Empresa</label>
                    <input type="text" 
                           class="form-input" 
                           id="rejectBusinessName" 
                           readonly 
                           style="background: var(--bg-elevated); cursor: not-allowed;">
                </div>
                
                <div class="form-field">
                    <label class="form-label">Motivo da Rejeição *</label>
                    <textarea id="rejectMotivo" 
                              class="form-textarea" 
                              required 
                              placeholder="Descreva detalhadamente o motivo da rejeição dos documentos..."
                              style="min-height: 120px;"></textarea>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('rejectModal')">
                        <i class="fa-solid fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-error">
                        <i class="fa-solid fa-ban"></i>
                        Rejeitar Documentos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.05); }
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideIn 0.3s ease;
}

.modal-header {
    padding: 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    color: var(--text-title);
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
}

.modal-close {
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-size: 1.5rem;
    cursor: pointer;
    transition: 0.2s;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
}

.modal-close:hover {
    color: var(--accent);
    background: var(--bg-elevated);
}

.modal-body {
    padding: 24px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--bg-elevated);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--accent);
    width: 0%;
    transition: width 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

.stat-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}
</style>

<script>
(function() {
    'use strict';
    
    // ========== FILTROS (SEM RECARREGAR) ==========
    window.aplicarFiltroCard = function(status) {
        const statusSelect = document.getElementById('statusSelect');
        if (statusSelect) {
            statusSelect.value = status;
        }
        aplicarFiltros();
    };

    window.aplicarFiltros = function() {
        const status = document.getElementById('statusSelect').value;
        const order = document.getElementById('orderSelect').value;
        const search = document.getElementById('searchInput').value;
        
        const params = new URLSearchParams();
        if (status !== 'todos') params.append('status', status);
        if (order !== 'recent') params.append('order', order);
        if (search) params.append('search', search);
        
        const url = 'modules/forms/forms' + (params.toString() ? '?' + params.toString() : '');
        
        if (typeof loadContent === 'function') {
            loadContent(url);
        } else {
            window.location.href = url;
        }
    };

    window.limparFiltros = function() {
        document.getElementById('statusSelect').value = 'todos';
        document.getElementById('orderSelect').value = 'recent';
        document.getElementById('searchInput').value = '';
        
        if (typeof loadContent === 'function') {
            loadContent('modules/forms/forms');
        } else {
            window.location.href = 'modules/forms/forms';
        }
    };

    window.irParaPagina = function(page) {
        const status = document.getElementById('statusSelect').value;
        const order = document.getElementById('orderSelect').value;
        const search = document.getElementById('searchInput').value;
        
        const params = new URLSearchParams();
        params.append('page', page);
        if (status !== 'todos') params.append('status', status);
        if (order !== 'recent') params.append('order', order);
        if (search) params.append('search', search);
        
        const url = 'modules/forms/forms?' + params.toString();
        
        if (typeof loadContent === 'function') {
            loadContent(url);
        } else {
            window.location.href = url;
        }
    };

    document.getElementById('statusSelect').addEventListener('change', aplicarFiltros);
    document.getElementById('orderSelect').addEventListener('change', aplicarFiltros);
    
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            aplicarFiltros();
        }, 500);
    });

    // ========== EXPORTAÇÃO (DENTRO DO DASHBOARD) ==========
    window.abrirModalExportacao = function() {
        document.getElementById('exportModal').classList.add('active');
    };

    window.iniciarExportacao = function() {
        closeModal('exportModal');
        
        const format = document.getElementById('exportFormat').value;
        const includeStats = document.getElementById('includeStats').checked;
        const includeFilters = document.getElementById('includeFilters').checked;
        
        const status = document.getElementById('statusSelect').value;
        const search = document.getElementById('searchInput').value;
        
        // Mostrar modal de progresso
        document.getElementById('progressModal').classList.add('active');
        
        // Simular progresso
        let progress = 0;
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        
        const progressInterval = setInterval(() => {
            progress += 10;
            progressFill.style.width = progress + '%';
            
            if (progress === 30) progressText.textContent = 'Coletando dados...';
            if (progress === 60) progressText.textContent = 'Processando registros...';
            if (progress === 90) progressText.textContent = 'Finalizando exportação...';
            
            if (progress >= 100) {
                clearInterval(progressInterval);
                progressText.textContent = 'Concluído!';
            }
        }, 200);
        
        // Construir URL com parâmetros
        const params = new URLSearchParams();
        params.append('format', format);
        if (status !== 'todos') params.append('status', status);
        if (search) params.append('search', search);
        if (includeStats) params.append('include_stats', '1');
        if (includeFilters) params.append('include_filters', '1');
        
        const url = 'modules/tabelas/actions/export_forms.php?' + params.toString();
        
        // Fazer download via iframe (não redireciona)
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = url;
        document.body.appendChild(iframe);
        
        // Remover iframe após 5 segundos
        setTimeout(() => {
            document.body.removeChild(iframe);
            closeModal('progressModal');
            showToast('Exportação concluída com sucesso!', 'success');
            
            // Resetar progress
            progressFill.style.width = '0%';
            progressText.textContent = 'Preparando exportação...';
        }, 2500);
    };

    // ========== VER DETALHES ==========
    window.viewDetails = function(userId) {
        const modal = document.getElementById('detailsModal');
        const content = document.getElementById('detailsContent');
        
        modal.classList.add('active');
        content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--accent);"></i></div>';
        
        fetch(`modules/tabelas/actions/get_business_details.php?id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const d = data.business;
                content.innerHTML = `
                    <div class="form-field">
                        <label class="form-label">Nome da Empresa</label>
                        <div class="form-input" style="background: var(--bg-elevated); cursor: default;">
                            ${escapeHtml(d.nome)}
                        </div>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">Email</label>
                        <div class="form-input" style="background: var(--bg-elevated); cursor: default;">
                            ${escapeHtml(d.email)}
                        </div>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">NIF</label>
                        <div class="form-input" style="background: var(--bg-elevated); cursor: default;">
                            ${escapeHtml(d.tax_id || 'Não fornecido')}
                        </div>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">Status</label>
                        <span class="badge ${d.status === 'aprovado' ? 'success' : (d.status === 'rejeitado' ? 'error' : 'warning')}">
                            ${d.status.toUpperCase()}
                        </span>
                    </div>
                    
                    ${d.motivo_rejeicao ? `
                    <div class="form-field">
                        <label class="form-label">Motivo da Rejeição</label>
                        <div class="alert error">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            <div>${escapeHtml(d.motivo_rejeicao)}</div>
                        </div>
                    </div>
                    ` : ''}
                    
                    <div class="form-field">
                        <label class="form-label">Licença Comercial</label>
                        ${d.license_path ? `
                            <a href="${escapeHtml(d.license_path)}" target="_blank" class="btn btn-primary">
                                <i class="fa-solid fa-file-pdf"></i>
                                Ver Documento
                            </a>
                        ` : '<p style="color: var(--text-muted);">Documento não enviado</p>'}
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="alert error">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        <div>Erro ao carregar detalhes: ${data.message || 'Erro desconhecido'}</div>
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            content.innerHTML = `
                <div class="alert error">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <div>Erro ao carregar detalhes</div>
                </div>
            `;
        });
    };

    // ========== APROVAR DOCUMENTO ==========
    window.aprovarDocumento = function(businessId, businessName) {
        if (!confirm(`Aprovar documentos de "${businessName}"?\n\nEsta ação irá:\n- Aprovar os documentos\n- Liberar acesso à plataforma\n- Enviar notificação ao usuário`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'aprovar');
        formData.append('business_id', businessId);
        
        fetch('modules/forms/forms', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Documentos aprovados com sucesso!', 'success');
                setTimeout(() => {
                    aplicarFiltros();
                }, 1500);
            } else {
                showToast(data.message || 'Erro ao aprovar', 'error');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            showToast('Erro ao processar solicitação', 'error');
        });
    };

    // ========== REJEITAR DOCUMENTO ==========
    window.showRejectModal = function(businessId, businessName) {
        document.getElementById('rejectBusinessId').value = businessId;
        document.getElementById('rejectBusinessName').value = businessName;
        document.getElementById('rejectMotivo').value = '';
        document.getElementById('rejectModal').classList.add('active');
        
        setTimeout(() => {
            document.getElementById('rejectMotivo').focus();
        }, 100);
    };

    window.submitReject = function(event) {
        event.preventDefault();
        
        const businessId = document.getElementById('rejectBusinessId').value;
        const motivo = document.getElementById('rejectMotivo').value.trim();
        
        if (!motivo) {
            showToast('Por favor, informe o motivo da rejeição', 'error');
            return false;
        }
        
        const formData = new FormData();
        formData.append('action', 'rejeitar');
        formData.append('business_id', businessId);
        formData.append('motivo', motivo);
        
        const form = document.getElementById('rejectForm');
        const submitBtn = form.querySelector('[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processando...';
        
        fetch('modules/forms/forms', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Documentos rejeitados', 'success');
                closeModal('rejectModal');
                setTimeout(() => {
                    aplicarFiltros();
                }, 1500);
            } else {
                showToast(data.message || 'Erro ao rejeitar', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-ban"></i> Rejeitar Documentos';
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            showToast('Erro ao processar solicitação', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-ban"></i> Rejeitar Documentos';
        });
        
        return false;
    };

    // ========== MODAL ==========
    window.closeModal = function(modalId) {
        document.getElementById(modalId).classList.remove('active');
    };

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });

    // ========== TOAST ==========
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
        `;
        toast.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: ${type === 'success' ? 'var(--accent)' : '#f85149'};
            color: ${type === 'success' ? 'var(--bg-card)' : '#fff'};
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            z-index: 10001;
            animation: fadeIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    window.showToast = showToast;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    window.escapeHtml = escapeHtml;

    console.log('✅ Gestão de Formulários carregada (v2.2 - Exportação no dashboard)');
})();
</script>