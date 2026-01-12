<?php
if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= PROCESSAR AÇÕES ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Aprovar documento
    if ($action === 'aprovar' && isset($_POST['business_id'])) {
        $businessId = (int)$_POST['business_id'];
        
        $mysqli->query("UPDATE businesses SET status_documentos = 'aprovado', motivo_rejeicao = NULL, updated_at = NOW() WHERE user_id = $businessId");
        
        // Notificar usuário
        $mysqli->query("INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status) 
                       VALUES ($adminId, $businessId, 'alert', 'high', 'Documentos Aprovados', 'Seus documentos foram aprovados! Você já pode usar a plataforma.', 'unread')");
        
        // Log de auditoria
        $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) 
                       VALUES ($adminId, 'APROVOU_DOCUMENTOS_$businessId', '{$_SERVER['REMOTE_ADDR']}')");
        
        $_SESSION['success_msg'] = 'Documentos aprovados com sucesso!';
        header('Location: ?reload=1');
        exit;
    }
    
    // Rejeitar documento
    if ($action === 'rejeitar' && isset($_POST['business_id'], $_POST['motivo'])) {
        $businessId = (int)$_POST['business_id'];
        $motivo = $mysqli->real_escape_string($_POST['motivo']);
        
        $mysqli->query("UPDATE businesses SET status_documentos = 'rejeitado', motivo_rejeicao = '$motivo', updated_at = NOW() WHERE user_id = $businessId");
        
        // Notificar usuário
        $mysqli->query("INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status) 
                       VALUES ($adminId, $businessId, 'alert', 'high', 'Documentos Rejeitados', 'Seus documentos foram rejeitados. Motivo: $motivo', 'unread')");
        
        // Log de auditoria
        $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) 
                       VALUES ($adminId, 'REJEITOU_DOCUMENTOS_$businessId', '{$_SERVER['REMOTE_ADDR']}')");
        
        $_SESSION['success_msg'] = 'Documentos rejeitados.';
        header('Location: ?reload=1');
        exit;
    }
}

/* ================= BUSCAR DADOS ================= */
// Filtros
$statusFilter = $_GET['status'] ?? 'todos';
$searchTerm = $_GET['search'] ?? '';
$orderBy = $_GET['order'] ?? 'recent';

// Construir WHERE clause
$whereClause = "WHERE u.type = 'company'";

if ($statusFilter !== 'todos') {
    $whereClause .= " AND b.status_documentos = '$statusFilter'";
}

if (!empty($searchTerm)) {
    $searchEscaped = $mysqli->real_escape_string($searchTerm);
    $whereClause .= " AND (u.nome LIKE '%$searchEscaped%' OR u.email LIKE '%$searchEscaped%' OR b.tax_id LIKE '%$searchEscaped%')";
}

// Order
$orderClause = match($orderBy) {
    'oldest' => 'ORDER BY u.created_at ASC',
    'name' => 'ORDER BY u.nome ASC',
    'status' => 'ORDER BY b.status_documentos ASC',
    default => 'ORDER BY u.created_at DESC'
};

// Query principal
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
        b.updated_at
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    $whereClause
    $orderClause
";

$result = $mysqli->query($query);

// Estatísticas
$statsPendente = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'pendente'")->fetch_assoc()['total'];
$statsAprovado = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'aprovado'")->fetch_assoc()['total'];
$statsRejeitado = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'rejeitado'")->fetch_assoc()['total'];
$statsTotal = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE type = 'company'")->fetch_assoc()['total'];
?>

<style>
:root {
    --bg-card: #121812;
    --bg-body: #050705;
    --text-main: #a0ac9f;
    --text-title: #ffffff;
    --accent-green: #00ff88;
    --accent-emerald: #00a63e;
    --accent-glow: rgba(0, 255, 136, 0.3);
    --border-color: rgba(0, 255, 136, 0.08);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.forms-container {
    padding: 20px;
    animation: fadeIn 0.5s ease;
}

.page-header {
    margin-bottom: 30px;
}

.page-title {
    font-size: 2rem;
    font-weight: 900;
    background: linear-gradient(135deg, var(--accent-green), var(--accent-emerald));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0 0 10px 0;
}

.page-subtitle {
    color: var(--text-main);
    font-size: 0.95rem;
}

/* ========== CARDS DE ESTATÍSTICAS ========== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 25px;
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent-green), var(--accent-emerald));
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px var(--accent-glow);
    border-color: var(--accent-green);
}

.stat-label {
    color: var(--text-main);
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.stat-value {
    color: var(--text-title);
    font-size: 2.5rem;
    font-weight: 900;
    margin: 10px 0;
}

.stat-icon {
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 2rem;
    opacity: 0.1;
}

/* ========== FILTROS ========== */
.filters-bar {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-label {
    color: var(--text-main);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.filter-select,
.filter-input {
    background: rgba(0, 255, 136, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 10px 15px;
    color: var(--text-title);
    font-size: 0.9rem;
    outline: none;
    transition: 0.3s;
    min-width: 180px;
}

.filter-select:focus,
.filter-input:focus {
    border-color: var(--accent-green);
    box-shadow: 0 0 15px var(--accent-glow);
}

.filter-btn {
    background: var(--accent-green);
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    color: #000;
    font-weight: 700;
    cursor: pointer;
    transition: 0.3s;
    margin-top: auto;
}

.filter-btn:hover {
    box-shadow: 0 0 20px var(--accent-glow);
    transform: translateY(-2px);
}

/* ========== TABELA ========== */
.forms-table {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    overflow: hidden;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table thead {
    background: rgba(0, 255, 136, 0.05);
}

.table th {
    padding: 20px;
    text-align: left;
    color: var(--text-main);
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-bottom: 1px solid var(--border-color);
}

.table td {
    padding: 20px;
    color: var(--text-title);
    border-bottom: 1px solid var(--border-color);
    font-size: 0.9rem;
}

.table tbody tr {
    transition: 0.2s;
}

.table tbody tr:hover {
    background: rgba(0, 255, 136, 0.03);
}

/* ========== BADGES ========== */
.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pendente {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.status-aprovado {
    background: rgba(0, 255, 136, 0.15);
    color: var(--accent-green);
    border: 1px solid rgba(0, 255, 136, 0.3);
}

.status-rejeitado {
    background: rgba(255, 77, 77, 0.15);
    color: #ff4d4d;
    border: 1px solid rgba(255, 77, 77, 0.3);
}

/* ========== BOTÕES DE AÇÃO ========== */
.action-btns {
    display: flex;
    gap: 8px;
}

.btn {
    padding: 8px 15px;
    border-radius: 8px;
    border: none;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-view {
    background: rgba(0, 255, 136, 0.1);
    color: var(--accent-green);
    border: 1px solid rgba(0, 255, 136, 0.3);
}

.btn-view:hover {
    background: rgba(0, 255, 136, 0.2);
    box-shadow: 0 0 15px var(--accent-glow);
}

.btn-approve {
    background: var(--accent-green);
    color: #000;
}

.btn-approve:hover {
    box-shadow: 0 0 15px var(--accent-glow);
    transform: translateY(-2px);
}

.btn-reject {
    background: rgba(255, 77, 77, 0.2);
    color: #ff4d4d;
    border: 1px solid rgba(255, 77, 77, 0.3);
}

.btn-reject:hover {
    background: rgba(255, 77, 77, 0.3);
}

/* ========== MODAL ========== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    animation: fadeIn 0.3s ease;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-title {
    color: var(--text-title);
    font-size: 1.5rem;
    font-weight: 800;
}

.modal-close {
    background: transparent;
    border: none;
    color: var(--text-main);
    font-size: 1.5rem;
    cursor: pointer;
    transition: 0.3s;
}

.modal-close:hover {
    color: var(--accent-green);
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    color: var(--text-main);
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.form-input,
.form-textarea {
    width: 100%;
    background: rgba(0, 255, 136, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 12px 15px;
    color: var(--text-title);
    font-size: 0.9rem;
    outline: none;
    transition: 0.3s;
}

.form-textarea {
    min-height: 120px;
    resize: vertical;
}

.form-input:focus,
.form-textarea:focus {
    border-color: var(--accent-green);
    box-shadow: 0 0 15px var(--accent-glow);
}

.modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-main);
}

.empty-state i {
    font-size: 4rem;
    opacity: 0.3;
    margin-bottom: 20px;
}
</style>

<div class="forms-container">
    <!-- HEADER -->
    <div class="page-header">
        <h1 class="page-title">📋 Gestão de Formulários</h1>
        <p class="page-subtitle">Gerencie documentos e registros de empresas</p>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: rgba(0,255,136,0.1); border: 1px solid var(--accent-green); border-radius: 10px; padding: 15px; margin-bottom: 20px; color: var(--accent-green);">
            <i class="fa-solid fa-check-circle"></i> <?= $_SESSION['success_msg'] ?>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <!-- ESTATÍSTICAS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-label">Total de Empresas</div>
            <div class="stat-value"><?= $statsTotal ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-label">Pendentes</div>
            <div class="stat-value" style="color: #ffc107;"><?= $statsPendente ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">✓</div>
            <div class="stat-label">Aprovados</div>
            <div class="stat-value" style="color: var(--accent-green);"><?= $statsAprovado ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">✗</div>
            <div class="stat-label">Rejeitados</div>
            <div class="stat-value" style="color: #ff4d4d;"><?= $statsRejeitado ?></div>
        </div>
    </div>

    <!-- FILTROS -->
    <form class="filters-bar" method="GET">
        <div class="filter-group">
            <label class="filter-label">Status</label>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="todos" <?= $statusFilter === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="pendente" <?= $statusFilter === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                <option value="aprovado" <?= $statusFilter === 'aprovado' ? 'selected' : '' ?>>Aprovados</option>
                <option value="rejeitado" <?= $statusFilter === 'rejeitado' ? 'selected' : '' ?>>Rejeitados</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">Ordenar por</label>
            <select name="order" class="filter-select" onchange="this.form.submit()">
                <option value="recent" <?= $orderBy === 'recent' ? 'selected' : '' ?>>Mais Recentes</option>
                <option value="oldest" <?= $orderBy === 'oldest' ? 'selected' : '' ?>>Mais Antigos</option>
                <option value="name" <?= $orderBy === 'name' ? 'selected' : '' ?>>Nome (A-Z)</option>
                <option value="status" <?= $orderBy === 'status' ? 'selected' : '' ?>>Status</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">Buscar</label>
            <input type="text" name="search" class="filter-input" placeholder="Nome, email ou NIF..." value="<?= htmlspecialchars($searchTerm) ?>">
        </div>

        <button type="submit" class="filter-btn">
            <i class="fa-solid fa-magnifying-glass"></i> Filtrar
        </button>
    </form>

    <!-- TABELA -->
    <div class="forms-table">
        <?php if ($result && $result->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>NIF</th>
                        <th>Status</th>
                        <th>Data Registro</th>
                        <th>Última Atualização</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700;"><?= htmlspecialchars($row['nome']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-main);"><?= htmlspecialchars($row['email']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($row['tax_id'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge status-<?= $row['status_documentos'] ?>">
                                    <?= strtoupper($row['status_documentos'] ?? 'pendente') ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                            <td><?= $row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-' ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-view" onclick="viewDetails(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nome']) ?>', '<?= htmlspecialchars($row['email']) ?>', '<?= htmlspecialchars($row['tax_id'] ?? '') ?>', '<?= $row['license_path'] ?? '' ?>', '<?= $row['status_documentos'] ?>', '<?= htmlspecialchars($row['motivo_rejeicao'] ?? '') ?>')">
                                        <i class="fa-solid fa-eye"></i> Ver
                                    </button>
                                    
                                    <?php if ($row['status_documentos'] === 'pendente'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Aprovar documentos desta empresa?')">
                                            <input type="hidden" name="action" value="aprovar">
                                            <input type="hidden" name="business_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-approve">
                                                <i class="fa-solid fa-check"></i> Aprovar
                                            </button>
                                        </form>
                                        
                                        <button class="btn btn-reject" onclick="showRejectModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nome']) ?>')">
                                            <i class="fa-solid fa-times"></i> Rejeitar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-folder-open"></i>
                <h3>Nenhum formulário encontrado</h3>
                <p>Não há registros com os filtros aplicados.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL DE DETALHES -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Detalhes do Formulário</h3>
            <button class="modal-close" onclick="closeModal('detailsModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div id="detailsContent"></div>
    </div>
</div>

<!-- MODAL DE REJEIÇÃO -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Rejeitar Documentos</h3>
            <button class="modal-close" onclick="closeModal('rejectModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="rejeitar">
            <input type="hidden" name="business_id" id="rejectBusinessId">
            
            <div class="form-group">
                <label class="form-label">Empresa</label>
                <input type="text" class="form-input" id="rejectBusinessName" readonly>
            </div>
            
            <div class="form-group">
                <label class="form-label">Motivo da Rejeição *</label>
                <textarea name="motivo" class="form-textarea" required placeholder="Descreva o motivo da rejeição dos documentos..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-view" onclick="closeModal('rejectModal')">Cancelar</button>
                <button type="submit" class="btn btn-reject">
                    <i class="fa-solid fa-times"></i> Rejeitar Documentos
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function viewDetails(id, nome, email, nif, licensePath, status, motivo) {
    const content = `
        <div class="form-group">
            <label class="form-label">Nome da Empresa</label>
            <div style="padding: 12px; background: rgba(0,255,136,0.05); border-radius: 10px; color: var(--text-title);">
                ${nome}
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Email</label>
            <div style="padding: 12px; background: rgba(0,255,136,0.05); border-radius: 10px; color: var(--text-title);">
                ${email}
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">NIF</label>
            <div style="padding: 12px; background: rgba(0,255,136,0.05); border-radius: 10px; color: var(--text-title);">
                ${nif || 'Não fornecido'}
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Status</label>
            <span class="status-badge status-${status}">${status.toUpperCase()}</span>
        </div>
        
        ${motivo ? `
        <div class="form-group">
            <label class="form-label">Motivo da Rejeição</label>
            <div style="padding: 12px; background: rgba(255,77,77,0.1); border: 1px solid rgba(255,77,77,0.3); border-radius: 10px; color: #ff4d4d;">
                ${motivo}
            </div>
        </div>
        ` : ''}
        
        <div class="form-group">
            <label class="form-label">Licença Comercial</label>
            ${licensePath ? `
                <a href="../../../../${licensePath}" target="_blank" class="btn btn-view" style="display: inline-flex;">
                    <i class="fa-solid fa-file-pdf"></i> Ver Documento
                </a>
            ` : '<p style="color: var(--text-main);">Documento não enviado</p>'}
        </div>
    `;
    
    document.getElementById('detailsContent').innerHTML = content;
    document.getElementById('detailsModal').classList.add('active');
}

function showRejectModal(businessId, businessName) {
    document.getElementById('rejectBusinessId').value = businessId;
    document.getElementById('rejectBusinessName').value = businessName;
    document.getElementById('rejectModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Fechar modal ao clicar fora
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if(e.target === this) {
            this.classList.remove('active');
        }
    });
});

console.log('✅ Forms module loaded!');
</script>