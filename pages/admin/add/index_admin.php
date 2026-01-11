<?php
define('IS_ADMIN_PAGE', true); 
define('REQUIRED_TYPE', 'admin'); 

session_start();
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';
require_once '../../registration/includes/mailer.php'; 

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

/* ================= 4. LOGICAS DE PROCESSAMENTO (POST) ================= */
$status_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate($_POST['csrf'])) {
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
    $res_count = $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = $adminId AND status = 'unread' AND category = 'chat'")->fetch_assoc();
    $res_alerts_count = $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = $adminId AND status = 'unread' AND category IN ('alert', 'security')")->fetch_assoc();
    
    echo json_encode([
        'unread_chat' => (int)$res_count['total'],
        'unread_alerts' => (int)$res_alerts_count['total'],
        'unread_total' => (int)$res_count['total'] + (int)$res_alerts_count['total']
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
</head>
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
            </div>
        </div>

        <div class="nav-menu">
            <div class="nav-label">Monitoramento</div>
            <div class="nav-group">
                <a href="javascript:void(0)" class="nav-item active" onclick="loadContent('modules/dashboard', this)">
                    <div class="nav-icon-box"><i class="fa-solid fa-gauge-high"></i></div>
                    <span>Dashboard</span>
                    <i class="fa-solid fa-chevron-down arrow-icon"></i>
                </a>
                <div class="nav-submenu">
                    <div class="tree-line"></div>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/pendencias', this)">
                        <div class="sub-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                        <span>Pendências</span> 
                        <span class="nav-count">08</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/analise', this)">
                        <div class="sub-icon"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                        <span>Análise de Contas</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/historico', this)">
                        <div class="sub-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <span>Histórico</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/plataformas', this)">
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
                <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/forms', this)">
                    <div class="nav-icon-box"><i class="fa-solid fa-rectangle-list"></i></div>
                    <span>Forms</span>
                    <i class="fa-solid fa-chevron-down arrow-icon"></i>
                </a>
                <div class="nav-submenu">
                    <div class="tree-line"></div>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/form-input', this)">
                        <div class="sub-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                        <span>Entrada de Dados</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/form-config', this)">
                        <div class="sub-icon"><i class="fa-solid fa-gears"></i></div>
                        <span>Configurações</span>
                    </a>
                </div>
            </div>

            <div class="nav-group">
                <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/tabelas', this)">
                    <div class="nav-icon-box"><i class="fa-solid fa-table-cells-large"></i></div>
                    <span>Tabelas</span>
                    <i class="fa-solid fa-chevron-down arrow-icon"></i>
                </a>
                <div class="nav-submenu">
                    <div class="tree-line"></div>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/tabela-geral', this)">
                        <div class="sub-icon"><i class="fa-solid fa-list-check"></i></div>
                        <span>Visão Geral</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/tabela-financeiro', this)">
                        <div class="sub-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                        <span>Financeiro</span>
                    </a>
                    <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/tabela-export', this)">
                        <div class="sub-icon"><i class="fa-solid fa-file-export"></i></div>
                        <span>Exportação</span>
                    </a>
                </div>
            </div>

            <div class="nav-label">Administração</div>
            <?php if ($isSuperAdmin): ?>
                <div class="nav-group">
                    <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/auditores', this)">
                        <div class="nav-icon-box"><i class="fa-solid fa-user-shield"></i></div>
                        <span>Auditores</span>
                        <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </a>
                    <div class="nav-submenu">
                        <div class="tree-line"></div>
                        <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/auditores/auditor-lista', this)">
                            <div class="sub-icon"><i class="fa-solid fa-users-viewfinder"></i></div>
                            <span>Lista de Auditores</span>
                        </a>
                        <a href="javascript:void(0)" class="sub-item" onclick="loadContent('modules/auditor-logs', this)">
                            <div class="sub-icon"><i class="fa-solid fa-file-signature"></i></div>
                            <span>Logs de Auditoria</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/usuarios', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-users-gear"></i></div>
                <span>Usuários</span>
            </a>

            <div class="nav-label">Páginas</div>
            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/autenticacao', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-shield-halved"></i></div>
                <span>Autenticação</span>
            </a>
            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/perfil', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-id-badge"></i></div>
                <span>Perfil do Admin</span>
            </a>
            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/pages', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-file-invoice"></i></div>
                <span>Pages</span>
            </a>

            <div class="nav-label">Suporte Técnico</div>
            <?php if ($isSuperAdmin): ?>
                <a href="javascript:void(0)" class="nav-item" style="color: var(--accent-green)" onclick="loadContent('modules/manual', this)">
                    <div class="nav-icon-box"><i class="fa-solid fa-book-bookmark"></i></div>
                    <span>Manual Superadmin</span>
                </a>
            <?php endif; ?>
            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('ajuda', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-circle-question"></i></div>
                <span>Ajuda</span>
            </a>
        </div>

        <div class="sidebar-footer-fixed">
            <div class="nav-label" style="padding: 10px 15px 10px;">Sistema</div>
            <a href="javascript:void(0)" class="nav-item" onclick="loadContent('modules/definicoes', this)">
                <div class="nav-icon-box"><i class="fa-solid fa-sliders"></i></div>
                <span>Definições</span>
            </a>
            <a href="#" class="nav-item logout-btn">
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
                        <?php if($has_new_msgs): ?>
                            <span class="badge-dot badge-pulse" id="chat-badge-dot" style="background: var(--accent-green);"></span>
                        <?php endif; ?>
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
                        <?php if($has_critical): ?>
                            <span class="badge-dot" id="alerts-badge-dot" style="background: #ff4d4d;"></span>
                        <?php endif; ?>
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
                        <div class="dropdown-item" onclick="loadContent('modules/perfil')">
                            <i class="fa-solid fa-user-gear"></i> Meus Dados
                        </div>
                        <div class="dropdown-item" onclick="loadContent('modules/seguranca')">
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

/**
 * SISTEMA DE NOTIFICAÇÕES EM TEMPO REAL (TIPO WHATSAPP)
 * Atualiza APENAS notificações/badges sem recarregar a página
 */
function updateNotificationsRealTime() {
    fetch('?action=get_counters', { 
        cache: 'no-cache',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        // Atualiza contador da sidebar
        const sidebarCount = document.getElementById('sidebar-msg-count');
        if(data.unread_total > 0) {
            if(sidebarCount) { 
                sidebarCount.innerText = data.unread_total; 
                sidebarCount.style.display = 'flex'; 
            }
        } else if(sidebarCount) { 
            sidebarCount.style.display = 'none'; 
        }

        // Atualiza badges do header
        const chatBadge = document.getElementById('chat-badge-dot');
        const alertBadge = document.getElementById('alerts-badge-dot');
        
        if(chatBadge) chatBadge.style.display = (data.unread_chat > 0) ? 'block' : 'none';
        if(alertBadge) alertBadge.style.display = (data.unread_alerts > 0) ? 'block' : 'none';
        
        // Se houver novas notificações, atualiza os dropdowns
        if(data.unread_total > 0) {
            updateNotificationDropdowns();
        }
    })
    .catch(() => {});
}

/**
 * ATUALIZA OS DROPDOWNS DE NOTIFICAÇÕES
 * Busca as últimas mensagens e alertas do servidor
 */
async function updateNotificationDropdowns() {
    try {
        const response = await fetch('modules/get_notifications.php', { 
            cache: 'no-cache',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        if (!response.ok) return;
        
        const data = await response.json();
        
        // Atualiza dropdown de mensagens
        updateMessagesDropdown(data.messages);
        
        // Atualiza dropdown de alertas
        updateAlertsDropdown(data.alerts);
        
    } catch (err) {
        console.log('Falha ao atualizar dropdowns');
    }
}

function updateMessagesDropdown(messages) {
    const dropdown = document.querySelector('.header-action-wrapper:nth-child(3) .header-dropdown');
    if (!dropdown) return;
    
    const dropdownFooter = dropdown.querySelector('.dropdown-footer');
    
    // Limpa conteúdo atual (mantém header e footer)
    const items = dropdown.querySelectorAll('.dropdown-item');
    items.forEach(item => item.remove());
    
    if (messages && messages.length > 0) {
        messages.forEach(msg => {
            // Define cor baseada na prioridade
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
    const dropdown = document.querySelector('.header-action-wrapper:nth-child(4) .header-dropdown');
    if (!dropdown) return;
    
    const dropdownFooter = dropdown.querySelector('.dropdown-footer');
    
    // Limpa conteúdo atual
    const items = dropdown.querySelectorAll('.dropdown-item');
    items.forEach(item => item.remove());
    
    if (alerts && alerts.length > 0) {
        alerts.forEach(alert => {
            // Mapeamento de ícones e cores por categoria
            const config = {
                'security': { icon: 'fa-shield-halved', color: '#ff4d4d' },
                'alert': { icon: 'fa-circle-info', color: '#4da3ff' },
                'system_error': { icon: 'fa-triangle-exclamation', color: '#ff9500' },
                'audit': { icon: 'fa-file-signature', color: '#00ff88' }
            };
            const current = config[alert.category] || config['alert'];
            
            // Define borda baseada na prioridade
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

// Inicia polling de notificações a cada 3 segundos
setInterval(updateNotificationsRealTime, 3000);

// Executa imediatamente ao carregar
updateNotificationsRealTime();

window.onload = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const savedPage = urlParams.get('page');
    const idParam = urlParams.get('id');
    
    const finalPage = idParam ? `${savedPage}?id=${idParam}` : (savedPage || 'modules/dashboard');
    loadContent(finalPage);
};

window.onpopstate = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const savedPage = urlParams.get('page');
    const idParam = urlParams.get('id');
    const finalPage = idParam ? `${savedPage}?id=${idParam}` : (savedPage || 'modules/dashboard');
    loadContent(finalPage);
};

let searchTimer;

document.getElementById('mainSearchInput').addEventListener('input', function(e) {
    const query = e.target.value;
    clearTimeout(searchTimer);

    if (query.length === 0) {
        loadContent('modules/dashboard');
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
</body>
</html>
