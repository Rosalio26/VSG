<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - DASHBOARD PRINCIPAL (RESUMIDO)
 * Módulo: modules/dashboard/dashboard.php
 * Descrição: Dashboard executivo SIMPLIFICADO com visão geral do sistema
 * Proteção: Admin NÃO SABE que SuperAdmins existem
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= CONSULTAS SQL - KPIs RESUMIDOS ================= */

// 1. TOTAL DE USUÁRIOS (SEM REVELAR TIPOS PARA ADMIN)
if ($isSuperAdmin) {
    // SuperAdmin vê o total REAL
    $total_usuarios = $mysqli->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE deleted_at IS NULL
    ")->fetch_assoc()['total'];
} else {
    // Admin vê total SEM SuperAdmins (mas não sabe que eles existem)
    $total_usuarios = $mysqli->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE deleted_at IS NULL
        AND role != 'superadmin'
    ")->fetch_assoc()['total'];
}

// 2. USUÁRIOS ONLINE (< 15 min)
if ($isSuperAdmin) {
    $usuarios_online = $mysqli->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 15 MINUTE))
        AND deleted_at IS NULL
    ")->fetch_assoc()['total'];
} else {
    $usuarios_online = $mysqli->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 15 MINUTE))
        AND role != 'superadmin'
        AND deleted_at IS NULL
    ")->fetch_assoc()['total'];
}

// 3. NOVOS CADASTROS (30 dias)
if ($isSuperAdmin) {
    $novos_cadastros = $mysqli->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND deleted_at IS NULL
    ")->fetch_assoc()['total'];
} else {
    $novos_cadastros = $mysqli->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND role != 'superadmin'
        AND deleted_at IS NULL
    ")->fetch_assoc()['total'];
}

// 4. USUÁRIOS BLOQUEADOS
if ($isSuperAdmin) {
    $usuarios_bloqueados = $mysqli->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE is_in_lockdown = 1
        AND deleted_at IS NULL
    ")->fetch_assoc()['total'];
} else {
    $usuarios_bloqueados = $mysqli->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE is_in_lockdown = 1
        AND role != 'superadmin'
        AND deleted_at IS NULL
    ")->fetch_assoc()['total'];
}

// 5. EMPRESAS CADASTRADAS
$total_empresas = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE u.deleted_at IS NULL
")->fetch_assoc()['total'];

// 6. DOCUMENTOS PENDENTES
$documentos_pendentes = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'pendente'
    AND u.deleted_at IS NULL
")->fetch_assoc()['total'];

// 7. DOCUMENTOS URGENTES (> 5 dias)
$documentos_urgentes = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'pendente' 
    AND DATEDIFF(NOW(), u.created_at) > 5
    AND u.deleted_at IS NULL
")->fetch_assoc()['total'];

// 8. TAXA DE APROVAÇÃO
$aprovacao = $mysqli->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status_documentos = 'aprovado' THEN 1 ELSE 0 END) as aprovados
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE u.deleted_at IS NULL
")->fetch_assoc();
$taxa_aprovacao = $aprovacao['total'] > 0 ? round(($aprovacao['aprovados'] / $aprovacao['total']) * 100, 1) : 0;

// 9. MENSAGENS NÃO LIDAS
$mensagens_nao_lidas = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE receiver_id = $adminId 
    AND status = 'unread'
")->fetch_assoc()['total'];

// 10. ATIVIDADE 24H
$atividade_24h = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM admin_audit_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
")->fetch_assoc()['total'];

// 11. ALERTAS CRÍTICOS
$alertas_criticos = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE receiver_id = $adminId 
    AND status = 'unread' 
    AND category IN ('alert', 'security', 'system_error')
")->fetch_assoc()['total'];

/* ================= GRÁFICO: CADASTROS POR DIA (7 dias) ================= */
if ($isSuperAdmin) {
    $sql_cadastros = "
        SELECT 
            DATE(created_at) as data,
            COUNT(*) as total
        FROM users
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND deleted_at IS NULL
        GROUP BY DATE(created_at)
        ORDER BY data ASC
    ";
} else {
    $sql_cadastros = "
        SELECT 
            DATE(created_at) as data,
            COUNT(*) as total
        FROM users
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND role != 'superadmin'
        AND deleted_at IS NULL
        GROUP BY DATE(created_at)
        ORDER BY data ASC
    ";
}

$result_cadastros = $mysqli->query($sql_cadastros);
$chart_cadastros = ['labels' => [], 'data' => []];
while ($row = $result_cadastros->fetch_assoc()) {
    $chart_cadastros['labels'][] = date('d/m', strtotime($row['data']));
    $chart_cadastros['data'][] = $row['total'];
}

/* ================= GRÁFICO: STATUS DE DOCUMENTOS ================= */
$result_status = $mysqli->query("
    SELECT 
        status_documentos as status,
        COUNT(*) as total
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE u.deleted_at IS NULL
    GROUP BY status_documentos
");

$chart_status = ['labels' => [], 'data' => [], 'colors' => []];
$status_colors = [
    'aprovado' => '#238636',
    'pendente' => '#d29922',
    'rejeitado' => '#f85149'
];

while ($row = $result_status->fetch_assoc()) {
    $chart_status['labels'][] = ucfirst($row['status'] ?? 'Indefinido');
    $chart_status['data'][] = $row['total'];
    $chart_status['colors'][] = $status_colors[$row['status']] ?? '#6e7681';
}

/* ================= DOCUMENTOS URGENTES (TOP 5) ================= */
$docs_urgentes = $mysqli->query("
    SELECT 
        b.user_id,
        u.nome as empresa_nome,
        u.email as empresa_email,
        DATEDIFF(NOW(), u.created_at) as dias_pendente
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'pendente'
    AND DATEDIFF(NOW(), u.created_at) > 5
    AND u.deleted_at IS NULL
    ORDER BY dias_pendente DESC
    LIMIT 5
");

/* ================= ATIVIDADES RECENTES ================= */
// Admin vê apenas SUAS ações e ações de outros Admins (não vê SuperAdmin)
if ($isSuperAdmin) {
    $sql_atividades = "
        SELECT 
            al.action,
            COALESCE(u.nome, 'Sistema') as admin_nome,
            al.created_at,
            al.ip_address
        FROM admin_audit_logs al
        LEFT JOIN users u ON al.admin_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 6
    ";
} else {
    $sql_atividades = "
        SELECT 
            al.action,
            COALESCE(u.nome, 'Sistema') as admin_nome,
            al.created_at,
            al.ip_address
        FROM admin_audit_logs al
        LEFT JOIN users u ON al.admin_id = u.id
        WHERE (u.role = 'admin' OR u.role IS NULL OR al.admin_id = $adminId)
        ORDER BY al.created_at DESC
        LIMIT 6
    ";
}
$atividades_recentes = $mysqli->query($sql_atividades);

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
        <i class="fa-solid fa-gauge-high" style="color: var(--accent);"></i>
        Dashboard Executivo
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.938rem;">
        Visão geral do sistema VisionGreen
    </p>
</div>

<!-- KPI CARDS (RESUMIDOS - 8 CARDS) -->
<div class="stats-grid">
    
    <!-- CARD 1: Total de Usuários (SEM REVELAR TIPOS) -->
    <div class="stat-card" onclick="loadContent('modules/usuarios/usuarios')">
        <div class="stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-label">Total de Usuários</div>
        <div class="stat-value"><?= number_format($total_usuarios, 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-user-group"></i>
            Todos os usuários
        </div>
    </div>

    <!-- CARD 2: Usuários Online -->
    <div class="stat-card" onclick="loadContent('modules/usuarios/usuarios?sessao=online')">
        <div class="stat-icon" style="animation: pulse 2s infinite;">
            <i class="fa-solid fa-wifi"></i>
        </div>
        <div class="stat-label">Usuários Online</div>
        <div class="stat-value" style="color: #3fb950;"><?= number_format($usuarios_online, 0, ',', '.') ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-circle" style="font-size: 0.5rem;"></i>
            Logados agora
        </div>
    </div>

    <!-- CARD 3: Empresas Cadastradas -->
    <div class="stat-card" onclick="loadContent('modules/tabelas/lista-empresas')">
        <div class="stat-icon">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="stat-label">Empresas</div>
        <div class="stat-value"><?= number_format($total_empresas, 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-briefcase"></i>
            Cadastradas
        </div>
    </div>

    <!-- CARD 4: Documentos Pendentes -->
    <div class="stat-card" onclick="loadContent('modules/dashboard/pendencias')">
        <div class="stat-icon">
            <i class="fa-solid fa-file-circle-exclamation"></i>
        </div>
        <div class="stat-label">Docs Pendentes</div>
        <div class="stat-value"><?= $documentos_pendentes ?></div>
        <?php if ($documentos_urgentes > 0): ?>
            <div class="stat-change negative">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= $documentos_urgentes ?> urgentes
            </div>
        <?php else: ?>
            <div class="stat-change positive">
                <i class="fa-solid fa-check-circle"></i>
                Em dia
            </div>
        <?php endif; ?>
    </div>

    <!-- CARD 5: Taxa de Aprovação -->
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="stat-label">Taxa de Aprovação</div>
        <div class="stat-value"><?= $taxa_aprovacao ?>%</div>
        <div class="progress-bar">
            <div class="progress-fill success" style="width: <?= $taxa_aprovacao ?>%;"></div>
        </div>
    </div>

    <!-- CARD 6: Novos Cadastros -->
    <div class="stat-card" onclick="loadContent('modules/usuarios/usuarios')">
        <div class="stat-icon">
            <i class="fa-solid fa-user-plus"></i>
        </div>
        <div class="stat-label">Novos (30d)</div>
        <div class="stat-value"><?= $novos_cadastros ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-calendar-check"></i>
            Último mês
        </div>
    </div>

    <!-- CARD 7: Mensagens -->
    <div class="stat-card" onclick="loadContent('modules/mensagens/mensagens')">
        <div class="stat-icon">
            <i class="fa-solid fa-comment-dots"></i>
        </div>
        <div class="stat-label">Mensagens</div>
        <div class="stat-value"><?= $mensagens_nao_lidas ?></div>
        <?php if ($mensagens_nao_lidas > 0): ?>
            <div class="stat-change negative">
                <i class="fa-solid fa-envelope"></i>
                Não lidas
            </div>
        <?php else: ?>
            <div class="stat-change positive">
                <i class="fa-solid fa-check"></i>
                Tudo lido
            </div>
        <?php endif; ?>
    </div>

    <!-- CARD 8: Alertas Críticos (SUPERADMIN ONLY) -->
    <?php if ($isSuperAdmin): ?>
    <div class="stat-card" onclick="loadContent('modules/mensagens/mensagens')">
        <div class="stat-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div class="stat-label">Alertas Sistema</div>
        <div class="stat-value"><?= $alertas_criticos ?></div>
        <div class="stat-change <?= $alertas_criticos > 0 ? 'negative' : 'positive' ?>">
            <i class="fa-solid fa-<?= $alertas_criticos > 0 ? 'bell' : 'check' ?>"></i>
            <?= $alertas_criticos > 0 ? 'Requer atenção' : 'OK' ?>
        </div>
    </div>
    <?php else: ?>
    <!-- Admin vê: Atividade 24h -->
    <div class="stat-card" onclick="loadContent('modules/dashboard/historico')">
        <div class="stat-icon">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="stat-label">Atividade (24h)</div>
        <div class="stat-value"><?= $atividade_24h ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-bolt"></i>
            Ações realizadas
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- GRÁFICOS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 32px;">
    
    <!-- GRÁFICO: Novos Cadastros (7 dias) -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-chart-line" style="color: var(--accent);"></i>
                Novos Cadastros (7 dias)
            </h3>
        </div>
        <div class="card-body">
            <canvas id="chartCadastros" height="200"></canvas>
        </div>
    </div>

    <!-- GRÁFICO: Status de Documentos -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-chart-pie" style="color: var(--accent);"></i>
                Status de Documentos
            </h3>
        </div>
        <div class="card-body">
            <canvas id="chartStatus" height="200"></canvas>
        </div>
    </div>

</div>

<!-- QUICK ACTIONS -->
<div class="card" style="margin-bottom: 32px;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-bolt" style="color: var(--accent);"></i>
            Ações Rápidas
        </h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            
            <button class="btn btn-primary" onclick="loadContent('modules/dashboard/pendencias')">
                <i class="fa-solid fa-clipboard-check"></i>
                Aprovar Documentos
                <?php if ($documentos_pendentes > 0): ?>
                    <span class="badge error" style="margin-left: auto;"><?= $documentos_pendentes ?></span>
                <?php endif; ?>
            </button>

            <button class="btn btn-secondary" onclick="loadContent('modules/usuarios/usuarios')">
                <i class="fa-solid fa-users"></i>
                Gerenciar Usuários
            </button>

            <button class="btn btn-secondary" onclick="loadContent('modules/tabelas/lista-empresas')">
                <i class="fa-solid fa-building"></i>
                Ver Empresas
            </button>

            <?php if ($isSuperAdmin): ?>
            <button class="btn btn-secondary" onclick="loadContent('modules/dashboard/plataformas')">
                <i class="fa-solid fa-layer-group"></i>
                Plataformas
            </button>
            
            <button class="btn btn-ghost" onclick="loadContent('modules/auditor/auditor-logs')">
                <i class="fa-solid fa-shield-halved"></i>
                Logs de Auditoria
            </button>
            
            <button class="btn btn-ghost" onclick="loadContent('system/settings')">
                <i class="fa-solid fa-gear"></i>
                Configurações
            </button>
            <?php else: ?>
            <button class="btn btn-secondary" onclick="loadContent('modules/dashboard/historico')">
                <i class="fa-solid fa-clock-rotate-left"></i>
                Histórico
            </button>
            
            <button class="btn btn-secondary" onclick="loadContent('modules/tabelas/relatorio')">
                <i class="fa-solid fa-file-chart"></i>
                Relatórios
            </button>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- GRID: DOCUMENTOS URGENTES + ATIVIDADES -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 24px; margin-bottom: 32px;">

    <!-- DOCUMENTOS URGENTES -->
    <?php if ($docs_urgentes && $docs_urgentes->num_rows > 0): ?>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">
                <i class="fa-solid fa-triangle-exclamation" style="color: #f85149;"></i>
                Documentos Urgentes
                <span class="badge error"><?= $documentos_urgentes ?></span>
            </h3>
            <a href="javascript:void(0)" onclick="loadContent('modules/dashboard/pendencias')" style="color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.875rem;">
                Ver todos <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Dias Pendente</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($doc = $docs_urgentes->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-primary);"><?= htmlspecialchars($doc['empresa_nome']) ?></strong><br>
                                <small style="color: var(--text-muted); font-size: 0.75rem;"><?= htmlspecialchars($doc['empresa_email']) ?></small>
                            </td>
                            <td>
                                <span class="badge error">
                                    <i class="fa-solid fa-clock"></i>
                                    <?= $doc['dias_pendente'] ?> dias
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-ghost" onclick="loadContent('modules/dashboard/detalhes?type=empresa&id=<?= $doc['user_id'] ?>')" title="Ver Detalhes">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ATIVIDADES RECENTES -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">
                <i class="fa-solid fa-clock-rotate-left" style="color: var(--accent);"></i>
                Atividades Recentes
            </h3>
            <?php if ($isSuperAdmin): ?>
            <a href="javascript:void(0)" onclick="loadContent('modules/auditor/auditor-logs')" style="color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.875rem;">
                Ver logs <i class="fa-solid fa-arrow-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($atividades_recentes && $atividades_recentes->num_rows > 0): ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php while ($ativ = $atividades_recentes->fetch_assoc()): ?>
                    <div style="display: flex; align-items: center; gap: 16px; padding: 14px; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: 10px;">
                        <div style="width: 40px; height: 40px; background: rgba(35, 134, 54, 0.1); border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--accent); flex-shrink: 0;">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="color: var(--text-primary); font-weight: 600; font-size: 0.875rem; margin-bottom: 4px;">
                                <?= htmlspecialchars($ativ['action']) ?>
                            </div>
                            <div style="color: var(--text-secondary); font-size: 0.75rem; display: flex; gap: 16px;">
                                <span>
                                    <i class="fa-solid fa-user"></i>
                                    <?= htmlspecialchars($ativ['admin_nome']) ?>
                                </span>
                                <span>
                                    <i class="fa-solid fa-clock"></i>
                                    <?= date('d/m/Y H:i', strtotime($ativ['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fa-solid fa-inbox"></i>
                    </div>
                    <div class="empty-title">Nenhuma atividade recente</div>
                    <div class="empty-description">As ações aparecerão aqui</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- SCRIPTS: GRÁFICOS CHART.JS -->
<script>
(function() {
    'use strict';
    
    function initCharts() {
        if (typeof Chart === 'undefined') {
            setTimeout(initCharts, 100);
            return;
        }

        // GRÁFICO 1: Novos Cadastros
        const ctxCadastros = document.getElementById('chartCadastros');
        if (ctxCadastros) {
            new Chart(ctxCadastros, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_cadastros['labels']) ?>,
                    datasets: [{
                        label: 'Novos Usuários',
                        data: <?= json_encode($chart_cadastros['data']) ?>,
                        borderColor: '#238636',
                        backgroundColor: 'rgba(35, 134, 54, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#238636',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(22, 27, 34, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#c9d1d9',
                            borderColor: 'rgba(48, 54, 61, 1)',
                            borderWidth: 1,
                            padding: 12
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#8b949e', stepSize: 1 },
                            grid: { color: 'rgba(48, 54, 61, 0.5)', drawBorder: false }
                        },
                        x: {
                            ticks: { color: '#8b949e' },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // GRÁFICO 2: Status de Documentos
        const ctxStatus = document.getElementById('chartStatus');
        if (ctxStatus) {
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($chart_status['labels']) ?>,
                    datasets: [{
                        data: <?= json_encode($chart_status['data']) ?>,
                        backgroundColor: <?= json_encode($chart_status['colors']) ?>,
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#c9d1d9',
                                padding: 15,
                                font: { size: 12, weight: '600' }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(22, 27, 34, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#c9d1d9',
                            borderColor: 'rgba(48, 54, 61, 1)',
                            borderWidth: 1,
                            padding: 12
                        }
                    },
                    cutout: '65%'
                }
            });
        }

        console.log('✅ Dashboard renderizado!');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }
})();
</script>