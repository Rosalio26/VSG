<?php
define('IS_ADMIN_PAGE', true); 

session_start();
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

/* ================= 1. SEGURANÇA E ROLE CHECK ================= */
if (!isset($_SESSION['auth']['role']) || !in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    $checkId = $_SESSION['auth']['user_id'] ?? 0;
    $check = $mysqli->query("SELECT role FROM users WHERE id = $checkId")->fetch_assoc();
    if (!$check || !in_array($check['role'], ['admin', 'superadmin'])) {
        header("Location: ../../registration/login/login.php?error=nao_e_admin");
        exit;
    }
}

$adminId = $_SESSION['auth']['user_id'];
$currentTab = $_GET['tab'] ?? 'pendentes';

/* ================= 2. PROCESSAR APROVAÇÃO/REJEIÇÃO COM LOG ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (isset($_POST['csrf']) && csrf_validate($_POST['csrf'])) {
        $targetUserId = (int)$_POST['user_id'];
        $status = $_POST['action'] === 'approve' ? 'aprovado' : 'rejeitado';
        $motivo = $_POST['motivo'] ?? NULL;

        $mysqli->begin_transaction();
        try {
            // 1. Atualiza Status
            $stmt = $mysqli->prepare("UPDATE businesses SET status_documentos = ?, motivo_rejeicao = ? WHERE user_id = ?");
            $stmt->bind_param('ssi', $status, $motivo, $targetUserId);
            $stmt->execute();

            // 2. Registra LOG (Supondo que você queira rastrear quem fez a ação)
            // Se não tiver tabela de logs ainda, pode ignorar esta parte ou criá-la depois
            /*
            $logMsg = "Admin ID $adminId $status a empresa do User ID $targetUserId";
            $mysqli->query("INSERT INTO admin_logs (admin_id, action, target_user_id, details) VALUES ($adminId, '$status', $targetUserId, '$motivo')");
            */

            $mysqli->commit();
            $msg = "Ação realizada com sucesso!";
        } catch (Exception $e) {
            $mysqli->rollback();
            $error = "Erro ao processar: " . $e->getMessage();
        }
    }
}

/* ================= 3. BUSCA POR SERIAL ================= */
$searchResult = null;
if (!empty($_GET['search_serial'])) {
    $serial = "%" . $_GET['search_serial'] . "%";
    $stmt = $mysqli->prepare("
        SELECT u.nome, b.status_documentos, b.license_path, b.tax_id 
        FROM users u 
        INNER JOIN businesses b ON u.id = b.user_id 
        WHERE b.license_path LIKE ?
    ");
    $stmt->bind_param('s', $serial);
    $stmt->execute();
    $searchResult = $stmt->get_result()->fetch_assoc();
}

/* ================= 4. MÉTRICAS TOTAIS ================= */
$totalEmpresas = $mysqli->query("SELECT COUNT(*) as total FROM businesses")->fetch_assoc()['total'] ?? 0;
$totalPendentes = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'pendente'")->fetch_assoc()['total'] ?? 0;
$totalPessoas = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE type = 'person'")->fetch_assoc()['total'] ?? 0;

$uploadBase = "../../registration/uploads/business/";
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>VisionGreen Admin - Central</title>
    <style>
        :root {
            --vg-green: #00a63e;
            --vg-dark: #101828;
            --vg-danger: #ff3232;
            --vg-sidebar: #111827;
        }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; margin: 0; display: flex; color: #334155; }
        
        /* Sidebar Navigation */
        .sidebar { width: 260px; background: var(--vg-sidebar); color: white; min-height: 100vh; padding: 25px; position: fixed; }
        .sidebar h2 { color: var(--vg-green); margin-bottom: 30px; font-size: 1.5rem; }
        .nav-link { display: block; padding: 12px 15px; color: #9ca3af; text-decoration: none; border-radius: 8px; margin-bottom: 8px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: rgba(0, 166, 62, 0.1); color: var(--vg-green); font-weight: bold; }

        .main-content { flex: 1; margin-left: 260px; padding: 40px; }
        
        /* Dashboard Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-bottom: 4px solid var(--vg-green); }
        .stat-card small { color: #64748b; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; }
        .stat-card div { font-size: 2rem; font-weight: bold; margin-top: 5px; }

        /* Search Section */
        .search-area { background: var(--vg-dark); padding: 20px; border-radius: 12px; margin-bottom: 30px; color: white; display: flex; align-items: center; gap: 15px; }
        .search-area input { flex: 1; padding: 10px; border-radius: 6px; border: 1px solid #374151; background: #1f2937; color: white; }
        
        .table-container { background: white; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 15px; text-align: left; color: #64748b; font-size: 0.75rem; text-transform: uppercase; }
        td { padding: 15px; border-top: 1px solid #f1f5f9; }

        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; }
        .badge-pending { background: #fef3c7; color: #92400e; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; }
        .modal { background: white; width: 450px; padding: 30px; border-radius: 15px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        
        .btn { padding: 10px 18px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 0.8rem; transition: 0.2s; }
        .btn-green { background: var(--vg-green); color: white; }
        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>VisionGreen</h2>
    <nav>
        <a href="?tab=pendentes" class="nav-link <?= $currentTab == 'pendentes' ? 'active' : '' ?>">📄 Pendentes de Validação</a>
        <a href="?tab=usuarios" class="nav-link <?= $currentTab == 'usuarios' ? 'active' : '' ?>">👥 Todos os Usuários</a>
        <a href="?tab=logs" class="nav-link <?= $currentTab == 'logs' ? 'active' : '' ?>">📜 Logs do Sistema</a>
        <hr style="opacity: 0.1; margin: 20px 0;">
        <a href="../../registration/login/logout.php" class="nav-link" style="color: var(--vg-danger);">🚪 Sair</a>
    </nav>
</div>

<div class="main-content">
    
    <div class="stats-grid">
        <div class="stat-card">
            <small>Pendentes</small>
            <div style="color: orange;"><?= $totalPendentes ?></div>
        </div>
        <div class="stat-card">
            <small>Empresas Ativas</small>
            <div><?= $totalEmpresas ?></div>
        </div>
        <div class="stat-card">
            <small>Usuários Totais</small>
            <div><?= $totalPessoas + $totalEmpresas ?></div>
        </div>
    </div>

    <div class="search-area">
        <span>🔎 Validar Serial:</span>
        <form method="GET" style="display:flex; flex:1; gap:10px;">
            <input type="text" name="search_serial" placeholder="Ex: 000-00000-0000-vsg" value="<?= $_GET['search_serial'] ?? '' ?>">
            <button type="submit" class="btn btn-green">Verificar</button>
        </form>
    </div>

    <?php if($searchResult): ?>
        <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid var(--vg-green);">
            <strong>Resultado da Busca:</strong> Empresa: <?= $searchResult['nome'] ?> | 
            Status: <span class="badge badge-pending"><?= $searchResult['status_documentos'] ?></span> | 
            <a href="<?= $uploadBase . $searchResult['license_path'] ?>" target="_blank">Ver Documento</a>
        </div>
    <?php endif; ?>

    <?php if($currentTab == 'pendentes'): ?>
        <h3>Documentações aguardando aprovação</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Tax ID / NUIT</th>
                        <th>Local</th>
                        <th>Documento</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $pendentes = $mysqli->query("SELECT u.id as user_id, u.nome, u.email, b.tax_id, b.license_path, b.city, b.country FROM users u INNER JOIN businesses b ON u.id = b.user_id WHERE b.status_documentos = 'pendente'");
                    while ($row = $pendentes->fetch_assoc()): 
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($row['nome']) ?></strong><br>
                            <small><?= htmlspecialchars($row['email']) ?></small>
                        </td>
                        <td><code><?= htmlspecialchars($row['tax_id']) ?></code></td>
                        <td><?= htmlspecialchars($row['city'] ?? 'N/A') ?></td>
                        <td><a href="<?= $uploadBase . $row['license_path'] ?>" target="_blank" class="btn btn-outline">👁️ Abrir</a></td>
                        <td>
                            <button onclick="openReject(<?= $row['user_id'] ?>)" class="btn btn-outline" style="color: var(--vg-danger);">Rejeitar</button>
                            <form method="POST" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-green">Aprovar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if($currentTab == 'usuarios'): ?>
        <h3>Gerenciamento de Contas</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        <th>Role</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $users = $mysqli->query("SELECT id, nome, email, type, role, status FROM users ORDER BY created_at DESC LIMIT 50");
                    while ($u = $users->fetch_assoc()): 
                    ?>
                    <tr>
                        <td>#<?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['nome']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= $u['type'] ?></td>
                        <td><strong><?= $u['role'] ?></strong></td>
                        <td><?= $u['status'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<div class="modal-overlay" id="rejectOverlay">
    <div class="modal">
        <h3>Rejeitar Documento</h3>
        <p>Informe ao usuário o motivo da rejeição para que ele possa corrigir.</p>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" id="reject_user_id">
            <input type="hidden" name="action" value="reject">
            <textarea name="motivo" rows="4" style="width: 100%; border-radius: 8px; padding: 10px; border: 1px solid #ddd; font-family: inherit;" required placeholder="Ex: Foto ilegível, Tax ID não corresponde..."></textarea>
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-green" style="background: var(--vg-danger); flex: 1;">Confirmar Rejeição</button>
                <button type="button" onclick="closeReject()" class="btn btn-outline" style="flex: 1;">Cancelar</button>
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
</script>

</body>
</html>