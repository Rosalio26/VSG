<?php
    if (!defined('IS_ADMIN_PAGE')) {
        require_once '../../../../registration/includes/db.php';
        session_start();
    }

    $adminId = $_SESSION['auth']['user_id'] ?? 0;
    $adminRole = $_SESSION['auth']['role'] ?? 'admin';
    $isSuperAdmin = ($adminRole === 'superadmin');

    /* ================= BUSCAR PENDÊNCIAS DO BANCO DE DADOS ================= */

    // 1. DOCUMENTOS AGUARDANDO APROVAÇÃO
    $sql_docs = "
        SELECT 
            b.id,
            b.user_id,
            u.nome as empresa_nome,
            u.email,
            b.status_documentos,
            u.created_at,
            b.license_path,
            b.tax_id,
            DATEDIFF(NOW(), u.created_at) as dias_pendente
        FROM businesses b
        INNER JOIN users u ON b.user_id = u.id
        WHERE b.status_documentos = 'pendente'
        ORDER BY u.created_at ASC
        LIMIT 10
    ";
    $result_docs = $mysqli->query($sql_docs);

    // 2. USUÁRIOS NOVOS SEM APROVAÇÃO (últimos 30 dias)
    $sql_users = "
        SELECT 
            u.id,
            u.nome,
            u.email,
            u.type,
            u.created_at,
            DATEDIFF(NOW(), u.created_at) as dias_registrado,
            b.status_documentos
        FROM users u
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE u.type = 'company' 
        AND (b.status_documentos IS NULL OR b.status_documentos = 'pendente')
        AND DATEDIFF(NOW(), u.created_at) <= 30
        ORDER BY u.created_at DESC
        LIMIT 10
    ";
    $result_users = $mysqli->query($sql_users);

    // 3. ALERTAS CRÍTICOS NÃO LIDOS
    $sql_alerts = "
        SELECT 
            n.id,
            n.subject,
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
        ORDER BY 
            FIELD(n.priority, 'critical', 'high', 'medium', 'low'),
            n.created_at DESC
        LIMIT 10
    ";
    $stmt_alerts = $mysqli->prepare($sql_alerts);
    $stmt_alerts->bind_param("i", $adminId);
    $stmt_alerts->execute();
    $result_alerts = $stmt_alerts->get_result();

    // 4. AUDITORIAS PENDENTES (se SuperAdmin)
    $result_audits = null;
    if ($isSuperAdmin) {
        $sql_audits = "
            SELECT 
                al.id,
                al.admin_id,
                COALESCE(u.nome, 'Admin Desconhecido') as auditor_nome,
                al.action,
                al.ip_address,
                al.created_at,
                DATEDIFF(NOW(), al.created_at) as dias_auditoria
            FROM admin_audit_logs al
            LEFT JOIN users u ON al.admin_id = u.id
            WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY al.created_at DESC
            LIMIT 10
        ";
        $result_audits = $mysqli->query($sql_audits);
    }

    // 5. CONTADORES TOTAIS
    $count_docs = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'pendente'")->fetch_assoc()['total'];
    $count_users = $mysqli->query("SELECT COUNT(*) as total FROM users u LEFT JOIN businesses b ON u.id = b.user_id WHERE u.type = 'company' AND (b.status_documentos IS NULL OR b.status_documentos = 'pendente') AND DATEDIFF(NOW(), u.created_at) <= 30")->fetch_assoc()['total'];
    $count_alerts = $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = $adminId AND status = 'unread' AND category IN ('alert', 'security', 'system_error')")->fetch_assoc()['total'];
    $count_audits = $isSuperAdmin ? $mysqli->query("SELECT COUNT(*) as total FROM admin_audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['total'] : 0;

    $total_pendencias = $count_docs + $count_users + $count_alerts + $count_audits;
?>

<style>
    .pendencias-container {
        padding: 30px;
        background: #0005078a;
        border-radius: 10px;
        min-height: 100vh;
    }

    .pendencias-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    .pendencias-title {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .pendencias-title h1 {
        color: #fff;
        font-size: 2rem;
        font-weight: 800;
        margin: 0;
    }

    .total-badge {
        background: var(--accent-green);
        color: #000;
        padding: 8px 16px;
        border-radius: 12px;
        font-weight: 900;
        font-size: 1.1rem;
        box-shadow: 0 0 20px rgba(0,255,136,0.3);
    }

    .filter-actions {
        display: flex;
        gap: 10px;
    }

    .filter-btn {
        background: rgba(33, 31, 31, 0.33);
        border: 1px solid rgba(255,255,255,0.1);
        color: #b6b6b6;
        padding: 10px 20px;
        border-radius: 10px;
        cursor: pointer;
        transition: 0.3s;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .filter-btn:hover, .filter-btn.active {
        background: var(--accent-green);
        color: #000;
        border-color: var(--accent-green);
    }

    .pendencias-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .pendencia-card {
        background: rgba(0, 1, 14, 0.25);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 16px;
        overflow: hidden;
        transition: 0.3s;
    }

    .pendencia-card:hover {
        border-color: rgba(0,255,136,0.3);
        box-shadow: 0 10px 40px rgba(0,255,136,0.1);
        transform: translateY(-2px);
    }

    .card-header {
        padding: 20px 25px;
        background: rgba(255,255,255,0.03);
        border-bottom: 1px solid rgba(255,255,255,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .card-icon {
        width: 40px;
        height: 40px;
        background: var(--accent-green);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: #000;
    }

    .card-icon.warning {
        background: #ff9500;
    }

    .card-icon.danger {
        background: #ff4d4d;
    }

    .card-icon.info {
        background: #4da3ff;
    }

    .card-title h3 {
        color: #fff;
        font-size: 1.1rem;
        font-weight: 700;
        margin: 0;
    }

    .card-count {
        background: rgba(0,255,136,0.1);
        color: var(--accent-green);
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 900;
        font-size: 0.9rem;
    }

    .card-count.warning {
        background: rgba(255,149,0,0.1);
        color: #ff9500;
    }

    .card-count.danger {
        background: rgba(255,77,77,0.1);
        color: #ff4d4d;
    }

    .card-count.info {
        background: rgba(77,163,255,0.1);
        color: #4da3ff;
    }

    .card-body {
        padding: 0;
    }

    .mini-table {
        width: 100%;
        border-collapse: collapse;
    }

    .mini-table thead {
        background: rgba(255,255,255,0.02);
    }

    .mini-table th {
        padding: 12px 20px;
        text-align: left;
        color: #666;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    .mini-table tbody tr {
        border-bottom: 1px solid rgba(255,255,255,0.03);
        transition: 0.2s;
        cursor: pointer;
    }

    .mini-table tbody tr:hover {
        background: rgba(0,255,136,0.05);
    }

    .mini-table td {
        padding: 15px 20px;
        color: #ccc;
        font-size: 0.85rem;
    }

    .mini-table td:first-child {
        font-weight: 600;
        color: #fff;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }

    .status-badge.pendente {
        background: rgba(255,149,0,0.1);
        color: #ff9500;
        border: 1px solid rgba(255,149,0,0.2);
    }

    .status-badge.em_analise {
        background: rgba(77,163,255,0.1);
        color: #4da3ff;
        border: 1px solid rgba(77,163,255,0.2);
    }

    .status-badge.critical {
        background: rgba(255,77,77,0.1);
        color: #ff4d4d;
        border: 1px solid rgba(255,77,77,0.2);
    }

    .status-badge.high {
        background: rgba(255,149,0,0.1);
        color: #ff9500;
        border: 1px solid rgba(255,149,0,0.2);
    }

    .status-badge.medium {
        background: rgba(77,163,255,0.1);
        color: #4da3ff;
        border: 1px solid rgba(77,163,255,0.2);
    }

    .status-badge.low {
        background: rgba(0,255,136,0.1);
        color: var(--accent-green);
        border: 1px solid rgba(0,255,136,0.2);
    }

    .days-badge {
        color: #666;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .days-badge.urgent {
        color: #ff4d4d;
        font-weight: 700;
    }

    .empty-state {
        padding: 60px 20px;
        text-align: center;
        color: #b4b4b4;
        font-weight: lighter;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .empty-state p {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0;
    }

    .card-footer {
        padding: 15px 25px;
        background: rgba(255,255,255,0.02);
        border-top: 1px solid rgba(255,255,255,0.05);
        text-align: center;
    }

    .view-all-btn {
        color: var(--accent-green);
        text-decoration: none;
        font-weight: 700;
        font-size: 0.85rem;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .view-all-btn:hover {
        color: #00ff88;
        gap: 12px;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .pulse {
        animation: pulse 2s infinite;
    }
</style>

<div class="pendencias-container">
    <!-- HEADER -->
    <div class="pendencias-header">
        <div class="pendencias-title">
            <h1>Pendências</h1>
            <div class="total-badge"><?= $total_pendencias ?></div>
        </div>
        
        <div class="filter-actions">
            <button class="filter-btn active" onclick="filterAll()">
                <i class="fa-solid fa-list"></i> Todas
            </button>
            <button class="filter-btn" onclick="filterUrgent()">
                <i class="fa-solid fa-exclamation-triangle"></i> Urgentes
            </button>
            <button class="filter-btn" onclick="refreshPendencias()">
                <i class="fa-solid fa-rotate"></i> Atualizar
            </button>
        </div>
    </div>

    <!-- GRID DE CARDS -->
    <div class="pendencias-grid">
        
        <!-- CARD 1: DOCUMENTOS AGUARDANDO APROVAÇÃO -->
        <div class="pendencia-card" data-category="documentos">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-icon">
                        <i class="fa-solid fa-file-circle-check"></i>
                    </div>
                    <h3>Documentos Pendentes</h3>
                </div>
                <div class="card-count"><?= $count_docs ?></div>
            </div>
            
            <div class="card-body">
                <?php if ($result_docs && $result_docs->num_rows > 0): ?>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Status</th>
                                <th>Tempo</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($doc = $result_docs->fetch_assoc()): ?>
                                <tr onclick="window.open('modules/dashboard/analise?id=<?= $doc['user_id'] ?>', '_blank')">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($doc['empresa_nome']) ?>&background=333&color=fff" style="width: 30px; height: 30px; border-radius: 8px;">
                                            <div>
                                                <strong><?= htmlspecialchars($doc['empresa_nome']) ?></strong><br>
                                                <small style="color: #666; font-size: 0.7rem;"><?= htmlspecialchars($doc['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $doc['status_documentos'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $doc['status_documentos'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="days-badge <?= $doc['dias_pendente'] > 5 ? 'urgent' : '' ?>">
                                            <?= $doc['dias_pendente'] ?> dias
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fa-solid fa-arrow-up-right-from-square" style="color: var(--accent-green); font-size: 0.9rem;"></i>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-check-circle"></i>
                        <p>Nenhum documento pendente</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($count_docs > 10): ?>
                <div class="card-footer">
                    <a href="javascript:void(0)" onclick="loadContent('modules/dashboard/analise')" class="view-all-btn">
                        Ver todos (<?= $count_docs ?>) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- CARD 2: USUÁRIOS NOVOS -->
        <div class="pendencia-card" data-category="usuarios">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-icon warning">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <h3>Novos Usuários</h3>
                </div>
                <div class="card-count warning"><?= $count_users ?></div>
            </div>
            
            <div class="card-body">
                <?php if ($result_users && $result_users->num_rows > 0): ?>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Tipo</th>
                                <th>Registrado</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($user = $result_users->fetch_assoc()): ?>
                                <tr onclick="window.open('modules/usuarios/usuarios?id=<?= $user['id'] ?>', '_blank')">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['nome']) ?>&background=ff9500&color=000" style="width: 30px; height: 30px; border-radius: 8px;">
                                            <div>
                                                <strong><?= htmlspecialchars($user['nome']) ?></strong><br>
                                                <small style="color: #666; font-size: 0.7rem;"><?= htmlspecialchars($user['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color: #ff9500; font-weight: 600; font-size: 0.8rem;">
                                            <?= ucfirst($user['type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="days-badge <?= $user['dias_registrado'] > 7 ? 'urgent' : '' ?>">
                                            <?= $user['dias_registrado'] ?> dias
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fa-solid fa-arrow-up-right-from-square" style="color: #ff9500; font-size: 0.9rem;"></i>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-users"></i>
                        <p>Nenhum usuário novo</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($count_users > 10): ?>
                <div class="card-footer">
                    <a href="javascript:void(0)" onclick="loadContent('modules/usuarios/usuarios')" class="view-all-btn">
                        Ver todos (<?= $count_users ?>) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- CARD 3: ALERTAS CRÍTICOS -->
        <div class="pendencia-card" data-category="alertas">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-icon danger">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h3>Alertas Críticos</h3>
                </div>
                <div class="card-count danger <?= $count_alerts > 0 ? 'pulse' : '' ?>"><?= $count_alerts ?></div>
            </div>
            
            <div class="card-body">
                <?php if ($result_alerts && $result_alerts->num_rows > 0): ?>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>Alerta</th>
                                <th>Categoria</th>
                                <th>Prioridade</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($alert = $result_alerts->fetch_assoc()): ?>
                                <tr onclick="window.open('modules/mensagens/mensagens?id=<?= $alert['id'] ?>', '_blank')">
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($alert['subject']) ?></strong><br>
                                            <small style="color: #666; font-size: 0.7rem;">
                                                <?= $alert['sender_name'] ? 'De: ' . htmlspecialchars($alert['sender_name']) : 'Sistema' ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color: #ff4d4d; font-weight: 600; font-size: 0.8rem; text-transform: uppercase;">
                                            <?= str_replace('_', ' ', $alert['category']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $alert['priority'] ?>">
                                            <?= ucfirst($alert['priority']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fa-solid fa-arrow-up-right-from-square" style="color: #ff4d4d; font-size: 0.9rem;"></i>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-shield-check"></i>
                        <p>Nenhum alerta crítico</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($count_alerts > 10): ?>
                <div class="card-footer">
                    <a href="javascript:void(0)" onclick="loadContent('modules/mensagens/mensagens')" class="view-all-btn">
                        Ver todos (<?= $count_alerts ?>) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- CARD 4: AUDITORIAS RECENTES (APENAS SUPERADMIN) -->
        <?php if ($isSuperAdmin): ?>
        <div class="pendencia-card" data-category="auditorias">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-icon info">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <h3>Auditorias (7 dias)</h3>
                </div>
                <div class="card-count info"><?= $count_audits ?></div>
            </div>
            
            <div class="card-body">
                <?php if ($result_audits && $result_audits->num_rows > 0): ?>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>Auditor</th>
                                <th>Ação</th>
                                <th>Data</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($audit = $result_audits->fetch_assoc()): ?>
                                <tr onclick="window.open('modules/auditor/auditor-logs?id=<?= $audit['id'] ?>', '_blank')">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($audit['auditor_nome']) ?>&background=4da3ff&color=000" style="width: 30px; height: 30px; border-radius: 8px;">
                                            <strong><?= htmlspecialchars($audit['auditor_nome']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color: #4da3ff; font-weight: 600; font-size: 0.8rem; text-transform: uppercase;">
                                            <?= htmlspecialchars($audit['action']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="days-badge">
                                            <?= date('d/m H:i', strtotime($audit['created_at'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small style="color: #666; font-family: 'Courier New', monospace;">
                                            <?= htmlspecialchars($audit['ip_address']) ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-clipboard-list"></i>
                        <p>Nenhuma auditoria recente</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($count_audits > 10): ?>
                <div class="card-footer">
                    <a href="javascript:void(0)" onclick="loadContent('modules/auditor/auditor-logs')" class="view-all-btn">
                        Ver todas (<?= $count_audits ?>) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
    // Filtrar todas as pendências
    function filterAll() {
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        event.target.closest('.filter-btn').classList.add('active');
        
        document.querySelectorAll('.pendencia-card').forEach(card => {
            card.style.display = 'block';
        });
    }

    // Filtrar apenas urgentes
    function filterUrgent() {
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        event.target.closest('.filter-btn').classList.add('active');
        
        document.querySelectorAll('.pendencia-card').forEach(card => {
            const hasUrgent = card.querySelector('.urgent, .pulse');
            card.style.display = hasUrgent ? 'block' : 'none';
        });
    }

    // Atualizar pendências
    function refreshPendencias() {
        const btn = event.target.closest('.filter-btn');
        const icon = btn.querySelector('i');
        
        icon.style.animation = 'spin 1s linear';
        
        setTimeout(() => {
            loadContent('modules/dashboard/pendencias');
        }, 500);
    }

    // Animação de rotação
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);

    // Auto-refresh a cada 30 segundos
    setInterval(() => {
        loadContent('modules/dashboard/pendencias');
    }, 30000);
</script>
