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

/* ================= 2. CÁLCULO DO TEMPO ================= */
$stmt_time = $mysqli->prepare("SELECT password_changed_at FROM users WHERE id = ?");
$stmt_time->bind_param("i", $adminId);
$stmt_time->execute();
$admin_data = $stmt_time->get_result()->fetch_assoc();
$remainingSeconds = 3600 - (time() - strtotime($admin_data['password_changed_at']));
if ($remainingSeconds < 0) $remainingSeconds = 0;

/* ================= 3. PROCESSAR FINALIZAÇÃO DA AUDITORIA ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_action'])) {
    if (isset($_POST['csrf']) && csrf_validate($_POST['csrf'])) {
        $targetUserId = (int)$_POST['user_id'];
        $alvaraStatus = $_POST['alvara_decision']; 
        $taxStatus    = $_POST['tax_decision'];    
        
        $manualTaxId  = !empty($_POST['manual_tax_id']) ? cleanInput($_POST['manual_tax_id']) : NULL;
        $motivoAlvara = !empty($_POST['motivo_alvara']) ? trim($_POST['motivo_alvara']) : "";
        $motivoTax    = !empty($_POST['motivo_tax']) ? trim($_POST['motivo_tax']) : "";

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
                $motivoCompleto = "";
                if($alvaraStatus === 'fail') $motivoCompleto .= "[ALVARÁ]: $motivoAlvara. ";
                if($taxStatus === 'fail') $motivoCompleto .= "[TAX ID]: $motivoTax.";
                
                $stmt = $mysqli->prepare("UPDATE businesses SET status_documentos = 'rejeitado', motivo_rejeicao = ? WHERE user_id = ?");
                $stmt->bind_param('si', $motivoCompleto, $targetUserId);
                $stmt->execute();
            }

            $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ($adminId, 'AUDIT_$statusFinal', '$ip_address')");
            $mysqli->commit();
        } catch (Exception $e) {
            $mysqli->rollback();
        }
    }
}

$uploadBase = "../../registration/uploads/business/";
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel de Auditoria VisionGreen</title>
    <style>
        :root { --vg-green: #00a63e; --vg-neon: #00ff41; --vg-dark: #0a0f1a; --vg-card: #111b2d; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--vg-dark); margin: 0; display: flex; color: #e2e8f0; }
        
        .sidebar { width: 260px; background: #050810; min-height: 100vh; padding: 25px; position: fixed; border-right: 1px solid var(--vg-green); }
        .main-content { flex: 1; margin-left: 310px; padding: 30px; }
        
        .nav-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #1e293b; padding-bottom: 10px; }
        .tab-link { color: #94a3b8; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; font-size: 13px; }
        .tab-link.active { background: rgba(0, 166, 62, 0.2); color: var(--vg-neon); }

        .security-bar { background: var(--vg-card); padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; border: 1px solid #1e293b; }
        
        table { width: 100%; border-collapse: collapse; background: var(--vg-card); border-radius: 8px; overflow: hidden; margin-top: 20px; }
        th { background: #0f172a; padding: 15px; text-align: left; color: var(--vg-green); font-size: 12px; }
        td { padding: 15px; border-top: 1px solid #1e293b; font-size: 14px; }

        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-aprovado { background: #16a34a33; color: #4ade80; border: 1px solid #16a34a; }
        .status-rejeitado { background: #dc262633; color: #f87171; border: 1px solid #dc2626; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 1000; }
        .modal-full { background: var(--vg-card); width: 95%; height: 92vh; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); border: 1px solid var(--vg-green); display: flex; overflow: hidden; border-radius: 12px; }
        
        .docs-section { flex: 1.3; background: #000; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; border-right: 1px solid #1e293b; }
        .doc-item { height: 350px; border: 1px solid #333; position: relative; background: #111; border-radius: 5px; overflow: hidden; }
        .doc-label { position: absolute; top: 10px; left: 10px; background: var(--vg-green); color: #000; padding: 3px 10px; font-weight: bold; font-size: 10px; z-index: 5; border-radius: 3px; }

        .control-section { flex: 1; padding: 25px; display: flex; flex-direction: column; overflow-y: auto; }
        .inspect-card { background: #050810; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #1e293b; }
        
        .btn { padding: 10px 20px; border: none; cursor: pointer; font-weight: bold; border-radius: 4px; transition: 0.2s; }
        .btn-ok { background: #16a34a; color: white; }
        .btn-fail { background: #dc2626; color: white; }
        .btn-final { background: var(--vg-green); color: black; width: 100%; padding: 15px; font-size: 1.1rem; margin-top: 20px; opacity: 0.5; pointer-events: none; }
        .btn-final.active { opacity: 1; pointer-events: auto; }

        .tax-edit { width: 100%; background: #111; border: 1px solid var(--vg-green); color: #fff; padding: 12px; margin-top: 10px; font-family: monospace; font-size: 1.2rem; border-radius: 5px; }
        textarea { width: 100%; background: #000; border: 1px solid #dc2626; color: #ff8a8a; padding: 10px; margin-top: 10px; font-size: 13px; resize: none; border-radius: 5px; }
        iframe, img { width: 100%; height: 100%; object-fit: contain; border: none; }
        
        .reverif-info { background: #dc262622; border-left: 4px solid #dc2626; padding: 15px; color: #f87171; font-size: 13px; margin-bottom: 20px; border-radius: 0 5px 5px 0; }
        
        /* Botão de Logout */
        .logout-link { margin-top: auto; color: #ef4444; border: 1px solid #ef4444; text-align: center; transition: 0.3s; }
        .logout-link:hover { background: #ef4444; color: white; }
    </style>
</head>
<body>

<div class="sidebar" style="display: flex; flex-direction: column;">
    <h2 style="color:var(--vg-green); margin-bottom: 5px;">VISION GREEN</h2>
    <p style="font-size:10px; color:#64748b; margin-top:0;">AUDITORIA CENTRALIZADA</p>
    
    <nav style="margin-top: 30px; display: flex; flex-direction: column; gap: 5px; flex-grow: 1;">
        <a href="?tab=pendentes" class="tab-link <?= $currentTab == 'pendentes' ? 'active' : '' ?>">● AGUARDANDO INSPEÇÃO</a>
        <a href="?tab=historico" class="tab-link <?= $currentTab == 'historico' ? 'active' : '' ?>">● REVERIFICAÇÃO / HISTÓRICO</a>
        
        <a href="../../registration/login/logout.php" class="tab-link logout-link" style="margin-top: 40px;">× SAIR DO SISTEMA</a>
    </nav>
</div>

<div class="main-content">
    <div class="security-bar">
        <strong>PAINEL DE AUDITORIA - <?= strtoupper($currentTab) ?></strong>
        <div id="countdown-circle" style="color:var(--vg-neon); font-family: monospace; font-size: 1.2rem;">00:00</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>EMPRESA</th>
                <th>TAX ID ATUAL</th>
                <?php if($currentTab == 'historico'): ?><th>STATUS ATUAL</th><?php endif; ?>
                <th>AÇÃO</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $statusFilter = ($currentTab == 'historico') ? "status_documentos IN ('aprovado', 'rejeitado')" : "status_documentos = 'pendente'";
            $res = $mysqli->query("SELECT u.id as user_id, u.nome, b.tax_id, b.tax_id_file, b.license_path, b.status_documentos, b.motivo_rejeicao FROM users u INNER JOIN businesses b ON u.id = b.user_id WHERE $statusFilter ORDER BY u.id DESC");
            
            if ($res->num_rows > 0):
                while($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['nome']) ?></strong></td>
                    <td><code><?= $row['tax_id'] ?: 'Anexo' ?></code></td>
                    <?php if($currentTab == 'historico'): ?>
                        <td><span class="status-badge status-<?= $row['status_documentos'] ?>"><?= $row['status_documentos'] ?></span></td>
                    <?php endif; ?>
                    <td>
                        <button class="btn <?= $currentTab == 'historico' ? 'btn-outline' : 'btn-ok' ?>" 
                                onclick='openInspector(<?= json_encode($row) ?>, "<?= $currentTab ?>")'>
                            <?= $currentTab == 'historico' ? 'REVERIFICAR' : 'AUDITAR' ?>
                        </button>
                    </td>
                </tr>
                <?php endwhile; 
            else: ?>
                <tr>
                    <td colspan="<?= ($currentTab == 'historico') ? '4' : '3' ?>" style="text-align: center; padding: 50px; color: #64748b; font-style: italic;">
                        Nenhum arquivo enviado para ser verificado
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="modal">
    <div class="modal-full">
        <div class="docs-section">
            <div class="doc-item">
                <span class="doc-label">1. ALVARÁ DE FUNCIONAMENTO</span>
                <div id="view_lic"></div>
            </div>
            <div class="doc-item" id="tax_doc_container">
                <span class="doc-label">2. COMPROVANTE FISCAL (TAX ID)</span>
                <div id="view_tax"></div>
            </div>
        </div>

        <div class="control-section">
            <h3 id="comp_name" style="color:var(--vg-neon); margin:0 0 20px 0;"></h3>
            
            <div id="reverif_header" class="reverif-info" style="display:none;">
                <strong>OBSERVAÇÃO ANTERIOR:</strong><br>
                <span id="txt_motivo_antigo"></span>
            </div>

            <form method="POST" id="auditForm">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" id="target_id">
                <input type="hidden" name="alvara_decision" id="alvara_decision">
                <input type="hidden" name="tax_decision" id="tax_decision">

                <div class="inspect-card">
                    <small style="color:#94a3b8">ESTADO DO ALVARÁ:</small>
                    <div class="btn-group" style="display:flex; gap:10px; margin-top:10px;">
                        <button type="button" class="btn btn-ok" id="btn_al_ok" onclick="setDecision('alvara', 'ok')">VÁLIDO</button>
                        <button type="button" class="btn btn-fail" id="btn_al_fail" onclick="setDecision('alvara', 'fail')">INVÁLIDO</button>
                    </div>
                    <textarea name="motivo_alvara" id="motivo_alvara" placeholder="Explique o motivo da invalidez do Alvará..."></textarea>
                </div>

                <div class="inspect-card" id="tax_card">
                    <small style="color:#94a3b8">VALIDAÇÃO FISCAL (TAX ID):</small>
                    <div id="tax_ui_logic"></div>
                    <textarea name="motivo_tax" id="motivo_tax" placeholder="Explique o motivo da invalidez do Tax ID..."></textarea>
                </div>

                <button type="submit" name="final_action" class="btn-final" id="btnFinal">SALVAR E FINALIZAR AUDITORIA</button>
                <button type="button" class="btn" style="width:100%; margin-top:10px; background:#334155; color:white;" onclick="closeInspector()">VOLTAR</button>
            </form>
        </div>
    </div>
</div>

<script>
    const uploadBase = "<?= $uploadBase ?>";
    
    function render(div, file) {
        const el = document.getElementById(div);
        if(!file) { el.innerHTML = '<center style="color:#444; padding-top:100px; font-size:12px;">Não anexado</center>'; return; }
        const ext = file.split('.').pop().toLowerCase();
        el.innerHTML = ext === 'pdf' ? `<iframe src="${uploadBase+file}#toolbar=0"></iframe>` : `<img src="${uploadBase+file}">`;
    }

    function openInspector(data, mode) {
        document.getElementById('comp_name').textContent = data.nome;
        document.getElementById('target_id').value = data.user_id;
        render('view_lic', data.license_path);
        render('view_tax', data.tax_id_file);

        if(mode === 'historico' && data.motivo_rejeicao) {
            document.getElementById('reverif_header').style.display = 'block';
            document.getElementById('txt_motivo_antigo').textContent = data.motivo_rejeicao;
        } else {
            document.getElementById('reverif_header').style.display = 'none';
        }

        const ui = document.getElementById('tax_ui_logic');
        document.getElementById('tax_doc_container').style.display = data.tax_id_file ? 'block' : 'none';
        
        if(data.tax_id_file) {
            ui.innerHTML = `
                <div style="display:flex; gap:10px; margin-top:10px;">
                    <button type="button" class="btn btn-ok" id="btn_tx_ok" onclick="setDecision('tax', 'ok')">NÚMERO OK</button>
                    <button type="button" class="btn btn-fail" id="btn_tx_fail" onclick="setDecision('tax', 'fail')">INVÁLIDO</button>
                </div>
                <input type="text" name="manual_tax_id" class="tax-edit" id="tax_input" placeholder="Extraia o Tax ID do documento..." style="display:none">
            `;
            document.getElementById('tax_decision').value = "";
        } else {
            ui.innerHTML = `<span class="tax-badge" style="font-family:monospace; font-size:1.4rem; color:var(--vg-neon); display:block; margin-top:10px;">${data.tax_id}</span>`;
            document.getElementById('tax_decision').value = "text_only";
        }

        document.getElementById('modal').style.display = 'block';
    }

    function setDecision(type, status) {
        document.getElementById(type + '_decision').value = status;
        const prefix = type === 'alvara' ? 'al' : 'tx';
        document.getElementById('btn_'+prefix+'_ok').style.opacity = status === 'ok' ? '1' : '0.3';
        document.getElementById('btn_'+prefix+'_fail').style.opacity = status === 'fail' ? '1' : '0.3';

        if(type === 'alvara') {
            document.getElementById('motivo_alvara').style.display = status === 'fail' ? 'block' : 'none';
        } else if(type === 'tax') {
            document.getElementById('tax_input').style.display = status === 'ok' ? 'block' : 'none';
            document.getElementById('motivo_tax').style.display = status === 'fail' ? 'block' : 'none';
        }
        validateFinalBtn();
    }

    function validateFinalBtn() {
        const al = document.getElementById('alvara_decision').value;
        const tx = document.getElementById('tax_decision').value;
        if(al !== "" && tx !== "") document.getElementById('btnFinal').classList.add('active');
    }

    function closeInspector() { document.getElementById('modal').style.display = 'none'; document.getElementById('auditForm').reset(); }

    let sec = <?= $remainingSeconds ?>;
    setInterval(() => {
        if(sec <= 0) window.location.href = "../../registration/login/logout.php";
        sec--;
        let m = Math.floor(sec/60), s = sec%60;
        document.getElementById('countdown-circle').textContent = (m<10?'0'+m:m)+':'+(s<10?'0'+s:s);
    }, 1000);
</script>
</body>
</html>