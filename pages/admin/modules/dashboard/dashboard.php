<?php
    if (!defined('IS_ADMIN_PAGE')) {
        require_once '../../../../registration/includes/db.php';
        session_start();
    }

    $adminId = $_SESSION['auth']['user_id'] ?? 0;
    $adminRole = $_SESSION['auth']['role'] ?? 'admin';
    $isSuperAdmin = ($adminRole === 'superadmin');

    /* ================= ESTATÍSTICAS GERAIS DO SISTEMA ================= */

    // 1. PENDÊNCIAS TOTAIS
    $pendencias = [
        'documentos' => $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'pendente'")->fetch_assoc()['total'],
        'usuarios' => $mysqli->query("SELECT COUNT(*) as total FROM users u LEFT JOIN businesses b ON u.id = b.user_id WHERE u.type = 'company' AND (b.status_documentos IS NULL OR b.status_documentos = 'pendente') AND DATEDIFF(NOW(), u.created_at) <= 30")->fetch_assoc()['total'],
        'alertas' => $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = $adminId AND status = 'unread' AND category IN ('alert', 'security', 'system_error')")->fetch_assoc()['total']
    ];
    $total_pendencias = array_sum($pendencias);

    // 2. ANÁLISE DE CONTAS
    $analise = [
        'total_empresas' => $mysqli->query("SELECT COUNT(*) as total FROM businesses")->fetch_assoc()['total'],
        'aprovadas' => $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'aprovado'")->fetch_assoc()['total'],
        'rejeitadas' => $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'rejeitado'")->fetch_assoc()['total'],
        'pendentes' => $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'pendente'")->fetch_assoc()['total']
    ];

    // Taxa de aprovação
    $analise['taxa_aprovacao'] = $analise['total_empresas'] > 0 
        ? round(($analise['aprovadas'] / $analise['total_empresas']) * 100, 1) 
        : 0;

    // 3. HISTÓRICO RECENTE (últimas 24h)
    $historico = [
        'novos_registros' => $mysqli->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['total'],
        'documentos_processados' => $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND status_documentos IN ('aprovado', 'rejeitado')")->fetch_assoc()['total'],
        'auditorias' => $isSuperAdmin ? $mysqli->query("SELECT COUNT(*) as total FROM admin_audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['total'] : 0
    ];

    // 4. PLATAFORMAS (Usuários por tipo)
    $plataformas = [
        'pessoas' => $mysqli->query("SELECT COUNT(*) as total FROM users WHERE type = 'person'")->fetch_assoc()['total'],
        'empresas' => $mysqli->query("SELECT COUNT(*) as total FROM users WHERE type = 'company'")->fetch_assoc()['total'],
        'admins' => $mysqli->query("SELECT COUNT(*) as total FROM users WHERE type = 'admin'")->fetch_assoc()['total']
    ];
    $plataformas['total'] = array_sum($plataformas);

    // 5. MENSAGENS
    $mensagens = [
        'nao_lidas' => $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = $adminId AND status = 'unread'")->fetch_assoc()['total'],
        'total_hoje' => $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = $adminId AND DATE(created_at) = CURDATE()")->fetch_assoc()['total']
    ];

    // 6. ATIVIDADE RECENTE (últimas 5 ações)
    $sql_atividade = "
        SELECT 
            al.action,
            COALESCE(u.nome, 'Sistema') as admin_nome,
            al.created_at,
            al.ip_address
        FROM admin_audit_logs al
        LEFT JOIN users u ON al.admin_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 5
    ";
    $atividade_recente = $mysqli->query($sql_atividade);

    // 7. DOCUMENTOS URGENTES (mais de 5 dias pendentes)
    $sql_urgentes = "
        SELECT 
            b.id,
            u.nome as empresa_nome,
            b.status_documentos,
            DATEDIFF(NOW(), u.created_at) as dias_pendente
        FROM businesses b
        INNER JOIN users u ON b.user_id = u.id
        WHERE b.status_documentos = 'pendente'
        AND DATEDIFF(NOW(), u.created_at) > 5
        ORDER BY dias_pendente DESC
        LIMIT 5
    ";
    $documentos_urgentes = $mysqli->query($sql_urgentes);
    $count_urgentes = $mysqli->query("SELECT COUNT(*) as total FROM businesses b INNER JOIN users u ON b.user_id = u.id WHERE b.status_documentos = 'pendente' AND DATEDIFF(NOW(), u.created_at) > 5")->fetch_assoc()['total'];

    // 8. STATUS DO SISTEMA
    $system_status = [
        'uptime' => 'Online',
        'last_backup' => 'Não implementado',
        'database_health' => 'Saudável'
    ];
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

    .dashboard-container {
        padding: 30px;
        background: #0005078a;
        border-radius: 10px;
        min-height: 100vh;
    }

    .dashboard-header {
        margin-bottom: 35px;
    }

    .dashboard-header h1 {
        color: var(--text-title);
        font-size: 2.2rem;
        font-weight: 800;
        margin: 0 0 10px 0;
        text-shadow: 0 0 30px var(--accent-glow);
    }

    .dashboard-subtitle {
        color: var(--text-main);
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 25px;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: var(--accent-green);
        transform: scaleX(0);
        transition: 0.3s;
        box-shadow: 0 0 20px var(--accent-glow);
    }

    .stat-card:hover {
        border-color: var(--accent-glow);
        transform: translateY(-4px);
        box-shadow: 0 10px 40px var(--accent-glow);
    }

    .stat-card:hover::before {
        transform: scaleX(1);
    }

    .stat-card.warning::before {
        background: #ff9500;
        box-shadow: 0 0 20px rgba(255, 149, 0, 0.3);
    }

    .stat-card.danger::before {
        background: #ff4d4d;
        box-shadow: 0 0 20px rgba(255, 77, 77, 0.3);
    }

    .stat-card.info::before {
        background: #4da3ff;
        box-shadow: 0 0 20px rgba(77, 163, 255, 0.3);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .stat-icon {
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
        box-shadow: 0 0 20px rgba(0, 255, 136, 0.1);
    }

    .stat-card.warning .stat-icon {
        background: rgba(255, 149, 0, 0.1);
        border-color: rgba(255, 149, 0, 0.2);
        color: #ff9500;
        box-shadow: 0 0 20px rgba(255, 149, 0, 0.1);
    }

    .stat-card.danger .stat-icon {
        background: rgba(255, 77, 77, 0.1);
        border-color: rgba(255, 77, 77, 0.2);
        color: #ff4d4d;
        box-shadow: 0 0 20px rgba(255, 77, 77, 0.1);
    }

    .stat-card.info .stat-icon {
        background: rgba(77, 163, 255, 0.1);
        border-color: rgba(77, 163, 255, 0.2);
        color: #4da3ff;
        box-shadow: 0 0 20px rgba(77, 163, 255, 0.1);
    }

    .stat-trend {
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 700;
        border: 1px solid;
    }

    .trend-up {
        background: rgba(0, 255, 136, 0.1);
        color: var(--accent-green);
        border-color: var(--border-color);
    }

    .trend-down {
        background: rgba(255, 77, 77, 0.1);
        color: #ff4d4d;
        border-color: rgba(255, 77, 77, 0.2);
    }

    .stat-content {
        margin-bottom: 15px;
    }

    .stat-label {
        color: var(--text-main);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .stat-value {
        color: var(--text-title);
        font-size: 2.2rem;
        font-weight: 800;
        line-height: 1;
    }

    .stat-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 15px;
        border-top: 1px solid var(--border-color);
    }

    .stat-link {
        color: var(--accent-green);
        font-size: 0.8rem;
        font-weight: 700;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: 0.3s;
    }

    .stat-link:hover {
        gap: 10px;
        color: #00ff88;
        text-shadow: 0 0 10px var(--accent-glow);
    }

    .stat-meta {
        color: var(--text-main);
        font-size: 0.75rem;
        font-weight: 600;
    }

    .wide-section {
        grid-column: 1 / -1;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 20px;
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
        font-size: 1.3rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .section-title i {
        color: var(--accent-green);
    }

    .activity-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .activity-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: rgba(0, 255, 136, 0.02);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        transition: 0.2s;
    }

    .activity-item:hover {
        background: rgba(0, 255, 136, 0.05);
        border-color: var(--accent-glow);
        box-shadow: 0 5px 20px rgba(0, 255, 136, 0.1);
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        background: rgba(0, 255, 136, 0.1);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-green);
        flex-shrink: 0;
    }

    .activity-content {
        flex: 1;
    }

    .activity-title {
        color: var(--text-title);
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 4px;
    }

    .activity-meta {
        color: var(--text-main);
        font-size: 0.75rem;
        display: flex;
        gap: 15px;
    }

    .urgent-badge {
        background: rgba(255, 77, 77, 0.1);
        color: #ff4d4d;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 900;
        text-transform: uppercase;
        border: 1px solid rgba(255, 77, 77, 0.2);
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: rgba(0, 255, 136, 0.05);
        border-radius: 10px;
        overflow: hidden;
        margin-top: 10px;
        border: 1px solid var(--border-color);
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--accent-green), var(--accent-emerald));
        border-radius: 10px;
        transition: 0.5s;
        box-shadow: 0 0 20px var(--accent-glow);
    }

    @keyframes pulse {
        0%, 100% { 
            opacity: 1; 
            box-shadow: 0 0 20px var(--accent-glow);
        }
        50% { 
            opacity: 0.7; 
            box-shadow: 0 0 40px var(--accent-glow);
        }
    }

    .pulse {
        animation: pulse 2s infinite;
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: var(--text-main);
        opacity: 0.5;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.3;
    }
</style>

<div class="dashboard-container">
    <!-- HEADER -->
    <div class="dashboard-header">
        <h1>Dashboard Principal</h1>
        <div class="dashboard-subtitle">
            <i class="fa-solid fa-circle" style="color: var(--accent-green); font-size: 0.6rem;"></i>
            Sistema operacional • Última atualização: <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <!-- GRID DE ESTATÍSTICAS -->
    <div class="dashboard-grid">
        
        <!-- CARD: PENDÊNCIAS -->
        <div class="stat-card danger <?= $total_pendencias > 0 ? 'pulse' : '' ?>" onclick="loadContent('modules/dashboard/pendencias')">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <?php if($total_pendencias > 0): ?>
                    <div class="stat-trend trend-down">REQUER AÇÃO</div>
                <?php endif; ?>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Pendências</div>
                <div class="stat-value"><?= $total_pendencias ?></div>
            </div>
            <div class="stat-footer">
                <span class="stat-link">
                    Ver detalhes <i class="fa-solid fa-arrow-right"></i>
                </span>
                <span class="stat-meta"><?= $pendencias['documentos'] ?> docs + <?= $pendencias['usuarios'] ?> users</span>
            </div>
        </div>

        <!-- CARD: ANÁLISE DE CONTAS -->
        <div class="stat-card" onclick="loadContent('modules/dashboard/analise')">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                </div>
                <div class="stat-trend trend-up"><?= $analise['taxa_aprovacao'] ?>%</div>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Empresas</div>
                <div class="stat-value"><?= $analise['total_empresas'] ?></div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $analise['taxa_aprovacao'] ?>%;"></div>
                </div>
            </div>
            <div class="stat-footer">
                <span class="stat-link">
                    Ver análise <i class="fa-solid fa-arrow-right"></i>
                </span>
                <span class="stat-meta"><?= $analise['aprovadas'] ?> aprovadas</span>
            </div>
        </div>

        <!-- CARD: HISTÓRICO 24H -->
        <div class="stat-card info" onclick="loadContent('modules/dashboard/historico')">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-label">Atividade (24h)</div>
                <div class="stat-value"><?= $historico['novos_registros'] + $historico['documentos_processados'] ?></div>
            </div>
            <div class="stat-footer">
                <span class="stat-link">
                    Ver histórico <i class="fa-solid fa-arrow-right"></i>
                </span>
                <span class="stat-meta"><?= $historico['novos_registros'] ?> novos registros</span>
            </div>
        </div>

        <!-- CARD: PLATAFORMAS -->
        <div class="stat-card warning" onclick="loadContent('modules/dashboard/plataformas')">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-label">Usuários Totais</div>
                <div class="stat-value"><?= $plataformas['total'] ?></div>
            </div>
            <div class="stat-footer">
                <span class="stat-link">
                    Ver plataformas <i class="fa-solid fa-arrow-right"></i>
                </span>
                <span class="stat-meta"><?= $plataformas['empresas'] ?> empresas</span>
            </div>
        </div>

        <!-- CARD: MENSAGENS -->
        <div class="stat-card <?= $mensagens['nao_lidas'] > 0 ? 'pulse' : '' ?>" onclick="loadContent('modules/mensagens/mensagens')">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fa-solid fa-comment-dots"></i>
                </div>
                <?php if($mensagens['nao_lidas'] > 0): ?>
                    <div class="stat-trend trend-down">NÃO LIDAS</div>
                <?php endif; ?>
            </div>
            <div class="stat-content">
                <div class="stat-label">Mensagens</div>
                <div class="stat-value"><?= $mensagens['nao_lidas'] ?></div>
            </div>
            <div class="stat-footer">
                <span class="stat-link">
                    Abrir inbox <i class="fa-solid fa-arrow-right"></i>
                </span>
                <span class="stat-meta"><?= $mensagens['total_hoje'] ?> hoje</span>
            </div>
        </div>

        <!-- CARD: USUÁRIOS -->
        <div class="stat-card info" onclick="loadContent('modules/usuarios/usuarios')">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
            </div>
            <div class="stat-content">
                <div class="stat-label">Gestão de Usuários</div>
                <div class="stat-value"><?= $plataformas['pessoas'] + $plataformas['empresas'] ?></div>
            </div>
            <div class="stat-footer">
                <span class="stat-link">
                    Gerenciar <i class="fa-solid fa-arrow-right"></i>
                </span>
                <span class="stat-meta"><?= $plataformas['admins'] ?> admins</span>
            </div>
        </div>

    </div>

    <!-- SEÇÃO: DOCUMENTOS URGENTES -->
    <?php if($count_urgentes > 0): ?>
    <div class="wide-section">
        <div class="section-header">
            <div class="section-title">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Documentos Urgentes
                <span class="urgent-badge"><?= $count_urgentes ?> ITENS</span>
            </div>
            <a href="javascript:void(0)" onclick="loadContent('modules/dashboard/pendencias')" style="color: var(--accent-green); text-decoration: none; font-weight: 700; font-size: 0.9rem;">
                Ver todos <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="activity-list">
            <?php while($doc = $documentos_urgentes->fetch_assoc()): ?>
                <div class="activity-item" onclick="window.open('modules/dashboard/analise?id=<?= $doc['id'] ?>', '_blank')" style="cursor: pointer;">
                    <div class="activity-icon" style="background: rgba(255,77,77,0.1); color: #ff4d4d;">
                        <i class="fa-solid fa-file-circle-exclamation"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title"><?= htmlspecialchars($doc['empresa_nome']) ?></div>
                        <div class="activity-meta">
                            <span><i class="fa-solid fa-clock"></i> <?= $doc['dias_pendente'] ?> dias pendente</span>
                            <span><i class="fa-solid fa-circle-info"></i> Status: <?= ucfirst($doc['status_documentos']) ?></span>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right" style="color: #333;"></i>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- SEÇÃO: ATIVIDADE RECENTE -->
    <div class="wide-section">
        <div class="section-header">
            <div class="section-title">
                <i class="fa-solid fa-chart-line"></i>
                Atividade Recente
            </div>
            <?php if($isSuperAdmin): ?>
                <a href="javascript:void(0)" onclick="loadContent('modules/auditor/auditor-logs')" style="color: var(--accent-green); text-decoration: none; font-weight: 700; font-size: 0.9rem;">
                    Ver logs completos <i class="fa-solid fa-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>

        <?php if($atividade_recente && $atividade_recente->num_rows > 0): ?>
            <div class="activity-list">
                <?php while($ativ = $atividade_recente->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?= htmlspecialchars($ativ['action']) ?></div>
                            <div class="activity-meta">
                                <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($ativ['admin_nome']) ?></span>
                                <span><i class="fa-solid fa-clock"></i> <?= date('d/m/Y H:i', strtotime($ativ['created_at'])) ?></span>
                                <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($ativ['ip_address']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-inbox"></i>
                <p>Nenhuma atividade recente registrada</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
    // Auto-refresh do dashboard a cada 60 segundos
    setInterval(() => {
        const currentPage = new URLSearchParams(window.location.search).get('page');
        if (currentPage === 'modules/dashboard/dashboard' || !currentPage) {
            loadContent('modules/dashboard/dashboard');
        }
    }, 60000);
</script>
