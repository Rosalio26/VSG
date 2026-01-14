<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - HISTÓRICO DE ATIVIDADES
 * Módulo: modules/dashboard/historico.php
 * Descrição: Timeline completa de logs e mudanças de status
 * Usa: dashboard-components.css
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= FILTROS ================= */
$filtro_periodo = $_GET['periodo'] ?? '7';
$filtro_tipo = $_GET['tipo'] ?? 'all';
$filtro_admin = $_GET['admin'] ?? 'all';

/* ================= CONSTRUIR WHERE CLAUSE ================= */
$where_conditions = ["1=1"];

// Filtro de período
if ($filtro_periodo !== 'all') {
    $where_conditions[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL $filtro_periodo DAY)";
}

// Filtro de tipo de ação
if ($filtro_tipo !== 'all') {
    $stmt = $mysqli->prepare("SELECT ? AS tipo");
    $stmt->bind_param("s", $filtro_tipo);
    $stmt->execute();
    $tipo_safe = $stmt->get_result()->fetch_assoc()['tipo'];
    $where_conditions[] = "al.action LIKE '%" . $mysqli->real_escape_string($tipo_safe) . "%'";
}

// Filtro de admin
if ($filtro_admin !== 'all') {
    $admin_id_safe = (int)$filtro_admin;
    $where_conditions[] = "al.admin_id = $admin_id_safe";
}

$where_clause = implode(" AND ", $where_conditions);

/* ================= BUSCAR LOGS DE AUDITORIA ================= */
$sql_historico = "
    SELECT 
        al.id,
        al.admin_id,
        COALESCE(u.nome, 'Sistema') as admin_nome,
        al.action,
        al.ip_address,
        al.created_at,
        DATEDIFF(NOW(), al.created_at) as dias_atras
    FROM admin_audit_logs al
    LEFT JOIN users u ON al.admin_id = u.id
    WHERE $where_clause
    ORDER BY al.created_at DESC
    LIMIT 100
";
$result_historico = $mysqli->query($sql_historico);

/* ================= BUSCAR HISTÓRICO DE EMPRESAS ================= */
$periodo_safe = $filtro_periodo === 'all' ? '365' : $filtro_periodo;
$sql_businesses_history = "
    SELECT 
        b.id,
        b.user_id,
        u.nome as empresa_nome,
        b.status_documentos,
        b.motivo_rejeicao,
        b.updated_at
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.updated_at >= DATE_SUB(NOW(), INTERVAL $periodo_safe DAY)
    AND b.status_documentos IN ('aprovado', 'rejeitado')
    ORDER BY b.updated_at DESC
    LIMIT 50
";
$result_business_history = $mysqli->query($sql_businesses_history);

/* ================= LISTA DE ADMINS PARA FILTRO ================= */
$sql_admins = "SELECT id, nome FROM users WHERE role IN ('admin', 'superadmin') ORDER BY nome ASC";
$result_admins = $mysqli->query($sql_admins);

/* ================= ESTATÍSTICAS DO PERÍODO ================= */
$sql_stats = "
    SELECT 
        COUNT(*) as total_acoes,
        COUNT(DISTINCT admin_id) as admins_ativos,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as acoes_24h
    FROM admin_audit_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL $periodo_safe DAY)
";
$stats = $mysqli->query($sql_stats)->fetch_assoc();

/* ================= DOCUMENTOS PROCESSADOS ================= */
$docs_aprovados = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'aprovado' AND updated_at >= DATE_SUB(NOW(), INTERVAL $periodo_safe DAY)")->fetch_assoc()['total'];
$docs_rejeitados = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'rejeitado' AND updated_at >= DATE_SUB(NOW(), INTERVAL $periodo_safe DAY)")->fetch_assoc()['total'];
?>

<!-- HEADER -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
    <h1 style="color: var(--text-title); font-size: 2rem; font-weight: 800; margin: 0;">
        <i class="fa-solid fa-clock-rotate-left" style="color: var(--accent);"></i>
        Histórico de Atividades
    </h1>
    <button class="btn btn-primary" onclick="exportarHistorico()">
        <i class="fa-solid fa-download"></i>
        Exportar CSV
    </button>
</div>

<!-- FILTROS -->
<div class="card mb-3">
    <div class="card-body">
        <div class="filters-row" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
            
            <div class="filter-item">
                <label class="form-label" style="margin-bottom: 8px;">Período:</label>
                <select class="form-select" id="filterPeriodo" onchange="aplicarFiltros()">
                    <option value="1" <?= $filtro_periodo === '1' ? 'selected' : '' ?>>Últimas 24h</option>
                    <option value="7" <?= $filtro_periodo === '7' ? 'selected' : '' ?>>Últimos 7 dias</option>
                    <option value="30" <?= $filtro_periodo === '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
                    <option value="90" <?= $filtro_periodo === '90' ? 'selected' : '' ?>>Últimos 90 dias</option>
                    <option value="all" <?= $filtro_periodo === 'all' ? 'selected' : '' ?>>Todo o histórico</option>
                </select>
            </div>

            <div class="filter-item">
                <label class="form-label" style="margin-bottom: 8px;">Tipo de Ação:</label>
                <select class="form-select" id="filterTipo" onchange="aplicarFiltros()">
                    <option value="all" <?= $filtro_tipo === 'all' ? 'selected' : '' ?>>Todas</option>
                    <option value="AUDIT" <?= $filtro_tipo === 'AUDIT' ? 'selected' : '' ?>>Auditorias</option>
                    <option value="LOGIN" <?= $filtro_tipo === 'LOGIN' ? 'selected' : '' ?>>Logins</option>
                    <option value="CREATE" <?= $filtro_tipo === 'CREATE' ? 'selected' : '' ?>>Criações</option>
                    <option value="UPDATE" <?= $filtro_tipo === 'UPDATE' ? 'selected' : '' ?>>Atualizações</option>
                    <option value="DELETE" <?= $filtro_tipo === 'DELETE' ? 'selected' : '' ?>>Exclusões</option>
                </select>
            </div>

            <?php if ($isSuperAdmin): ?>
            <div class="filter-item">
                <label class="form-label" style="margin-bottom: 8px;">Admin:</label>
                <select class="form-select" id="filterAdmin" onchange="aplicarFiltros()">
                    <option value="all">Todos</option>
                    <?php 
                    $result_admins->data_seek(0);
                    while($admin = $result_admins->fetch_assoc()): 
                    ?>
                        <option value="<?= $admin['id'] ?>" <?= $filtro_admin == $admin['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($admin['nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="filter-actions" style="margin-left: auto; display: flex; gap: 8px;">
                <button class="btn btn-ghost" onclick="limparFiltros()">
                    <i class="fa-solid fa-filter-circle-xmark"></i>
                    Limpar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- KPI CARDS -->
<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-list-check"></i>
        </div>
        <div class="stat-label">Total de Ações</div>
        <div class="stat-value"><?= number_format($stats['total_acoes'], 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-bolt"></i>
            No período
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="stat-label">Últimas 24h</div>
        <div class="stat-value"><?= $stats['acoes_24h'] ?></div>
        <div class="stat-change <?= $stats['acoes_24h'] > 0 ? 'positive' : 'neutral' ?>">
            <i class="fa-solid fa-calendar-day"></i>
            Hoje
        </div>
    </div>

    <div class="stat-card" onclick="loadContent('modules/dashboard/analise')">
        <div class="stat-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="stat-label">Docs Aprovados</div>
        <div class="stat-value"><?= $docs_aprovados ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-arrow-up"></i>
            Aprovados
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-times-circle"></i>
        </div>
        <div class="stat-label">Docs Rejeitados</div>
        <div class="stat-value"><?= $docs_rejeitados ?></div>
        <div class="stat-change negative">
            <i class="fa-solid fa-arrow-down"></i>
            Rejeitados
        </div>
    </div>
</div>

<!-- TIMELINE GRID -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 24px;">
    
    <!-- LOGS DE AUDITORIA -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-clipboard-list"></i>
                Logs de Auditoria
            </h3>
            <span class="badge info"><?= $result_historico ? $result_historico->num_rows : 0 ?></span>
        </div>
        
        <div class="card-body" style="max-height: 700px; overflow-y: auto;">
            <?php if ($result_historico && $result_historico->num_rows > 0): ?>
                <div class="timeline">
                    <?php while($log = $result_historico->fetch_assoc()): ?>
                    <?php
                        // Determinar classe da timeline baseado na ação
                        $timelineClass = 'pending';
                        if (strpos($log['action'], 'APROVADO') !== false || strpos($log['action'], 'LOGIN') !== false) {
                            $timelineClass = 'completed';
                        } elseif (strpos($log['action'], 'REJEITADO') !== false || strpos($log['action'], 'DELETE') !== false) {
                            $timelineClass = 'rejected';
                        }
                    ?>
                    <div class="timeline-item <?= $timelineClass ?>">
                        <div class="timeline-content">
                            <div class="timeline-title"><?= htmlspecialchars($log['action']) ?></div>
                            <div class="timeline-meta">
                                <i class="fa-solid fa-user"></i>
                                <?= htmlspecialchars($log['admin_nome']) ?> • 
                                <i class="fa-solid fa-clock"></i>
                                <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?> • 
                                <i class="fa-solid fa-location-dot"></i>
                                <?= htmlspecialchars($log['ip_address']) ?>
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
                    <div class="empty-title">Nenhum log encontrado</div>
                    <div class="empty-description">Ajuste os filtros para ver mais resultados</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DOCUMENTOS PROCESSADOS -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-building-circle-check"></i>
                Documentos Processados
            </h3>
            <span class="badge success"><?= $result_business_history ? $result_business_history->num_rows : 0 ?></span>
        </div>
        
        <div class="card-body" style="max-height: 700px; overflow-y: auto;">
            <?php if ($result_business_history && $result_business_history->num_rows > 0): ?>
                <div class="timeline">
                    <?php while($biz = $result_business_history->fetch_assoc()): ?>
                    <div class="timeline-item <?= $biz['status_documentos'] === 'aprovado' ? 'completed' : 'rejected' ?>">
                        <div class="timeline-content">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <div class="timeline-title"><?= htmlspecialchars($biz['empresa_nome']) ?></div>
                                <span class="badge <?= $biz['status_documentos'] === 'aprovado' ? 'success' : 'error' ?>">
                                    <?= ucfirst($biz['status_documentos']) ?>
                                </span>
                            </div>
                            <div class="timeline-meta">
                                <i class="fa-solid fa-clock"></i>
                                <?= date('d/m/Y H:i', strtotime($biz['updated_at'])) ?>
                            </div>
                            
                            <?php if ($biz['status_documentos'] === 'rejeitado' && !empty($biz['motivo_rejeicao'])): ?>
                            <div class="alert error" style="margin-top: 12px; padding: 12px;">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                <div>
                                    <strong style="font-size: 0.75rem;">Motivo:</strong><br>
                                    <span style="font-size: 0.75rem;"><?= htmlspecialchars($biz['motivo_rejeicao']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fa-solid fa-inbox"></i>
                    </div>
                    <div class="empty-title">Nenhum documento processado</div>
                    <div class="empty-description">No período selecionado</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
(function() {
    'use strict';
    
    window.aplicarFiltros = function() {
        const periodo = document.getElementById('filterPeriodo').value;
        const tipo = document.getElementById('filterTipo').value;
        const admin = document.getElementById('filterAdmin')?.value || 'all';
        
        let url = 'modules/dashboard/historico?';
        const params = [];
        
        if (periodo) params.push('periodo=' + periodo);
        if (tipo !== 'all') params.push('tipo=' + tipo);
        if (admin !== 'all') params.push('admin=' + admin);
        
        url += params.join('&');
        loadContent(url);
    };

    window.limparFiltros = function() {
        loadContent('modules/dashboard/historico');
    };

    window.exportarHistorico = function() {
        const periodo = document.getElementById('filterPeriodo').value;
        const tipo = document.getElementById('filterTipo').value;
        const admin = document.getElementById('filterAdmin')?.value || 'all';
        
        // Criar CSV dinamicamente
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Tipo,Ação,Admin,IP,Data\n";
        
        // Buscar dados da tabela
        const timeline = document.querySelectorAll('.timeline-item');
        timeline.forEach(item => {
            const title = item.querySelector('.timeline-title')?.textContent || '';
            const meta = item.querySelector('.timeline-meta')?.textContent || '';
            const [admin, data, ip] = meta.split('•').map(s => s.trim());
            
            csvContent += `"Log","${title.replace(/"/g, '""')}","${admin}","${ip}","${data}"\n`;
        });
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `historico_${periodo}dias_${Date.now()}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        alert('✅ CSV exportado com sucesso!');
    };
    
    console.log('✅ Histórico carregado com sucesso');
})();
</script>