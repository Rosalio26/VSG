<?php
/**
 * ================================================================================
 * VISIONGREEN - PERFIL DO ADMINISTRADOR
 * Módulo: modules/pages/admin-perfil.php
 * Descrição: Visualizar perfil do admin logado ou lista de admins (superadmin)
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';

if (!in_array($adminRole, ['admin', 'superadmin'])) {
    echo '<div class="alert error">
            <i class="fa-solid fa-lock"></i>
            <div><strong>Erro:</strong> Acesso restrito apenas para Administradores.</div>
          </div>';
    exit;
}

// Verificar se é superadmin visualizando perfil de outro admin
$viewAdminId = (int)($_GET['id'] ?? 0);
$isSuperAdmin = ($adminRole === 'superadmin');
$isViewingOther = $viewAdminId && $viewAdminId !== $adminId && $isSuperAdmin;

// Se não for superadmin tentando ver outro perfil, visualiza seu próprio
if (!$isViewingOther) {
    $viewAdminId = $adminId;
}

// Buscar dados do admin
$adminSql = "SELECT 
                id, 
                public_id, 
                nome, 
                email, 
                email_corporativo, 
                role, 
                status, 
                created_at, 
                updated_at, 
                last_activity, 
                password_changed_at 
            FROM users 
            WHERE id = ? 
            AND type = 'admin' 
            AND role IN ('admin', 'superadmin') 
            AND deleted_at IS NULL";

$stmt = $mysqli->prepare($adminSql);
$stmt->bind_param("i", $viewAdminId);
$stmt->execute();
$viewAdmin = $stmt->get_result()->fetch_assoc();

if (!$viewAdmin) {
    echo '<div class="alert error">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <div><strong>Erro:</strong> Administrador não encontrado.</div>
          </div>';
    exit;
}

// Se for superadmin na página raiz, mostrar lista de admins
$showList = $isSuperAdmin && !$viewAdminId && !isset($_GET['id']);

// Calcular status online
$isOnline = false;
$lastSeenText = "Offline";
if (!empty($viewAdmin['last_activity'])) {
    $timeDiff = time() - strtotime($viewAdmin['last_activity']);
    if ($timeDiff <= 300) { 
        $isOnline = true; 
    } else {
        $minutos = round($timeDiff / 60);
        if ($minutos < 60) {
            $lastSeenText = "Visto há $minutos min";
        } else {
            $horas = round($minutos / 60);
            $lastSeenText = "Visto há " . $horas . "h";
        }
    }
}

// Calcular dias desde registro
$diasRegistro = (time() - strtotime($viewAdmin['created_at'])) / 86400;

// Buscar histórico de auditoria do admin
$historico = [];
$histSql = "
    SELECT 
        al.id,
        al.action,
        al.ip_address,
        al.details,
        al.created_at,
        u.nome as executor_nome
    FROM admin_audit_logs al
    LEFT JOIN users u ON al.admin_id = u.id
    WHERE al.admin_id = ?
    ORDER BY al.created_at DESC
    LIMIT 50
";

$histStmt = $mysqli->prepare($histSql);
$histStmt->bind_param("i", $viewAdminId);
$histStmt->execute();
$histResult = $histStmt->get_result();
while ($row = $histResult->fetch_assoc()) {
    $historico[] = $row;
}

// Buscar lista de admins para superadmin
$adminsList = [];
if ($isSuperAdmin && !isset($_GET['id'])) {
    $listSql = "SELECT 
                    id, 
                    public_id, 
                    nome, 
                    email, 
                    email_corporativo, 
                    role, 
                    status, 
                    last_activity, 
                    created_at 
                FROM users 
                WHERE type = 'admin' 
                AND role IN ('admin', 'superadmin') 
                AND deleted_at IS NULL
                ORDER BY role DESC, nome ASC";
    
    $listStmt = $mysqli->prepare($listSql);
    $listStmt->execute();
    $adminsList = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Contar ações do admin
$actionCount = count($historico);
?>

<?php if (!isset($_GET['id']) && $isSuperAdmin): ?>
    <!-- LISTA DE ADMINISTRADORES (SUPERADMIN) -->
    <div style="margin-bottom: 30px;">
        <h2 style="color: #fff; margin: 0 0 8px 0;">
            <i class="fa-solid fa-users-gear"></i>
            Administradores do Sistema
        </h2>
        <p style="color: #666; font-size: 0.85rem; margin: 0;">
            Visualize todos os administradores registrados no sistema.
        </p>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($adminsList)): ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Administrador</th>
                                <th>Cargo</th>
                                <th>Status</th>
                                <th>Disponibilidade</th>
                                <th>Data de Criação</th>
                                <th style="text-align: center;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adminsList as $admin): 
                                $isAdminOnline = false;
                                $adminLastSeenText = "Offline";
                                
                                if (!empty($admin['last_activity'])) {
                                    $adminTimeDiff = time() - strtotime($admin['last_activity']);
                                    if ($adminTimeDiff <= 300) { 
                                        $isAdminOnline = true; 
                                    } else {
                                        $adminMinutos = round($adminTimeDiff / 60);
                                        if ($adminMinutos < 60) {
                                            $adminLastSeenText = "Visto há $adminMinutos min";
                                        } else {
                                            $adminLastSeenText = "Visto há " . round($adminMinutos/60) . "h";
                                        }
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(0,255,136,0.1); display: flex; align-items: center; justify-content: center; color: var(--accent); font-weight: bold; border: 1px solid rgba(0,255,136,0.2);">
                                            <?= strtoupper(substr($admin['nome'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong style="color: #fff; display: block;">
                                                <?= htmlspecialchars($admin['nome']) ?>
                                                <?php if ($admin['id'] == $adminId): ?>
                                                    <small style="color: var(--accent);">(Você)</small>
                                                <?php endif; ?>
                                            </strong>
                                            <small style="color: #666;">
                                                <?= htmlspecialchars($admin['email_corporativo']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $admin['role'] == 'superadmin' ? 'warning' : 'info' ?>">
                                        <?= strtoupper($admin['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $admin['status'] == 'active' ? 'success' : 'error' ?>">
                                        <?= ucfirst($admin['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 8px; height: 8px; border-radius: 50%; background: <?= $isAdminOnline ? '#00ff88' : '#555' ?>;"></div>
                                        <small style="color: <?= $isAdminOnline ? '#00ff88' : '#666' ?>;">
                                            <?= $isAdminOnline ? 'ONLINE' : $adminLastSeenText ?>
                                        </small>
                                    </div>
                                </td>
                                <td style="font-size: 0.875rem; color: #666;">
                                    <?= date('d/m/Y', strtotime($admin['created_at'])) ?>
                                </td>
                                <td style="text-align: center;">
                                    <button onclick="loadContent('modules/pages/admin-perfil?id=<?= $admin['id'] ?>')" class="btn btn-sm btn-ghost" style="justify-content: center;">
                                        <i class="fa-solid fa-eye"></i>
                                        Ver Perfil
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="empty-title">Nenhum administrador encontrado</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- PERFIL DO ADMINISTRADOR -->
    <div class="detail-header">
        <div class="detail-info">
            <h1 class="detail-title">
                <i class="fa-solid fa-user-gear"></i>
                <?= htmlspecialchars($viewAdmin['nome']) ?>
            </h1>
            <div class="detail-subtitle">
                <span class="badge <?= $isOnline ? 'success' : 'neutral' ?>">
                    <i class="fa-solid fa-circle" style="font-size: 0.5rem;"></i>
                    <?= $isOnline ? 'ONLINE AGORA' : $lastSeenText ?>
                </span>
                • Cadastro há <?= round($diasRegistro) ?> dias
            </div>
        </div>
        
        <div class="detail-actions">
            <?php if ($isSuperAdmin && $isViewingOther): ?>
                <button class="btn btn-ghost" onclick="loadContent('modules/pages/admin-perfil')">
                    <i class="fa-solid fa-arrow-left"></i>
                    Voltar
                </button>
            <?php else: ?>
                <button class="btn btn-ghost" onclick="history.back()">
                    <i class="fa-solid fa-arrow-left"></i>
                    Voltar
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- DETAIL GRID -->
    <div class="detail-grid">
        
        <!-- COLUNA ESQUERDA -->
        <div>
            
            <!-- INFORMAÇÕES BÁSICAS -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-circle-info"></i>
                        Informações Básicas
                    </h3>
                </div>
                <div class="card-body">
                    <ul class="info-list">
                        <li>
                            <span class="info-label">UID (Identificador Único)</span>
                            <code style="background: rgba(35,134,54,0.1); color: var(--accent); padding: 4px 8px; border-radius: 4px; font-weight: 700;">
                                <?= htmlspecialchars($viewAdmin['public_id']) ?>
                            </code>
                        </li>
                        <li>
                            <span class="info-label">Email Pessoal</span>
                            <a href="mailto:<?= htmlspecialchars($viewAdmin['email']) ?>" style="color: var(--accent); text-decoration: none;">
                                <?= htmlspecialchars($viewAdmin['email']) ?>
                            </a>
                        </li>
                        <li>
                            <span class="info-label">Email Corporativo</span>
                            <a href="mailto:<?= htmlspecialchars($viewAdmin['email_corporativo']) ?>" style="color: var(--accent); text-decoration: none;">
                                <?= htmlspecialchars($viewAdmin['email_corporativo']) ?>
                            </a>
                        </li>
                        <li>
                            <span class="info-label">Cargo</span>
                            <span class="badge <?= $viewAdmin['role'] == 'superadmin' ? 'warning' : 'info' ?>">
                                <?= strtoupper($viewAdmin['role']) ?>
                            </span>
                        </li>
                        <li>
                            <span class="info-label">Status</span>
                            <span class="badge <?= $viewAdmin['status'] == 'active' ? 'success' : 'error' ?>">
                                <?= ucfirst($viewAdmin['status']) ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- DATAS IMPORTANTES -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-calendar"></i>
                        Datas Importantes
                    </h3>
                </div>
                <div class="card-body">
                    <ul class="info-list">
                        <li>
                            <span class="info-label">Data de Criação</span>
                            <span class="info-value"><?= date('d/m/Y H:i', strtotime($viewAdmin['created_at'])) ?></span>
                        </li>
                        <?php if ($viewAdmin['password_changed_at']): ?>
                        <li>
                            <span class="info-label">Última Mudança de Senha</span>
                            <span class="info-value"><?= date('d/m/Y H:i', strtotime($viewAdmin['password_changed_at'])) ?></span>
                        </li>
                        <?php endif; ?>
                        <li>
                            <span class="info-label">Última Atividade</span>
                            <span class="info-value"><?= $viewAdmin['last_activity'] ? date('d/m/Y H:i', strtotime($viewAdmin['last_activity'])) : 'Nunca' ?></span>
                        </li>
                        <?php if ($viewAdmin['updated_at']): ?>
                        <li>
                            <span class="info-label">Última Atualização</span>
                            <span class="info-value"><?= date('d/m/Y H:i', strtotime($viewAdmin['updated_at'])) ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- COLUNA DIREITA -->
        <div>
            
            <!-- ESTATÍSTICAS -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-chart-line"></i>
                        Estatísticas
                    </h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div style="text-align: center; padding: 20px; background: rgba(35,134,54,0.05); border-radius: 10px;">
                            <div style="font-size: 2.5rem; font-weight: 800; color: var(--accent); margin-bottom: 8px;">
                                <?= $actionCount ?>
                            </div>
                            <div style="font-size: 0.875rem; color: #666;">
                                Ações Registradas
                            </div>
                        </div>
                        <div style="text-align: center; padding: 20px; background: rgba(56,139,253,0.05); border-radius: 10px;">
                            <div style="font-size: 2.5rem; font-weight: 800; color: #58a6ff; margin-bottom: 8px;">
                                <?= round($diasRegistro) ?>
                            </div>
                            <div style="font-size: 0.875rem; color: #666;">
                                Dias de Cadastro
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HISTÓRICO DE AÇÕES -->
            <?php if (!empty($historico)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-history"></i>
                        Histórico de Ações
                    </h3>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($historico as $h): 
                            $timelineClass = 'pending';
                            if (strpos($h['action'], 'CREATE') !== false || strpos($h['action'], 'CREATED') !== false) {
                                $timelineClass = 'completed';
                            } elseif (strpos($h['action'], 'DELETE') !== false || strpos($h['action'], 'DELETED') !== false) {
                                $timelineClass = 'rejected';
                            }
                        ?>
                        <div class="timeline-item <?= $timelineClass ?>">
                            <div class="timeline-content">
                                <div class="timeline-title"><?= htmlspecialchars($h['action']) ?></div>
                                <div class="timeline-meta">
                                    <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?> • 
                                    IP: <?= htmlspecialchars($h['ip_address'] ?? 'N/A') ?>
                                </div>
                                <?php if ($h['details']): ?>
                                    <div style="margin-top: 8px; font-size: 0.75rem; color: #666;">
                                        <?php 
                                            $detailsArray = @json_decode($h['details'], true);
                                            if (is_array($detailsArray)) {
                                                foreach ($detailsArray as $key => $value) {
                                                    echo '<strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars((string)$value) . '<br>';
                                                }
                                            } else {
                                                echo htmlspecialchars($h['details']);
                                            }
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-history"></i>
                        Histórico de Ações
                    </h3>
                </div>
                <div class="card-body">
                    <div class="empty-state" style="padding: 40px 20px;">
                        <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
                        <div class="empty-title">Nenhuma ação registrada</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<style>
    .detail-header {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .detail-info {
        flex: 1;
    }

    .detail-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-title);
        margin: 0 0 8px 0;
    }

    .detail-subtitle {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .detail-actions {
        display: flex;
        gap: 12px;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
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
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--text-title);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-body {
        padding: 24px;
    }

    .mb-3 {
        margin-bottom: 20px;
    }

    .info-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .info-list li {
        padding: 12px 0;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .info-list li:last-child {
        border-bottom: none;
    }

    .info-label {
        font-size: 0.813rem;
        color: var(--text-secondary);
        font-weight: 600;
    }

    .info-value {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 600;
        text-align: right;
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

    .badge.warning {
        background: rgba(158, 106, 3, 0.15);
        color: #d29922;
    }

    .badge.info {
        background: rgba(56, 139, 253, 0.15);
        color: #58a6ff;
    }

    .badge.neutral {
        background: rgba(110, 118, 129, 0.15);
        color: #8b949e;
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

    .btn-ghost {
        background: transparent;
        color: var(--text-secondary);
        border: 1px solid var(--border);
    }

    .btn-ghost:hover {
        background: rgba(255,255,255,0.05);
        color: var(--text-primary);
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.75rem;
    }

    .timeline {
        position: relative;
        padding-left: 40px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 12px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--border);
    }

    .timeline-item {
        position: relative;
        padding-bottom: 24px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -34px;
        top: 4px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--accent);
        border: 2px solid var(--bg-card);
        z-index: 1;
    }

    .timeline-item.completed::before {
        background: var(--status-success);
    }

    .timeline-item.rejected::before {
        background: var(--status-error);
    }

    .timeline-content {
        background: var(--bg-elevated);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 12px 16px;
    }

    .timeline-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .timeline-meta {
        font-size: 0.75rem;
        color: var(--text-secondary);
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

    @media (max-width: 768px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }

        .detail-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
    }
</style>