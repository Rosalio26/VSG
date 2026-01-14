<?php
if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = $adminRole === 'super_admin';

/* ================= FILTROS E BUSCA ================= */
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'todos';
$planFilter = $_GET['plan'] ?? 'todos';
$docsFilter = $_GET['docs'] ?? 'todos';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

/* ================= CONSTRUIR QUERY ================= */
$whereConditions = ["u.type = 'company'"];
$params = [];
$types = '';

if ($searchTerm) {
    $whereConditions[] = "(u.nome LIKE ? OR u.email LIKE ? OR b.tax_id LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

if ($statusFilter !== 'todos') {
    $whereConditions[] = "us.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($planFilter !== 'todos') {
    $whereConditions[] = "us.plan_id = ?";
    $params[] = $planFilter;
    $types .= 'i';
}

if ($docsFilter !== 'todos') {
    $whereConditions[] = "b.status_documentos = ?";
    $params[] = $docsFilter;
    $types .= 's';
}

$whereClause = implode(' AND ', $whereConditions);

// Validar sort column
$allowedSorts = ['nome', 'email', 'created_at', 'mrr', 'health_score'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'created_at';
}

$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

/* ================= CONTAR TOTAL ================= */
$countSql = "
    SELECT COUNT(DISTINCT u.id) as total
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    LEFT JOIN user_subscriptions us ON u.id = us.user_id
    WHERE $whereClause
";

$countStmt = $mysqli->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $perPage);

/* ================= BUSCAR EMPRESAS ================= */
$sql = "
    SELECT 
        u.id,
        u.nome,
        u.email,
        u.created_at,
        b.tax_id,
        b.status_documentos,
        b.motivo_rejeicao,
        b.license_path,
        us.status as subscription_status,
        us.mrr,
        us.next_billing_date,
        sp.name as plan_name,
        sp.price as plan_price,
        chs.score as health_score,
        chs.risk_level,
        chs.churn_probability
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    LEFT JOIN user_subscriptions us ON u.id = us.user_id
    LEFT JOIN subscription_plans sp ON us.plan_id = sp.id
    LEFT JOIN company_health_score chs ON u.id = chs.user_id
    WHERE $whereClause
    ORDER BY u.$sortBy $sortOrder
    LIMIT $perPage OFFSET $offset
";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$companies = $stmt->get_result();

/* ================= BUSCAR PLANOS PARA FILTRO ================= */
$plans = $mysqli->query("SELECT id, name FROM subscription_plans WHERE is_active = 1 ORDER BY price ASC");

/* ================= ESTATÍSTICAS RÁPIDAS ================= */
$stats = $mysqli->query("
    SELECT 
        COUNT(DISTINCT u.id) as total,
        COUNT(DISTINCT CASE WHEN us.status = 'active' THEN u.id END) as active,
        COUNT(DISTINCT CASE WHEN b.status_documentos = 'pendente' THEN u.id END) as pending_docs,
        COUNT(DISTINCT CASE WHEN chs.risk_level IN ('high', 'critical') THEN u.id END) as high_risk
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    LEFT JOIN user_subscriptions us ON u.id = us.user_id
    LEFT JOIN company_health_score chs ON u.id = chs.user_id
    WHERE u.type = 'company'
")->fetch_assoc();
?>

<style>
:root {
    --bg-page: #0d1117;
    --bg-card: #161b22;
    --bg-elevated: #21262d;
    --bg-hover: #30363d;
    --text-primary: #c9d1d9;
    --text-secondary: #8b949e;
    --text-muted: #6e7681;
    --accent: #238636;
    --border: #30363d;
    --success: #238636;
    --warning: #9e6a03;
    --error: #da3633;
    --info: #388bfd;
}

* {
    box-sizing: border-box;
}

.lista-container {
    padding: 24px;
    background: var(--bg-page);
    min-height: 100vh;
}

/* ========== HEADER ========== */
.page-header {
    margin-bottom: 32px;
    padding-bottom: 20px;
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

.header-actions {
    display: flex;
    gap: 12px;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--bg-card);
    color: var(--text-primary);
    border: 1px solid var(--border);
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.btn-back:hover {
    background: var(--bg-elevated);
    border-color: var(--accent);
}

.btn-add {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-add:hover {
    background: #2ea043;
}

/* ========== STATS ROW ========== */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.stat-mini {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    text-align: center;
}

.stat-mini-label {
    font-size: 0.813rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.stat-mini-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--accent);
}

/* ========== FILTERS BAR ========== */
.filters-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
}

.filters-top {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    min-width: 250px;
}

.search-input {
    width: 100%;
    padding: 10px 16px 10px 40px;
    background: var(--bg-page);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 0.875rem;
    transition: all 0.2s;
}

.search-input:focus {
    outline: none;
    border-color: var(--accent);
}

.search-box {
    position: relative;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
}

.filter-select {
    padding: 10px 12px;
    background: var(--bg-page);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 0.875rem;
    cursor: pointer;
    min-width: 150px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--accent);
}

.filters-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.results-info {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.bulk-actions {
    display: none;
    align-items: center;
    gap: 12px;
}

.bulk-actions.active {
    display: flex;
}

.bulk-select-info {
    font-size: 0.875rem;
    color: var(--text-primary);
    font-weight: 600;
}

.btn-bulk {
    padding: 8px 14px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 0.813rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-bulk:hover {
    background: var(--bg-hover);
}

.btn-bulk.danger:hover {
    background: var(--error);
    border-color: var(--error);
    color: #fff;
}

/* ========== TABLE ========== */
.table-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 24px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: var(--bg-elevated);
}

th {
    padding: 14px 16px;
    text-align: left;
    font-size: 0.813rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

th.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    padding-right: 28px;
}

th.sortable:hover {
    color: var(--text-primary);
}

th.sortable::after {
    content: '⇅';
    position: absolute;
    right: 8px;
    opacity: 0.3;
}

th.sortable.asc::after {
    content: '↑';
    opacity: 1;
    color: var(--accent);
}

th.sortable.desc::after {
    content: '↓';
    opacity: 1;
    color: var(--accent);
}

tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}

tbody tr:hover {
    background: var(--bg-elevated);
}

tbody tr.selected {
    background: rgba(35, 134, 54, 0.1);
}

td {
    padding: 16px;
    font-size: 0.875rem;
    color: var(--text-primary);
}

td.checkbox-cell {
    width: 40px;
    padding: 16px 8px;
}

input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent);
}

/* ========== BADGES ========== */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.badge.success {
    background: rgba(35, 134, 54, 0.15);
    color: #7ee787;
}

.badge.warning {
    background: rgba(158, 106, 3, 0.15);
    color: #f0c065;
}

.badge.error {
    background: rgba(218, 54, 51, 0.15);
    color: #ff7b72;
}

.badge.info {
    background: rgba(56, 139, 253, 0.15);
    color: #58a6ff;
}

.badge.neutral {
    background: rgba(139, 148, 158, 0.15);
    color: #8b949e;
}

/* ========== HEALTH SCORE ========== */
.health-mini {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
}

.health-mini.excellent {
    background: rgba(35, 134, 54, 0.15);
    color: #7ee787;
}

.health-mini.good {
    background: rgba(56, 139, 253, 0.15);
    color: #58a6ff;
}

.health-mini.warning {
    background: rgba(158, 106, 3, 0.15);
    color: #f0c065;
}

.health-mini.critical {
    background: rgba(218, 54, 51, 0.15);
    color: #ff7b72;
}

/* ========== ACTIONS ========== */
.actions-cell {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.875rem;
}

.btn-icon:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
    border-color: var(--accent);
}

.btn-icon.danger:hover {
    background: var(--error);
    border-color: var(--error);
    color: #fff;
}

/* ========== PAGINATION ========== */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    padding: 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
}

.page-btn {
    min-width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.875rem;
    text-decoration: none;
}

.page-btn:hover {
    background: var(--bg-hover);
    border-color: var(--accent);
}

.page-btn.active {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
    font-weight: 600;
}

.page-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.page-info {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0 12px;
}

/* ========== EMPTY STATE ========== */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 16px;
    opacity: 0.3;
}

.empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.empty-description {
    font-size: 0.938rem;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .filters-top {
        flex-direction: column;
    }
    
    .search-box,
    .filter-select {
        width: 100%;
    }
    
    table {
        font-size: 0.813rem;
    }
    
    th, td {
        padding: 12px 8px;
    }
    
    .btn-icon {
        width: 28px;
        height: 28px;
    }
}
</style>

<div class="lista-container">
    <div class="page-header">
        <div class="header-top">
            <h1 class="header-title">🏢 Lista de Empresas</h1>
            <div class="header-actions">
                <a href="javascript:loadContent('modules/tabelas/tabelas')" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </a>
                <button class="btn-add" onclick="loadContent('modules/forms/form-input')">
                    <i class="fa-solid fa-plus"></i> Nova Empresa
                </button>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-mini">
            <div class="stat-mini-label">Total</div>
            <div class="stat-mini-value"><?= number_format($stats['total'], 0) ?></div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Ativas</div>
            <div class="stat-mini-value"><?= number_format($stats['active'], 0) ?></div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Docs Pendentes</div>
            <div class="stat-mini-value"><?= number_format($stats['pending_docs'], 0) ?></div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Alto Risco</div>
            <div class="stat-mini-value"><?= number_format($stats['high_risk'], 0) ?></div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filters-bar">
        <form id="filterForm" method="GET" onsubmit="return false;">
            <div class="filters-top">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" name="search" id="searchInput" class="search-input" 
                           placeholder="Buscar por nome, email ou NIF..." 
                           value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
                
                <select name="status" id="statusFilter" class="filter-select">
                    <option value="todos">Status: Todos</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativas</option>
                    <option value="trial" <?= $statusFilter === 'trial' ? 'selected' : '' ?>>Trial</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Canceladas</option>
                    <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expiradas</option>
                </select>
                
                <select name="plan" id="planFilter" class="filter-select">
                    <option value="todos">Plano: Todos</option>
                    <?php while ($plan = $plans->fetch_assoc()): ?>
                        <option value="<?= $plan['id'] ?>" <?= $planFilter == $plan['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($plan['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <select name="docs" id="docsFilter" class="filter-select">
                    <option value="todos">Docs: Todos</option>
                    <option value="pendente" <?= $docsFilter === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="aprovado" <?= $docsFilter === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                    <option value="rejeitado" <?= $docsFilter === 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                </select>
            </div>
            
            <div class="filters-bottom">
                <div class="results-info">
                    Exibindo <?= $companies->num_rows ?> de <?= number_format($totalRecords, 0) ?> empresas
                </div>
                
                <div class="bulk-actions" id="bulkActions">
                    <span class="bulk-select-info" id="bulkCount">0 selecionadas</span>
                    <button type="button" class="btn-bulk" onclick="bulkApprove()">
                        <i class="fa-solid fa-check"></i> Aprovar
                    </button>
                    <button type="button" class="btn-bulk danger" onclick="bulkDelete()">
                        <i class="fa-solid fa-trash"></i> Deletar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-wrapper">
            <table id="companiesTable">
                <thead>
                    <tr>
                        <th class="checkbox-cell">
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th class="sortable <?= $sortBy === 'nome' ? strtolower($sortOrder) : '' ?>" 
                            onclick="sortTable('nome')">
                            Empresa
                        </th>
                        <th>Contato</th>
                        <th class="sortable <?= $sortBy === 'created_at' ? strtolower($sortOrder) : '' ?>" 
                            onclick="sortTable('created_at')">
                            Cadastro
                        </th>
                        <th>Plano</th>
                        <th class="sortable <?= $sortBy === 'mrr' ? strtolower($sortOrder) : '' ?>" 
                            onclick="sortTable('mrr')">
                            MRR
                        </th>
                        <th>Health</th>
                        <th>Docs</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($companies->num_rows > 0): ?>
                        <?php while ($company = $companies->fetch_assoc()): ?>
                            <?php
                            $healthScore = $company['health_score'] ?? 50;
                            $healthClass = $healthScore >= 80 ? 'excellent' : ($healthScore >= 60 ? 'good' : ($healthScore >= 40 ? 'warning' : 'critical'));
                            
                            $statusClass = [
                                'active' => 'success',
                                'trial' => 'info',
                                'cancelled' => 'error',
                                'expired' => 'neutral'
                            ][$company['subscription_status'] ?? 'expired'] ?? 'neutral';
                            
                            $statusText = [
                                'active' => 'Ativa',
                                'trial' => 'Trial',
                                'cancelled' => 'Cancelada',
                                'expired' => 'Sem Assinatura'
                            ][$company['subscription_status'] ?? 'expired'] ?? 'Inativa';
                            
                            $docsClass = [
                                'aprovado' => 'success',
                                'pendente' => 'warning',
                                'rejeitado' => 'error'
                            ][$company['status_documentos'] ?? 'pendente'] ?? 'neutral';
                            
                            $docsText = [
                                'aprovado' => 'Aprovado',
                                'pendente' => 'Pendente',
                                'rejeitado' => 'Rejeitado'
                            ][$company['status_documentos'] ?? 'pendente'] ?? 'N/A';
                            ?>
                            <tr data-id="<?= $company['id'] ?>">
                                <td class="checkbox-cell">
                                    <input type="checkbox" class="row-checkbox" value="<?= $company['id'] ?>">
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($company['nome']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        NIF: <?= htmlspecialchars($company['tax_id'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($company['email']) ?></div>
                                </td>
                                <td>
                                    <div><?= date('d/m/Y', strtotime($company['created_at'])) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?= date('H:i', strtotime($company['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($company['plan_name']): ?>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($company['plan_name']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                                            <?= number_format($company['plan_price'], 0) ?> MT/mês
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--accent);">
                                        <?= $company['mrr'] ? number_format($company['mrr'], 0) . ' MT' : '-' ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="health-mini <?= $healthClass ?>">
                                        <?= $healthScore ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $docsClass ?>">
                                        <?= $docsText ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $statusClass ?>">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <button class="btn-icon" onclick="viewCompany(<?= $company['id'] ?>)" title="Ver Detalhes">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button class="btn-icon" onclick="editCompany(<?= $company['id'] ?>)" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <?php if ($isSuperAdmin): ?>
                                            <button class="btn-icon danger" onclick="deleteCompany(<?= $company['id'] ?>, '<?= htmlspecialchars($company['nome']) ?>')" title="Deletar">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fa-solid fa-inbox"></i>
                                    </div>
                                    <div class="empty-title">Nenhuma empresa encontrada</div>
                                    <div class="empty-description">
                                        <?= $searchTerm ? 'Tente ajustar os filtros de busca' : 'Comece adicionando uma nova empresa' ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <a href="javascript:void(0)" 
               class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" 
               onclick="<?= $page > 1 ? "goToPage(" . ($page - 1) . ")" : 'return false' ?>">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1): ?>
                <a href="javascript:void(0)" class="page-btn" onclick="goToPage(1)">1</a>
                <?php if ($startPage > 2): ?>
                    <span class="page-info">...</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="javascript:void(0)" 
                   class="page-btn <?= $i === $page ? 'active' : '' ?>" 
                   onclick="goToPage(<?= $i ?>)">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <span class="page-info">...</span>
                <?php endif; ?>
                <a href="javascript:void(0)" class="page-btn" onclick="goToPage(<?= $totalPages ?>)"><?= $totalPages ?></a>
            <?php endif; ?>
            
            <a href="javascript:void(0)" 
               class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" 
               onclick="<?= $page < $totalPages ? "goToPage(" . ($page + 1) . ")" : 'return false' ?>">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            
            <span class="page-info">Página <?= $page ?> de <?= $totalPages ?></span>
        </div>
    <?php endif; ?>
</div>

<script>
// Aplicar filtros
function applyFilters() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    params.append('sort', '<?= $sortBy ?>');
    params.append('order', '<?= $sortOrder ?>');
    
    for (let [key, value] of formData.entries()) {
        if (value && value !== 'todos') {
            params.append(key, value);
        }
    }
    
    const url = 'modules/tabelas/lista-empresas' + (params.toString() ? '?' + params.toString() : '');
    console.log('🔍 Aplicando filtros:', url);
    loadContent(url);
}

// Busca com delay
let searchTimeout;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 500);
});

// Filtros mudaram
document.getElementById('statusFilter').addEventListener('change', applyFilters);
document.getElementById('planFilter').addEventListener('change', applyFilters);
document.getElementById('docsFilter').addEventListener('change', applyFilters);

// Ordenação
function sortTable(column) {
    const currentSort = '<?= $sortBy ?>';
    const currentOrder = '<?= $sortOrder ?>';
    
    let newOrder = 'DESC';
    if (column === currentSort) {
        newOrder = currentOrder === 'DESC' ? 'ASC' : 'DESC';
    }
    
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    params.append('sort', column);
    params.append('order', newOrder);
    
    for (let [key, value] of formData.entries()) {
        if (value && value !== 'todos') {
            params.append(key, value);
        }
    }
    
    const url = 'modules/tabelas/lista-empresas?' + params.toString();
    loadContent(url);
}

// Paginação
function goToPage(page) {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    params.append('page', page);
    params.append('sort', '<?= $sortBy ?>');
    params.append('order', '<?= $sortOrder ?>');
    
    for (let [key, value] of formData.entries()) {
        if (value && value !== 'todos') {
            params.append(key, value);
        }
    }
    
    const url = 'modules/tabelas/lista-empresas?' + params.toString();
    loadContent(url);
}

// Select All
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = this.checked;
        cb.closest('tr').classList.toggle('selected', this.checked);
    });
    updateBulkActions();
});

// Individual checkbox
document.querySelectorAll('.row-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        this.closest('tr').classList.toggle('selected', this.checked);
        updateBulkActions();
    });
});

// Atualizar bulk actions
function updateBulkActions() {
    const selected = document.querySelectorAll('.row-checkbox:checked');
    const bulkActions = document.getElementById('bulkActions');
    const bulkCount = document.getElementById('bulkCount');
    
    if (selected.length > 0) {
        bulkActions.classList.add('active');
        bulkCount.textContent = selected.length + ' selecionada' + (selected.length > 1 ? 's' : '');
    } else {
        bulkActions.classList.remove('active');
    }
}

// Ver detalhes
function viewCompany(id) {
    loadContent('modules/tabelas/tabela-geral');
    setTimeout(() => {
        // Tentar abrir o modal (se a função existir)
        if (typeof openCompanyDetails === 'function') {
            openCompanyDetails(id, 'Empresa');
        }
    }, 500);
}

// Editar
function editCompany(id) {
    loadContent('modules/forms/form-input?edit=' + id);
}

// Deletar
function deleteCompany(id, name) {
    if (confirm('Tem certeza que deseja deletar ' + name + '?\n\nEsta ação não pode ser desfeita!')) {
        console.log('🗑️ Deletar empresa:', id);
        // Implementar AJAX para deletar
        alert('Funcionalidade em desenvolvimento');
    }
}

// Bulk approve
function bulkApprove() {
    const selected = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
    if (confirm('Aprovar documentos de ' + selected.length + ' empresa(s)?')) {
        console.log('✅ Aprovar em massa:', selected);
        alert('Funcionalidade em desenvolvimento');
    }
}

// Bulk delete
function bulkDelete() {
    const selected = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
    if (confirm('ATENÇÃO: Deletar ' + selected.length + ' empresa(s)?\n\nEsta ação não pode ser desfeita!')) {
        console.log('🗑️ Deletar em massa:', selected);
        alert('Funcionalidade em desenvolvimento');
    }
}

console.log('✅ Lista-empresas.php carregado!');
</script>