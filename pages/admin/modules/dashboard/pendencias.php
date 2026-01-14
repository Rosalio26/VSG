<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - PENDÊNCIAS COM FILTROS DE DATA
 * Módulo: modules/dashboard/pendencias.php
 * Descrição: Central de pendências com filtros temporais
 * Dados: Direto do banco de dados (agora até o passado)
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= FILTROS DE DATA ================= */
$filtro_docs = $_GET['filtro_docs'] ?? 'all'; // all, hoje, 7dias, 30dias
$filtro_users = $_GET['filtro_users'] ?? 'all';
$filtro_alerts = $_GET['filtro_alerts'] ?? 'all';

/* ================= CONSTRUIR CONDIÇÕES DE DATA ================= */

// Função helper para construir WHERE de data
function getDateCondition($filtro, $column = 'created_at', $reverse = false) {
    switch($filtro) {
        case 'hoje':
            return $reverse 
                ? "DATE($column) = CURDATE()" 
                : "DATE($column) = CURDATE()";
        case '7dias':
            return $reverse
                ? "$column >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
                : "$column >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        case '30dias':
            return $reverse
                ? "$column >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
                : "$column >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        case 'all':
        default:
            return "1=1"; // Sem filtro
    }
}

/* ================= 1. DOCUMENTOS PENDENTES ================= */
$where_docs = getDateCondition($filtro_docs, 'u.created_at');

$sql_docs = "
    SELECT 
        b.id,
        b.user_id,
        u.nome as empresa_nome,
        u.email,
        u.telefone,
        b.status_documentos,
        b.license_path,
        b.tax_id,
        u.created_at,
        DATEDIFF(NOW(), u.created_at) as dias_pendente
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'pendente'
    AND u.deleted_at IS NULL
    AND $where_docs
    ORDER BY u.created_at ASC
";
$result_docs = $mysqli->query($sql_docs);

// Contador
$count_docs = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'pendente'
    AND u.deleted_at IS NULL
    AND $where_docs
")->fetch_assoc()['total'];

/* ================= 2. USUÁRIOS NOVOS SEM APROVAÇÃO ================= */
$where_users = getDateCondition($filtro_users, 'u.created_at');

$sql_users = "
    SELECT 
        u.id,
        u.nome,
        u.email,
        u.telefone,
        u.type,
        u.status,
        u.is_in_lockdown,
        u.email_verified_at,
        u.created_at,
        DATEDIFF(NOW(), u.created_at) as dias_registrado,
        b.status_documentos
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    WHERE u.type = 'company' 
    AND (b.status_documentos IS NULL OR b.status_documentos = 'pendente')
    AND u.deleted_at IS NULL
    AND $where_users
    ORDER BY u.created_at DESC
";
$result_users = $mysqli->query($sql_users);

// Contador
$count_users = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    WHERE u.type = 'company' 
    AND (b.status_documentos IS NULL OR b.status_documentos = 'pendente')
    AND u.deleted_at IS NULL
    AND $where_users
")->fetch_assoc()['total'];

/* ================= 3. ALERTAS CRÍTICOS NÃO LIDOS ================= */
$where_alerts = getDateCondition($filtro_alerts, 'n.created_at');

$sql_alerts = "
    SELECT 
        n.id,
        n.subject,
        n.message,
        n.category,
        n.priority,
        n.created_at,
        u.nome as sender_name,
        DATEDIFF(NOW(), n.created_at) as dias_aberto
    FROM notifications n
    LEFT JOIN users u ON n.sender_id = u.id
    WHERE n.receiver_id = ? 
    AND n.status = 'unread'
    AND n.category IN ('alert', 'security', 'system_error')
    AND $where_alerts
    ORDER BY 
        FIELD(n.priority, 'critical', 'high', 'medium', 'low'),
        n.created_at DESC
";
$stmt_alerts = $mysqli->prepare($sql_alerts);
$stmt_alerts->bind_param("i", $adminId);
$stmt_alerts->execute();
$result_alerts = $stmt_alerts->get_result();

// Contador
$count_alerts_query = "
    SELECT COUNT(*) as total 
    FROM notifications n
    WHERE n.receiver_id = $adminId 
    AND n.status = 'unread'
    AND n.category IN ('alert', 'security', 'system_error')
    AND $where_alerts
";
$count_alerts = $mysqli->query($count_alerts_query)->fetch_assoc()['total'];

/* ================= 4. TOTAL DE PENDÊNCIAS ================= */
$total_pendencias = $count_docs + $count_users + $count_alerts;

/* ================= 5. ESTATÍSTICAS POR PERÍODO ================= */

// Documentos por período
$stats_docs = [
    'hoje' => $mysqli->query("SELECT COUNT(*) as total FROM businesses b INNER JOIN users u ON b.user_id = u.id WHERE b.status_documentos = 'pendente' AND u.deleted_at IS NULL AND DATE(u.created_at) = CURDATE()")->fetch_assoc()['total'],
    '7dias' => $mysqli->query("SELECT COUNT(*) as total FROM businesses b INNER JOIN users u ON b.user_id = u.id WHERE b.status_documentos = 'pendente' AND u.deleted_at IS NULL AND u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['total'],
    '30dias' => $mysqli->query("SELECT COUNT(*) as total FROM businesses b INNER JOIN users u ON b.user_id = u.id WHERE b.status_documentos = 'pendente' AND u.deleted_at IS NULL AND u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['total'],
    'all' => $count_docs
];

// Usuários por período
$stats_users = [
    'hoje' => $mysqli->query("SELECT COUNT(*) as total FROM users u LEFT JOIN businesses b ON u.id = b.user_id WHERE u.type = 'company' AND (b.status_documentos IS NULL OR b.status_documentos = 'pendente') AND u.deleted_at IS NULL AND DATE(u.created_at) = CURDATE()")->fetch_assoc()['total'],
    '7dias' => $mysqli->query("SELECT COUNT(*) as total FROM users u LEFT JOIN businesses b ON u.id = b.user_id WHERE u.type = 'company' AND (b.status_documentos IS NULL OR b.status_documentos = 'pendente') AND u.deleted_at IS NULL AND u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['total'],
    '30dias' => $mysqli->query("SELECT COUNT(*) as total FROM users u LEFT JOIN businesses b ON u.id = b.user_id WHERE u.type = 'company' AND (b.status_documentos IS NULL OR b.status_documentos = 'pendente') AND u.deleted_at IS NULL AND u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['total'],
    'all' => $count_users
];

// Alertas por período
$stats_alerts = [
    'hoje' => $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = $adminId AND status = 'unread' AND category IN ('alert', 'security', 'system_error') AND DATE(created_at) = CURDATE()")->fetch_assoc()['total'],
    '7dias' => $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = $adminId AND status = 'unread' AND category IN ('alert', 'security', 'system_error') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['total'],
    '30dias' => $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = $adminId AND status = 'unread' AND category IN ('alert', 'security', 'system_error') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['total'],
    'all' => $count_alerts
];

?>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.05); }
}
</style>

<!-- HEADER -->
<div style="margin-bottom: 32px;">
    <h1 style="color: var(--text-title); font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">
        <i class="fa-solid fa-clipboard-list" style="color: var(--accent);"></i>
        Pendências
        <span class="badge <?= $total_pendencias > 0 ? 'error' : 'success' ?>" style="margin-left: 12px; font-size: 1.2rem; <?= $total_pendencias > 10 ? 'animation: pulse 2s infinite;' : '' ?>">
            <?= $total_pendencias ?>
        </span>
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.938rem;">
        Tarefas e documentos que requerem sua atenção
    </p>
</div>

<!-- GRID DE CARDS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 24px;">
    
    <!-- ===== CARD 1: DOCUMENTOS PENDENTES ===== -->
    <div class="card" data-category="documentos">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">
                <i class="fa-solid fa-file-circle-check" style="color: var(--accent);"></i>
                Documentos Pendentes
            </h3>
            <span class="badge <?= $count_docs > 5 ? 'error' : ($count_docs > 0 ? 'warning' : 'success') ?>" style="font-size: 1rem;">
                <?= $count_docs ?>
            </span>
        </div>
        
        <!-- FILTROS DE DATA -->
        <div class="card-body" style="padding: 16px; border-bottom: 1px solid var(--border);">
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button class="btn btn-sm <?= $filtro_docs === 'all' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarDocs('all')">
                    Todos (<?= $stats_docs['all'] ?>)
                </button>
                <button class="btn btn-sm <?= $filtro_docs === 'hoje' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarDocs('hoje')">
                    Hoje (<?= $stats_docs['hoje'] ?>)
                </button>
                <button class="btn btn-sm <?= $filtro_docs === '7dias' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarDocs('7dias')">
                    7 dias (<?= $stats_docs['7dias'] ?>)
                </button>
                <button class="btn btn-sm <?= $filtro_docs === '30dias' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarDocs('30dias')">
                    30 dias (<?= $stats_docs['30dias'] ?>)
                </button>
            </div>
        </div>
        
        <!-- TABELA -->
        <div class="card-body" style="padding: 0;">
            <?php if ($result_docs && $result_docs->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Cadastrado</th>
                                <th>Tempo Pendente</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($doc = $result_docs->fetch_assoc()): ?>
                                <tr onclick="loadContent('modules/dashboard/analise?id=<?= $doc['user_id'] ?>')" style="cursor: pointer;">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 40px; height: 40px; border-radius: 8px; background: var(--accent)20; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--accent);">
                                                <i class="fa-solid fa-building"></i>
                                            </div>
                                            <div>
                                                <strong style="color: var(--text-primary);"><?= htmlspecialchars($doc['empresa_nome']) ?></strong><br>
                                                <small style="color: var(--text-muted); font-size: 0.75rem;"><?= htmlspecialchars($doc['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="color: var(--text-secondary); font-size: 0.875rem;">
                                        <?= date('d/m/Y', strtotime($doc['created_at'])) ?>
                                        <br>
                                        <small style="color: var(--text-muted); font-size: 0.75rem;">
                                            <?= date('H:i', strtotime($doc['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $doc['dias_pendente'] > 7 ? 'error' : ($doc['dias_pendente'] > 3 ? 'warning' : 'neutral') ?>" <?= $doc['dias_pendente'] > 7 ? 'style="animation: pulse 2s infinite;"' : '' ?>>
                                            <i class="fa-solid fa-clock"></i>
                                            <?= $doc['dias_pendente'] ?> dia<?= $doc['dias_pendente'] != 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-icon btn-ghost" title="Analisar Documentos">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fa-solid fa-check-circle"></i>
                    </div>
                    <div class="empty-title">Nenhum documento pendente</div>
                    <div class="empty-description">
                        <?php if ($filtro_docs !== 'all'): ?>
                            Nenhum documento no período selecionado
                        <?php else: ?>
                            Todos os documentos foram processados
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== CARD 2: NOVOS USUÁRIOS ===== -->
    <div class="card" data-category="usuarios">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">
                <i class="fa-solid fa-user-plus" style="color: #d29922;"></i>
                Empresas Sem Aprovação
            </h3>
            <span class="badge warning" style="font-size: 1rem;">
                <?= $count_users ?>
            </span>
        </div>
        
        <!-- FILTROS DE DATA -->
        <div class="card-body" style="padding: 16px; border-bottom: 1px solid var(--border);">
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button class="btn btn-sm <?= $filtro_users === 'all' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarUsers('all')">
                    Todos (<?= $stats_users['all'] ?>)
                </button>
                <button class="btn btn-sm <?= $filtro_users === 'hoje' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarUsers('hoje')">
                    Hoje (<?= $stats_users['hoje'] ?>)
                </button>
                <button class="btn btn-sm <?= $filtro_users === '7dias' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarUsers('7dias')">
                    7 dias (<?= $stats_users['7dias'] ?>)
                </button>
                <button class="btn btn-sm <?= $filtro_users === '30dias' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarUsers('30dias')">
                    30 dias (<?= $stats_users['30dias'] ?>)
                </button>
            </div>
        </div>
        
        <!-- TABELA -->
        <div class="card-body" style="padding: 0;">
            <?php if ($result_users && $result_users->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Cadastrado</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($user = $result_users->fetch_assoc()): ?>
                                <tr onclick="loadContent('modules/dashboard/detalhes?type=empresa&id=<?= $user['id'] ?>')" style="cursor: pointer;">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 40px; height: 40px; border-radius: 8px; background: #d2992220; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: #d29922;">
                                                <i class="fa-solid fa-building"></i>
                                            </div>
                                            <div>
                                                <strong style="color: var(--text-primary);"><?= htmlspecialchars($user['nome']) ?></strong><br>
                                                <small style="color: var(--text-muted); font-size: 0.75rem;"><?= htmlspecialchars($user['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="color: var(--text-secondary); font-size: 0.875rem;">
                                        <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                        <br>
                                        <small style="color: var(--text-muted); font-size: 0.75rem;">
                                            Há <?= $user['dias_registrado'] ?> dia<?= $user['dias_registrado'] != 1 ? 's' : '' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($user['is_in_lockdown']): ?>
                                            <span class="badge error">
                                                <i class="fa-solid fa-lock"></i>
                                                Bloqueado
                                            </span>
                                        <?php else: ?>
                                            <span class="badge warning">
                                                <i class="fa-solid fa-clock"></i>
                                                Aguardando
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-icon btn-ghost" title="Ver Detalhes">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="empty-title">Nenhuma empresa pendente</div>
                    <div class="empty-description">
                        <?php if ($filtro_users !== 'all'): ?>
                            Nenhum cadastro no período selecionado
                        <?php else: ?>
                            Todas as empresas foram aprovadas
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== CARD 3: ALERTAS CRÍTICOS ===== -->
    <div class="card" data-category="alertas">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">
                <i class="fa-solid fa-triangle-exclamation" style="color: #f85149;"></i>
                Alertas Críticos
            </h3>
            <span class="badge error" style="font-size: 1rem; <?= $count_alerts > 0 ? 'animation: pulse 2s infinite;' : '' ?>">
                <?= $count_alerts ?>
            </span>
        </div>
        
        <!-- FILTROS DE DATA -->
        <div class="card-body" style="padding: 16px; border-bottom: 1px solid var(--border);">
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button class="btn btn-sm <?= $filtro_alerts === 'all' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarAlerts('all')">
                    Todos (<?= $stats_alerts['all'] ?>)
                </button>
                <button class="btn btn-sm <?= $filtro_alerts === 'hoje' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarAlerts('hoje')">
                    Hoje (<?= $stats_alerts['hoje'] ?>)
                </button>
                <button class="btn btn-sm <?= $filtro_alerts === '7dias' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarAlerts('7dias')">
                    7 dias (<?= $stats_alerts['7dias'] ?>)
                </button>
                <button class="btn btn-sm <?= $filtro_alerts === '30dias' ? 'btn-primary' : 'btn-ghost' ?>" onclick="filtrarAlerts('30dias')">
                    30 dias (<?= $stats_alerts['30dias'] ?>)
                </button>
            </div>
        </div>
        
        <!-- TABELA -->
        <div class="card-body" style="padding: 0;">
            <?php if ($result_alerts && $result_alerts->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Alerta</th>
                                <th>Categoria</th>
                                <th>Criado</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($alert = $result_alerts->fetch_assoc()): ?>
                                <tr onclick="loadContent('modules/mensagens/mensagens?id=<?= $alert['id'] ?>')" style="cursor: pointer;">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 40px; height: 40px; border-radius: 8px; background: #f8514920; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: #f85149;">
                                                <i class="fa-solid fa-bell"></i>
                                            </div>
                                            <div>
                                                <strong style="color: var(--text-primary);"><?= htmlspecialchars($alert['subject']) ?></strong><br>
                                                <small style="color: var(--text-muted); font-size: 0.75rem;">
                                                    <?= $alert['sender_name'] ? 'De: ' . htmlspecialchars($alert['sender_name']) : 'Sistema' ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $alert['priority'] === 'critical' ? 'error' : ($alert['priority'] === 'high' ? 'warning' : 'neutral') ?>">
                                            <?= ucfirst($alert['priority']) ?>
                                        </span>
                                    </td>
                                    <td style="color: var(--text-secondary); font-size: 0.875rem;">
                                        <?= date('d/m/Y', strtotime($alert['created_at'])) ?>
                                        <br>
                                        <small style="color: var(--text-muted); font-size: 0.75rem;">
                                            Há <?= $alert['dias_aberto'] ?> dia<?= $alert['dias_aberto'] != 1 ? 's' : '' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <button class="btn btn-icon btn-ghost" title="Ver Alerta">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fa-solid fa-shield-check"></i>
                    </div>
                    <div class="empty-title">Nenhum alerta crítico</div>
                    <div class="empty-description">
                        <?php if ($filtro_alerts !== 'all'): ?>
                            Nenhum alerta no período selecionado
                        <?php else: ?>
                            Sistema operando normalmente
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
(function() {
    'use strict';
    
    // Filtrar documentos por período
    window.filtrarDocs = function(periodo) {
        const params = new URLSearchParams(window.location.search);
        if (periodo === 'all') {
            params.delete('filtro_docs');
        } else {
            params.set('filtro_docs', periodo);
        }
        loadContent('modules/dashboard/pendencias?' + params.toString());
    };
    
    // Filtrar usuários por período
    window.filtrarUsers = function(periodo) {
        const params = new URLSearchParams(window.location.search);
        if (periodo === 'all') {
            params.delete('filtro_users');
        } else {
            params.set('filtro_users', periodo);
        }
        loadContent('modules/dashboard/pendencias?' + params.toString());
    };
    
    // Filtrar alertas por período
    window.filtrarAlerts = function(periodo) {
        const params = new URLSearchParams(window.location.search);
        if (periodo === 'all') {
            params.delete('filtro_alerts');
        } else {
            params.set('filtro_alerts', periodo);
        }
        loadContent('modules/dashboard/pendencias?' + params.toString());
    };
    
    console.log('✅ Pendências carregadas com filtros de data!');
})();
</script>