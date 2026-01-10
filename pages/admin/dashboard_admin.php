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
    header("Location: ../../registration/login/login.php?error=sessao_invalida");
    exit;
}

$adminId = $_SESSION['auth']['user_id'];
$adminRole = $_SESSION['auth']['role'];
$isSuperAdmin = ($adminRole === 'superadmin');
$ip_address = $_SERVER['REMOTE_ADDR'];
$currentTab = $_GET['tab'] ?? 'resumo';

/* ================= 2. CONTROLE DE SESSÃO (1H vs 24H) ================= */
$stmt_time = $mysqli->prepare("SELECT password_changed_at FROM users WHERE id = ?");
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

/* ================= 3. LOGICAS DE PROCESSAMENTO (POST) ================= */
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
                if ($manualTaxId) $updateSql .= ", tax_id = '$manualTaxId'";
                $updateSql .= " WHERE user_id = $targetUserId";
                $mysqli->query($updateSql);
            } else {
                $statusFinal = 'rejeitado';
                $motivo = "[ALVARÁ]: {$_POST['motivo_alvara']} | [TAX]: {$_POST['motivo_tax']}";
                $stmt = $mysqli->prepare("UPDATE businesses SET status_documentos = 'rejeitado', motivo_rejeicao = ? WHERE user_id = ?");
                $stmt->bind_param('si', $motivo, $targetUserId);
                $stmt->execute();
            }
            $stmtLog = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES (?, ?, ?)");
            $actStr = "AUDIT_$statusFinal";
            $stmtLog->bind_param("iss", $adminId, $actStr, $ip_address);
            $stmtLog->execute();
            $mysqli->commit();
            $status_msg = "Auditoria finalizada com sucesso.";
        } catch (Exception $e) { $mysqli->rollback(); }
    }
}

$uploadBase = "../../registration/uploads/business/";
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionGreen OS | <?= strtoupper($adminRole) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style/dashboard_admin.css">
    <link rel="stylesheet" href="../../assets/style/geral.css">
</head>
<body>

    <aside class="sidebar">
        <div class="logo-area">
            <h2>VISION <span style="color:#fff">GREEN</span></h2>
            <small style="color:var(--text-dim); font-size: 0.6rem;">CORE ADMINISTRATION</small>
        </div>

        <nav class="nav-group">
            <a href="?tab=resumo" class="nav-link <?= $currentTab == 'resumo' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i> Painel Geral
            </a>
            <a href="?tab=pendentes" class="nav-link <?= $currentTab == 'pendentes' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock"></i> Pendências de Auditoria
            </a>
            <a href="?tab=historico" class="nav-link <?= $currentTab == 'historico' ? 'active' : '' ?>">
                <i class="fa-solid fa-list-check"></i> Registro de Histórico
            </a>

            <?php if($isSuperAdmin): ?>
            <div style="margin-top: 30px; padding: 0 15px; color: var(--accent); font-size: 0.65rem; font-weight: bold;">SUPERVISÃO MASTER</div>
            <a href="?tab=equipe" class="nav-link <?= $currentTab == 'equipe' ? 'active' : '' ?>">
                <i class="fa-solid fa-users-gear"></i> Gestão de Auditores
            </a>
            <button onclick="loadAdminForm(this)" class="nav-link btn-special">
                <i class="fa-solid fa-user-plus"></i> Novo Auditor
            </button>
            <?php endif; ?>
        </nav>

        <a href="../../registration/login/logout.php" class="btn btn-outline-danger" style="justify-content: center; margin-top: 20px;">
            <i class="fa-solid fa-power-off"></i> Encerrar Sessão
        </a>
    </aside>

    <main class="viewport">
        <header class="top-bar">
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 0.8rem; color: var(--text-dim);">MODO:</span>
                <span class="btn" style="background: rgba(0, 166, 62, 0.1); color: var(--accent-neon); pointer-events: none; border: 1px solid var(--accent); padding: 4px 12px; font-size: 0.7rem;">
                    <?= strtoupper($adminRole) ?>
                </span>
            </div>
            <div class="session-timer" id="timer">--:--</div>
        </header>

        <div class="content-scroll" id="mainContent">
            
            <?php if($status_msg): ?>
                <div style="background: rgba(0, 166, 62, 0.1); border: 1px solid var(--accent); color: var(--accent-neon); padding: 15px; border-radius: 8px; margin-bottom: 25px;">
                    <i class="fa-solid fa-circle-check"></i> <?= $status_msg ?>
                </div>
            <?php endif; ?>

            <?php if($currentTab == 'resumo'): ?>
                <div class="stats-grid">
                    <?php 
                        $p = $mysqli->query("SELECT COUNT(*) as c FROM businesses WHERE status_documentos = 'pendente'")->fetch_assoc()['c'];
                        $a = $mysqli->query("SELECT COUNT(*) as c FROM businesses WHERE status_documentos = 'aprovado'")->fetch_assoc()['c'];
                    ?>
                    <div class="stat-card"><small>Aguardando Auditoria</small><div><?= $p ?></div></div>
                    <div class="stat-card"><small>Empresas Aprovadas</small><div><?= $a ?></div></div>
                </div>
            <?php endif; ?>

            <?php if($currentTab == 'equipe' && $isSuperAdmin): ?>
                <h3 style="color:var(--accent-neon); margin-bottom: 20px;">Equipe de Auditoria</h3>

                <div class="data-table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>UID</th>
                                <th>NOME COMPLETO</th>
                                <th>E-MAIL CORPORATIVO</th>
                                <th>TROCA DE SENHA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $admins = $mysqli->query("SELECT public_id, nome, email, password_changed_at FROM users WHERE role = 'admin' ORDER BY id DESC");
                            while($adm = $admins->fetch_assoc()): ?>
                            <tr>
                                <td><code style="color:var(--accent-neon);"><?= $adm['public_id'] ?></code></td>
                                <td><?= htmlspecialchars($adm['nome']) ?></td>
                                <td><?= htmlspecialchars($adm['email']) ?></td>
                                <td><?= !empty($adm['password_changed_at']) ? date('d/m/Y H:i', strtotime($adm['password_changed_at'])) : '---' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <div class="data-table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Tax ID Declarado</th>
                                <?php if($currentTab == 'historico'): ?><th>Resultado</th><?php endif; ?>
                                <th style="text-align: right;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $filter = ($currentTab == 'historico') ? "status_documentos IN ('aprovado', 'rejeitado')" : "status_documentos = 'pendente'";
                            $res = $mysqli->query("SELECT u.id as user_id, u.nome, b.tax_id, b.tax_id_file, b.license_path, b.status_documentos FROM users u INNER JOIN businesses b ON u.id = b.user_id WHERE $filter ORDER BY u.id DESC");
                            while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['nome']) ?></strong></td>
                                <td><code><?= $row['tax_id'] ?: 'Ver anexo' ?></code></td>
                                <?php if($currentTab == 'historico'): ?>
                                    <td><span style="color: <?= $row['status_documentos'] == 'aprovado' ? 'var(--accent-neon)' : 'var(--danger)' ?>"><?= strtoupper($row['status_documentos']) ?></span></td>
                                <?php endif; ?>
                                <td style="text-align: right;">
                                    <button class="btn btn-primary" onclick='openAudit(<?= json_encode($row) ?>)'>
                                        <i class="fa-solid fa-magnifying-glass"></i> Auditar
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div class="modal-overlay" id="auditModal">
        <div class="modal-content">
            <div class="doc-viewer">
                <div id="v_lic" style="height: 50%; border: 1px solid var(--border); border-radius: 8px; overflow: hidden;"></div>
                <div id="v_tax" style="height: 50%; border: 1px solid var(--border); border-radius: 8px; overflow: hidden;"></div>
            </div>
            <div class="audit-panel">
                <h2 id="aud_company" style="margin-top:0; color:var(--accent-neon);"></h2>
                <form method="POST" id="auditForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" id="aud_user_id">
                    <input type="hidden" name="alvara_decision" id="al_dec">
                    <input type="hidden" name="tax_decision" id="tx_dec">
                    <div id="audit_steps_content"></div>
                    <div style="margin-top: 30px; display: flex; flex-direction: column; gap: 10px;">
                        <button type="submit" name="final_action" id="btnSubmit" class="btn btn-primary" style="padding: 20px; justify-content: center; opacity: 0.3; pointer-events: none;">FINALIZAR AUDITORIA</button>
                        <button type="button" class="btn" style="background:transparent; color:var(--text-dim); justify-content: center;" onclick="closeAudit()">DESCARTAR E VOLTAR</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const uploadBase = "<?= $uploadBase ?>";
        let sec = <?= (int)$remainingSeconds ?>;

        /* --- LOGICA AJAX NOVO ADMIN DENTRO DO CONTEÚDO PRINCIPAL --- */
        async function loadAdminForm(btn) {
            const contentArea = document.getElementById('mainContent');
            
            // Remove active de outras abas
            document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');

            contentArea.innerHTML = '<div style="text-align:center; padding:100px;"><i class="fa-solid fa-circle-notch fa-spin fa-3x" style="color:var(--accent-neon)"></i><p style="margin-top:15px; color:var(--text-dim);">Carregando módulo de registro...</p></div>';
            
            try {
                const response = await fetch('register_new_admin.php');
                if (!response.ok) throw new Error('Erro ao carregar');
                const html = await response.text();
                contentArea.innerHTML = `<div style="max-width:800px; margin: 0 auto; animation: fadeIn 0.3s ease;">${html}</div>`;
            } catch (err) {
                contentArea.innerHTML = '<div style="color:var(--danger); text-align:center; padding:50px;">Erro ao carregar o formulário. Verifique se o arquivo register_new_admin.php existe.</div>';
            }
        }

        /* --- TIMER --- */
        setInterval(() => {
            if(sec <= 0) window.location.href = "../../registration/login/logout.php?info=timeout";
            sec--;
            let h = Math.floor(sec/3600), m = Math.floor((sec % 3600)/60), s = sec%60;
            document.getElementById('timer').textContent = (h>0?h+':':'') + (m<10?'0'+m:m)+':'+(s<10?'0'+s:s);
        }, 1000);

        /* --- AUDIT FUNCTIONS --- */
        function openAudit(data) {
            document.getElementById('aud_company').textContent = data.nome;
            document.getElementById('aud_user_id').value = data.user_id;
            renderFile('v_lic', data.license_path);
            renderFile('v_tax', data.tax_id_file);
            document.getElementById('auditModal').style.display = 'flex';
        }

        function renderFile(div, file) {
            const el = document.getElementById(div);
            if(!file) { el.innerHTML = '<center style="padding-top:20%; color:#333;">ANEXO INDISPONÍVEL</center>'; return; }
            const ext = file.split('.').pop().toLowerCase();
            el.innerHTML = ext === 'pdf' ? `<iframe src="${uploadBase+file}#toolbar=0" style="width:100%; height:100%; border:none;"></iframe>` : `<img src="${uploadBase+file}" style="max-width:100%; max-height:100%; object-fit:contain;">`;
        }

        function closeAudit() { 
            document.getElementById('auditModal').style.display = 'none'; 
            document.getElementById('auditForm').reset();
        }
    </script>
    
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</body>
</html>