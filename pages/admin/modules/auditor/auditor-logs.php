<?php
/**
 * ================================================================================
 * VISIONGREEN - LOGS DE AUDITORIA DO AUDITOR
 * Módulo: modules/auditor/auditor_logs.php
 * Descrição: Visualizar e gerenciar logs de ações dos auditores
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';

if ($adminRole !== 'superadmin') {
    echo '<div class="alert error">
            <i class="fa-solid fa-lock"></i>
            <div><strong>Erro:</strong> Acesso restrito apenas para Superadministradores.</div>
          </div>';
    exit;
}

// Parâmetros de paginação e filtros
$page = (int)($_GET['page'] ?? 1);
$perPage = 50;
$offset = ($page - 1) * $perPage;
$auditorId = (int)($_GET['auditor_id'] ?? 0);
$action = $_GET['action'] ?? '';
$search = trim($_GET['search'] ?? '');

// Construir query base
$whereConditions = [];
$params = [];
$types = '';

if ($auditorId) {
    $whereConditions[] = 'al.admin_id = ?';
    $params[] = $auditorId;
    $types .= 'i';
}

if ($action) {
    $whereConditions[] = 'al.action LIKE ?';
    $params[] = '%' . $action . '%';
    $types .= 's';
}

if ($search) {
    $whereConditions[] = '(al.action LIKE ? OR al.ip_address LIKE ? OR u.nome LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types .= 'ssss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Contar total de registros
$countSql = "
    SELECT COUNT(*) as total
    FROM admin_audit_logs al
    LEFT JOIN users u ON al.admin_id = u.id
    $whereClause
";

$countStmt = $mysqli->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$totalRecords = $countResult['total'];
$totalPages = ceil($totalRecords / $perPage);

// Validar página
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}

// Buscar logs
$sql = "
    SELECT 
        al.id,
        al.admin_id,
        al.action,
        al.ip_address,
        al.user_agent,
        al.details,
        al.created_at,
        u.nome,
        u.public_id,
        u.email
    FROM admin_audit_logs al
    LEFT JOIN users u ON al.admin_id = u.id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logsResult = $stmt->get_result();
$logs = [];
while ($row = $logsResult->fetch_assoc()) {
    $logs[] = $row;
}

// Buscar lista de auditores para filtro
$auditoresStmt = $mysqli->prepare("
    SELECT id, nome, public_id 
    FROM users 
    WHERE type = 'admin' AND role IN ('admin', 'superadmin') 
    AND deleted_at IS NULL
    ORDER BY nome ASC
");
$auditoresStmt->execute();
$auditores = $auditoresStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Lista de ações para filtro
$actions = [
    'CREATE_AUDITOR' => 'Criar Auditor',
    'UPDATE_AUDITOR' => 'Atualizar Auditor',
    'DELETE_AUDITOR' => 'Deletar Auditor',
    'RESET_PASSWORD' => 'Resetar Senha',
    'LOGIN' => 'Login',
    'LOGOUT' => 'Logout'
];
?>

<!-- PAGE HEADER -->
<div style="margin-bottom: 30px;">
    <h2 style="color: #fff; margin: 0 0 8px 0;">
        <i class="fa-solid fa-receipt"></i>
        Logs de Auditoria
    </h2>
    <p style="color: #666; font-size: 0.85rem; margin: 0;">
        Visualize todas as ações realizadas pelos auditores no sistema.
    </p>
</div>


<!-- TABELA DE LOGS -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($logs)): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Auditor</th>
                            <th>Ação</th>
                            <th>Endereço IP</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-size: 0.875rem; color: #666;">
                                    <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #fff;">
                                        <?= htmlspecialchars($log['nome'] ?? 'Sistema') ?>
                                    </div>
                                    <small style="color: #666;">
                                        <?= htmlspecialchars($log['public_id'] ?? 'N/A') ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge <?= strpos($log['action'], 'DELETE') !== false ? 'error' : (strpos($log['action'], 'CREATE') !== false ? 'success' : 'info') ?>">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;">
                                        <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                    </code>
                                </td>
                                <td>
                                    <?php 
                                        $details = $log['details'];
                                        if ($details) {
                                            $detailsArray = @json_decode($details, true);
                                            if (is_array($detailsArray)) {
                                                echo '<small style="color: #666;">';
                                                foreach ($detailsArray as $key => $value) {
                                                    echo '<strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars((string)$value) . '<br>';
                                                }
                                                echo '</small>';
                                            } else {
                                                echo '<small style="color: #666;">' . htmlspecialchars($details) . '</small>';
                                            }
                                        } else {
                                            echo '<small style="color: #999;">Sem detalhes</small>';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINAÇÃO -->
            <?php if ($totalPages > 1): ?>
            <div style="padding: 20px; text-align: center; border-top: 1px solid var(--border);">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <button onclick="irParaPagina(1)" class="page-btn">Primeira</button>
                        <button onclick="irParaPagina(<?= $page - 1 ?>)" class="page-btn"><i class="fa-solid fa-chevron-left"></i></button>
                    <?php endif; ?>
                    
                    <span class="page-info">Página <?= $page ?> de <?= $totalPages ?></span>
                    
                    <?php if ($page < $totalPages): ?>
                        <button onclick="irParaPagina(<?= $page + 1 ?>)" class="page-btn"><i class="fa-solid fa-chevron-right"></i></button>
                        <button onclick="irParaPagina(<?= $totalPages ?>)" class="page-btn">Última</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
                <div class="empty-title">Nenhum log encontrado</div>
                <div class="empty-description">
                    <?php if ($search || $auditorId || $action): ?>
                        Nenhum resultado para os filtros selecionados.
                    <?php else: ?>
                        Nenhuma ação registrada ainda.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .form-group {
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

    .badge.error {
        background: rgba(248, 81, 73, 0.15);
        color: #f85149;
    }

    .badge.info {
        background: rgba(56, 139, 253, 0.15);
        color: #58a6ff;
    }

    .table-wrapper {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table thead {
        background: var(--bg-elevated);
    }

    .table th {
        padding: 14px 16px;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-secondary);
        text-transform: uppercase;
        border-bottom: 1px solid var(--border);
    }

    .table td {
        padding: 16px;
        font-size: 0.875rem;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
    }

    .table tbody tr:hover {
        background: var(--bg-elevated);
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    .page-btn {
        min-width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.875rem;
        text-decoration: none;
        font-weight: 600;
        margin: 0 4px;
    }

    .page-btn:hover {
        background: var(--bg-elevated);
        border-color: var(--accent);
        color: var(--text-primary);
    }

    .page-info {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0 12px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-secondary);
    }

    .empty-icon {
        font-size: 4rem;
        opacity: 0.3;
        margin-bottom: 20px;
    }

    .empty-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .empty-description {
        font-size: 0.938rem;
        color: var(--text-secondary);
    }

    .card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .card-body {
        padding: 20px;
    }

    .mb-3 {
        margin-bottom: 20px;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
    }
</style>

<script>
    function aplicarFiltros() {
        const search = document.getElementById('searchInput').value;
        const auditorId = document.getElementById('auditorSelect').value;
        const action = document.getElementById('actionSelect').value;
        
        let url = 'modules/auditor/auditor-logs';
        let params = [];
        
        if (search) params.push('search=' + encodeURIComponent(search));
        if (auditorId) params.push('auditor_id=' + auditorId);
        if (action) params.push('action=' + action);
        
        if (params.length > 0) {
            url += '?' + params.join('&');
        }
        
        loadContent(url);
    }
    
    function limparFiltros() {
        document.getElementById('searchInput').value = '';
        document.getElementById('auditorSelect').value = '';
        document.getElementById('actionSelect').value = '';
        loadContent('modules/auditor/auditor-logs');
    }
    
    function irParaPagina(pageNum) {
        const search = document.getElementById('searchInput').value;
        const auditorId = document.getElementById('auditorSelect').value;
        const action = document.getElementById('actionSelect').value;
        
        let url = 'modules/auditor/auditor-logs';
        let params = [];
        
        params.push('page=' + pageNum);
        if (search) params.push('search=' + encodeURIComponent(search));
        if (auditorId) params.push('auditor_id=' + auditorId);
        if (action) params.push('action=' + action);
        
        url += '?' + params.join('&');
        loadContent(url);
    }
</script>