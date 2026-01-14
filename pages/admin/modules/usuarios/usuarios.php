<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - GESTÃO DE USUÁRIOS
 * Módulo: modules/usuarios/usuarios.php
 * Descrição: Lista, filtro, busca e ações em usuários (person + company)
 * ================================================================================
 */

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
    
    $mysqli->begin_transaction();
    try {
        if ($action === 'bloquear') {
            $stmt = $mysqli->prepare("UPDATE users SET is_in_lockdown = 1, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // Log de auditoria
            $stmt = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
            $auditAction = "USER_BLOCKED_$userId";
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param("iss", $adminId, $auditAction, $ip);
            $stmt->execute();
            
            $mysqli->commit();
            echo json_encode(['success' => true, 'message' => '🔒 Usuário bloqueado com sucesso!']);
            
        } elseif ($action === 'desbloquear') {
            $stmt = $mysqli->prepare("UPDATE users SET is_in_lockdown = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // Log de auditoria
            $stmt = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
            $auditAction = "USER_UNBLOCKED_$userId";
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param("iss", $adminId, $auditAction, $ip);
            $stmt->execute();
            
            $mysqli->commit();
            echo json_encode(['success' => true, 'message' => '🔓 Usuário desbloqueado com sucesso!']);
            
        } elseif ($action === 'ativar') {
            $stmt = $mysqli->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            $mysqli->commit();
            echo json_encode(['success' => true, 'message' => '✅ Usuário ativado!']);
            
        } elseif ($action === 'desativar') {
            $stmt = $mysqli->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            $mysqli->commit();
            echo json_encode(['success' => true, 'message' => '⏸️ Usuário desativado!']);
            
        } elseif ($action === 'deletar' && $isSuperAdmin) {
            // Soft delete (restaurado)
            $stmt = $mysqli->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // Log de auditoria
            $stmt = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
            $auditAction = "USER_DELETED_$userId";
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param("iss", $adminId, $auditAction, $ip);
            $stmt->execute();
            
            $mysqli->commit();
            echo json_encode(['success' => true, 'message' => '🗑️ Usuário deletado (soft delete)!']);
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* ================= FILTROS ================= */
$filtro_tipo = $_GET['tipo'] ?? 'all'; // all, person, company
$filtro_status = $_GET['status'] ?? 'all'; // all, active, inactive
$filtro_lockdown = $_GET['lockdown'] ?? 'all'; // all, yes, no
$filtro_sessao = $_GET['sessao'] ?? 'all'; // all, online, offline
$filtro_busca = $_GET['busca'] ?? '';
$pagina = (int)($_GET['pagina'] ?? 1);
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

/* ================= CONSTRUIR QUERY ================= */
$where_conditions = ["1=1", "deleted_at IS NULL"];

// Filtro de ROLE-BASED ACCESS (SuperAdmin vs Admin)
if ($isSuperAdmin) {
    // SuperAdmin vê TODOS os tipos (person, company, admin, superadmin)
    if ($filtro_tipo !== 'all') {
        $tipo_safe = $mysqli->real_escape_string($filtro_tipo);
        if (in_array($tipo_safe, ['person', 'company'])) {
            $where_conditions[] = "type = '$tipo_safe'";
        } else {
            // Para admin/superadmin, verificar por role
            $where_conditions[] = "role = '$tipo_safe'";
        }
    }
    // Se filtro_tipo = 'all', não adiciona condição (mostra todos)
} else {
    // Admin NÃO vê superadmin
    if ($filtro_tipo !== 'all') {
        $tipo_safe = $mysqli->real_escape_string($filtro_tipo);
        if (in_array($tipo_safe, ['person', 'company'])) {
            $where_conditions[] = "type = '$tipo_safe'";
        } elseif ($tipo_safe === 'admin') {
            $where_conditions[] = "role = 'admin'";
        }
        // Ignora filtro 'superadmin' para admins normais
    } else {
        // Admin vê: person, company, admin (mas não superadmin)
        $where_conditions[] = "(type IN ('person', 'company') OR (role = 'admin'))";
        $where_conditions[] = "role != 'superadmin'";
    }
}

// Filtro de status
if ($filtro_status !== 'all') {
    $status_safe = $mysqli->real_escape_string($filtro_status);
    $where_conditions[] = "status = '$status_safe'";
}

// Filtro de lockdown
if ($filtro_lockdown === 'yes') {
    $where_conditions[] = "is_in_lockdown = 1";
} elseif ($filtro_lockdown === 'no') {
    $where_conditions[] = "is_in_lockdown = 0";
}

// Filtro de sessão (online/offline)
if ($filtro_sessao === 'online') {
    $where_conditions[] = "last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 15 MINUTE))";
} elseif ($filtro_sessao === 'offline') {
    $where_conditions[] = "(last_activity IS NULL OR last_activity <= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 15 MINUTE)))";
}

// Busca
if (!empty($filtro_busca)) {
    $busca_safe = $mysqli->real_escape_string($filtro_busca);
    $where_conditions[] = "(nome LIKE '%$busca_safe%' OR email LIKE '%$busca_safe%')";
}

$where_clause = implode(" AND ", $where_conditions);

/* ================= BUSCAR USUÁRIOS ================= */
$sql = "
    SELECT 
        u.id,
        u.nome,
        u.email,
        u.telefone,
        u.type,
        u.role,
        u.status,
        u.is_in_lockdown,
        u.email_verified_at,
        u.last_activity,
        u.created_at,
        b.status_documentos,
        DATEDIFF(NOW(), u.created_at) as dias_cadastrado,
        CASE 
            WHEN u.last_activity IS NULL THEN 999
            WHEN u.last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY)) THEN DATEDIFF(NOW(), FROM_UNIXTIME(u.last_activity))
            ELSE 0
        END as dias_inativo
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    WHERE $where_clause
    ORDER BY u.created_at DESC
    LIMIT $por_pagina OFFSET $offset
";
$result = $mysqli->query($sql);

/* ================= CONTAR TOTAL ================= */
$sql_count = "SELECT COUNT(*) as total FROM users u WHERE $where_clause";
$total_usuarios = $mysqli->query($sql_count)->fetch_assoc()['total'];
$total_paginas = ceil($total_usuarios / $por_pagina);

/* ================= ESTATÍSTICAS ================= */
if ($isSuperAdmin) {
    // SuperAdmin vê TODOS separadamente
    $stats = $mysqli->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN type = 'person' THEN 1 ELSE 0 END) as pessoas,
            SUM(CASE WHEN type = 'company' THEN 1 ELSE 0 END) as empresas,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN role = 'superadmin' THEN 1 ELSE 0 END) as superadmins,
            SUM(CASE WHEN is_in_lockdown = 1 THEN 1 ELSE 0 END) as bloqueados,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as ativos,
            SUM(CASE WHEN last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 15 MINUTE)) THEN 1 ELSE 0 END) as online
        FROM users 
        WHERE deleted_at IS NULL
    ")->fetch_assoc();
} else {
    // Admin vê total SEM SABER de SuperAdmins
    // Para Admin: "Admins" inclui apenas os que ele vê (admins normais)
    // Ele não sabe que existem SuperAdmins
    $stats = $mysqli->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN type = 'person' THEN 1 ELSE 0 END) as pessoas,
            SUM(CASE WHEN type = 'company' THEN 1 ELSE 0 END) as empresas,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            0 as superadmins,
            SUM(CASE WHEN is_in_lockdown = 1 THEN 1 ELSE 0 END) as bloqueados,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as ativos,
            SUM(CASE WHEN last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 15 MINUTE)) THEN 1 ELSE 0 END) as online
        FROM users 
        WHERE deleted_at IS NULL
        AND (type IN ('person', 'company') OR role = 'admin')
        AND role != 'superadmin'
    ")->fetch_assoc();
}
?>

<style>
@keyframes pulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.7;
        transform: scale(1.05);
    }
}
</style>

<!-- HEADER -->
<div style="margin-bottom: 32px;">
    <h1 style="color: var(--text-title); font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">
        <i class="fa-solid fa-users" style="color: var(--accent);"></i>
        Gestão de Usuários
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.938rem;">
        Gerenciar usuários pessoas e empresas do sistema
    </p>
</div>

<!-- KPI CARDS -->
<div class="stats-grid mb-3" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-label">Total de Usuários</div>
        <div class="stat-value"><?= number_format($stats['total'], 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-user-group"></i>
            Todos os tipos
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-user"></i>
        </div>
        <div class="stat-label">Pessoas</div>
        <div class="stat-value"><?= number_format($stats['pessoas'], 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-id-card"></i>
            Type: person
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="stat-label">Empresas</div>
        <div class="stat-value"><?= number_format($stats['empresas'], 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-briefcase"></i>
            Type: company
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-user-tie"></i>
        </div>
        <div class="stat-label">Admins</div>
        <div class="stat-value"><?= number_format($stats['admins'], 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-shield"></i>
            Role: admin
        </div>
    </div>
    
    <?php if ($isSuperAdmin): ?>
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div class="stat-label">SuperAdmins</div>
        <div class="stat-value"><?= number_format($stats['superadmins'], 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-crown"></i>
            Role: superadmin
        </div>
    </div>
    <?php endif; ?>
    
    <div class="stat-card">
        <div class="stat-icon" style="animation: pulse 2s infinite;">
            <i class="fa-solid fa-wifi"></i>
        </div>
        <div class="stat-label">Online Agora</div>
        <div class="stat-value" style="color: #3fb950;"><?= number_format($stats['online'], 0, ',', '.') ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-circle" style="font-size: 0.5rem;"></i>
            Logados
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div class="stat-label">Ativos</div>
        <div class="stat-value"><?= number_format($stats['ativos'], 0, ',', '.') ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-check-circle"></i>
            Status ativo
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-lock"></i>
        </div>
        <div class="stat-label">Bloqueados</div>
        <div class="stat-value"><?= $stats['bloqueados'] ?></div>
        <div class="stat-change <?= $stats['bloqueados'] > 0 ? 'negative' : 'positive' ?>">
            <i class="fa-solid fa-shield-halved"></i>
            Lockdown
        </div>
    </div>
</div>

<!-- FILTROS E BUSCA -->
<div class="card mb-3">
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
            
            <!-- Busca -->
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Buscar usuário:</label>
                <div class="input-group">
                    <span class="input-icon">
                        <i class="fa-solid fa-search"></i>
                    </span>
                    <input type="text" class="form-input" id="inputBusca" placeholder="Nome ou email..." value="<?= htmlspecialchars($filtro_busca) ?>">
                </div>
            </div>
            
            <!-- Tipo -->
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Tipo:</label>
                <select class="form-select" id="filterTipo">
                    <option value="all" <?= $filtro_tipo === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="person" <?= $filtro_tipo === 'person' ? 'selected' : '' ?>>👤 Pessoa</option>
                    <option value="company" <?= $filtro_tipo === 'company' ? 'selected' : '' ?>>🏢 Empresa</option>
                    <option value="admin" <?= $filtro_tipo === 'admin' ? 'selected' : '' ?>>🛡️ Admin</option>
                    <?php if ($isSuperAdmin): ?>
                    <option value="superadmin" <?= $filtro_tipo === 'superadmin' ? 'selected' : '' ?>>👑 SuperAdmin</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <!-- Status -->
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Status:</label>
                <select class="form-select" id="filterStatus">
                    <option value="all" <?= $filtro_status === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="active" <?= $filtro_status === 'active' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inactive" <?= $filtro_status === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
            
            <!-- Sessão -->
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Sessão:</label>
                <select class="form-select" id="filterSessao">
                    <option value="all" <?= $filtro_sessao === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="online" <?= $filtro_sessao === 'online' ? 'selected' : '' ?>>🟢 Online</option>
                    <option value="offline" <?= $filtro_sessao === 'offline' ? 'selected' : '' ?>>⚫ Offline</option>
                </select>
            </div>
            
            <!-- Lockdown -->
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Bloqueio:</label>
                <select class="form-select" id="filterLockdown">
                    <option value="all" <?= $filtro_lockdown === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="yes" <?= $filtro_lockdown === 'yes' ? 'selected' : '' ?>>Bloqueados</option>
                    <option value="no" <?= $filtro_lockdown === 'no' ? 'selected' : '' ?>>Desbloqueados</option>
                </select>
            </div>
            
            <!-- Botões -->
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-primary" onclick="aplicarFiltros()">
                    <i class="fa-solid fa-filter"></i>
                    Filtrar
                </button>
                <button class="btn btn-ghost" onclick="limparFiltros()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            
        </div>
    </div>
</div>

<!-- TABELA DE USUÁRIOS -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title">
            <i class="fa-solid fa-list"></i>
            Lista de Usuários
            <span class="badge info"><?= $total_usuarios ?></span>
        </h3>
        <div style="display: flex; gap: 8px;">
            <button class="btn btn-secondary btn-sm" onclick="exportarCSV()">
                <i class="fa-solid fa-download"></i>
                Exportar CSV
            </button>
        </div>
    </div>
    
    <?php if ($result && $result->num_rows > 0): ?>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Sessão</th>
                        <th>Verificado</th>
                        <th>Última Atividade</th>
                        <th>Cadastrado</th>
                        <th style="text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $result->fetch_assoc()): ?>
                    <tr>
                        <!-- Usuário -->
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php
                                    // Determinar ícone e cor baseado em type/role (com verificação)
                                    $userRole = $user['role'] ?? null;
                                    $userType = $user['type'] ?? 'person';
                                    
                                    $userIcon = 'user';
                                    $userColor = '#3fb950';
                                    
                                    if ($userRole === 'superadmin') {
                                        $userIcon = 'crown';
                                        $userColor = '#a371f7';
                                    } elseif ($userRole === 'admin' || $userType === 'admin') {
                                        $userIcon = 'user-shield';
                                        $userColor = '#f85149';
                                    } elseif ($userType === 'company') {
                                        $userIcon = 'building';
                                        $userColor = '#388bfd';
                                    }
                                ?>
                                <div style="width: 40px; height: 40px; border-radius: 8px; background: <?= $userColor ?>20; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: <?= $userColor ?>;">
                                    <i class="fa-solid fa-<?= $userIcon ?>"></i>
                                </div>
                                <div>
                                    <strong style="color: var(--text-primary); display: block; margin-bottom: 2px;">
                                        <?= htmlspecialchars($user['nome']) ?>
                                        <?php if ($user['is_in_lockdown']): ?>
                                            <i class="fa-solid fa-lock" style="color: #f85149; font-size: 0.75rem; margin-left: 6px;" title="Bloqueado"></i>
                                        <?php endif; ?>
                                    </strong>
                                    <small style="color: var(--text-muted); font-size: 0.75rem;">
                                        <?= htmlspecialchars($user['email']) ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        
                        <!-- Tipo -->
                        <td>
                            <?php
                                $userRole = $user['role'] ?? null;
                                $userType = $user['type'] ?? 'person';
                            ?>
                            <?php if ($userRole === 'superadmin'): ?>
                                <span class="badge" style="background: #a371f720; color: #a371f7; border-color: #a371f7;">
                                    <i class="fa-solid fa-crown"></i>
                                    SuperAdmin
                                </span>
                            <?php elseif ($userRole === 'admin' || $userType === 'admin'): ?>
                                <span class="badge error">
                                    <i class="fa-solid fa-user-shield"></i>
                                    Admin
                                </span>
                            <?php elseif ($userType === 'company'): ?>
                                <span class="badge info">
                                    <i class="fa-solid fa-building"></i>
                                    Company
                                </span>
                            <?php else: ?>
                                <span class="badge success">
                                    <i class="fa-solid fa-id-card"></i>
                                    Person
                                </span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Status da Conta -->
                        <td>
                            <?php if ($user['is_in_lockdown']): ?>
                                <span class="badge error">
                                    <i class="fa-solid fa-lock"></i>
                                    Bloqueado
                                </span>
                            <?php elseif ($user['status'] === 'active'): ?>
                                <span class="badge success">
                                    <i class="fa-solid fa-check-circle"></i>
                                    Ativo
                                </span>
                            <?php else: ?>
                                <span class="badge neutral">
                                    <i class="fa-solid fa-pause-circle"></i>
                                    Inativo
                                </span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Status da Sessão (Logado/Deslogado) -->
                        <td>
                            <?php
                                // Considera logado se última atividade foi há menos de 15 minutos
                                $isOnline = false;
                                if ($user['last_activity']) {
                                    $minutosInativo = (time() - $user['last_activity']) / 60;
                                    $isOnline = ($minutosInativo < 15);
                                }
                            ?>
                            <?php if ($isOnline): ?>
                                <span class="badge success" style="animation: pulse 2s infinite;">
                                    <i class="fa-solid fa-circle" style="font-size: 0.5rem;"></i>
                                    Logado
                                </span>
                            <?php else: ?>
                                <span class="badge neutral">
                                    <i class="fa-solid fa-circle" style="font-size: 0.5rem; opacity: 0.3;"></i>
                                    Deslogado
                                </span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Verificado -->
                        <td>
                            <?php if ($user['email_verified_at']): ?>
                                <span class="badge success">
                                    <i class="fa-solid fa-shield-check"></i>
                                    Sim
                                </span>
                            <?php else: ?>
                                <span class="badge warning">
                                    <i class="fa-solid fa-envelope"></i>
                                    Não
                                </span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Última Atividade -->
                        <td>
                            <?php if ($user['last_activity']): ?>
                                <?php 
                                    $diasInativo = $user['dias_inativo'];
                                    if ($diasInativo == 0) {
                                        echo '<span style="color: #3fb950;">Hoje</span>';
                                    } elseif ($diasInativo < 7) {
                                        echo '<span style="color: #d29922;">Há ' . $diasInativo . ' dias</span>';
                                    } else {
                                        echo '<span style="color: #f85149;">Há ' . $diasInativo . ' dias</span>';
                                    }
                                ?>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">Nunca</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Cadastrado -->
                        <td style="color: var(--text-secondary); font-size: 0.813rem;">
                            <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                        </td>
                        
                        <!-- Ações -->
                        <td>
                            <div class="actions-cell">
                                <!-- Ver Detalhes -->
                                <button class="btn btn-icon btn-ghost" onclick="loadContent('modules/dashboard/detalhes?type=<?php 
                                    $userRole = $user['role'] ?? null;
                                    $userType = $user['type'] ?? 'person';
                                    
                                    if ($userRole === 'superadmin' || $userRole === 'admin') {
                                        echo 'usuario';
                                    } elseif ($userType === 'company') {
                                        echo 'empresa';
                                    } else {
                                        echo 'usuario';
                                    }
                                ?>&id=<?= $user['id'] ?>')" title="Ver Detalhes">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                
                                <!-- Bloquear/Desbloquear -->
                                <?php if ($user['is_in_lockdown']): ?>
                                    <button class="btn btn-icon btn-ghost" onclick="desbloquearUsuario(<?= $user['id'] ?>)" title="Desbloquear">
                                        <i class="fa-solid fa-lock-open" style="color: #3fb950;"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-icon btn-ghost" onclick="bloquearUsuario(<?= $user['id'] ?>)" title="Bloquear">
                                        <i class="fa-solid fa-lock" style="color: #f85149;"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <!-- Deletar (SuperAdmin) -->
                                <?php if ($isSuperAdmin): ?>
                                    <button class="btn btn-icon btn-ghost" onclick="deletarUsuario(<?= $user['id'] ?>)" title="Deletar">
                                        <i class="fa-solid fa-trash" style="color: #f85149;"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- PAGINAÇÃO -->
    <?php if ($total_paginas > 1): ?>
    <div class="card-footer">
        <div class="pagination">
            <?php if ($pagina > 1): ?>
                <button class="btn btn-sm btn-ghost" onclick="irParaPagina(<?= $pagina - 1 ?>)">
                    <i class="fa-solid fa-chevron-left"></i>
                    Anterior
                </button>
            <?php endif; ?>
            
            <span style="color: var(--text-secondary); font-size: 0.875rem; margin: 0 16px;">
                Página <?= $pagina ?> de <?= $total_paginas ?>
            </span>
            
            <?php if ($pagina < $total_paginas): ?>
                <button class="btn btn-sm btn-ghost" onclick="irParaPagina(<?= $pagina + 1 ?>)">
                    Próxima
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fa-solid fa-users-slash"></i>
            </div>
            <div class="empty-title">Nenhum usuário encontrado</div>
            <div class="empty-description">
                Ajuste os filtros ou tente uma nova busca
            </div>
            <button class="btn btn-primary" onclick="limparFiltros()" style="margin-top: 16px;">
                <i class="fa-solid fa-filter-circle-xmark"></i>
                Limpar Filtros
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    'use strict';
    
    // Aplicar filtros
    window.aplicarFiltros = function() {
        const busca = document.getElementById('inputBusca').value;
        const tipo = document.getElementById('filterTipo').value;
        const status = document.getElementById('filterStatus').value;
        const sessao = document.getElementById('filterSessao').value;
        const lockdown = document.getElementById('filterLockdown').value;
        
        let url = 'modules/usuarios/usuarios?';
        const params = [];
        
        if (busca) params.push('busca=' + encodeURIComponent(busca));
        if (tipo !== 'all') params.push('tipo=' + tipo);
        if (status !== 'all') params.push('status=' + status);
        if (sessao !== 'all') params.push('sessao=' + sessao);
        if (lockdown !== 'all') params.push('lockdown=' + lockdown);
        
        url += params.join('&');
        loadContent(url);
    };
    
    // Limpar filtros
    window.limparFiltros = function() {
        loadContent('modules/usuarios/usuarios');
    };
    
    // Ir para página
    window.irParaPagina = function(pagina) {
        const url = new URL(window.location.href);
        url.searchParams.set('pagina', pagina);
        loadContent('modules/usuarios/usuarios?' + url.searchParams.toString());
    };
    
    // Busca ao pressionar Enter
    document.getElementById('inputBusca')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            aplicarFiltros();
        }
    });
    
    // Bloquear usuário
    window.bloquearUsuario = function(userId) {
        if (!confirm('⚠️ Tem certeza que deseja BLOQUEAR este usuário?')) return;
        
        const formData = new FormData();
        formData.append('ajax_action', 'bloquear');
        formData.append('user_id', userId);
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                aplicarFiltros();
            }
        })
        .catch(err => {
            console.error(err);
            alert('❌ Erro ao processar ação');
        });
    };
    
    // Desbloquear usuário
    window.desbloquearUsuario = function(userId) {
        if (!confirm('Deseja DESBLOQUEAR este usuário?')) return;
        
        const formData = new FormData();
        formData.append('ajax_action', 'desbloquear');
        formData.append('user_id', userId);
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                aplicarFiltros();
            }
        })
        .catch(err => {
            console.error(err);
            alert('❌ Erro ao processar ação');
        });
    };
    
    // Deletar usuário (SuperAdmin)
    window.deletarUsuario = function(userId) {
        if (!confirm('⚠️⚠️ ATENÇÃO: Deseja DELETAR este usuário?\n\nEsta ação marca o usuário como deletado (soft delete).')) return;
        if (!confirm('Confirme novamente: DELETAR?')) return;
        
        const formData = new FormData();
        formData.append('ajax_action', 'deletar');
        formData.append('user_id', userId);
        
        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                aplicarFiltros();
            }
        })
        .catch(err => {
            console.error(err);
            alert('❌ Erro ao processar ação');
        });
    };
    
    // Exportar CSV
    window.exportarCSV = function() {
        alert('🚧 Exportação CSV em desenvolvimento');
        // TODO: Implementar exportação
    };
    
    console.log('✅ Módulo de usuários carregado');
})();
</script>