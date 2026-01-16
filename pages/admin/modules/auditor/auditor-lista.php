<?php
require_once '../../../../registration/includes/db.php';
session_start();

// Proteção: Apenas Superadmin pode gerir outros auditores
$isAdmin = (isset($_SESSION['auth']['role']) && $_SESSION['auth']['role'] === 'superadmin');

if (!$isAdmin) {
    echo "<div style='padding:20px; color:#ff4d4d; background:rgba(255,77,77,0.05); border-radius:10px;'>
            <i class='fa-solid fa-lock'></i> Acesso restrito apenas para Superadministradores.
          </div>";
    exit;
}

// Busca todos os usuários com cargos administrativos
$sql = "SELECT id, public_id, nome, email, email_corporativo, role, status, created_at, last_activity 
        FROM users 
        WHERE type = 'admin'
        AND role IN ('admin', 'superadmin') 
        AND deleted_at IS NULL
        ORDER BY role DESC, nome ASC";
$result = $mysqli->query($sql);
$auditores = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $auditores[] = $row;
    }
}
?>

<div style="padding: 20px; animation: fadeIn 0.4s ease;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
        <div>
            <h2 style="color:#fff; margin:0;">Gestão de Auditores</h2>
            <p style="color:#666; font-size:0.85rem;">Visualize e gerencie os acessos da equipe administrativa VisionGreen.</p>
        </div>
        <button class="btn btn-primary" onclick="window.location.href='modules/auditor/register_auditor.php'" 
                style="padding: 12px 20px; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-user-plus"></i> Novo Auditor
        </button>
    </div>

    <!-- TABELA DE AUDITORES -->
    <div class="card">
        <div class="card-body">
            <?php if (count($auditores) > 0): ?>
                <div style="background: rgba(255,255,255,0.02); border-radius: 15px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse; color: #ccc; text-align: left;">
                        <thead>
                            <tr style="background: rgba(255,255,255,0.03); color: #888; font-size: 0.75rem; text-transform: uppercase;">
                                <th style="padding: 20px;">Auditor</th>
                                <th style="padding: 20px;">Disponibilidade</th>
                                <th style="padding: 20px;">Cargo</th>
                                <th style="padding: 20px;">Status</th>
                                <th style="padding: 20px;">Data Registro</th>
                            </tr>
                        </thead>
                        <tbody id="auditorTableBody">
                            <?php foreach ($auditores as $auditor): 
                                $isSelf = ($auditor['id'] == $_SESSION['auth']['user_id']);
                                $isOnline = false;
                                $lastSeenText = "Offline";
                                
                                if (!empty($auditor['last_activity'])) {
                                    $timeDiff = time() - strtotime($auditor['last_activity']);
                                    if ($timeDiff <= 300) { 
                                        $isOnline = true; 
                                    } else {
                                        $minutos = round($timeDiff / 60);
                                        if ($minutos < 60) {
                                            $lastSeenText = "Visto há $minutos min";
                                        } else {
                                            $lastSeenText = "Visto há " . round($minutos/60) . "h";
                                        }
                                    }
                                }
                            ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.02); transition: 0.3s; cursor: pointer;" onclick="loadContent('modules/auditor/detalhes?id=<?= $auditor['id'] ?>')">
                                <td style="padding: 20px;">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div style="position:relative;">
                                            <div style="width:35px; height:35px; border-radius:50%; background:rgba(0,255,136,0.1); display:flex; align-items:center; justify-content:center; color:var(--accent-green); font-weight:bold; border: 1px solid rgba(0,255,136,0.2);">
                                                <?= strtoupper(substr($auditor['nome'], 0, 1)) ?>
                                            </div>
                                            <div style="position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background: <?= $isOnline ? '#00ff88' : '#555' ?>; border: 2px solid #111;"></div>
                                        </div>
                                        <div>
                                            <strong style="color:#fff; display:block;"><?= htmlspecialchars($auditor['nome']) ?> <?= $isSelf ? '<small style="color:var(--accent-green)">(Você)</small>' : '' ?></strong>
                                            <small style="color:#555;"><?= htmlspecialchars($auditor['email_corporativo']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 20px;">
                                    <?php if($isOnline): ?>
                                        <span style="color:#00ff88; font-size:0.75rem; font-weight:bold; display:flex; align-items:center; gap:5px;">
                                            <span class="pulse-dot"></span> ONLINE AGORA
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#666; font-size:0.75rem;">
                                            <i class="fa-solid fa-clock-rotate-left"></i> <?= $lastSeenText ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 20px;">
                                    <span style="font-size:0.7rem; font-weight:800; padding:4px 10px; border-radius:20px; background: <?= $auditor['role'] == 'superadmin' ? 'rgba(255,204,0,0.1)' : 'rgba(0,162,255,0.1)' ?>; color: <?= $auditor['role'] == 'superadmin' ? '#ffcc00' : '#00a2ff' ?>; border: 1px solid currentColor;">
                                        <?= strtoupper($auditor['role']) ?>
                                    </span>
                                </td>
                                <td style="padding: 20px;">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <div style="width:8px; height:8px; border-radius:50%; background: <?= $auditor['status'] == 'active' ? '#00ff88' : '#ff4d4d' ?>;"></div>
                                        <span style="font-size:0.8rem;"><?= ucfirst($auditor['status']) ?></span>
                                    </div>
                                </td>
                                <td style="padding: 20px; font-size:0.85rem; color:#666;">
                                    <?= date('d/m/Y', strtotime($auditor['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 3rem; color: #555; margin-bottom: 20px;">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h3 style="color: #fff; margin-bottom: 10px;">Nenhum auditor encontrado</h3>
                    <p style="color: #666; margin-bottom: 30px;">Comece criando o primeiro auditor administrativo.</p>
                    <button class="btn btn-primary" onclick="window.location.href='modules/auditor/register_auditor.php'">
                        <i class="fa-solid fa-user-plus"></i> Criar Primeiro Auditor
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.5); opacity: 0; }
    100% { transform: scale(1); opacity: 0; }
}

.pulse-dot {
    width: 6px;
    height: 6px;
    background: #00ff88;
    border-radius: 50%;
    position: relative;
}

.pulse-dot::after {
    content: '';
    width: 100%;
    height: 100%;
    background: #00ff88;
    border-radius: 50%;
    position: absolute;
    top: 0;
    left: 0;
    animation: pulse 1.5s infinite;
}

tr:hover {
    background: rgba(255,255,255,0.01);
}

.card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.card-body {
    padding: 20px;
}

.mb-3 {
    margin-bottom: 20px;
}

.form-field {
    margin-bottom: 0;
}

.form-label {
    display: block;
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 8px;
}

.form-input,
.form-select {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.938rem;
}

.form-input:focus,
.form-select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(0, 255, 136, 0.1);
}

.btn {
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: var(--accent);
    color: #000;
}

.btn-primary:hover {
    background: #00e080;
}

.btn-ghost {
    background: transparent;
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.btn-ghost:hover {
    background: rgba(255,255,255,0.05);
    color: var(--text-primary);
}
</style>

<script>
    function limparFiltros() {
        document.getElementById('searchInput').value = '';
        document.getElementById('orderSelect').value = 'role_desc';
        location.reload();
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 16px 20px;
            background: ${type === 'success' ? 'rgba(0, 255, 136, 0.1)' : 'rgba(255, 77, 77, 0.1)'};
            border: 1px solid ${type === 'success' ? 'var(--accent)' : '#ff4d4d'};
            color: ${type === 'success' ? 'var(--accent)' : '#ff4d4d'};
            border-radius: 8px;
            z-index: 2000;
            animation: fadeIn 0.3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>