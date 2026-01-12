<?php
    if (!defined('IS_ADMIN_PAGE')) {
        require_once '../../../../registration/includes/db.php';
        session_start();
    }

    $adminId = $_SESSION['auth']['user_id'] ?? 0;
    $adminRole = $_SESSION['auth']['role'] ?? 'admin';
    $isSuperAdmin = ($adminRole === 'superadmin');

    /* ================= BUSCAR HISTÓRICO DE ATIVIDADES ================= */

    // Filtros
    $filtro_periodo = $_GET['periodo'] ?? '7';
    $filtro_tipo = $_GET['tipo'] ?? 'all';
    $filtro_admin = $_GET['admin'] ?? 'all';

    // Query de logs de auditoria
    $where_conditions = ["1=1"];

    // Filtro de período
    if ($filtro_periodo !== 'all') {
        $where_conditions[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL $filtro_periodo DAY)";
    }

    // Filtro de tipo de ação
    if ($filtro_tipo !== 'all') {
        $tipo_escaped = $mysqli->real_escape_string($filtro_tipo);
        $where_conditions[] = "al.action LIKE '%$tipo_escaped%'";
    }

    // Filtro de admin
    if ($filtro_admin !== 'all') {
        $where_conditions[] = "al.admin_id = " . (int)$filtro_admin;
    }

    $where_clause = implode(" AND ", $where_conditions);

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

    // Buscar histórico de mudanças de status em businesses
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
        WHERE b.updated_at >= DATE_SUB(NOW(), INTERVAL $filtro_periodo DAY)
        AND b.status_documentos IN ('aprovado', 'rejeitado')
        ORDER BY b.updated_at DESC
        LIMIT 50
    ";

    $result_business_history = $mysqli->query($sql_businesses_history);

    // Lista de admins para filtro
    $sql_admins = "SELECT id, nome FROM users WHERE role IN ('admin', 'superadmin') ORDER BY nome ASC";
    $result_admins = $mysqli->query($sql_admins);

    // Estatísticas do período
    $sql_stats = "
        SELECT 
            COUNT(*) as total_acoes,
            COUNT(DISTINCT admin_id) as admins_ativos,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as acoes_24h
        FROM admin_audit_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL $filtro_periodo DAY)
    ";
    $stats = $mysqli->query($sql_stats)->fetch_assoc();

    // Documentos processados no período
    $docs_aprovados = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'aprovado' AND updated_at >= DATE_SUB(NOW(), INTERVAL $filtro_periodo DAY)")->fetch_assoc()['total'];
    $docs_rejeitados = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'rejeitado' AND updated_at >= DATE_SUB(NOW(), INTERVAL $filtro_periodo DAY)")->fetch_assoc()['total'];
?>

<style>
    :root {
        --bg-sidebar: #0b0f0a;
        --bg-body: #050705;
        --bg-card: #121812;
        --text-main: #a0ac9f;
        --text-title: #ffffff;
        --accent-green: #00ff88;
        --accent-emerald: #00a63e;
        --accent-glow: rgba(0, 255, 136, 0.3);
        --border-color: rgba(0, 255, 136, 0.08);
    }

    .historico-container {
        padding: 30px;
        background: #0005078a;
        border-radius: 10px;
        min-height: 100vh;
    }

    .historico-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .historico-header h1 {
        color: var(--text-title);
        font-size: 2rem;
        font-weight: 800;
        margin: 0;
        text-shadow: 0 0 30px var(--accent-glow);
    }

    .export-btn {
        background: var(--accent-green);
        color: #000;
        border: none;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .export-btn:hover {
        box-shadow: 0 0 20px var(--accent-glow);
        transform: translateY(-2px);
    }

    .filter-bar {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 20px 25px;
        margin-bottom: 25px;
        display: flex;
        gap: 15px;
        align-items: center;
        flex-wrap: wrap;
    }

    .filter-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .filter-label {
        color: var(--text-main);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-select {
        background: rgba(0, 255, 136, 0.05);
        border: 1px solid var(--border-color);
        color: var(--text-title);
        padding: 10px 15px;
        border-radius: 10px;
        font-size: 0.9rem;
        outline: none;
        transition: 0.3s;
        min-width: 150px;
    }

    .filter-select:focus {
        border-color: var(--accent-green);
        box-shadow: 0 0 20px var(--accent-glow);
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-mini-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        transition: 0.3s;
    }

    .stat-mini-card:hover {
        border-color: var(--accent-glow);
        box-shadow: 0 5px 20px var(--accent-glow);
    }

    .stat-mini-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .stat-mini-info {
        flex: 1;
    }

    .stat-mini-value {
        color: var(--text-title);
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 5px;
    }

    .stat-mini-label {
        color: var(--text-main);
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .stat-mini-icon {
        width: 50px;
        height: 50px;
        background: rgba(0, 255, 136, 0.1);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: var(--accent-green);
    }

    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .timeline-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 25px;
        max-height: 800px;
        overflow-y: auto;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }

    .section-title {
        color: var(--text-title);
        font-size: 1.2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-title i {
        color: var(--accent-green);
    }

    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(180deg, var(--accent-green), transparent);
    }

    .timeline-item {
        position: relative;
        padding-bottom: 25px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -34px;
        top: 0;
        width: 10px;
        height: 10px;
        background: var(--accent-green);
        border-radius: 50%;
        box-shadow: 0 0 10px var(--accent-glow);
    }

    .timeline-card {
        background: rgba(0, 255, 136, 0.02);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 15px;
        transition: 0.2s;
    }

    .timeline-card:hover {
        background: rgba(0, 255, 136, 0.05);
        border-color: var(--accent-glow);
    }

    .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }

    .timeline-action {
        color: var(--text-title);
        font-weight: 600;
        font-size: 0.9rem;
    }

    .timeline-time {
        color: var(--text-main);
        font-size: 0.75rem;
    }

    .timeline-meta {
        color: var(--text-main);
        font-size: 0.8rem;
        display: flex;
        gap: 15px;
        margin-top: 8px;
    }

    .timeline-meta span {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .action-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        border: 1px solid;
    }

    .action-badge.aprovado {
        background: rgba(0, 255, 136, 0.1);
        color: var(--accent-green);
        border-color: var(--border-color);
    }

    .action-badge.rejeitado {
        background: rgba(255, 77, 77, 0.1);
        color: #ff4d4d;
        border-color: rgba(255, 77, 77, 0.2);
    }

    .action-badge.sistema {
        background: rgba(77, 163, 255, 0.1);
        color: #4da3ff;
        border-color: rgba(77, 163, 255, 0.2);
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-main);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.3;
        color: var(--accent-green);
    }

    /* Scrollbar personalizada */
    .timeline-section::-webkit-scrollbar {
        width: 8px;
    }

    .timeline-section::-webkit-scrollbar-track {
        background: rgba(0, 255, 136, 0.02);
        border-radius: 10px;
    }

    .timeline-section::-webkit-scrollbar-thumb {
        background: var(--accent-green);
        border-radius: 10px;
    }

    .timeline-section::-webkit-scrollbar-thumb:hover {
        background: #00ff88;
    }

    @media (max-width: 1200px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="historico-container">
    <!-- HEADER -->
    <div class="historico-header">
        <h1>Histórico de Atividades</h1>
        <button class="export-btn" onclick="exportarHistorico()">
            <i class="fa-solid fa-download"></i>
            Exportar CSV
        </button>
    </div>

    <!-- FILTROS -->
    <div class="filter-bar">
        <div class="filter-group">
            <span class="filter-label">Período:</span>
            <select class="filter-select" id="filterPeriodo" onchange="aplicarFiltros()">
                <option value="1" <?= $filtro_periodo === '1' ? 'selected' : '' ?>>Últimas 24h</option>
                <option value="7" <?= $filtro_periodo === '7' ? 'selected' : '' ?>>Últimos 7 dias</option>
                <option value="30" <?= $filtro_periodo === '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
                <option value="90" <?= $filtro_periodo === '90' ? 'selected' : '' ?>>Últimos 90 dias</option>
                <option value="all" <?= $filtro_periodo === 'all' ? 'selected' : '' ?>>Todo o histórico</option>
            </select>
        </div>

        <div class="filter-group">
            <span class="filter-label">Tipo de Ação:</span>
            <select class="filter-select" id="filterTipo" onchange="aplicarFiltros()">
                <option value="all" <?= $filtro_tipo === 'all' ? 'selected' : '' ?>>Todas</option>
                <option value="AUDIT" <?= $filtro_tipo === 'AUDIT' ? 'selected' : '' ?>>Auditorias</option>
                <option value="LOGIN" <?= $filtro_tipo === 'LOGIN' ? 'selected' : '' ?>>Logins</option>
                <option value="CREATE" <?= $filtro_tipo === 'CREATE' ? 'selected' : '' ?>>Criações</option>
                <option value="UPDATE" <?= $filtro_tipo === 'UPDATE' ? 'selected' : '' ?>>Atualizações</option>
                <option value="DELETE" <?= $filtro_tipo === 'DELETE' ? 'selected' : '' ?>>Exclusões</option>
            </select>
        </div>

        <?php if ($isSuperAdmin): ?>
        <div class="filter-group">
            <span class="filter-label">Admin:</span>
            <select class="filter-select" id="filterAdmin" onchange="aplicarFiltros()">
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

        <button class="export-btn" onclick="limparFiltros()" style="margin-left: auto; background: rgba(255,255,255,0.05); color: var(--text-main);">
            <i class="fa-solid fa-filter-circle-xmark"></i>
            Limpar
        </button>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="stats-row">
        <div class="stat-mini-card">
            <div class="stat-mini-content">
                <div class="stat-mini-info">
                    <div class="stat-mini-value"><?= $stats['total_acoes'] ?></div>
                    <div class="stat-mini-label">Total de Ações</div>
                </div>
                <div class="stat-mini-icon">
                    <i class="fa-solid fa-list-check"></i>
                </div>
            </div>
        </div>

        <div class="stat-mini-card">
            <div class="stat-mini-content">
                <div class="stat-mini-info">
                    <div class="stat-mini-value"><?= $stats['acoes_24h'] ?></div>
                    <div class="stat-mini-label">Últimas 24h</div>
                </div>
                <div class="stat-mini-icon" style="background: rgba(255, 149, 0, 0.1); color: #ff9500;">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
        </div>

        <div class="stat-mini-card">
            <div class="stat-mini-content">
                <div class="stat-mini-info">
                    <div class="stat-mini-value"><?= $docs_aprovados ?></div>
                    <div class="stat-mini-label">Docs Aprovados</div>
                </div>
                <div class="stat-mini-icon">
                    <i class="fa-solid fa-check-circle"></i>
                </div>
            </div>
        </div>

        <div class="stat-mini-card">
            <div class="stat-mini-content">
                <div class="stat-mini-info">
                    <div class="stat-mini-value"><?= $docs_rejeitados ?></div>
                    <div class="stat-mini-label">Docs Rejeitados</div>
                </div>
                <div class="stat-mini-icon" style="background: rgba(255, 77, 77, 0.1); color: #ff4d4d;">
                    <i class="fa-solid fa-times-circle"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- TIMELINE DUPLA -->
    <div class="content-grid">
        <!-- LOGS DE AUDITORIA -->
        <div class="timeline-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fa-solid fa-clipboard-list"></i>
                    Logs de Auditoria
                </div>
            </div>

            <?php if ($result_historico && $result_historico->num_rows > 0): ?>
                <div class="timeline">
                    <?php while($log = $result_historico->fetch_assoc()): ?>
                        <div class="timeline-item">
                            <div class="timeline-card">
                                <div class="timeline-header">
                                    <div class="timeline-action">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </div>
                                    <div class="timeline-time">
                                        <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="timeline-meta">
                                    <span>
                                        <i class="fa-solid fa-user"></i>
                                        <?= htmlspecialchars($log['admin_nome']) ?>
                                    </span>
                                    <span>
                                        <i class="fa-solid fa-location-dot"></i>
                                        <?= htmlspecialchars($log['ip_address']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-inbox"></i>
                    <p>Nenhum log encontrado</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- MUDANÇAS DE STATUS DE EMPRESAS -->
        <div class="timeline-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fa-solid fa-building-circle-check"></i>
                    Documentos Processados
                </div>
            </div>

            <?php if ($result_business_history && $result_business_history->num_rows > 0): ?>
                <div class="timeline">
                    <?php while($biz = $result_business_history->fetch_assoc()): ?>
                        <div class="timeline-item">
                            <div class="timeline-card">
                                <div class="timeline-header">
                                    <div class="timeline-action">
                                        <?= htmlspecialchars($biz['empresa_nome']) ?>
                                        <span class="action-badge <?= $biz['status_documentos'] ?>">
                                            <?= ucfirst($biz['status_documentos']) ?>
                                        </span>
                                    </div>
                                    <div class="timeline-time">
                                        <?= date('d/m/Y H:i', strtotime($biz['updated_at'])) ?>
                                    </div>
                                </div>
                                <?php if ($biz['status_documentos'] === 'rejeitado' && !empty($biz['motivo_rejeicao'])): ?>
                                    <div style="margin-top: 10px; padding: 10px; background: rgba(255,77,77,0.05); border-radius: 8px; border: 1px solid rgba(255,77,77,0.2);">
                                        <small style="color: #ff4d4d; font-size: 0.75rem;">
                                            <i class="fa-solid fa-exclamation-triangle"></i>
                                            <?= htmlspecialchars($biz['motivo_rejeicao']) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-inbox"></i>
                    <p>Nenhum documento processado</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function aplicarFiltros() {
        const periodo = document.getElementById('filterPeriodo').value;
        const tipo = document.getElementById('filterTipo').value;
        const admin = document.getElementById('filterAdmin')?.value || 'all';
        
        let url = 'modules/dashboard/historico?';
        if (periodo) url += 'periodo=' + periodo + '&';
        if (tipo !== 'all') url += 'tipo=' + tipo + '&';
        if (admin !== 'all') url += 'admin=' + admin;
        
        loadContent(url);
    }

    function limparFiltros() {
        loadContent('modules/dashboard/historico');
    }

    function exportarHistorico() {
        const periodo = document.getElementById('filterPeriodo').value;
        window.open('modules/dashboard/historico_inc/export.php?periodo=' + periodo, '_blank');
    }
</script>
