<?php
    if (!defined('IS_ADMIN_PAGE')) {
        require_once '../../../../registration/includes/db.php';
        session_start();
    }

    $adminId = $_SESSION['auth']['user_id'] ?? 0;
    $adminRole = $_SESSION['auth']['role'] ?? 'admin';
    $isSuperAdmin = ($adminRole === 'superadmin');

    /* ================= PROCESSAR AÇÕES VIA AJAX ================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
        header('Content-Type: application/json');
        
        $userId = (int)$_POST['user_id'];
        $action = $_POST['ajax_action'];
        
        if ($action === 'aprovar') {
            $mysqli->query("UPDATE businesses SET status_documentos = 'aprovado', motivo_rejeicao = NULL WHERE user_id = $userId");
            $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ($adminId, 'APROVADO_EMPRESA_$userId', '{$_SERVER['REMOTE_ADDR']}')");
            echo json_encode(['success' => true, 'message' => 'Empresa aprovada com sucesso!']);
        } elseif ($action === 'rejeitar') {
            $motivo = $mysqli->real_escape_string($_POST['motivo']);
            $mysqli->query("UPDATE businesses SET status_documentos = 'rejeitado', motivo_rejeicao = '$motivo' WHERE user_id = $userId");
            $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ($adminId, 'REJEITADO_EMPRESA_$userId', '{$_SERVER['REMOTE_ADDR']}')");
            echo json_encode(['success' => true, 'message' => 'Empresa rejeitada.']);
        }
        exit;
    }

    /* ================= BUSCAR DADOS DA EMPRESA ================= */
    $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($userId === 0) {
        // Se não tiver ID, mostra dashboard de análises
        
        /* ================= ESTATÍSTICAS DE ANÁLISES ================= */
        
        // Empresas para análise
        $sql_empresas_analise = "
            SELECT 
                b.user_id,
                u.nome as empresa_nome,
                u.email,
                b.status_documentos,
                b.business_type,
                u.created_at,
                DATEDIFF(NOW(), u.created_at) as dias_registro
            FROM businesses b
            INNER JOIN users u ON b.user_id = u.id
            WHERE b.status_documentos = 'pendente'
            ORDER BY u.created_at ASC
            LIMIT 10
        ";
        $empresas_analise = $mysqli->query($sql_empresas_analise);
        
        // Empresas já analisadas (últimas 10)
        $sql_empresas_analisadas = "
            SELECT 
                b.user_id,
                u.nome as empresa_nome,
                b.status_documentos,
                b.updated_at,
                COALESCE(ua.nome, 'Sistema') as analisado_por
            FROM businesses b
            INNER JOIN users u ON b.user_id = u.id
            LEFT JOIN admin_audit_logs al ON al.action LIKE CONCAT('%EMPRESA_', b.user_id)
            LEFT JOIN users ua ON al.admin_id = ua.id
            WHERE b.status_documentos IN ('aprovado', 'rejeitado')
            ORDER BY b.updated_at DESC
            LIMIT 10
        ";
        $empresas_analisadas = $mysqli->query($sql_empresas_analisadas);
        
        // Usuários para análise (comportamento suspeito)
        $sql_usuarios_analise = "
            SELECT 
                u.id,
                u.nome,
                u.email,
                u.type,
                u.status,
                u.last_activity,
                u.created_at,
                u.is_in_lockdown,
                CASE 
                    WHEN u.is_in_lockdown = 1 THEN 'Bloqueado'
                    WHEN u.last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY)) THEN 'Inativo 30+ dias'
                    WHEN u.email_verified_at IS NULL THEN 'Email não verificado'
                    ELSE 'Normal'
                END as alerta
            FROM users u
            WHERE u.type IN ('person', 'company')
            AND (
                u.is_in_lockdown = 1 
                OR u.last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
                OR u.email_verified_at IS NULL
            )
            ORDER BY u.is_in_lockdown DESC, u.created_at DESC
            LIMIT 10
        ";
        $usuarios_analise = $mysqli->query($sql_usuarios_analise);
        
        // Admins para análise (só SuperAdmin)
        $admins_analise = null;
        if ($isSuperAdmin) {
            $sql_admins_analise = "
                SELECT 
                    u.id,
                    u.nome,
                    u.email,
                    u.role,
                    u.status,
                    u.last_activity,
                    u.password_changed_at,
                    u.created_at,
                    DATEDIFF(NOW(), u.password_changed_at) as dias_sem_trocar_senha,
                    (SELECT COUNT(*) FROM admin_audit_logs WHERE admin_id = u.id) as total_acoes,
                    (SELECT COUNT(*) FROM admin_audit_logs WHERE admin_id = u.id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as acoes_7_dias
                FROM users u
                WHERE u.role IN ('admin', 'superadmin')
                AND u.id != ?
                ORDER BY u.last_activity DESC
                LIMIT 10
            ";
            $stmt_admins = $mysqli->prepare($sql_admins_analise);
            $stmt_admins->bind_param("i", $adminId);
            $stmt_admins->execute();
            $admins_analise = $stmt_admins->get_result();
        }
        
        // Contadores
        $count_empresas_pendentes = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'pendente'")->fetch_assoc()['total'];
        $count_empresas_analisadas_hoje = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos IN ('aprovado', 'rejeitado') AND DATE(updated_at) = CURDATE()")->fetch_assoc()['total'];
        $count_usuarios_suspeitos = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE type IN ('person', 'company') AND (is_in_lockdown = 1 OR last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY)) OR email_verified_at IS NULL)")->fetch_assoc()['total'];
        $count_admins_total = $isSuperAdmin ? $mysqli->query("SELECT COUNT(*) as total FROM users WHERE role IN ('admin', 'superadmin') AND id != $adminId")->fetch_assoc()['total'] : 0;
        
?>
    
<style>
    .dashboard-analise {
        padding: 30px;
        background: #0005078a;
        border-radius: 10px;
        min-height: 100vh;
    }
    
    .dash-header {
        margin-bottom: 35px;
    }
    
    .dash-header h1 {
        color: var(--text-title);
        font-size: 2.2rem;
        font-weight: 800;
        margin: 0 0 10px 0;
        text-shadow: 0 0 30px var(--accent-glow);
    }
    
    .dash-subtitle {
        color: var(--text-main);
        font-size: 1rem;
    }
    
    .stats-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 35px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 25px;
        transition: 0.3s;
    }
    
    .stat-card:hover {
        border-color: var(--accent-glow);
        box-shadow: 0 5px 20px var(--accent-glow);
    }
    
    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
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
    }
    
    .stat-value {
        color: var(--text-title);
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: var(--text-main);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .sections-grid {
        display: grid;
        gap: 25px;
    }
    
    .analysis-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
    }
    
    .section-header {
        padding: 25px;
        background: rgba(0, 255, 136, 0.03);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
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
    
    .list-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .list-table thead {
        background: rgba(0, 255, 136, 0.02);
    }
    
    .list-table th {
        padding: 15px 20px;
        text-align: left;
        color: var(--text-main);
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .list-table tbody tr {
        border-bottom: 1px solid var(--border-color);
        transition: 0.2s;
        cursor: pointer;
    }
    
    .list-table tbody tr:hover {
        background: rgba(0, 255, 136, 0.05);
    }
    
    .list-table td {
        padding: 18px 20px;
        color: var(--text-main);
        font-size: 0.9rem;
    }
    
    .list-table td strong {
        color: var(--text-title);
        display: block;
        margin-bottom: 4px;
    }
    
    .list-table td small {
        font-size: 0.75rem;
        opacity: 0.7;
    }
    
    .badge {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
        border: 1px solid;
    }
    
    .badge.pendente {
        background: rgba(255, 149, 0, 0.1);
        color: #ff9500;
        border-color: rgba(255, 149, 0, 0.2);
    }
    
    .badge.aprovado {
        background: rgba(0, 255, 136, 0.1);
        color: var(--accent-green);
        border-color: var(--border-color);
    }
    
    .badge.rejeitado {
        background: rgba(255, 77, 77, 0.1);
        color: #ff4d4d;
        border-color: rgba(255, 77, 77, 0.2);
    }
    
    .badge.warning {
        background: rgba(255, 149, 0, 0.1);
        color: #ff9500;
        border-color: rgba(255, 149, 0, 0.2);
    }
    
    .badge.danger {
        background: rgba(255, 77, 77, 0.1);
        color: #ff4d4d;
        border-color: rgba(255, 77, 77, 0.2);
    }
    
    .btn-analyze {
        background: var(--accent-green);
        color: #000;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
        font-size: 0.85rem;
    }
    
    .btn-analyze:hover {
        box-shadow: 0 0 20px var(--accent-glow);
        transform: translateY(-2px);
    }
    
    .empty-message {
        padding: 60px 20px;
        text-align: center;
        color: var(--text-main);
    }
    
    .empty-message i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.3;
    }
</style>
    
<div class="dashboard-analise">
    <!-- HEADER -->
    <div class="dash-header">
        <h1>Centro de Análises</h1>
        <p class="dash-subtitle">Análise de empresas, usuários e administradores</p>
    </div>
    
    <!-- ESTATÍSTICAS -->
    <div class="stats-overview">
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-icon">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
            </div>
            <div class="stat-value"><?= $count_empresas_pendentes ?></div>
            <div class="stat-label">Empresas Pendentes</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-icon" style="background: rgba(0, 255, 136, 0.1); color: var(--accent-green);">
                    <i class="fa-solid fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-value" style="color: var(--accent-green);"><?= $count_empresas_analisadas_hoje ?></div>
            <div class="stat-label">Analisadas Hoje</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-icon" style="background: rgba(255, 149, 0, 0.1); color: #ff9500;">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
            </div>
            <div class="stat-value" style="color: #ff9500;"><?= $count_usuarios_suspeitos ?></div>
            <div class="stat-label">Usuários Suspeitos</div>
        </div>
        
        <?php if ($isSuperAdmin): ?>
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-icon" style="background: rgba(77, 163, 255, 0.1); color: #4da3ff;">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
            </div>
            <div class="stat-value" style="color: #4da3ff;"><?= $count_admins_total ?></div>
            <div class="stat-label">Admins Ativos</div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- EMPRESAS PARA ANALISAR -->
    <div class="analysis-section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fa-solid fa-building"></i>
                Empresas Para Analisar
            </h2>
        </div>
        
        <?php if ($empresas_analise && $empresas_analise->num_rows > 0): ?>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Tempo Pendente</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($emp = $empresas_analise->fetch_assoc()): ?>
                        <tr onclick="loadContent('modules/dashboard/analise?id=<?= $emp['user_id'] ?>')">
                            <td>
                                <strong><?= htmlspecialchars($emp['empresa_nome']) ?></strong>
                                <small><?= htmlspecialchars($emp['email']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($emp['business_type'] ?? 'N/A') ?></td>
                            <td><span class="badge pendente">Pendente</span></td>
                            <td>
                                <span style="<?= $emp['dias_registro'] > 5 ? 'color: #ff4d4d; font-weight: 700;' : '' ?>">
                                    <?= $emp['dias_registro'] ?> dias
                                </span>
                            </td>
                            <td>
                                <button class="btn-analyze" onclick="event.stopPropagation(); loadContent('modules/dashboard/analise?id=<?= $emp['user_id'] ?>')">
                                    <i class="fa-solid fa-magnifying-glass"></i> Analisar
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">
                <i class="fa-solid fa-check-double"></i>
                <p>Nenhuma empresa pendente de análise</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- EMPRESAS JÁ ANALISADAS -->
    <div class="analysis-section" style="margin-top: 25px;">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fa-solid fa-clipboard-check"></i>
                Empresas Analisadas Recentemente
            </h2>
        </div>
        
        <?php if ($empresas_analisadas && $empresas_analisadas->num_rows > 0): ?>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Decisão</th>
                        <th>Data</th>
                        <th>Analisado Por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($emp_analisada = $empresas_analisadas->fetch_assoc()): ?>
                        <tr onclick="loadContent('modules/dashboard/analise?id=<?= $emp_analisada['user_id'] ?>')">
                            <td><strong><?= htmlspecialchars($emp_analisada['empresa_nome']) ?></strong></td>
                            <td><span class="badge <?= $emp_analisada['status_documentos'] ?>"><?= ucfirst($emp_analisada['status_documentos']) ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($emp_analisada['updated_at'])) ?></td>
                            <td><?= htmlspecialchars($emp_analisada['analisado_por']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">
                <i class="fa-solid fa-inbox"></i>
                <p>Nenhuma empresa analisada ainda</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- USUÁRIOS SUSPEITOS -->
    <div class="analysis-section" style="margin-top: 25px;">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fa-solid fa-user-xmark"></i>
                Usuários Requerendo Análise
            </h2>
        </div>
        
        <?php if ($usuarios_analise && $usuarios_analise->num_rows > 0): ?>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Alerta</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = $usuarios_analise->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($user['nome']) ?></strong>
                                <small><?= htmlspecialchars($user['email']) ?></small>
                            </td>
                            <td><?= ucfirst($user['type']) ?></td>
                            <td>
                                <span class="badge <?= $user['is_in_lockdown'] ? 'danger' : 'warning' ?>">
                                    <?= htmlspecialchars($user['alerta']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-analyze" onclick="analisarUsuario(<?= $user['id'] ?>)">
                                    <i class="fa-solid fa-user-check"></i> Verificar
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">
                <i class="fa-solid fa-shield-check"></i>
                <p>Nenhum usuário suspeito detectado</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ADMINS (SÓ SUPERADMIN) -->
    <?php if ($isSuperAdmin && $admins_analise): ?>
    <div class="analysis-section" style="margin-top: 25px;">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fa-solid fa-user-tie"></i>
                Administradores - Auditoria
            </h2>
        </div>
        
        <?php if ($admins_analise->num_rows > 0): ?>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Admin</th>
                        <th>Role</th>
                        <th>Atividade</th>
                        <th>Senha</th>
                        <th>Ações (7d)</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($admin = $admins_analise->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($admin['nome']) ?></strong>
                                <small><?= htmlspecialchars($admin['email']) ?></small>
                            </td>
                            <td><?= ucfirst($admin['role']) ?></td>
                            <td>
                                <?php 
                                $dias_inativo = floor((time() - $admin['last_activity']) / 86400);
                                $cor = $dias_inativo > 7 ? '#ff4d4d' : ($dias_inativo > 3 ? '#ff9500' : 'var(--accent-green)');
                                ?>
                                <span style="color: <?= $cor ?>; font-weight: 700;">
                                    <?= $dias_inativo ?> dias
                                </span>
                            </td>
                            <td>
                                <?php 
                                $senha_dias = $admin['dias_sem_trocar_senha'];
                                $senha_cor = $senha_dias > 90 ? '#ff4d4d' : ($senha_dias > 60 ? '#ff9500' : 'var(--accent-green)');
                                ?>
                                <span style="color: <?= $senha_cor ?>; font-weight: 700;">
                                    <?= $senha_dias ?> dias
                                </span>
                            </td>
                            <td><?= $admin['acoes_7_dias'] ?> / <?= $admin['total_acoes'] ?></td>
                            <td>
                                <button class="btn-analyze" onclick="analisarAdmin(<?= $admin['id'] ?>)">
                                    <i class="fa-solid fa-shield-halved"></i> Auditar
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">
                <i class="fa-solid fa-users-gear"></i>
                <p>Nenhum administrador para auditar</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
    
<script>
    function analisarUsuario(userId) {
        // Carrega página de análise de usuário
        loadContent('modules/dashboard/analise_inc/usuario-analise?id=' + userId);
    }

    function analisarAdmin(adminId) {
        // Carrega página de auditoria de admin
        loadContent('modules/auditor/admin-auditoria?id=' + adminId);
    }
</script>
    
<?php
        exit;
    }

    $sql = "
        SELECT 
            b.*,
            u.nome as empresa_nome,
            u.email,
            u.telefone,
            u.created_at as registro_em,
            DATEDIFF(NOW(), u.created_at) as dias_registro
        FROM businesses b
        INNER JOIN users u ON b.user_id = u.id
        WHERE b.user_id = ?
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $empresa = $stmt->get_result()->fetch_assoc();

    if (!$empresa) {
        ?>
        <div style="padding: 60px; text-align: center; background: var(--bg-card); border-radius: 16px; margin: 30px;">
            <i class="fa-solid fa-building-slash" style="font-size: 4rem; color: #ff4d4d; margin-bottom: 20px;"></i>
            <h2 style="color: var(--text-title); margin-bottom: 10px;">Empresa não encontrada</h2>
            <p style="color: var(--text-main); margin-bottom: 20px;">O ID fornecido não corresponde a nenhuma empresa</p>
            <button onclick="loadContent('modules/dashboard/pendencias')" style="background: var(--accent-green); color: #000; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; cursor: pointer;">
                <i class="fa-solid fa-arrow-left"></i> Voltar para Pendências
            </button>
        </div>
        <?php
        exit;
    }

    $uploadPath = "../../../../registration/uploads/business/";
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

    .analise-wrapper {
        padding: 30px;
        background: var(--bg-body);
        min-height: 100vh;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--border-color);
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .back-button {
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        padding: 12px 20px;
        border-radius: 10px;
        cursor: pointer;
        transition: 0.3s;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .back-button:hover {
        border-color: var(--accent-green);
        color: var(--accent-green);
        box-shadow: 0 0 20px var(--accent-glow);
    }

    .page-title {
        color: var(--text-title);
        font-size: 2rem;
        font-weight: 800;
        margin: 0;
        text-shadow: 0 0 30px var(--accent-glow);
    }

    .status-tag {
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        border: 2px solid;
    }

    .status-tag.pendente {
        background: rgba(255, 149, 0, 0.1);
        color: #ff9500;
        border-color: #ff9500;
    }

    .status-tag.aprovado {
        background: rgba(0, 255, 136, 0.1);
        color: var(--accent-green);
        border-color: var(--accent-green);
    }

    .status-tag.rejeitado {
        background: rgba(255, 77, 77, 0.1);
        color: #ff4d4d;
        border-color: #ff4d4d;
    }

    .main-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 25px;
    }

    .card-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 20px;
    }

    .card-title {
        color: var(--text-title);
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }

    .card-title i {
        color: var(--accent-green);
    }

    .info-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    .info-box {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .info-label {
        color: var(--text-main);
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-content {
        color: var(--text-title);
        font-size: 1rem;
        font-weight: 600;
    }

    .info-content code {
        background: rgba(0, 255, 136, 0.1);
        padding: 6px 12px;
        border-radius: 8px;
        color: var(--accent-green);
        font-family: 'Courier New', monospace;
        border: 1px solid var(--border-color);
    }

    .doc-viewer {
        background: rgba(0, 255, 136, 0.02);
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        margin-bottom: 20px;
    }

    .doc-viewer h3 {
        color: var(--text-title);
        margin-bottom: 20px;
        font-size: 1.1rem;
    }

    .doc-image {
        max-width: 100%;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        margin-bottom: 15px;
    }

    .doc-empty {
        padding: 40px;
        color: var(--text-main);
    }

    .doc-empty i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.3;
    }

    .download-button {
        background: var(--accent-green);
        color: #000;
        border: none;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .download-button:hover {
        box-shadow: 0 0 20px var(--accent-glow);
        transform: translateY(-2px);
    }

    .actions-sidebar {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 25px;
        position: sticky;
        top: 20px;
        height: fit-content;
    }

    .action-button {
        width: 100%;
        padding: 16px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        border: none;
        margin-bottom: 12px;
    }

    .btn-approve {
        background: var(--accent-green);
        color: #000;
    }

    .btn-approve:hover {
        box-shadow: 0 0 30px var(--accent-glow);
        transform: translateY(-2px);
    }

    .btn-reject {
        background: rgba(255, 77, 77, 0.1);
        color: #ff4d4d;
        border: 2px solid rgba(255, 77, 77, 0.3);
    }

    .btn-reject:hover {
        background: rgba(255, 77, 77, 0.2);
        border-color: #ff4d4d;
    }

    .reject-textarea {
        width: 100%;
        background: rgba(0, 255, 136, 0.05);
        border: 1px solid var(--border-color);
        color: var(--text-title);
        padding: 12px;
        border-radius: 10px;
        font-size: 0.9rem;
        resize: vertical;
        min-height: 100px;
        margin-top: 10px;
        font-family: inherit;
        display: none;
    }

    .reject-textarea:focus {
        outline: none;
        border-color: var(--accent-green);
        box-shadow: 0 0 20px var(--accent-glow);
    }

    .timeline-box {
        margin-top: 25px;
        padding-top: 25px;
        border-top: 1px solid var(--border-color);
    }

    .timeline-title {
        color: var(--text-title);
        font-size: 0.9rem;
        font-weight: 700;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .timeline-item {
        display: flex;
        gap: 12px;
        padding: 12px;
        background: rgba(0, 255, 136, 0.02);
        border-left: 3px solid var(--accent-green);
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .timeline-item i {
        color: var(--accent-green);
        margin-top: 3px;
    }

    .timeline-content strong {
        color: var(--text-title);
        display: block;
        font-size: 0.9rem;
        margin-bottom: 4px;
    }

    .timeline-content small {
        color: var(--text-main);
        font-size: 0.75rem;
    }

    .alert-danger {
        background: rgba(255, 77, 77, 0.1);
        border: 1px solid rgba(255, 77, 77, 0.3);
        border-radius: 12px;
        padding: 15px;
        margin-top: 20px;
    }

    .alert-danger strong {
        color: #ff4d4d;
        display: block;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .alert-danger p {
        color: var(--text-main);
        font-size: 0.85rem;
        margin: 0;
        line-height: 1.5;
    }

    @media (max-width: 1200px) {
        .main-grid {
            grid-template-columns: 1fr;
        }
        
        .actions-sidebar {
            position: static;
        }
    }
</style>

<div class="analise-wrapper">
    <!-- HEADER -->
    <div class="page-header">
        <div class="header-left">
            <button onclick="loadContent('modules/dashboard/pendencias')" class="back-button">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </button>
            <h1 class="page-title">Análise de Documentos</h1>
        </div>
        <span class="status-tag <?= $empresa['status_documentos'] ?>">
            <?= ucfirst($empresa['status_documentos']) ?>
        </span>
    </div>

    <!-- GRID PRINCIPAL -->
    <div class="main-grid">
        <!-- COLUNA ESQUERDA -->
        <div>
            <!-- INFORMAÇÕES DA EMPRESA -->
            <div class="card-section">
                <h2 class="card-title">
                    <i class="fa-solid fa-building"></i>
                    Informações da Empresa
                </h2>

                <div class="info-row">
                    <div class="info-box">
                        <div class="info-label">Nome da Empresa</div>
                        <div class="info-content"><?= htmlspecialchars($empresa['empresa_nome']) ?></div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">Email</div>
                        <div class="info-content"><?= htmlspecialchars($empresa['email']) ?></div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">Telefone</div>
                        <div class="info-content"><?= htmlspecialchars($empresa['telefone']) ?></div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">Tax ID</div>
                        <div class="info-content"><code><?= htmlspecialchars($empresa['tax_id'] ?? 'N/A') ?></code></div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">Tipo de Negócio</div>
                        <div class="info-content"><?= htmlspecialchars($empresa['business_type'] ?? 'N/A') ?></div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">País</div>
                        <div class="info-content"><?= htmlspecialchars($empresa['country'] ?? 'N/A') ?></div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">Região</div>
                        <div class="info-content"><?= htmlspecialchars($empresa['region'] ?? 'N/A') ?></div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">Cidade</div>
                        <div class="info-content"><?= htmlspecialchars($empresa['city'] ?? 'N/A') ?></div>
                    </div>
                </div>

                <?php if (!empty($empresa['description'])): ?>
                    <div class="info-box" style="margin-top: 15px;">
                        <div class="info-label">Descrição</div>
                        <div class="info-content" style="color: var(--text-main); font-weight: normal; line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($empresa['description'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- DOCUMENTOS -->
            <div class="card-section">
                <h2 class="card-title">
                    <i class="fa-solid fa-file-lines"></i>
                    Documentos Anexados
                </h2>

                <!-- ALVARÁ / LICENÇA -->
                <div class="doc-viewer">
                    <h3><i class="fa-solid fa-file-contract"></i> Alvará Comercial / Licença</h3>
                    
                    <?php if (!empty($empresa['license_path'])): ?>
                        <?php 
                        $ext = strtolower(pathinfo($empresa['license_path'], PATHINFO_EXTENSION));
                        $filePath = $uploadPath . $empresa['license_path'];
                        ?>
                        
                        <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                            <img src="<?= $filePath ?>" class="doc-image" alt="Alvará">
                        <?php else: ?>
                            <div class="doc-empty">
                                <i class="fa-solid fa-file-pdf"></i>
                                <p>Documento PDF anexado</p>
                            </div>
                        <?php endif; ?>
                        
                        <a href="<?= $filePath ?>" target="_blank" class="download-button">
                            <i class="fa-solid fa-download"></i>
                            Baixar Documento
                        </a>
                    <?php else: ?>
                        <div class="doc-empty">
                            <i class="fa-solid fa-file-slash"></i>
                            <p>Nenhum documento anexado</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAX ID FILE -->
                <?php if (!empty($empresa['tax_id_file'])): ?>
                <div class="doc-viewer">
                    <h3><i class="fa-solid fa-file-invoice"></i> Comprovante Tax ID</h3>
                    
                    <?php 
                    $extTax = strtolower(pathinfo($empresa['tax_id_file'], PATHINFO_EXTENSION));
                    $taxFilePath = $uploadPath . $empresa['tax_id_file'];
                    ?>
                    
                    <?php if (in_array($extTax, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                        <img src="<?= $taxFilePath ?>" class="doc-image" alt="Tax ID">
                    <?php else: ?>
                        <div class="doc-empty">
                            <i class="fa-solid fa-file-pdf"></i>
                            <p>Documento PDF anexado</p>
                        </div>
                    <?php endif; ?>
                    
                    <a href="<?= $taxFilePath ?>" target="_blank" class="download-button">
                        <i class="fa-solid fa-download"></i>
                        Baixar Documento
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- COLUNA DIREITA - AÇÕES -->
        <div>
            <div class="actions-sidebar">
                <h2 class="card-title" style="border: none; padding-bottom: 0; margin-bottom: 20px;">
                    <i class="fa-solid fa-gavel"></i>
                    Decisão de Análise
                </h2>

                <?php if ($empresa['status_documentos'] === 'pendente'): ?>
                    <button onclick="aprovarEmpresa()" class="action-button btn-approve">
                        <i class="fa-solid fa-check-circle"></i>
                        APROVAR EMPRESA
                    </button>

                    <div style="margin: 15px 0; text-align: center; color: var(--text-main); font-size: 0.85rem;">ou</div>

                    <button onclick="mostrarRejeicao()" class="action-button btn-reject">
                        <i class="fa-solid fa-times-circle"></i>
                        REJEITAR EMPRESA
                    </button>
                    
                    <textarea 
                        id="motivoRejeicao" 
                        class="reject-textarea" 
                        placeholder="Descreva o motivo da rejeição (mínimo 10 caracteres)..."></textarea>
                    
                    <button onclick="rejeitarEmpresa()" id="btnConfirmarRejeicao" class="action-button btn-reject" style="display: none; margin-top: 10px; background: #ff4d4d; color: #fff;">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        CONFIRMAR REJEIÇÃO
                    </button>
                <?php else: ?>
                    <div style="background: rgba(0, 255, 136, 0.05); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; text-align: center;">
                        <p style="color: var(--text-main); margin: 0;">
                            Esta empresa já foi <strong style="color: var(--text-title);"><?= $empresa['status_documentos'] === 'aprovado' ? 'aprovada' : 'rejeitada' ?></strong>.
                        </p>
                    </div>
                <?php endif; ?>

                <!-- TIMELINE -->
                <div class="timeline-box">
                    <h4 class="timeline-title">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        Linha do Tempo
                    </h4>
                    
                    <div class="timeline-item">
                        <i class="fa-solid fa-user-plus"></i>
                        <div class="timeline-content">
                            <strong>Registro Inicial</strong>
                            <small><?= date('d/m/Y H:i', strtotime($empresa['registro_em'])) ?> (há <?= $empresa['dias_registro'] ?> dias)</small>
                        </div>
                    </div>

                    <?php if ($empresa['updated_at']): ?>
                    <div class="timeline-item">
                        <i class="fa-solid fa-pen"></i>
                        <div class="timeline-content">
                            <strong>Última Atualização</strong>
                            <small><?= date('d/m/Y H:i', strtotime($empresa['updated_at'])) ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($empresa['status_documentos'] === 'rejeitado' && !empty($empresa['motivo_rejeicao'])): ?>
                    <div class="alert-danger">
                        <strong><i class="fa-solid fa-exclamation-triangle"></i> Motivo da Rejeição:</strong>
                        <p><?= nl2br(htmlspecialchars($empresa['motivo_rejeicao'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    const userId = <?= $userId ?>;

    function mostrarRejeicao() {
        document.getElementById('motivoRejeicao').style.display = 'block';
        document.getElementById('btnConfirmarRejeicao').style.display = 'flex';
        document.getElementById('motivoRejeicao').focus();
    }

    function aprovarEmpresa() {
        if (!confirm('Tem certeza que deseja APROVAR esta empresa?')) return;
        
        const formData = new FormData();
        formData.append('ajax_action', 'aprovar');
        formData.append('user_id', userId);
        
        fetch(window.location.href.split('?')[0] + '?id=' + userId, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadContent('modules/dashboard/pendencias');
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Erro ao processar requisição');
        });
    }

    function rejeitarEmpresa() {
        const motivo = document.getElementById('motivoRejeicao').value.trim();
        
        if (motivo.length < 10) {
            alert('Por favor, descreva o motivo da rejeição com pelo menos 10 caracteres.');
            return;
        }
        
        if (!confirm('Tem certeza que deseja REJEITAR esta empresa?\n\nMotivo: ' + motivo)) return;
        
        const formData = new FormData();
        formData.append('ajax_action', 'rejeitar');
        formData.append('user_id', userId);
        formData.append('motivo', motivo);
        
        fetch(window.location.href.split('?')[0] + '?id=' + userId, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadContent('modules/dashboard/pendencias');
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Erro ao processar requisição');
        });
    }
</script>