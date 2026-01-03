<?php
define('IS_ADMIN_PAGE', true); 
define('REQUIRED_TYPE', 'admin'); 

session_start();
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

/* ================= 1. SEGURANÇA E ROLE CHECK ================= */
if (!isset($_SESSION['auth']['role']) || !in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    header("Location: ../../registration/login/login.php?error=nao_e_admin");
    exit;
}

$adminId = $_SESSION['auth']['user_id'];
$ip_address = $_SERVER['REMOTE_ADDR'];
$currentTab = $_GET['tab'] ?? 'pendentes';

/* ================= 2. CÁLCULO REAL DO TEMPO RESTANTE ================= */
$stmt_time = $mysqli->prepare("SELECT password_changed_at FROM users WHERE id = ?");
$stmt_time->bind_param("i", $adminId);
$stmt_time->execute();
$admin_data = $stmt_time->get_result()->fetch_assoc();

$lastChange = strtotime($admin_data['password_changed_at']);
$now = time(); 
$elapsed = $now - $lastChange;
$remainingSeconds = 3600 - $elapsed;

if ($remainingSeconds < 0) $remainingSeconds = 0;

/* ================= 3. PROCESSAR APROVAÇÃO/REJEIÇÃO ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (isset($_POST['csrf']) && csrf_validate($_POST['csrf'])) {
        $targetUserId = (int)$_POST['user_id'];
        $status = $_POST['action'] === 'approve' ? 'aprovado' : 'rejeitado';
        $motivo = $_POST['motivo'] ?? NULL;

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare("UPDATE businesses SET status_documentos = ?, motivo_rejeicao = ? WHERE user_id = ?");
            $stmt->bind_param('ssi', $status, $motivo, $targetUserId);
            $stmt->execute();

            $logAction = "DOC_" . strtoupper($status);
            $stmt_log = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES (?, ?, ?)");
            $stmt_log->bind_param('iss', $adminId, $logAction, $ip_address);
            $stmt_log->execute();

            $mysqli->commit();
        } catch (Exception $e) {
            $mysqli->rollback();
        }
    }
}

$totalPendentes = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'pendente'")->fetch_assoc()['total'] ?? 0;
$totalEmpresas = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'aprovado'")->fetch_assoc()['total'] ?? 0;
$uploadBase = "../../registration/uploads/business/";
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>VisionGreen - Command Center</title>
    <style>
        :root {
            --vg-green: #00a63e;
            --vg-neon: #00ff41;
            --vg-dark: #0a0f1a;
            --vg-card: #111b2d;
            --vg-border: #1e293b;
        }
        body { font-family: 'Segoe UI', sans-serif; background: var(--vg-dark); margin: 0; display: flex; color: #e2e8f0; overflow-x: hidden; }
        .sidebar { width: 260px; background: #050810; color: white; min-height: 100vh; padding: 25px; position: fixed; border-right: 1px solid var(--vg-green); z-index: 100; }
        .sidebar h2 { color: var(--vg-green); font-family: monospace; letter-spacing: 2px; border-bottom: 2px solid var(--vg-green); padding-bottom: 10px; }
        .nav-link { display: block; padding: 12px; color: #94a3b8; text-decoration: none; border-radius: 4px; margin-bottom: 5px; transition: 0.3s; }
        .nav-link.active { background: rgba(0, 166, 62, 0.2); color: var(--vg-neon); border-left: 4px solid var(--vg-green); }
        .main-content { flex: 1; margin-left: 310px; padding: 30px; }
        .security-bar { background: var(--vg-card); padding: 15px 25px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--vg-border); margin-bottom: 30px; }
        #countdown-circle { font-size: 1.2rem; font-weight: bold; color: var(--vg-neon); font-family: monospace; background: #050810; padding: 8px 15px; border-radius: 5px; border: 1px solid var(--vg-green); }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--vg-card); padding: 20px; border-radius: 10px; border-left: 4px solid var(--vg-green); }
        .stat-card small { color: #64748b; text-transform: uppercase; font-size: 0.7rem; }
        .stat-card div { font-size: 1.8rem; font-weight: bold; }
        .table-container { background: var(--vg-card); border-radius: 8px; overflow: hidden; border: 1px solid var(--vg-border); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0f172a; padding: 15px; text-align: left; color: var(--vg-green); font-size: 0.8rem; }
        td { padding: 15px; border-top: 1px solid var(--vg-border); }
        .btn { padding: 8px 15px; border-radius: 4px; border: none; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn-green { background: var(--vg-green); color: black; }
        .btn-outline { background: transparent; border: 1px solid #475569; color: #94a3b8; }
        
        /* Modal Overlay Rejeição */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1000; }
        .modal { background: var(--vg-card); width: 400px; padding: 30px; border-radius: 10px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); border: 1px solid #ff3232; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>VISION GREEN</h2>
    <nav>
        <a href="?tab=pendentes" class="nav-link <?= $currentTab == 'pendentes' ? 'active' : '' ?>">● PENDENTES</a>
        <a href="?tab=usuarios" class="nav-link <?= $currentTab == 'usuarios' ? 'active' : '' ?>">● USUÁRIOS</a>
        <a href="?tab=logs" class="nav-link <?= $currentTab == 'logs' ? 'active' : '' ?>">● AUDITORIA</a>
        <div style="margin-top: 50px;">
            <a href="../../registration/login/logout.php" class="nav-link" style="color: #ff3232;">× SAIR DO SISTEMA</a>
        </div>
    </nav>
</div>

<div class="main-content">
    <div class="security-bar">
        <div>
            <span style="color: var(--vg-green);">🛡️ PROTOCOLO ATIVO:</span> 
            <small style="margin-left: 10px; opacity: 0.6;">Sessão administrativa rotativa</small>
        </div>
        <div class="timer-display">
            <span style="font-size: 0.8rem; opacity: 0.7;">EXPIRAÇÃO EM:</span>
            <div id="countdown-circle">00:00</div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <small>Pendentes</small>
            <div style="color: #fbbf24;"><?= $totalPendentes ?></div>
        </div>
        <div class="stat-card">
            <small>Empresas Verificadas</small>
            <div><?= $totalEmpresas ?></div>
        </div>
        <div class="stat-card">
            <small>IP de Acesso</small>
            <div style="font-size: 1rem; color: var(--vg-green);"><?= $ip_address ?></div>
        </div>
    </div>

    <?php if($currentTab == 'pendentes'): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>EMPRESA</th>
                        <th>NUIT / TAX ID</th>
                        <th>DOCUMENTO</th>
                        <th>AÇÕES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $pendentes = $mysqli->query("SELECT u.id as user_id, u.nome, b.tax_id, b.license_path FROM users u INNER JOIN businesses b ON u.id = b.user_id WHERE b.status_documentos = 'pendente'");
                    while ($row = $pendentes->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['nome']) ?></strong></td>
                        <td><code><?= htmlspecialchars($row['tax_id']) ?></code></td>
                        <td><a href="<?= $uploadBase . $row['license_path'] ?>" target="_blank" class="btn btn-outline" style="font-size: 11px;">VER ARQUIVO</a></td>
                        <td>
                            <button onclick="openReject(<?= $row['user_id'] ?>)" class="btn btn-outline" style="color: #ff3232;">REJEITAR</button>
                            <form method="POST" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-green">APROVAR</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="rejectOverlay">
    <div class="modal">
        <h3 style="color: #ff3232;">REJEITAR ACESSO</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" id="reject_user_id">
            <input type="hidden" name="action" value="reject">
            <textarea name="motivo" rows="4" style="width: 100%; background: #050810; color: white; border: 1px solid #444; padding: 10px; margin-bottom: 15px;" required placeholder="Motivo..."></textarea>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn" style="background: #ff3232; color: white; flex: 1;">REJEITAR</button>
                <button type="button" onclick="closeReject()" class="btn btn-outline" style="flex: 1;">VOLTAR</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openReject(id) {
        document.getElementById('reject_user_id').value = id;
        document.getElementById('rejectOverlay').style.display = 'block';
    }
    function closeReject() {
        document.getElementById('rejectOverlay').style.display = 'none';
    }

    /* ================= TIMER DE ROTAÇÃO E SEGURANÇA ================= */
    let timeLeft = <?= $remainingSeconds ?>; 
    let warningShown = false;

    const timerInterval = setInterval(function() {
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            window.location.href = "../../registration/login/logout.php?reason=expired";
            return;
        }

        timeLeft--;
        
        let minutes = Math.floor(timeLeft / 60);
        let seconds = timeLeft % 60;
        document.getElementById('countdown-circle').textContent = 
            (minutes < 10 ? "0" + minutes : minutes) + ":" + (seconds < 10 ? "0" + seconds : seconds);

        // Notificação Crítica aos 60 segundos
        if (timeLeft <= 60 && !warningShown) {
            warningShown = true;
            triggerSecurityRotation();
        }
    }, 1000);

    function triggerSecurityRotation() {
        // Busca a nova senha via AJAX
        fetch('generate_next_password.php')
            .then(response => response.json())
            .then(data => {
                const newPass = data.success ? data.new_password : "ERRO_GERACAO";
                showSecurityOverlay(newPass);
            });
    }

    function showSecurityOverlay(newPass) {
        const overlay = document.createElement('div');
        overlay.style = "position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.98); z-index:10000; display:flex; align-items:center; justify-content:center; flex-direction:column; text-align:center; font-family:monospace;";
        overlay.innerHTML = `
            <div style="border: 2px solid #ff3232; padding: 40px; border-radius: 10px; background: #050810; color: #ff3232; max-width: 500px;">
                <h1 style="color:#ff3232; margin-top:0;">⚠️ SESSÃO EXPIRANDO</h1>
                <p style="color:white;">O PROTOCOLO DE SEGURANÇA EXIGE ROTAÇÃO DE SENHA EM <span id="lastSeconds">60</span>s.</p>
                <p style="color:#00ff41;">COPIE SUA NOVA SENHA ABAIXO:</p>
                <div style="font-size: 2.5rem; background: #111; padding: 15px; border: 1px dashed #00ff41; margin: 20px 0; color:#fff;">${newPass}</div>
                <p style="font-size:0.8rem; color:#94a3b8;">Ao clicar abaixo, você será desconectado para aplicar as novas credenciais.</p>
                <button onclick="window.location.href='../../registration/login/logout.php?reason=rotation'" 
                        style="background:#ff3232; color:white; padding:15px 30px; border:none; cursor:pointer; font-weight:bold; width:100%; border-radius:5px; font-size:1rem;">
                    ESTOU CIENTE (SAIR AGORA)
                </button>
            </div>
        `;
        document.body.appendChild(overlay);

        // Contador regressivo dentro do modal
        let modalSecs = 60;
        const modalTimer = setInterval(() => {
            modalSecs--;
            if(document.getElementById('lastSeconds')) {
                document.getElementById('lastSeconds').textContent = modalSecs;
            }
            if (modalSecs <= 0) {
                clearInterval(modalTimer);
                window.location.href = "../../registration/login/logout.php?reason=force_expired";
            }
        }, 1000);
    }
</script>
</body>
</html>