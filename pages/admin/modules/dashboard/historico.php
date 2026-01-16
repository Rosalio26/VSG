<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - HISTÓRICO DE ATIVIDADES (CORRIGIDO)
 * Módulo: modules/dashboard/historico.php
 * Descrição: Timeline completa de logs com filtro por role
 * CORREÇÃO: Link para detalhes agora usa user_id correto
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

/* ================= CONSTRUIR WHERE CLAUSE COM PROTEÇÃO DE ROLE ================= */
$where_conditions = [];

// 🔐 PROTEÇÃO DE ROLE: Admin NÃO VÊ logs de SuperAdmins
if ($isSuperAdmin) {
    $where_conditions[] = "1=1";
} else {
    $where_conditions[] = "(u.role = 'admin' OR u.role IS NULL OR al.admin_id = $adminId)";
    $where_conditions[] = "(u.role != 'superadmin' OR u.role IS NULL)";
}

// Filtro de período
if ($filtro_periodo !== 'all') {
    $periodo_int = (int)$filtro_periodo;
    $where_conditions[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL $periodo_int DAY)";
}

// Filtro de tipo de ação
if ($filtro_tipo !== 'all') {
    $tipo_safe = $mysqli->real_escape_string($filtro_tipo);
    $where_conditions[] = "al.action LIKE '%$tipo_safe%'";
}

// Filtro de admin (só se for SuperAdmin)
if ($filtro_admin !== 'all' && $isSuperAdmin) {
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
        u.email as admin_email,
        u.role as admin_role,
        al.action,
        al.ip_address,
        al.user_agent,
        al.details,
        al.created_at,
        DATEDIFF(NOW(), al.created_at) as dias_atras
    FROM admin_audit_logs al
    LEFT JOIN users u ON al.admin_id = u.id
    WHERE $where_clause
    ORDER BY al.created_at DESC
    LIMIT 100
";
$result_historico = $mysqli->query($sql_historico);

/* ================= BUSCAR HISTÓRICO DE EMPRESAS (CORRIGIDO) ================= */
$periodo_safe = $filtro_periodo === 'all' ? '365' : (int)$filtro_periodo;
$sql_businesses_history = "
    SELECT 
        b.id as business_id,
        b.user_id,
        u.nome as empresa_nome,
        b.status_documentos,
        b.motivo_rejeicao,
        b.updated_at
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.updated_at >= DATE_SUB(NOW(), INTERVAL $periodo_safe DAY)
    AND b.status_documentos IN ('aprovado', 'rejeitado')
    AND u.deleted_at IS NULL
    ORDER BY b.updated_at DESC
    LIMIT 50
";
$result_business_history = $mysqli->query($sql_businesses_history);

/* ================= LISTA DE ADMINS PARA FILTRO (ROLE-BASED) ================= */
if ($isSuperAdmin) {
    $sql_admins = "
        SELECT id, nome, role 
        FROM users 
        WHERE role IN ('admin', 'superadmin')
        AND deleted_at IS NULL
        ORDER BY 
            FIELD(role, 'superadmin', 'admin'),
            nome ASC
    ";
} else {
    $sql_admins = "
        SELECT id, nome, role 
        FROM users 
        WHERE role = 'admin'
        AND deleted_at IS NULL
        ORDER BY nome ASC
    ";
}
$result_admins = $mysqli->query($sql_admins);

/* ================= ESTATÍSTICAS DO PERÍODO (ROLE-BASED) ================= */
if ($isSuperAdmin) {
    $sql_stats = "
        SELECT 
            COUNT(*) as total_acoes,
            COUNT(DISTINCT al.admin_id) as admins_ativos,
            COUNT(CASE WHEN al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as acoes_24h
        FROM admin_audit_logs al
        LEFT JOIN users u ON al.admin_id = u.id
        WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL $periodo_safe DAY)
    ";
} else {
    $sql_stats = "
        SELECT 
            COUNT(*) as total_acoes,
            COUNT(DISTINCT al.admin_id) as admins_ativos,
            COUNT(CASE WHEN al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as acoes_24h
        FROM admin_audit_logs al
        LEFT JOIN users u ON al.admin_id = u.id
        WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL $periodo_safe DAY)
        AND (u.role = 'admin' OR u.role IS NULL OR al.admin_id = $adminId)
        AND (u.role != 'superadmin' OR u.role IS NULL)
    ";
}
$stats = $mysqli->query($sql_stats)->fetch_assoc();

/* ================= DOCUMENTOS PROCESSADOS ================= */
$docs_aprovados = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'aprovado' 
    AND b.updated_at >= DATE_SUB(NOW(), INTERVAL $periodo_safe DAY)
    AND u.deleted_at IS NULL
")->fetch_assoc()['total'];

$docs_rejeitados = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.status_documentos = 'rejeitado' 
    AND b.updated_at >= DATE_SUB(NOW(), INTERVAL $periodo_safe DAY)
    AND u.deleted_at IS NULL
")->fetch_assoc()['total'];
?>

<!-- HEADER -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="color: var(--text-title); font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">
            <i class="fa-solid fa-clock-rotate-left" style="color: var(--accent);"></i>
            Histórico de Atividades
            <?php if (!$isSuperAdmin): ?>
                <span class="badge info" style="margin-left: 12px; font-size: 0.8rem;">
                    <i class="fa-solid fa-info-circle"></i>
                    Visualização Limitada
                </span>
            <?php endif; ?>
        </h1>
        <p style="color: var(--text-secondary); font-size: 0.875rem; margin: 0;">
            Mostrando <?= $result_historico ? $result_historico->num_rows : 0 ?> registros
        </p>
    </div>
    <div style="display: flex; gap: 8px;">
        <button class="btn btn-secondary" onclick="exportarHistoricoJSON()">
            <i class="fa-solid fa-file-code"></i>
            JSON
        </button>
        <button class="btn btn-secondary" onclick="exportarHistoricoExcel()">
            <i class="fa-solid fa-file-excel"></i>
            Excel
        </button>
        <button class="btn btn-primary" onclick="exportarHistoricoCSV()">
            <i class="fa-solid fa-download"></i>
            CSV
        </button>
    </div>
</div>

<!-- FILTROS -->
<div class="card mb-3">
    <div class="card-body">
        <div class="filters-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
            
            <div class="filter-item">
                <label class="form-label" style="margin-bottom: 8px; font-weight: 600;">Período:</label>
                <select class="form-select" id="filterPeriodo" onchange="aplicarFiltros()">
                    <option value="1" <?= $filtro_periodo === '1' ? 'selected' : '' ?>>Últimas 24h</option>
                    <option value="7" <?= $filtro_periodo === '7' ? 'selected' : '' ?>>Últimos 7 dias</option>
                    <option value="30" <?= $filtro_periodo === '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
                    <option value="90" <?= $filtro_periodo === '90' ? 'selected' : '' ?>>Últimos 90 dias</option>
                    <option value="all" <?= $filtro_periodo === 'all' ? 'selected' : '' ?>>Todo o histórico</option>
                </select>
            </div>

            <div class="filter-item">
                <label class="form-label" style="margin-bottom: 8px; font-weight: 600;">Tipo de Ação:</label>
                <select class="form-select" id="filterTipo" onchange="aplicarFiltros()">
                    <option value="all" <?= $filtro_tipo === 'all' ? 'selected' : '' ?>>Todas</option>
                    <option value="AUDIT" <?= $filtro_tipo === 'AUDIT' ? 'selected' : '' ?>>Auditorias</option>
                    <option value="LOGIN" <?= $filtro_tipo === 'LOGIN' ? 'selected' : '' ?>>Logins</option>
                    <option value="CREATE" <?= $filtro_tipo === 'CREATE' ? 'selected' : '' ?>>Criações</option>
                    <option value="UPDATE" <?= $filtro_tipo === 'UPDATE' ? 'selected' : '' ?>>Atualizações</option>
                    <option value="DELETE" <?= $filtro_tipo === 'DELETE' ? 'selected' : '' ?>>Exclusões</option>
                    <option value="BLOCKED" <?= $filtro_tipo === 'BLOCKED' ? 'selected' : '' ?>>Bloqueios</option>
                </select>
            </div>

            <?php if ($isSuperAdmin): ?>
            <div class="filter-item">
                <label class="form-label" style="margin-bottom: 8px; font-weight: 600;">Admin:</label>
                <select class="form-select" id="filterAdmin" onchange="aplicarFiltros()">
                    <option value="all">Todos os admins</option>
                    <?php 
                    $result_admins->data_seek(0);
                    while($admin = $result_admins->fetch_assoc()): 
                    ?>
                        <option value="<?= $admin['id'] ?>" <?= $filtro_admin == $admin['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($admin['nome']) ?>
                            <?php if ($admin['role'] === 'superadmin'): ?>
                                👑
                            <?php endif; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="filter-actions" style="display: flex; gap: 8px;">
                <button class="btn btn-ghost" onclick="limparFiltros()">
                    <i class="fa-solid fa-filter-circle-xmark"></i>
                    Limpar
                </button>
                <button class="btn btn-ghost" onclick="atualizarHistorico()">
                    <i class="fa-solid fa-rotate"></i>
                    Atualizar
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
            <i class="fa-solid fa-users-gear"></i>
        </div>
        <div class="stat-label">Admins Ativos</div>
        <div class="stat-value"><?= $stats['admins_ativos'] ?></div>
        <div class="stat-change info">
            <i class="fa-solid fa-user-shield"></i>
            <?= $isSuperAdmin ? 'Todos' : 'Admins' ?>
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

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-chart-pie"></i>
        </div>
        <div class="stat-label">Taxa Aprovação</div>
        <div class="stat-value">
            <?php 
            $total_processados = $docs_aprovados + $docs_rejeitados;
            $taxa = $total_processados > 0 ? round(($docs_aprovados / $total_processados) * 100, 1) : 0;
            echo $taxa;
            ?>%
        </div>
        <div class="stat-change <?= $taxa >= 80 ? 'positive' : ($taxa >= 50 ? 'neutral' : 'negative') ?>">
            <i class="fa-solid fa-<?= $taxa >= 80 ? 'check' : 'chart-line' ?>"></i>
            <?= $docs_aprovados ?>/<?= $total_processados ?>
        </div>
    </div>
</div>

<!-- TIMELINE GRID -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 24px;">
    
    <!-- LOGS DE AUDITORIA -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">
                <i class="fa-solid fa-clipboard-list"></i>
                Logs de Auditoria
            </h3>
            <span class="badge info"><?= $result_historico ? $result_historico->num_rows : 0 ?></span>
        </div>
        
        <div class="card-body" style="max-height: 700px; overflow-y: auto; padding: 16px;">
            <?php if ($result_historico && $result_historico->num_rows > 0): ?>
                <div class="timeline">
                    <?php while($log = $result_historico->fetch_assoc()): ?>
                    <?php
                        // Determinar classe da timeline baseado na ação
                        $timelineClass = 'pending';
                        if (strpos($log['action'], 'APPROVE') !== false || strpos($log['action'], 'LOGIN_SUCCESS') !== false || strpos($log['action'], 'CREATE') !== false) {
                            $timelineClass = 'completed';
                        } elseif (strpos($log['action'], 'REJECT') !== false || strpos($log['action'], 'DELETE') !== false || strpos($log['action'], 'BLOCKED') !== false || strpos($log['action'], 'LOGIN_FAILED') !== false) {
                            $timelineClass = 'rejected';
                        }
                    ?>
                    <div class="timeline-item <?= $timelineClass ?>">
                        <div class="timeline-content">
                            <div class="timeline-title" style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                <span><?= htmlspecialchars($log['action']) ?></span>
                                <?php if ($isSuperAdmin && $log['admin_role']): ?>
                                    <span class="badge <?= $log['admin_role'] === 'superadmin' ? 'error' : 'info' ?>" style="font-size: 0.65rem;">
                                        <?= $log['admin_role'] === 'superadmin' ? '👑 SUPERADMIN' : '🛡️ ADMIN' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-meta" style="margin-top: 8px;">
                                <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.75rem; color: var(--text-secondary);">
                                    <span>
                                        <i class="fa-solid fa-user"></i>
                                        <?= htmlspecialchars($log['admin_nome']) ?>
                                    </span>
                                    <span>
                                        <i class="fa-solid fa-clock"></i>
                                        <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                    </span>
                                    <span>
                                        <i class="fa-solid fa-location-dot"></i>
                                        <?= htmlspecialchars($log['ip_address']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($isSuperAdmin && !empty($log['details'])): ?>
                                <div style="margin-top: 10px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 6px; font-size: 0.7rem; color: var(--text-muted); font-family: 'Courier New', monospace; max-height: 100px; overflow-y: auto;">
                                    <strong style="color: var(--text-primary);">Detalhes:</strong><br>
                                    <?= htmlspecialchars(substr($log['details'], 0, 200)) ?><?= strlen($log['details']) > 200 ? '...' : '' ?>
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
                    <div class="empty-title">Nenhum log encontrado</div>
                    <div class="empty-description">
                        <?php if (!$isSuperAdmin): ?>
                            Você só vê logs de admins regulares
                        <?php else: ?>
                            Ajuste os filtros para ver mais resultados
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DOCUMENTOS PROCESSADOS (CORRIGIDO) -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">
                <i class="fa-solid fa-building-circle-check"></i>
                Documentos Processados
            </h3>
            <span class="badge success"><?= $result_business_history ? $result_business_history->num_rows : 0 ?></span>
        </div>
        
        <div class="card-body" style="max-height: 700px; overflow-y: auto; padding: 16px;">
            <?php if ($result_business_history && $result_business_history->num_rows > 0): ?>
                <div class="timeline">
                    <?php while($biz = $result_business_history->fetch_assoc()): ?>
                    <div class="timeline-item <?= $biz['status_documentos'] === 'aprovado' ? 'completed' : 'rejected' ?>">
                        <div class="timeline-content">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <div class="timeline-title"><?= htmlspecialchars($biz['empresa_nome']) ?></div>
                                <span class="badge <?= $biz['status_documentos'] === 'aprovado' ? 'success' : 'error' ?>">
                                    <i class="fa-solid fa-<?= $biz['status_documentos'] === 'aprovado' ? 'check' : 'times' ?>"></i>
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
                                    <strong style="font-size: 0.75rem;">Motivo da Rejeição:</strong><br>
                                    <span style="font-size: 0.75rem;"><?= htmlspecialchars($biz['motivo_rejeicao']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- CORREÇÃO: Usar user_id em vez de business_id -->
                            <button class="btn btn-sm btn-ghost" style="margin-top: 8px;" onclick="loadContent('modules/dashboard/analise?id=<?= $biz['user_id'] ?>')">
                                <i class="fa-solid fa-eye"></i>
                                Ver Detalhes
                            </button>
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

    window.atualizarHistorico = function() {
        const periodo = document.getElementById('filterPeriodo').value;
        const tipo = document.getElementById('filterTipo').value;
        const admin = document.getElementById('filterAdmin')?.value || 'all';
        
        let url = 'modules/dashboard/historico?';
        const params = [];
        if (periodo) params.push('periodo=' + periodo);
        if (tipo !== 'all') params.push('tipo=' + tipo);
        if (admin !== 'all') params.push('admin=' + admin);
        
        url += params.join('&') + '&_t=' + Date.now();
        loadContent(url);
    };

    window.exportarHistoricoCSV = function() {
        const periodo = document.getElementById('filterPeriodo').value;
        
        let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
        csvContent += "ID,Tipo,Ação,Admin,IP,Data/Hora\n";
        
        const timeline = document.querySelectorAll('.timeline-item');
        let rowNum = 1;
        timeline.forEach(item => {
            const title = item.querySelector('.timeline-title span')?.textContent.trim() || '';
            const metaSpans = item.querySelectorAll('.timeline-meta span');
            
            const admin = metaSpans[0]?.textContent.replace(/\s+/g, ' ').trim() || '';
            const data = metaSpans[1]?.textContent.replace(/\s+/g, ' ').trim() || '';
            const ip = metaSpans[2]?.textContent.replace(/\s+/g, ' ').trim() || '';
            
            csvContent += `${rowNum},"Log","${title.replace(/"/g, '""')}","${admin}","${ip}","${data}"\n`;
            rowNum++;
        });
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `historico_logs_${periodo}dias_${Date.now()}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        console.log('✅ CSV exportado com sucesso!');
    };

    window.exportarHistoricoJSON = function() {
        const periodo = document.getElementById('filterPeriodo').value;
        
        const logs = [];
        document.querySelectorAll('.timeline-item').forEach((item, index) => {
            const title = item.querySelector('.timeline-title span')?.textContent.trim() || '';
            const metaSpans = item.querySelectorAll('.timeline-meta span');
            
            logs.push({
                id: index + 1,
                action: title,
                admin: metaSpans[0]?.textContent.replace(/\s+/g, ' ').trim() || '',
                timestamp: metaSpans[1]?.textContent.replace(/\s+/g, ' ').trim() || '',
                ip: metaSpans[2]?.textContent.replace(/\s+/g, ' ').trim() || ''
            });
        });
        
        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(logs, null, 2));
        const link = document.createElement("a");
        link.setAttribute("href", dataStr);
        link.setAttribute("download", `historico_logs_${periodo}dias_${Date.now()}.json`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        console.log('✅ JSON exportado com sucesso!');
    };

    window.exportarHistoricoExcel = function() {
        alert('🚧 Exportação Excel em desenvolvimento.\nPor favor, use CSV por enquanto.');
    };
    
    console.log('✅ Histórico carregado com proteção de role');
    <?php if (!$isSuperAdmin): ?>
    console.log('ℹ️ Modo Admin: Logs de SuperAdmins ocultos');
    <?php else: ?>
    console.log('👑 Modo SuperAdmin: Todos os logs visíveis');
    <?php endif; ?>
})();
</script>