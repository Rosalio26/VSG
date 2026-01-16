<?php
/**
 * ================================================================================
 * VISIONGREEN - AUTENTICAÇÃO E SEGURANÇA
 * Módulo: modules/pages/autenticacao.php
 * Descrição: Visualizar logs de autenticação, sessões ativas e configurações de segurança
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';

if (!in_array($adminRole, ['admin', 'superadmin'])) {
    echo '<div class="alert error">
            <i class="fa-solid fa-lock"></i>
            <div><strong>Erro:</strong> Acesso restrito apenas para Administradores.</div>
          </div>';
    exit;
}

$isSuperAdmin = ($adminRole === 'superadmin');

// Parâmetros de filtro
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;
$adminFilter = (int)($_GET['admin_id'] ?? 0);
$viewAdminId = $adminFilter > 0 && $isSuperAdmin ? $adminFilter : $adminId;

// Buscar dados do admin sendo visualizado
$adminSql = "SELECT id, nome, public_id FROM users WHERE id = ? AND type = 'admin' AND deleted_at IS NULL";
$adminStmt = $mysqli->prepare($adminSql);
$adminStmt->bind_param("i", $viewAdminId);
$adminStmt->execute();
$viewAdmin = $adminStmt->get_result()->fetch_assoc();

if (!$viewAdmin) {
    echo '<div class="alert error">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <div><strong>Erro:</strong> Administrador não encontrado.</div>
          </div>';
    exit;
}

// Contar total de logins
$countSql = "SELECT COUNT(*) as total FROM login_logs WHERE user_id = ?";
$countStmt = $mysqli->prepare($countSql);
$countStmt->bind_param("i", $viewAdminId);
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$totalLogins = $countResult['total'];
$totalPages = ceil($totalLogins / $perPage);

// Buscar logs de login
$loginSql = "
    SELECT 
        id,
        user_id,
        ip_address,
        user_agent,
        login_time
    FROM login_logs
    WHERE user_id = ?
    ORDER BY login_time DESC
    LIMIT ? OFFSET ?
";

$loginStmt = $mysqli->prepare($loginSql);
$loginStmt->bind_param("iii", $viewAdminId, $perPage, $offset);
$loginStmt->execute();
$logins = $loginStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar tentativas de login falhadas
$failedSql = "
    SELECT 
        email,
        ip,
        attempts,
        last_attempt
    FROM login_attempts
    ORDER BY last_attempt DESC
    LIMIT 20
";

$failedStmt = $mysqli->prepare($failedSql);
$failedStmt->execute();
$failedAttempts = $failedStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar lista de admins para superadmin
$adminsList = [];
if ($isSuperAdmin) {
    $listSql = "
        SELECT id, nome, public_id 
        FROM users 
        WHERE type = 'admin' AND role IN ('admin', 'superadmin') 
        AND deleted_at IS NULL
        ORDER BY nome ASC
    ";
    $listStmt = $mysqli->prepare($listSql);
    $listStmt->execute();
    $adminsList = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Detectar navegador e sistema operacional
function detectBrowser($userAgent) {
    if (strpos($userAgent, 'Chrome') !== false && strpos($userAgent, 'Edg') === false) {
        return 'Chrome';
    } elseif (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
        return 'Safari';
    } elseif (strpos($userAgent, 'Firefox') !== false) {
        return 'Firefox';
    } elseif (strpos($userAgent, 'Edg') !== false) {
        return 'Edge';
    } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
        return 'Internet Explorer';
    }
    return 'Desconhecido';
}

function detectOS($userAgent) {
    if (strpos($userAgent, 'Windows') !== false) {
        return 'Windows';
    } elseif (strpos($userAgent, 'Macintosh') !== false) {
        return 'macOS';
    } elseif (strpos($userAgent, 'Linux') !== false) {
        return 'Linux';
    } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
        return 'iOS';
    } elseif (strpos($userAgent, 'Android') !== false) {
        return 'Android';
    }
    return 'Desconhecido';
}
?>

<!-- PAGE HEADER -->
<div style="margin-bottom: 30px;">
    <h2 style="color: #fff; margin: 0 0 8px 0;">
        <i class="fa-solid fa-shield-halved"></i>
        Autenticação e Segurança
    </h2>
    <p style="color: #666; font-size: 0.85rem; margin: 0;">
        Visualize logs de autenticação, sessões e atividades de segurança.
    </p>
</div>

<!-- TABS -->
<div style="margin-bottom: 24px; border-bottom: 1px solid var(--border);">
    <div style="display: flex; gap: 4px;">
        <button onclick="loadContent('modules/pages/autenticacao?tab=autenticacao<?= $adminFilter > 0 ? '&admin_id=' . $adminFilter : '' ?>')" class="tab-btn <?= !isset($_GET['tab']) || $_GET['tab'] === 'autenticacao' ? 'active' : '' ?>">
            <i class="fa-solid fa-lock"></i>
            Autenticação
        </button>
        <button onclick="loadContent('modules/pages/autenticacao?tab=empresas<?= $adminFilter > 0 ? '&admin_id=' . $adminFilter : '' ?>')" class="tab-btn <?= isset($_GET['tab']) && $_GET['tab'] === 'empresas' ? 'active' : '' ?>">
            <i class="fa-solid fa-building"></i>
            Empresas
        </button>
        <button onclick="loadContent('modules/pages/autenticacao?tab=usuarios<?= $adminFilter > 0 ? '&admin_id=' . $adminFilter : '' ?>')" class="tab-btn <?= isset($_GET['tab']) && $_GET['tab'] === 'usuarios' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i>
            Usuários
        </button>
    </div>
</div>

<?php
// Determinar qual aba mostrar
$currentTab = $_GET['tab'] ?? 'autenticacao';
?>

<!-- ABA: AUTENTICAÇÃO -->
<?php if ($currentTab === 'autenticacao'): ?>

<!-- FILTRO DE ADMIN (SUPERADMIN) -->
<?php if ($isSuperAdmin): ?>
<div class="card mb-3">
    <div class="card-body">
        <label class="form-label">Visualizar logs de:</label>
        <select class="form-select" onchange="loadContent('modules/pages/autenticacao?admin_id=' + this.value)" style="max-width: 400px;">
            <option value="">Minha conta (Superadmin)</option>
            <?php foreach ($adminsList as $admin): ?>
                <option value="<?= $admin['id'] ?>" <?= $adminFilter == $admin['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($admin['nome']) ?> (<?= htmlspecialchars($admin['public_id']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- GRID DE ESTATÍSTICAS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    
    <!-- Total de Logins -->
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: rgba(35,134,54,0.1); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 1.5rem;">
                <i class="fa-solid fa-right-to-bracket"></i>
            </div>
            <div>
                <div style="font-size: 0.813rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">
                    Total de Logins
                </div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--text-title);">
                    <?= $totalLogins ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Último Login -->
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: rgba(56,139,253,0.1); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: #58a6ff; font-size: 1.5rem;">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div>
                <div style="font-size: 0.813rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">
                    Último Login
                </div>
                <div style="font-size: 1rem; font-weight: 600; color: var(--text-title);">
                    <?= !empty($logins) ? date('d/m/Y H:i', strtotime($logins[0]['login_time'])) : 'N/A' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tentativas Falhadas -->
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: rgba(248,81,73,0.1); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: #f85149; font-size: 1.5rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <div style="font-size: 0.813rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">
                    Tentativas Falhadas
                </div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--text-title);">
                    <?= count($failedAttempts) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LOGS DE LOGIN -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-list"></i>
            Histórico de Logins
        </h3>
    </div>
    <div class="card-body">
        <?php if (!empty($logins)): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Endereço IP</th>
                            <th>Navegador</th>
                            <th>Sistema Operacional</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logins as $login): 
                            $browser = detectBrowser($login['user_agent']);
                            $os = detectOS($login['user_agent']);
                            $browserIcon = match($browser) {
                                'Chrome' => 'fab fa-chrome',
                                'Firefox' => 'fab fa-firefox',
                                'Safari' => 'fab fa-safari',
                                'Edge' => 'fab fa-edge',
                                default => 'fa-solid fa-globe'
                            };
                            $osIcon = match($os) {
                                'Windows' => 'fab fa-windows',
                                'macOS' => 'fab fa-apple',
                                'Linux' => 'fab fa-linux',
                                'iOS' => 'fab fa-apple',
                                'Android' => 'fab fa-android',
                                default => 'fa-solid fa-computer'
                            };
                        ?>
                        <tr>
                            <td style="font-size: 0.875rem; color: #666;">
                                <?= date('d/m/Y H:i:s', strtotime($login['login_time'])) ?>
                            </td>
                            <td>
                                <code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;">
                                    <?= htmlspecialchars($login['ip_address']) ?>
                                </code>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem;">
                                    <i class="<?= $browserIcon ?>"></i>
                                    <?= htmlspecialchars($browser) ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem;">
                                    <i class="<?= $osIcon ?>"></i>
                                    <?= htmlspecialchars($os) ?>
                                </div>
                            </td>
                            <td>
                                <small style="color: #666; word-break: break-all;">
                                    <?= htmlspecialchars(substr($login['user_agent'], 0, 60)) ?>...
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINAÇÃO -->
            <?php if ($totalPages > 1): ?>
            <div style="padding: 20px; text-align: center; border-top: 1px solid var(--border);">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <button onclick="irParaPaginaAuth(1)" class="page-btn">Primeira</button>
                        <button onclick="irParaPaginaAuth(<?= $page - 1 ?>)" class="page-btn"><i class="fa-solid fa-chevron-left"></i></button>
                    <?php endif; ?>
                    
                    <span class="page-info">Página <?= $page ?> de <?= $totalPages ?></span>
                    
                    <?php if ($page < $totalPages): ?>
                        <button onclick="irParaPaginaAuth(<?= $page + 1 ?>)" class="page-btn"><i class="fa-solid fa-chevron-right"></i></button>
                        <button onclick="irParaPaginaAuth(<?= $totalPages ?>)" class="page-btn">Última</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
                <div class="empty-title">Nenhum login registrado</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- TENTATIVAS FALHADAS -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-exclamation-triangle"></i>
            Tentativas de Login Falhadas
        </h3>
    </div>
    <div class="card-body">
        <?php if (!empty($failedAttempts)): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Endereço IP</th>
                            <th>Tentativas</th>
                            <th>Última Tentativa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failedAttempts as $attempt): ?>
                        <tr>
                            <td>
                                <code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;">
                                    <?= htmlspecialchars($attempt['email']) ?>
                                </code>
                            </td>
                            <td>
                                <code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;">
                                    <?= htmlspecialchars($attempt['ip']) ?>
                                </code>
                            </td>
                            <td>
                                <span class="badge <?= $attempt['attempts'] > 3 ? 'error' : 'warning' ?>">
                                    <?= $attempt['attempts'] ?> tentativas
                                </span>
                            </td>
                            <td style="font-size: 0.875rem; color: #666;">
                                <?= date('d/m/Y H:i:s', strtotime($attempt['last_attempt'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-shield-check"></i></div>
                <div class="empty-title">Nenhuma tentativa falhada</div>
                <div class="empty-description">Sua conta está segura</div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ABA: EMPRESAS -->
<?php if ($currentTab === 'empresas'): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-building"></i>
                Empresas Cadastradas
            </h3>
        </div>
        <div class="card-body">
            <?php 
                // Buscar empresas
                $empresasSql = "
                    SELECT 
                        b.id,
                        b.user_id,
                        b.tax_id,
                        b.status_documentos,
                        b.updated_at,
                        u.nome,
                        u.email,
                        u.created_at as user_created_at,
                        u.status as user_status
                    FROM businesses b
                    INNER JOIN users u ON b.user_id = u.id
                    ORDER BY b.updated_at DESC
                    LIMIT 20
                ";
                
                $empresasStmt = $mysqli->prepare($empresasSql);
                $empresasStmt->execute();
                $empresas = $empresasStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            ?>
            
            <?php if (!empty($empresas)): ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>NUIT</th>
                                <th>Status Docs</th>
                                <th>Status</th>
                                <th>Data de Criação</th>
                                <th style="text-align: center;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($empresas as $empresa): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: #fff;">
                                        <?= htmlspecialchars($empresa['nome']) ?>
                                    </div>
                                    <small style="color: #666;">
                                        <?= htmlspecialchars($empresa['email']) ?>
                                    </small>
                                </td>
                                <td>
                                    <code style="background: var(--bg-elevated); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;">
                                        <?= htmlspecialchars($empresa['tax_id'] ?? 'N/A') ?>
                                    </code>
                                </td>
                                <td>
                                    <span class="badge <?= 
                                        $empresa['status_documentos'] === 'aprovado' ? 'success' : 
                                        ($empresa['status_documentos'] === 'rejeitado' ? 'error' : 'warning')
                                    ?>">
                                        <?= ucfirst($empresa['status_documentos']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $empresa['user_status'] == 'active' ? 'success' : 'error' ?>">
                                        <?= ucfirst($empresa['user_status']) ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.875rem; color: #666;">
                                    <?= date('d/m/Y', strtotime($empresa['updated_at'])) ?>
                                </td>
                                <td style="text-align: center;">
                                    <button onclick="loadContent('modules/dashboard/detalhes?type=empresa&id=<?= $empresa['user_id'] ?>')" class="btn btn-sm btn-ghost">
                                        <i class="fa-solid fa-eye"></i>
                                        Ver
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-solid fa-building"></i></div>
                    <div class="empty-title">Nenhuma empresa encontrada</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- ABA: USUÁRIOS -->
<?php if ($currentTab === 'usuarios'): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-users"></i>
                Usuários Pessoa Física
            </h3>
        </div>
        <div class="card-body">
            <?php 
                // Buscar usuários pessoa física
                $usuariosSql = "
                    SELECT 
                        id,
                        public_id,
                        nome,
                        email,
                        telefone,
                        status,
                        email_verified_at,
                        created_at
                    FROM users
                    WHERE type = 'person' AND deleted_at IS NULL
                    ORDER BY created_at DESC
                    LIMIT 20
                ";
                
                $usuariosStmt = $mysqli->prepare($usuariosSql);
                $usuariosStmt->execute();
                $usuarios = $usuariosStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            ?>
            
            <?php if (!empty($usuarios)): ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Email Verificado</th>
                                <th>Status</th>
                                <th>Data de Criação</th>
                                <th style="text-align: center;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: #fff;">
                                        <?= htmlspecialchars($usuario['nome']) ?>
                                    </div>
                                    <small style="color: #666;">
                                        <?= htmlspecialchars($usuario['public_id']) ?>
                                    </small>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($usuario['email']) ?>" style="color: var(--accent); text-decoration: none; font-size: 0.875rem;">
                                        <?= htmlspecialchars($usuario['email']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="tel:<?= htmlspecialchars($usuario['telefone']) ?>" style="color: #666; text-decoration: none; font-size: 0.875rem;">
                                        <?= htmlspecialchars($usuario['telefone']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px; font-size: 0.875rem;">
                                        <?php if ($usuario['email_verified_at']): ?>
                                            <i class="fa-solid fa-check-circle" style="color: #3fb950;"></i>
                                            <small style="color: #666;">
                                                <?= date('d/m/Y', strtotime($usuario['email_verified_at'])) ?>
                                            </small>
                                        <?php else: ?>
                                            <i class="fa-solid fa-circle-xmark" style="color: #f85149;"></i>
                                            <small style="color: #666;">Não verificado</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= 
                                        $usuario['status'] === 'active' ? 'success' : 
                                        ($usuario['status'] === 'blocked' ? 'error' : 'warning')
                                    ?>">
                                        <?= ucfirst($usuario['status']) ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.875rem; color: #666;">
                                    <?= date('d/m/Y', strtotime($usuario['created_at'])) ?>
                                </td>
                                <td style="text-align: center;">
                                    <button onclick="loadContent('modules/dashboard/detalhes?type=usuario&id=<?= $usuario['id'] ?>')" class="btn btn-sm btn-ghost">
                                        <i class="fa-solid fa-eye"></i>
                                        Ver
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="empty-title">Nenhum usuário encontrado</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<style>
    .tab-btn {
        padding: 12px 20px;
        background: transparent;
        border: none;
        color: var(--text-secondary);
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        position: relative;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .tab-btn:hover {
        color: var(--text-primary);
    }

    .tab-btn.active {
        color: var(--accent);
    }

    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--accent);
    }
    .card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--text-title);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-body {
        padding: 20px;
    }

    .mb-3 {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        color: var(--text-secondary);
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .form-select {
        width: 100%;
        padding: 10px 12px;
        background: var(--bg-elevated);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .form-select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(35, 134, 54, 0.1);
    }

    .table-wrapper {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table thead {
        background: var(--bg-elevated);
    }

    .table th {
        padding: 14px 16px;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-secondary);
        text-transform: uppercase;
        border-bottom: 1px solid var(--border);
    }

    .table td {
        padding: 16px;
        font-size: 0.875rem;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
    }

    .table tbody tr:hover {
        background: var(--bg-elevated);
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .badge.warning {
        background: rgba(158, 106, 3, 0.15);
        color: #d29922;
    }

    .badge.error {
        background: rgba(248, 81, 73, 0.15);
        color: #f85149;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-secondary);
    }

    .empty-icon {
        font-size: 4rem;
        opacity: 0.3;
        margin-bottom: 20px;
    }

    .empty-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .empty-description {
        font-size: 0.938rem;
        color: var(--text-secondary);
    }

    .page-btn {
        min-width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.875rem;
        font-weight: 600;
        margin: 0 4px;
    }

    .page-btn:hover {
        background: var(--bg-elevated);
        border-color: var(--accent);
        color: var(--text-primary);
    }

    .page-info {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0 12px;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
    }
</style>

<script>
    function irParaPaginaAuth(pageNum) {
        const adminId = new URLSearchParams(window.location.search).get('admin_id');
        const tab = new URLSearchParams(window.location.search).get('tab') || 'autenticacao';
        let url = 'modules/pages/autenticacao?tab=' + tab + '&page=' + pageNum;
        if (adminId) {
            url += '&admin_id=' + adminId;
        }
        loadContent(url);
    }
</script>