<?php
define('IS_ADMIN_PAGE', true); 
define('REQUIRED_TYPE', 'admin'); 
define('REQUIRED_ROLE', ['admin', 'superadmin']);
session_start();
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';
require_once '../../registration/middleware/middleware_auth.php';

/* ================= 1. SEGURANÇA E FINGERPRINT ================= */
if (!isset($_SESSION['auth']['role']) || !in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    header("Location: ../../registration/login/login.php?error=nao_e_admin");
    exit;
}

$fingerprint = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
if (!isset($_SESSION['secure_fingerprint'])) {
    $_SESSION['secure_fingerprint'] = $fingerprint;
} elseif ($_SESSION['secure_fingerprint'] !== $fingerprint) {
    session_destroy();
    header("Location: ../../registration/login/login.php?error=sessao_vencida");
    exit;
}

$adminId = $_SESSION['auth']['user_id'];
$adminRole = $_SESSION['auth']['role'];
$isSuperAdmin = ($adminRole === 'superadmin');
$ip_address = $_SERVER['REMOTE_ADDR'];
$currentTab = $_GET['tab'] ?? 'resumo';

/* ================= 2. CONTROLE DE SESSÃO (1H vs 24H) ================= */
$stmt_time = $mysqli->prepare("SELECT nome, password_changed_at FROM users WHERE id = ?");
$stmt_time->bind_param("i", $adminId);
$stmt_time->execute();
$admin_data = $stmt_time->get_result()->fetch_assoc();

$timeoutLimit = $isSuperAdmin ? 3600 : 86400;
$lastChangeTs = !empty($admin_data['password_changed_at']) ? strtotime($admin_data['password_changed_at']) : time();
$remainingSeconds = $timeoutLimit - (time() - $lastChangeTs);

if ($remainingSeconds <= 0) {
    session_destroy();
    header("Location: ../../registration/login/login.php?info=acesso_expirado");
    exit;
}

/* ================= 3. NOTIFICAÇÕES E MENSAGENS (FILTRANDO POR 'UNREAD') ================= */
/**
 * Melhoria: Adicionado filtro 'unread' para que o Header reflita apenas o que é novo.
 */
$sql_msgs = "SELECT n.id, IFNULL(u.nome, 'Sistema') AS sender_name, n.subject, n.created_at 
             FROM notifications n 
             LEFT JOIN users u ON n.sender_id = u.id 
             WHERE n.receiver_id = ? AND n.status = 'unread' 
             ORDER BY n.created_at DESC LIMIT 3";

$stmt_msgs = $mysqli->prepare($sql_msgs);
$stmt_msgs->bind_param("i", $adminId);
$stmt_msgs->execute();
$res_msgs = $stmt_msgs->get_result();

// Contagem para o Badge (ponto verde)
$has_new_msgs = ($res_msgs->num_rows > 0);

// Busca alertas críticos (Segurança ou Sistema) que não foram lidos
$sql_alerts = "SELECT id, category, subject, created_at FROM notifications 
               WHERE receiver_id = ? AND category IN ('alert', 'security') AND status = 'unread'
               ORDER BY created_at DESC LIMIT 5";
$stmt_alerts = $mysqli->prepare($sql_alerts);
$stmt_alerts->bind_param("i", $adminId);
$stmt_alerts->execute();
$res_alerts = $stmt_alerts->get_result();

// Verifica se existe algum alerta não lido para o ponto vermelho
$has_critical = $mysqli->query("SELECT id FROM notifications WHERE receiver_id = $adminId AND category != 'chat' AND status = 'unread' LIMIT 1")->num_rows > 0;

/* ================= CONTAGEM DE PENDÊNCIAS PARA SIDEBAR ================= */
$total_pendencias_sidebar = 0;

// Documentos pendentes
$count_docs_sidebar = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'pendente'")->fetch_assoc()['total'];
$total_pendencias_sidebar += $count_docs_sidebar;

// Usuários novos (últimos 30 dias)
$count_users_sidebar = $mysqli->query("SELECT COUNT(*) as total FROM users u LEFT JOIN businesses b ON u.id = b.user_id WHERE u.type = 'company' AND (b.status_documentos IS NULL OR b.status_documentos = 'pendente') AND DATEDIFF(NOW(), u.created_at) <= 30")->fetch_assoc()['total'];
$total_pendencias_sidebar += $count_users_sidebar;

// Alertas críticos não lidos
$count_alerts_sidebar = $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = $adminId AND status = 'unread' AND category IN ('alert', 'security', 'system_error')")->fetch_assoc()['total'];
$total_pendencias_sidebar += $count_alerts_sidebar;


/* ================= 4. LOGICAS DE PROCESSAMENTO (POST) ================= */
$status_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate($_POST['csrf'] ?? null)) {
    if (isset($_POST['final_action'])) {
        $targetUserId = (int)$_POST['user_id'];
        $alvaraStatus = $_POST['alvara_decision']; 
        $taxStatus    = $_POST['tax_decision'];
        $manualTaxId  = !empty($_POST['manual_tax_id']) ? cleanInput($_POST['manual_tax_id']) : NULL;

        $mysqli->begin_transaction();
        try {
            if ($alvaraStatus === 'ok' && ($taxStatus === 'ok' || $taxStatus === 'text_only')) {
                $statusFinal = 'aprovado';
                $updateSql = "UPDATE businesses SET status_documentos = 'aprovado', motivo_rejeicao = NULL";
                if ($manualTaxId) {
                    $updateSql .= ", tax_id = '$manualTaxId'";
                }
                $updateSql .= " WHERE user_id = $targetUserId";
                $mysqli->query($updateSql);
            } else {
                $statusFinal = 'rejeitado';
                $motivo = "[ALVARÁ]: " . cleanInput($_POST['motivo_alvara']) . " | [TAX]: " . cleanInput($_POST['motivo_tax']);
                $stmt = $mysqli->prepare("UPDATE businesses SET status_documentos = 'rejeitado', motivo_rejeicao = ? WHERE user_id = ?");
                $stmt->bind_param('si', $motivo, $targetUserId);
                $stmt->execute();
            }

            $stmtLog = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES (?, ?, ?)");
            $actStr = "AUDIT_" . strtoupper($statusFinal);
            $stmtLog->bind_param("iss", $adminId, $actStr, $ip_address);
            $stmtLog->execute();

            $mysqli->commit();
            $status_msg = "Auditoria finalizada com sucesso.";
        } catch (Exception $e) { 
            $mysqli->rollback(); 
            $status_msg = "Erro no processamento: " . $e->getMessage();
        }
    }
}

$uploadBase = "../../registration/uploads/business/";

/**
 * LOGICA DE RESPOSTA PARA ATUALIZAÇÃO SILENCIOSA (POLLING)
 * Se a requisição vier via AJAX pedindo contadores, devolvemos JSON e encerramos o script.
 */
if (isset($_GET['action']) && $_GET['action'] === 'get_counters') {
    header('Content-Type: application/json');
    
    // Conta mensagens não lidas (categoria 'chat')
    $res_chat = $mysqli->query("
        SELECT COUNT(*) as total 
        FROM notifications 
        WHERE receiver_id = $adminId 
          AND status = 'unread' 
          AND category = 'chat'
    ")->fetch_assoc();
    
    // Conta alertas não lidos (todas as outras categorias)
    $res_alerts = $mysqli->query("
        SELECT COUNT(*) as total 
        FROM notifications 
        WHERE receiver_id = $adminId 
          AND status = 'unread' 
          AND category IN ('alert', 'security', 'system_error', 'audit')
    ")->fetch_assoc();
    
    echo json_encode([
        'unread_chat' => (int)$res_chat['total'],
        'unread_alerts' => (int)$res_alerts['total'],
        'unread_total' => (int)$res_chat['total'] + (int)$res_alerts['total']
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionGreen | Emerald Dashboard Master</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style/dashboard_admin.css">
    <link rel="stylesheet" href="assets/style/dashboard-components.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    // Configuração global do Chart.js
    window.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart !== 'undefined') {
            Chart.defaults.color = '#8b949e';
            Chart.defaults.borderColor = '#30363d';
            Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
            console.log('✅ Chart.js carregado globalmente');
            window.chartJSLoaded = true;
        } else {
            console.error('❌ Chart.js não carregou');
        }
    });
    </script>
</head>
<style>
    /* ========== BOTÃO DE ROTAÇÃO DE SENHA ========== */
    .security-widget {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px;
        background: rgba(255,255,255,0.02);
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.05);
    }

    .rotate-password-btn {
        background: var(--accent-green);
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        cursor: pointer;
        color: #000;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        margin-left: auto;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .rotate-password-btn:hover {
        transform: rotate(180deg);
        background: #00ff88;
        box-shadow: 0 0 20px rgba(0,255,136,0.5);
    }

    /* ========== MODAL DE SENHA ========== */
    .password-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        backdrop-filter: blur(10px);
        z-index: 10000;
    }

    .password-modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .password-modal-content {
        background: #1a1a1a;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 20px;
        padding: 40px;
        max-width: 500px;
        width: 90%;
        animation: slideUp 0.4s ease;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .password-text {
        font-family: 'Courier New', monospace;
        font-size: 1.8rem;
        font-weight: 900;
        color: var(--accent-green);
        letter-spacing: 3px;
    }
</style>

<body id="masterBody">
    <div id="progress-bar"></div>
    <aside class="sidebar">
        <div class="collapse-btn" onclick="toggleSidebar()">
            <i class="fa-solid fa-chevron-left"></i>
        </div>

        <div class="header-section">
            <div class="brand-area">
                <div class="logo-icon"><i class="fa-solid fa-leaf"></i></div>
                <div class="logo-text">
                    <span class="logo-main">VISIONGREEN</span>
                    <span class="master-role" style="
                        font-size: 0.8rem; 
                        text-align: center;
                        margin-top: 4px;
                        padding: 2px 6px; 
                        border-radius: 4px; 
                        font-weight: 800;
                        background: <?= $isSuperAdmin ? 'rgba(255,204,0,0.1)' : 'rgba(0,255,136,0.1)' ?>; 
                        color: <?= $isSuperAdmin ? '#ffcc00' : '#00ff88' ?>; 
                        border: 1px solid currentColor;
                        text-transform: uppercase;">
                        <?= strtoupper($adminRole) ?>
                    </span>
                </div>
            </div>
            <div class="security-widget">
                <div class="security-icon"><i class="fa-solid fa-key"></i></div>
                <div class="security-info">
                    <span class="security-label">Segurança do painel</span>
                    <span class="security-timer">Novo Password em: <span id="timer">--:--</span></span>
                </div>
                <!-- ⭐ NOVO: Botão de rotação manual -->
                 <?php if ($isSuperAdmin): ?>
                    <button onclick="rotatePasswordManually()" class="rotate-password-btn" title="Gerar nova senha agora">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="nav-menu">
            <div class="nav-label">Monitoramento</div>
            <div class="nav-group">
                <a href="javascript:void(0)" class="nav-item active" onclick="loadContent('modules/dashboard/dashboard', this)">
                    <div class="nav-icon-box"><i class="fa-solid fa-gauge-high"></i></div>
                    <span>Dashboard</span>
                    <i class="fa-solid fa-chevron-down arrow-icon"></i>
                </a>
                <div class="nav-submenu">
                    <div class="tree-line"></div>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/dashboard/pendencias', this)">
                        <div class="sub-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                        <span>Pendências</span> 
                        <?php if($total_pendencias_sidebar > 0): ?>
                            <span class="nav-count"><?= $total_pendencias_sidebar ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/dashboard/analise', this)">
                        <div class="sub-icon"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                        <span>Análise de Contas</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/dashboard/historico', this)">
                        <div class="sub-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <span>Histórico</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/dashboard/plataformas', this)">
                        <div class="sub-icon"><i class="fa-solid fa-layer-group"></i></div>
                        <span>Plataformas</span>
                    </a>
                </div>
            </div>

            <div class="nav-label">Comunicação</div>
            <?php
                /* ================= 5. CONTAGEM DE MENSAGENS PARA O BADGE ================= */
                // CORREÇÃO: Alterado de 'admin_notifications' para 'notifications' conforme seu banco de dados
                $sql_count = "SELECT COUNT(*) as total FROM notifications WHERE receiver_id = ? AND status = 'unread'";
                $stmt_count = $mysqli->prepare($sql_count);

                if ($stmt_count) {
                    $stmt_count->bind_param("i", $adminId);
                    $stmt_count->execute();
                    $res_count = $stmt_count->get_result()->fetch_assoc();
                    $count_msgs = $res_count['total'];
                    $stmt_count->close();
                } else {
                    $count_msgs = 0; // Fallback caso haja erro na preparação
                }
            ?>

            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/mensagens/mensagens', this)">
                <div class="nav-icon-box">
                    <i class="fa-solid fa-comment-dots"></i>
                </div>
                <span>Mensagens</span>
                
                <?php if(isset($count_msgs) && $count_msgs > 0): ?>
                    <span class="nav-count" id="sidebar-msg-count" style="
                        background: #ff4d4d; 
                        color: white; 
                        padding: 2px 6px; 
                        border-radius: 50%; 
                        font-size: 0.7rem; 
                        font-weight: bold; 
                        margin-left: auto;
                        min-width: 15px;
                        text-align: center;
                    ">
                        <?= $count_msgs ?>
                    </span>
                <?php endif; ?>
            </a>

            <div class="nav-label">Dados de Base</div>
            <div class="nav-group">
                <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/forms/forms', this)">
                    <div class="nav-icon-box"><i class="fa-solid fa-rectangle-list"></i></div>
                    <span>Forms</span>
                    <i class="fa-solid fa-chevron-down arrow-icon"></i>
                </a>
                <div class="nav-submenu">
                    <div class="tree-line"></div>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/forms/form-input', this)">
                        <div class="sub-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                        <span>Entrada de Dados</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/forms/form-config', this)">
                        <div class="sub-icon"><i class="fa-solid fa-gears"></i></div>
                        <span>Configurações</span>
                    </a>
                </div>
            </div>

            <div class="nav-group">
                <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/tabelas/tabelas', this)">
                    <div class="nav-icon-box"><i class="fa-solid fa-table-cells-large"></i></div>
                    <span>Tabelas</span>
                    <i class="fa-solid fa-chevron-down arrow-icon"></i>
                </a>
                <div class="nav-submenu">
                    <div class="tree-line"></div>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/tabelas/tabela-geral', this)">
                        <div class="sub-icon"><i class="fa-solid fa-list-check"></i></div>
                        <span>Visão Geral</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/tabelas/tabela-financeiro', this)">
                        <div class="sub-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                        <span>Financeiro</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/tabelas/tabela-export', this)">
                        <div class="sub-icon"><i class="fa-solid fa-file-export"></i></div>
                        <span>Exportação</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/tabelas/lista-empresas', this)">
                        <div class="sub-icon"><i class="fa-solid fa-building-user"></i></div>
                        <span>Lista De Empresas</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/tabelas/relatorio', this)">
                        <div class="sub-icon"><i class="fa-solid fa-chart-line"></i></div>
                        <span>Relatorios</span>
                    </a>
                </div>
            </div>

            <div class="nav-label">Administração</div>
            <?php if ($isSuperAdmin): ?>
                <div class="nav-group">
                    <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/auditor/auditores', this)">
                        <div class="nav-icon-box"><i class="fa-solid fa-user-shield"></i></div>
                        <span>Auditores</span>
                        <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </a>
                    <div class="nav-submenu">
                        <div class="tree-line"></div>
                        <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/auditor/auditor-lista', this)">
                            <div class="sub-icon"><i class="fa-solid fa-users-viewfinder"></i></div>
                            <span>Lista de Auditores</span>
                        </a>
                        <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/auditor/auditor-logs', this)">
                            <div class="sub-icon"><i class="fa-solid fa-file-signature"></i></div>
                            <span>Logs de Auditoria</span>
                        </a>
                        <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/auditor/admin-auditoria', this)">
                            <div class="sub-icon">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                            <span>Admin Auditor</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/usuarios/usuarios', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-users-gear"></i></div>
                <span>Usuários</span>
            </a>

            <div class="nav-label">Páginas</div>
            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/pages/autenticacao', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-shield-halved"></i></div>
                <span>Autenticação</span>
            </a>
            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/pages/admin-perfil', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-id-badge"></i></div>
                <span>Perfil do Admin</span>
            </a>
            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/pages/pages', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-file-invoice"></i></div>
                <span>Pages</span>
            </a>

            <div class="nav-label">Suporte Técnico</div>
            <?php if ($isSuperAdmin): ?>
                <a href="javascript:void(0)" class="nav-item" style="color: var(--accent-green)" onclick="loadContent('modules/suporte/manual-admin', this)">
                    <div class="nav-icon-box"><i class="fa-solid fa-book-bookmark"></i></div>
                    <span>Manual Superadmin</span>
                </a>
            <?php endif; ?>
            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/suporte/help-sub-admin', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-circle-question"></i></div>
                <span>Ajuda</span>
            </a>
        </div>

        <div class="sidebar-footer-fixed">
            <div class="nav-label" style="padding: 10px 15px 10px;">Sistema</div>
            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('system/settings', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-sliders"></i></div>
                <span>Definições</span>
            </a>
            <a href="../../registration/login/logout.php" class="nav-item logout-btn">
                <div class="nav-icon-box"><i class="fa-solid fa-power-off"></i></div>
                <span>Encerrar Sessão</span>
            </a>
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="header-section-main">
            <div class="header-left">
                <div class="breadcrumb-area">
                    <div class="b-path"><span id="parent-name">Monitoramento</span> <i class="fa-solid fa-chevron-right" style="font-size: 0.5rem;"></i></div>
                    <div class="b-current" id="current-page-title">Dashboard</div>
                </div>

                <div class="search-container">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="mainSearchInput" placeholder="Pesquisar por UID, Empresa ou Auditor..." autocomplete="off">
                    <span class="search-shortcut">/</span>
                </div>
            </div>

            <div class="header-right">
                <div class="system-ping" id="status-indicator" title="Verificando Conexão...">
                    <div class="ping-dot"></div>
                    <span id="status-text">LIVE</span>
                </div>

                <div class="icon-action-btn" title="Alternar Tema (Verde/Azul)" onclick="toggleTheme()">
                    <i class="fa-solid fa-palette"></i>
                </div>

                <div class="header-action-wrapper">
                    <div class="icon-action-btn" title="Chat Admin">
                        <i class="fa-solid fa-comment-dots"></i>
                        <span class="badge-dot badge-pulse" id="chat-badge-dot" style="background: var(--accent-green); display: <?= $has_new_msgs ? 'block' : 'none' ?>;"></span>
                    </div>
                    <div class="header-dropdown">
                        <div class="dropdown-header">Mensagens Recentes</div>
                        
                        <?php if ($res_msgs->num_rows > 0): ?>
                            <?php while($msg = $res_msgs->fetch_assoc()): ?>
                                <div class="dropdown-item" onclick="openNotification(<?= $msg['id'] ?>, 'message')">
                                    <i class="fa-solid fa-circle-user"></i>
                                    <div>
                                        <strong><?= htmlspecialchars($msg['sender_name']) ?></strong><br>
                                        <small><?= htmlspecialchars($msg['subject']) ?></small>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="dropdown-item"><small>Nenhuma mensagem nova</small></div>
                        <?php endif; ?>

                        <div class="dropdown-footer" style="cursor:pointer" onclick="loadContent('modules/mensagens/mensagens')">
                            Ver todas as mensagens
                        </div>
                    </div>
                </div>

                <div class="header-action-wrapper">
                    <div class="icon-action-btn" title="Alertas Críticos">
                        <i class="fa-solid fa-bell"></i>
                        <span class="badge-dot" id="alerts-badge-dot" style="background: #ff4d4d; display: <?= $has_critical ? 'block' : 'none' ?>;"></span>
                    </div>
                    <div class="header-dropdown">
                        <div class="dropdown-header">Centro de Notificações</div>
                        
                        <?php if ($res_alerts->num_rows > 0): ?>
                            <?php while($alert = $res_alerts->fetch_assoc()): ?>
                                <div class="dropdown-item" onclick="openNotification(<?= $alert['id'] ?>, 'alert')">
                                    <?php 
                                        // Mapeamento simples: se mudar o ENUM no futuro, basta adicionar o ícone aqui
                                        $config = [
                                            'security' => ['icon' => 'fa-triangle-exclamation', 'color' => '#ff4d4d'],
                                            'alert'    => ['icon' => 'fa-circle-info', 'color' => '#4da3ff'],
                                            'message'  => ['icon' => 'fa-envelope', 'color' => '#00ff88']
                                        ];
                                        $current = $config[$alert['category']] ?? $config['alert'];
                                    ?>
                                    <i class="fa-solid <?= $current['icon'] ?>" style="color: <?= $current['color'] ?>;"></i>
                                    <div>
                                        <strong><?= htmlspecialchars($alert['subject']) ?></strong><br>
                                        <small><?= date('d/m H:i', strtotime($alert['created_at'])) ?></small>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="dropdown-item"><small>Sem alertas novos</small></div>
                        <?php endif; ?>
                        <div class="dropdown-footer" onclick="clearAllAlerts()" style="cursor:pointer">Limpar notificações</div>
                    </div>
                </div>

                <div style="width: 1px; height: 30px; background: var(--border-color); margin: 0 10px;"></div>
                
                <div class="header-action-wrapper">
                    <div class="master-profile">
                        <div class="master-info" style="text-align: right;">
                            <span class="master-name" style="display: block; font-weight: 700; color: #fff;">
                                <?= htmlspecialchars($admin_data['nome']) ?>
                            </span>
                            
                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 6px; margin-top: 4px;">

                                <span class="admin-id-tag" style="
                                    font-family: 'Courier New', monospace; 
                                    font-size: 0.85rem; 
                                    color: #a4a3a3; 
                                    background: #000; 
                                    padding: 2px 6px; 
                                    border-radius: 4px; 
                                    border: 1px solid #222;">
                                    #<?= $_SESSION['auth']['public_id'] ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="avatar-box" style="position: relative; margin-left: 15px;">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_data['nome']) ?>&background=00ff88&color=000&bold=true" 
                                alt="Admin Avatar" 
                                style="width: 42px; height: 42px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);">
                            
                            <div class="status-indicator" style="
                                position: absolute; 
                                bottom: -2px; 
                                right: -2px; 
                                width: 12px; 
                                height: 12px; 
                                background: #00ff88; 
                                border: 2px solid #050505; 
                                border-radius: 50%;
                                box-shadow: 0 0 10px rgba(0, 255, 136, 0.5);">
                            </div>
                        </div>
                    </div>
                    <div class="header-dropdown" style="width: 200px;">
                        <div class="dropdown-header">Minha Conta</div>
                        <div class="dropdown-item" onclick="loadContent('modules/pages/admin-perfil')">
                            <i class="fa-solid fa-user-gear"></i> Meus Dados
                        </div>
                        <div class="dropdown-item" onclick="loadContent('system/settings')">
                            <i class="fa-solid fa-key"></i> Segurança
                        </div>
                        <a href="../../registration/login/logout.php" class="dropdown-item" style="color: #ff4d4d; border-top: 1px solid rgba(255,255,255,0.05); text-decoration: none;">
                            <i class="fa-solid fa-power-off"></i> Encerrar Sessão
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="main-wrapper-content" id="content-area">
            <!-- ============================================ -->
            <!-- ADICIONAR NO DASHBOARD_ADMIN.PHP -->
            <!-- Logo após <div class="main-wrapper-content" id="content-area"> -->
            <!-- ============================================ -->

            <?php
            // Verifica se há aviso de senha expirada
            if (isset($_SESSION['password_expired']) && $_SESSION['password_expired'] === true) {
                $expiredSince = $_SESSION['password_expired_since'] ?? time();
                $expiredTime = $_SESSION['password_expired_time'] ?? 'desconhecido';
                $isSuperAdmin = ($adminRole === 'superadmin');
            ?>

            <style>
            .password-warning-banner {
                position: fixed;
                top: 70px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 9999;
                max-width: 800px;
                width: 90%;
                animation: slideDown 0.5s ease, shake 0.5s ease 0.5s;
            }

            @keyframes slideDown {
                from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
                to { opacity: 1; transform: translateX(-50%) translateY(0); }
            }

            @keyframes shake {
                0%, 100% { transform: translateX(-50%) translateY(0); }
                10%, 30%, 50%, 70%, 90% { transform: translateX(-50%) translateY(-5px); }
                20%, 40%, 60%, 80% { transform: translateX(-50%) translateY(5px); }
            }

            .banner-container {
                background: linear-gradient(135deg, #ff4d4d 0%, #ff1a1a 100%);
                border: 2px solid rgba(255,255,255,0.2);
                border-radius: 15px;
                padding: 20px 25px;
                box-shadow: 0 10px 40px rgba(255,77,77,0.4);
                display: flex;
                align-items: center;
                gap: 20px;
                position: relative;
                overflow: hidden;
            }

            .banner-container::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                animation: shimmer 3s infinite;
            }

            @keyframes shimmer {
                0% { left: -100%; }
                100% { left: 100%; }
            }

            .banner-icon {
                font-size: 2.5rem;
                color: #fff;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0%, 100% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.1); opacity: 0.8; }
            }

            .banner-content {
                flex: 1;
            }

            .banner-title {
                color: #fff;
                font-size: 1.2rem;
                font-weight: 900;
                margin: 0 0 8px 0;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .banner-message {
                color: rgba(255,255,255,0.95);
                font-size: 0.9rem;
                margin: 0 0 12px 0;
                line-height: 1.5;
            }

            .banner-details {
                display: flex;
                gap: 20px;
                font-size: 0.85rem;
                color: rgba(255,255,255,0.8);
            }

            .banner-detail-item {
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .banner-actions {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .btn-renew-now {
                background: #fff;
                color: #ff4d4d;
                border: none;
                padding: 12px 24px;
                border-radius: 10px;
                font-weight: 900;
                font-size: 0.9rem;
                cursor: pointer;
                transition: all 0.3s ease;
                white-space: nowrap;
                display: flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                justify-content: center;
            }

            .btn-renew-now:hover {
                background: #ffff00;
                transform: scale(1.05);
                box-shadow: 0 5px 20px rgba(255,255,255,0.3);
            }

            .btn-dismiss {
                background: transparent;
                color: rgba(255,255,255,0.8);
                border: 1px solid rgba(255,255,255,0.3);
                padding: 8px 16px;
                border-radius: 8px;
                font-size: 0.75rem;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .btn-dismiss:hover {
                background: rgba(255,255,255,0.1);
                color: #fff;
            }

            /* Responsivo */
            @media (max-width: 768px) {
                .password-warning-banner {
                    top: 60px;
                    width: 95%;
                }
                
                .banner-container {
                    flex-direction: column;
                    padding: 20px;
                }
                
                .banner-details {
                    flex-direction: column;
                    gap: 8px;
                }
                
                .banner-actions {
                    width: 100%;
                }
                
                .btn-renew-now {
                    width: 100%;
                }
            }

            @keyframes slideUp {
                from { opacity: 1; transform: translateX(-50%) translateY(0); }
                to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            }
            </style>

            <div class="password-warning-banner" id="passwordWarningBanner">
                <div class="banner-container">
                    <div class="banner-icon">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    
                    <div class="banner-content">
                        <h3 class="banner-title">
                            ⚠️ Senha Expirada - Ação Necessária
                        </h3>
                        <p class="banner-message">
                            Sua senha de administrador expirou há <strong><?= $expiredTime ?></strong>. 
                            Por motivos de segurança, renove imediatamente para manter o acesso.
                        </p>
                        <div class="banner-details">
                            <div class="banner-detail-item">
                                <i class="fa-solid fa-clock"></i>
                                <span>Expirou: <?= date('d/m/Y H:i', $expiredSince) ?></span>
                            </div>
                            <div class="banner-detail-item">
                                <i class="fa-solid fa-shield"></i>
                                <span><?= $isSuperAdmin ? 'Limite: 1 hora' : 'Limite: 24 horas' ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="banner-actions">
                        <button class="btn-renew-now" onclick="rotatePasswordManually()">
                            <i class="fa-solid fa-rotate"></i>
                            RENOVAR AGORA
                        </button>
                        <button class="btn-dismiss" onclick="dismissWarning()">
                            Lembrar depois
                        </button>
                    </div>
                </div>
            </div>

            <script>
            // Função para dispensar o aviso temporariamente
            function dismissWarning() {
                const banner = document.getElementById('passwordWarningBanner');
                if (banner) {
                    banner.style.animation = 'slideUp 0.3s ease';
                    setTimeout(() => {
                        banner.style.display = 'none';
                    }, 300);
                    
                    // Salva no sessionStorage para não aparecer até recarregar
                    sessionStorage.setItem('warningDismissed', 'true');
                }
            }
            
            // Verifica se foi dispensado antes
            if (sessionStorage.getItem('warningDismissed') === 'true') {
                const banner = document.getElementById('passwordWarningBanner');
                if (banner) banner.style.display = 'none';
            }

            // Remove o item ao renovar a senha
            window.addEventListener('beforeunload', function() {
                if (document.querySelector('.password-modal')) {
                    sessionStorage.removeItem('warningDismissed');
                }
            });
            </script>

            <?php
            }
            ?>

        </div>
        
    </div>

<script>
    let sec = <?= (int)$remainingSeconds ?>;

    function toggleSidebar() {
        document.getElementById('masterBody').classList.toggle('sidebar-is-collapsed');
    }

    function toggleTheme() {
        document.getElementById('masterBody').classList.toggle('theme-ocean');
    }

    const timerInterval = setInterval(() => {
        if (sec <= 0) {
            clearInterval(timerInterval);
            window.location.href = "../../registration/login/logout.php?info=timeout";
        }
        
        sec--;
        
        let h = Math.floor(sec / 3600);
        let m = Math.floor((sec % 3600) / 60);
        let s = sec % 60;
        
        const timerEl = document.getElementById('timer');
        if(timerEl) {
            timerEl.textContent = 
                (h > 0 ? h + ':' : '') + 
                (m < 10 ? '0' + m : m) + ':' + 
                (s < 10 ? '0' + s : s);
        }
    }, 1000);

    async function checkServerStatus() {
        const indicator = document.getElementById('status-indicator');
        const text = document.getElementById('status-text');
        
        try {
            const response = await fetch(window.location.href, { method: 'HEAD', cache: 'no-cache' });
            if (response.ok) {
                indicator.classList.remove('is-offline');
                text.innerText = "LIVE";
            } else {
                throw new Error();
            }
        } catch (e) {
            indicator.classList.add('is-offline');
            text.innerText = "OFFLINE";
        }
    }

    setInterval(checkServerStatus, 30000);
    checkServerStatus();

    let contentCache = new Map();
    let isLoading = false;

    async function loadContent(pageName, element = null) {
        if (isLoading) return;
        
        const contentArea = document.getElementById('content-area');
        if (!contentArea) return;

        isLoading = true;
        
        const parts = pageName.split('?');
        const cleanName = parts[0].replace(/\.(php|html)$/, "");
        const queryString = parts[1] ? '?' + parts[1] : "";
        const fullUrl = cleanName + queryString;

        if (!element) {
            element = document.querySelector(`[onclick*="'${cleanName}'"]`);
        }

        try {
            let html;
            
            if (contentCache.has(fullUrl)) {
                html = contentCache.get(fullUrl);
            } else {
                let response = await fetch(cleanName + '.php' + queryString, { cache: 'no-cache' });
                if (!response.ok) {
                    response = await fetch(cleanName + '.html' + queryString, { cache: 'no-cache' });
                }

                if (!response.ok) throw new Error('Não foi possível localizar o arquivo "' + cleanName + '"');
                
                html = await response.text();
                
                if (contentCache.size > 20) {
                    const firstKey = contentCache.keys().next().value;
                    contentCache.delete(firstKey);
                }
                contentCache.set(fullUrl, html);
            }
            
            contentArea.innerHTML = html;

            contentArea.querySelectorAll('script').forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                document.body.appendChild(newScript);
                document.body.removeChild(newScript); 
            });

            if(element && element.classList) {
                document.querySelectorAll('.nav-item, .sub-item').forEach(btn => {
                    if(btn && btn.classList) btn.classList.remove('active');
                });
                
                element.classList.add('active');
                
                const labelSpan = element.querySelector('span');
                if(labelSpan) document.getElementById('current-page-title').innerText = labelSpan.innerText;
                
                const parentGroup = element.closest('.nav-group');
                if(parentGroup) {
                    const navLabel = parentGroup.previousElementSibling;
                    if(navLabel && navLabel.classList.contains('nav-label')) {
                        document.getElementById('parent-name').innerText = navLabel.innerText;
                    }

                    const submenu = parentGroup.querySelector('.nav-submenu');
                    const navItem = parentGroup.querySelector('.nav-item');
                    if (submenu) {
                        submenu.style.display = 'block';
                        if (navItem) navItem.classList.add('menu-open');
                    }
                } else {
                    const standaloneLabel = element.previousElementSibling;
                    if(standaloneLabel && standaloneLabel.classList.contains('nav-label')) {
                        document.getElementById('parent-name').innerText = standaloneLabel.innerText;
                    }
                }
            }

            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=' + cleanName + queryString;
            window.history.pushState({ path: newUrl }, '', newUrl);

        } catch (error) {
            contentArea.innerHTML = `
                <div style="padding:20px; color:#ff4d4d; background: rgba(255,0,0,0.05); border-radius: 8px; border: 1px solid rgba(255,0,0,0.1);">
                    <strong>Erro de Carregamento:</strong> ${error.message}
                </div>`;
        } finally {
            isLoading = false;
        }
    }

    // ==================== SISTEMA DE NOTIFICAÇÕES EM TEMPO REAL ====================

    function updateNotificationsRealTime() {
        fetch('?action=get_counters', { 
            cache: 'no-cache',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => {
            if (!res.ok) throw new Error('Erro na requisição');
            return res.json();
        })
        .then(data => {
            // Atualiza contador da sidebar
            const sidebarCount = document.getElementById('sidebar-msg-count');
            if(data.unread_total > 0) {
                if(sidebarCount) { 
                    sidebarCount.innerText = data.unread_total; 
                    sidebarCount.style.display = 'flex'; 
                } else {
                    // Criar badge se não existir
                    createSidebarBadge(data.unread_total);
                }
            } else if(sidebarCount) { 
                sidebarCount.style.display = 'none'; 
            }

            // Atualiza badges do header
            const chatBadge = document.getElementById('chat-badge-dot');
            const alertBadge = document.getElementById('alerts-badge-dot');
            
            if(chatBadge) chatBadge.style.display = (data.unread_chat > 0) ? 'block' : 'none';
            if(alertBadge) alertBadge.style.display = (data.unread_alerts > 0) ? 'block' : 'none';
            
            // Atualiza dropdowns se houver notificações
            if(data.unread_total > 0) {
                updateNotificationDropdowns();
            }
        })
        .catch(err => {
            console.error('Erro ao atualizar notificações:', err);
        });
    }

    function createSidebarBadge(count) {
        const mensagensLink = document.querySelector('[onclick*="mensagens/mensagens"]');
        if (!mensagensLink) return;
        
        const badge = document.createElement('span');
        badge.id = 'sidebar-msg-count';
        badge.className = 'nav-count';
        badge.style.cssText = `
            background: #ff4d4d; 
            color: white; 
            padding: 2px 6px; 
            border-radius: 50%; 
            font-size: 0.7rem; 
            font-weight: bold; 
            margin-left: auto;
            min-width: 15px;
            text-align: center;
        `;
        badge.innerText = count;
        mensagensLink.appendChild(badge);
    }

    async function updateNotificationDropdowns() {
        try {
            const response = await fetch('modules/get_notifications.php', { 
                cache: 'no-cache',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            updateMessagesDropdown(data.messages);
            updateAlertsDropdown(data.alerts);
            
        } catch (err) {
            console.error('Erro ao atualizar dropdowns:', err);
        }
    }

    function updateMessagesDropdown(messages) {
        // Busca o primeiro dropdown (mensagens)
        const allDropdowns = document.querySelectorAll('.header-action-wrapper .header-dropdown');
        if (allDropdowns.length === 0) return;
        
        const dropdown = allDropdowns[0];
        const dropdownFooter = dropdown.querySelector('.dropdown-footer');
        if (!dropdownFooter) return;
        
        // Remove itens antigos
        const items = dropdown.querySelectorAll('.dropdown-item');
        items.forEach(item => item.remove());
        
        if (messages && messages.length > 0) {
            messages.forEach(msg => {
                const priorityColors = {
                    'critical': '#ff4d4d',
                    'high': '#ff9500',
                    'medium': '#4da3ff',
                    'low': '#00ff88'
                };
                const priorityColor = priorityColors[msg.priority] || '#00ff88';
                
                const itemHTML = `
                    <div class="dropdown-item" onclick="openNotification(${msg.id}, 'message')" style="border-left: 3px solid ${priorityColor};">
                        <i class="fa-solid fa-circle-user"></i>
                        <div>
                            <strong>${escapeHtml(msg.sender_name)}</strong><br>
                            <small>${escapeHtml(msg.subject)}</small>
                        </div>
                    </div>
                `;
                dropdownFooter.insertAdjacentHTML('beforebegin', itemHTML);
            });
        } else {
            const emptyHTML = '<div class="dropdown-item"><small>Nenhuma mensagem nova</small></div>';
            dropdownFooter.insertAdjacentHTML('beforebegin', emptyHTML);
        }
    }

    function updateAlertsDropdown(alerts) {
        // Busca o segundo dropdown (alertas)
        const allDropdowns = document.querySelectorAll('.header-action-wrapper .header-dropdown');
        if (allDropdowns.length < 2) return;
        
        const dropdown = allDropdowns[1];
        const dropdownFooter = dropdown.querySelector('.dropdown-footer');
        if (!dropdownFooter) return;
        
        // Remove itens antigos
        const items = dropdown.querySelectorAll('.dropdown-item');
        items.forEach(item => item.remove());
        
        if (alerts && alerts.length > 0) {
            alerts.forEach(alert => {
                const config = {
                    'security': { icon: 'fa-shield-halved', color: '#ff4d4d' },
                    'alert': { icon: 'fa-circle-info', color: '#4da3ff' },
                    'system_error': { icon: 'fa-triangle-exclamation', color: '#ff9500' },
                    'audit': { icon: 'fa-file-signature', color: '#00ff88' }
                };
                const current = config[alert.category] || config['alert'];
                
                const priorityColors = {
                    'critical': '#ff4d4d',
                    'high': '#ff9500',
                    'medium': '#4da3ff',
                    'low': '#00ff88'
                };
                const borderColor = priorityColors[alert.priority] || '#4da3ff';
                
                const itemHTML = `
                    <div class="dropdown-item" onclick="openNotification(${alert.id}, 'alert')" style="border-left: 3px solid ${borderColor};">
                        <i class="fa-solid ${current.icon}" style="color: ${current.color};"></i>
                        <div>
                            <strong>${escapeHtml(alert.subject)}</strong><br>
                            <small>${alert.created_at}</small>
                        </div>
                    </div>
                `;
                dropdownFooter.insertAdjacentHTML('beforebegin', itemHTML);
            });
        } else {
            const emptyHTML = '<div class="dropdown-item"><small>Sem alertas novos</small></div>';
            dropdownFooter.insertAdjacentHTML('beforebegin', emptyHTML);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Atualiza a cada 5 segundos (mais seguro que 3s)
    setInterval(updateNotificationsRealTime, 5000);

    // Executa imediatamente ao carregar
    updateNotificationsRealTime();

    window.onload = () => {
        const urlParams = new URLSearchParams(window.location.search);
        const savedPage = urlParams.get('page');
        const idParam = urlParams.get('id');
        
        // Se NÃO houver página na URL, carrega o dashboard
        // Se houver, carrega a página especificada
        if (!savedPage) {
            loadContent('modules/dashboard/dashboard');
        } else {
            const finalPage = idParam ? `${savedPage}?id=${idParam}` : savedPage;
            loadContent(finalPage);
        }
    };

    window.onpopstate = () => {
        const urlParams = new URLSearchParams(window.location.search);
        const savedPage = urlParams.get('page');
        const idParam = urlParams.get('id');
        const finalPage = idParam ? `${savedPage}?id=${idParam}` : (savedPage || 'modules/dashboard/dashboard');
        loadContent(finalPage);
    };

    let searchTimer;

    document.getElementById('mainSearchInput').addEventListener('input', function(e) {
        const query = e.target.value;
        clearTimeout(searchTimer);

        if (query.length === 0) {
            loadContent('modules/dashboard/dashboard');
            return;
        }

        searchTimer = setTimeout(() => {
            const searchUrl = 'modules/search_engine.php?q=' + encodeURIComponent(query);
            fetchAndRenderSearch(searchUrl);
            window.history.pushState({ path: query }, '', '?page=modules/search_engine&q=' + query);
        }, 300);
    });

    async function fetchAndRenderSearch(url) {
        const contentArea = document.getElementById('content-area');
        
        try {
            const response = await fetch(url);
            const html = await response.text();
            
            contentArea.innerHTML = html;
            
            document.querySelectorAll('.nav-item, .sub-item').forEach(btn => {
                if(btn && btn.classList) btn.classList.remove('active');
            });
        } catch (err) {
            console.error("Erro na busca automática:", err);
        }
    }

    async function openNotification(msgId, category = 'message') {
        if (category === 'message') {
            const chatBadge = document.getElementById('chat-badge-dot');
            if (chatBadge) chatBadge.style.display = 'none';

            const sidebarCount = document.getElementById('sidebar-msg-count');
            if (sidebarCount) {
                let currentCount = parseInt(sidebarCount.innerText);
                if (currentCount > 1) {
                    sidebarCount.innerText = currentCount - 1;
                } else {
                    sidebarCount.style.display = 'none';
                }
            }
        } else {
            const bellBadge = document.getElementById('alerts-badge-dot');
            if (bellBadge) bellBadge.style.display = 'none';
        }

        loadContent(`modules/mensagens/mensagens?id=${msgId}`);

        document.querySelectorAll('.header-dropdown').forEach(el => {
            el.style.display = 'none';
            setTimeout(() => el.style.display = '', 500);
        });
    }

    async function clearAllAlerts() {
        const alertBadge = document.getElementById('alerts-badge-dot');
        if(alertBadge) alertBadge.style.display = 'none';

        try {
            await fetch('modules/mensagens/mensagens.php?action=clear_alerts');
            loadContent('modules/mensagens/mensagens');
        } catch (err) {
            console.error("Erro ao limpar alertas");
        }
    }
</script>

<script>
    // ========== SISTEMA DE ROTAÇÃO MANUAL DE SENHA ==========
    async function rotatePasswordManually() {
        if (!confirm('🔐 Deseja gerar uma nova senha agora?\n\nIsso irá invalidar sua senha atual imediatamente.')) {
            return;
        }
        
        const btn = document.querySelector('.rotate-password-btn');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        btn.disabled = true;
        
        try {
            const response = await fetch('system/passwords/generate_next_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            const data = await response.json();
            
            if (data.success) {
                showPasswordModal(data);
                sec = <?= $isSuperAdmin ? 3600 : 86400 ?>; // Reseta timer
            } else {
                alert('❌ Erro: ' + data.message);
            }
        } catch (error) {
            alert('❌ Erro ao conectar com o servidor.');
        } finally {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    }

    function showPasswordModal(data) {
        const modal = document.createElement('div');
        modal.id = 'passwordModal';
        modal.className = 'password-modal';
        
        modal.innerHTML = `
            <div class="password-modal-content">
                <div style="text-align: center; margin-bottom: 30px;">
                    <i class="fa-solid fa-shield-halved" style="font-size: 3rem; color: var(--accent-green);"></i>
                    <h2 style="color: #fff; margin: 15px 0 5px;">🔐 Nova Senha Gerada</h2>
                    <p style="color: #888;">${data.role.toUpperCase()}</p>
                </div>
                
                <div style="background: #000; border: 2px solid var(--accent-green); border-radius: 12px; padding: 20px; text-align: center;">
                    <div style="color: var(--accent-green); font-size: 0.8rem; font-weight: 600; margin-bottom: 10px;">SUA NOVA SENHA</div>
                    <div class="password-text" id="generatedPassword">${data.new_password}</div>
                    <div style="color: #666; font-size: 0.75rem; margin-top: 10px;">Copie e guarde em local seguro</div>
                </div>
                
                <div style="background: rgba(255,204,0,0.1); border-left: 3px solid #ffcc00; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <p style="color: #aaa; font-size: 0.85rem; margin: 5px 0;">
                        <strong style="color: #ffcc00;">⏰ Validade:</strong> ${data.expires_in}<br>
                        <strong style="color: #ffcc00;">📧 Email:</strong> ${data.email_address}
                    </p>
                </div>
                
                ${data.email_sent ? 
                    '<div style="text-align: center; background: rgba(0,255,136,0.1); color: var(--accent-green); padding: 10px; border-radius: 8px; font-size: 0.85rem;"><i class="fa-solid fa-check-circle"></i> Email enviado com sucesso!</div>' :
                    '<div style="text-align: center; background: rgba(255,77,77,0.1); color: #ff4d4d; padding: 10px; border-radius: 8px; font-size: 0.85rem;"><i class="fa-solid fa-exclamation-triangle"></i> Erro ao enviar email. Copie a senha!</div>'
                }
                
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button onclick="copyPassword()" style="flex: 1; padding: 12px; background: var(--accent-green); color: #000; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">
                        <i class="fa-solid fa-copy"></i> Copiar Senha
                    </button>
                    <button onclick="closePasswordModal()" style="flex: 1; padding: 12px; background: rgba(255,255,255,0.05); color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">
                        <i class="fa-solid fa-times"></i> Fechar
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        setTimeout(() => modal.classList.add('show'), 10);
    }

    function copyPassword() {
        const passwordText = document.getElementById('generatedPassword').textContent;
        navigator.clipboard.writeText(passwordText).then(() => {
            const btn = event.target.closest('button');
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
            setTimeout(() => btn.innerHTML = original, 2000);
        });
    }

    function closePasswordModal() {
        const modal = document.getElementById('passwordModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        }
    }
</script>

<script>
// DEBUG: Verificar carregamento
console.log('=== DEBUG SYSTEM ===');
console.log('CSS carregado:', !!document.querySelector('[href*="dashboard-components"]'));
console.log('Chart.js:', typeof Chart !== 'undefined');
console.log('Load function:', typeof loadContent !== 'undefined');
console.log('====================');

// Interceptar loadContent
const originalLoad = window.loadContent;
window.loadContent = function(path, element) {
    console.log('🔍 Loading:', path);
    return originalLoad(path, element);
};
</script>
</body>
</html>
