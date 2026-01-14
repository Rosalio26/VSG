<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - DASHBOARD PRINCIPAL
 * Módulo: modules/dashboard/dashboard.php
 * Descrição: Dashboard executivo com KPIs, gráficos e atividades
 * ================================================================================
 */

// Segurança: Verificar se foi chamado corretamente
if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

// Dados do Admin
$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= CONSULTAS SQL - KPIs PRINCIPAIS ================= */

// 1. TOTAL DE EMPRESAS
$sql_empresas = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN b.status_documentos = 'aprovado' THEN 1 ELSE 0 END) as aprovadas,
        SUM(CASE WHEN b.status_documentos = 'pendente' THEN 1 ELSE 0 END) as pendentes,
        SUM(CASE WHEN DATE(u.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as novos_30d
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
";
$empresas = $mysqli->query($sql_empresas)->fetch_assoc();

// Calcular crescimento (simplificado - em produção comparar com mês anterior)
$empresas['crescimento'] = $empresas['total'] > 0 ? round(($empresas['novos_30d'] / $empresas['total']) * 100, 1) : 0;
$empresas['taxa_aprovacao'] = $empresas['total'] > 0 ? round(($empresas['aprovadas'] / $empresas['total']) * 100, 1) : 0;

// 2. DOCUMENTOS PENDENTES
$documentos_pendentes = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses 
    WHERE status_documentos = 'pendente'
")->fetch_assoc()['total'];

// 3. DOCUMENTOS URGENTES (> 5 dias)
$documentos_urgentes = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'pendente' 
    AND DATEDIFF(NOW(), u.created_at) > 5
")->fetch_assoc()['total'];

// 4. MENSAGENS NÃO LIDAS
$mensagens_nao_lidas = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE receiver_id = $adminId 
    AND status = 'unread'
")->fetch_assoc()['total'];

// 5. ATIVIDADE 24H
$atividade_24h = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM admin_audit_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
")->fetch_assoc()['total'];

// 6. NOVOS REGISTROS (30 dias)
$novos_registros = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch_assoc()['total'];

// 7. ALERTAS CRÍTICOS
$alertas_criticos = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE receiver_id = $adminId 
    AND status = 'unread' 
    AND category IN ('alert', 'security', 'system_error')
")->fetch_assoc()['total'];

// 8. USUÁRIOS POR TIPO
$usuarios_tipo = $mysqli->query("
    SELECT 
        SUM(CASE WHEN type = 'person' THEN 1 ELSE 0 END) as pessoas,
        SUM(CASE WHEN type = 'company' THEN 1 ELSE 0 END) as empresas,
        SUM(CASE WHEN type = 'admin' THEN 1 ELSE 0 END) as admins,
        COUNT(*) as total
    FROM users
")->fetch_assoc();

/* ================= GRÁFICOS - DADOS ================= */

// GRÁFICO 1: Novos Cadastros (últimos 7 dias)
$sql_cadastros = "
    SELECT 
        DATE(created_at) as data,
        COUNT(*) as total
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY data ASC
";
$result_cadastros = $mysqli->query($sql_cadastros);
$chart_cadastros = ['labels' => [], 'data' => []];
while ($row = $result_cadastros->fetch_assoc()) {
    $chart_cadastros['labels'][] = date('d/m', strtotime($row['data']));
    $chart_cadastros['data'][] = $row['data'];
}

// GRÁFICO 2: Distribuição de Status
$sql_status = "
    SELECT 
        status_documentos as status,
        COUNT(*) as total
    FROM businesses
    GROUP BY status_documentos
";
$result_status = $mysqli->query($sql_status);
$chart_status = ['labels' => [], 'data' => [], 'colors' => []];
$status_colors = [
    'aprovado' => '#238636',
    'pendente' => '#f0c065',
    'rejeitado' => '#ff7b72'
];
while ($row = $result_status->fetch_assoc()) {
    $chart_status['labels'][] = ucfirst($row['status'] ?? 'Indefinido');
    $chart_status['data'][] = $row['total'];
    $chart_status['colors'][] = $status_colors[$row['status']] ?? '#6e7681';
}

/* ================= ATIVIDADES RECENTES ================= */
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
$atividades_recentes = $mysqli->query($sql_atividades);

/* ================= DOCUMENTOS URGENTES (TOP 5) ================= */
$sql_urgentes = "
    SELECT 
        b.id,
        u.nome as empresa_nome,
        u.email as empresa_email,
        b.status_documentos,
        DATEDIFF(NOW(), u.created_at) as dias_pendente
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'pendente'
    AND DATEDIFF(NOW(), u.created_at) > 5
    ORDER BY dias_pendente DESC
    LIMIT 5
";
$docs_urgentes = $mysqli->query($sql_urgentes);

?>

<!-- KPI CARDS GRID -->
<div class="stats-grid">
    
    <!-- CARD 1: Total Empresas -->
    <div class="stat-card" onclick="loadContent('modules/tabelas/lista-empresas')">
        <div class="stat-icon">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="stat-label">Total de Empresas</div>
        <div class="stat-value"><?= number_format($empresas['total'], 0, ',', '.') ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-arrow-up"></i>
            +<?= $empresas['crescimento'] ?>% (30 dias)
        </div>
    </div>

    <!-- CARD 2: Documentos Pendentes -->
    <div class="stat-card" onclick="loadContent('modules/dashboard/pendencias')">
        <div class="stat-icon">
            <i class="fa-solid fa-file-circle-exclamation"></i>
        </div>
        <div class="stat-label">Documentos Pendentes</div>
        <div class="stat-value"><?= $documentos_pendentes ?></div>
        <?php if ($documentos_urgentes > 0): ?>
            <div class="stat-change negative">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= $documentos_urgentes ?> urgentes
            </div>
        <?php else: ?>
            <div class="stat-change neutral">
                <i class="fa-solid fa-check-circle"></i>
                Em dia
            </div>
        <?php endif; ?>
    </div>

    <!-- CARD 3: Taxa de Aprovação -->
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="stat-label">Taxa de Aprovação</div>
        <div class="stat-value"><?= $empresas['taxa_aprovacao'] ?>%</div>
        <div class="progress-bar">
            <div class="progress-fill success" style="width: <?= $empresas['taxa_aprovacao'] ?>%;"></div>
        </div>
    </div>

    <!-- CARD 4: Mensagens -->
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

    <!-- CARD 5: Atividade 24h -->
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

    <!-- CARD 6: Novos Cadastros -->
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-user-plus"></i>
        </div>
        <div class="stat-label">Novos Cadastros (30d)</div>
        <div class="stat-value"><?= $novos_registros ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-calendar-check"></i>
            Último mês
        </div>
    </div>

    <!-- CARD 7: Alertas Críticos -->
    <div class="stat-card" onclick="loadContent('modules/mensagens/mensagens')">
        <div class="stat-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="stat-label">Alertas Críticos</div>
        <div class="stat-value"><?= $alertas_criticos ?></div>
        <?php if ($alertas_criticos > 0): ?>
            <div class="stat-change negative">
                <i class="fa-solid fa-bell"></i>
                Requer atenção
            </div>
        <?php else: ?>
            <div class="stat-change positive">
                <i class="fa-solid fa-shield-halved"></i>
                Sistema OK
            </div>
        <?php endif; ?>
    </div>

    <!-- CARD 8: Usuários Totais -->
    <div class="stat-card" onclick="loadContent('modules/usuarios/usuarios')">
        <div class="stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-label">Usuários Totais</div>
        <div class="stat-value"><?= number_format($usuarios_tipo['total'], 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-layer-group"></i>
            <?= $usuarios_tipo['empresas'] ?> empresas
        </div>
    </div>

</div>

<!-- GRÁFICOS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 32px;">
    
    <!-- GRÁFICO: Novos Cadastros (7 dias) -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-chart-line" style="color: var(--accent-green);"></i>
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
                <i class="fa-solid fa-chart-pie" style="color: var(--accent-green);"></i>
                Distribuição de Status
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
            <i class="fa-solid fa-bolt" style="color: var(--accent-green);"></i>
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

            <button class="btn btn-secondary" onclick="loadContent('modules/tabelas/lista-empresas')">
                <i class="fa-solid fa-building"></i>
                Ver Todas Empresas
            </button>

            <button class="btn btn-secondary" onclick="loadContent('modules/tabelas/tabela-financeiro')">
                <i class="fa-solid fa-dollar-sign"></i>
                Análise Financeira
            </button>

            <button class="btn btn-secondary" onclick="loadContent('modules/tabelas/relatorio')">
                <i class="fa-solid fa-file-chart"></i>
                Gerar Relatório
            </button>

            <?php if ($isSuperAdmin): ?>
            <button class="btn btn-ghost" onclick="loadContent('modules/auditor/auditor-logs')">
                <i class="fa-solid fa-shield-halved"></i>
                Logs de Auditoria
            </button>
            <?php endif; ?>

            <button class="btn btn-ghost" onclick="loadContent('system/settings')">
                <i class="fa-solid fa-gear"></i>
                Configurações
            </button>

        </div>
    </div>
</div>

<!-- DOCUMENTOS URGENTES -->
<?php if ($docs_urgentes && $docs_urgentes->num_rows > 0): ?>
<div class="card" style="margin-bottom: 32px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title">
            <i class="fa-solid fa-triangle-exclamation" style="color: #ff4d4d;"></i>
            Documentos Urgentes
            <span class="badge error"><?= $documentos_urgentes ?></span>
        </h3>
        <a href="javascript:void(0)" onclick="loadContent('modules/dashboard/pendencias')" style="color: var(--accent-green); text-decoration: none; font-weight: 600; font-size: 0.875rem;">
            Ver todos <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Dias Pendente</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($doc = $docs_urgentes->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight: 600;"><?= htmlspecialchars($doc['empresa_nome']) ?></td>
                        <td style="color: var(--text-secondary);"><?= htmlspecialchars($doc['empresa_email']) ?></td>
                        <td>
                            <span class="badge warning">
                                <i class="fa-solid fa-clock"></i>
                                <?= ucfirst($doc['status_documentos']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge error">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <?= $doc['dias_pendente'] ?> dias
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <button class="btn btn-icon btn-ghost" onclick="loadContent('modules/dashboard/pendencias')" title="Analisar">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                            </div>
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
            <i class="fa-solid fa-clock-rotate-left" style="color: var(--accent-green);"></i>
            Atividades Recentes
        </h3>
        <?php if ($isSuperAdmin): ?>
        <a href="javascript:void(0)" onclick="loadContent('modules/auditor/auditor-logs')" style="color: var(--accent-green); text-decoration: none; font-weight: 600; font-size: 0.875rem;">
            Ver logs completos <i class="fa-solid fa-arrow-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($atividades_recentes && $atividades_recentes->num_rows > 0): ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php while ($ativ = $atividades_recentes->fetch_assoc()): ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 14px; background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 10px; transition: all 0.2s;">
                    <div style="width: 40px; height: 40px; background: rgba(0, 255, 136, 0.1); border: 1px solid var(--border-color); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--accent-green); flex-shrink: 0;">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: var(--text-title); font-weight: 600; font-size: 0.875rem; margin-bottom: 4px;">
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
                            <span>
                                <i class="fa-solid fa-location-dot"></i>
                                <?= htmlspecialchars($ativ['ip_address']) ?>
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
                <div class="empty-description">As ações do sistema aparecerão aqui</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SCRIPTS: GRÁFICOS CHART.JS -->
<script>
(function() {
    'use strict';
    
    // Aguardar Chart.js carregar
    function initCharts() {
        if (typeof Chart === 'undefined') {
            setTimeout(initCharts, 100);
            return;
        }

        // GRÁFICO 1: Novos Cadastros (Line Chart)
        const ctxCadastros = document.getElementById('chartCadastros');
        if (ctxCadastros) {
            new Chart(ctxCadastros, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_cadastros['labels']) ?>,
                    datasets: [{
                        label: 'Novos Cadastros',
                        data: <?= json_encode($chart_cadastros['data']) ?>,
                        borderColor: 'rgba(0, 255, 136, 1)',
                        backgroundColor: 'rgba(0, 255, 136, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: 'rgba(0, 255, 136, 1)',
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
                            backgroundColor: 'rgba(18, 24, 18, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#a0ac9f',
                            borderColor: 'rgba(0, 255, 136, 0.3)',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { 
                                color: '#8b949e',
                                stepSize: 1
                            },
                            grid: {
                                color: 'rgba(0, 255, 136, 0.05)',
                                drawBorder: false
                            }
                        },
                        x: {
                            ticks: { color: '#8b949e' },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // GRÁFICO 2: Status de Documentos (Doughnut Chart)
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
                                color: '#a0ac9f',
                                padding: 15,
                                font: { size: 12, weight: '600' }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(18, 24, 18, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#a0ac9f',
                            borderColor: 'rgba(0, 255, 136, 0.3)',
                            borderWidth: 1,
                            padding: 12
                        }
                    },
                    cutout: '65%'
                }
            });
        }

        console.log('✅ Dashboard charts renderizados com sucesso!');
    }

    // Iniciar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }

    // Auto-refresh (opcional - apenas se estiver na página)
    setInterval(() => {
        const currentPage = new URLSearchParams(window.location.search).get('page');
        if (currentPage === 'modules/dashboard/dashboard' || !currentPage) {
            // Opcional: recarregar dados via AJAX sem reload completo
            // loadContent('modules/dashboard/dashboard');
        }
    }, 300000); // 5 minutos
})();
</script>