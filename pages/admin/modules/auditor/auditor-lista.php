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

// Busca todos os usuários com cargos administrativos (Incluindo last_activity para o status online)
$sql = "SELECT id, public_id, nome, email, email_corporativo, role, status, created_at, last_activity 
        FROM users 
        WHERE role IN ('admin', 'superadmin') 
        ORDER BY role DESC, nome ASC";
$result = $mysqli->query($sql);
?>

<div style="padding: 20px; animation: fadeIn 0.4s ease;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
        <div>
            <h2 style="color:#fff; margin:0;">Gestão de Auditores</h2>
            <p style="color:#666; font-size:0.85rem;">Visualize e gira os acessos da equipa administrativa VisionGreen.</p>
        </div>
        <button class="btn-special" onclick="window.location.href='dashboard_register_admin.php'" 
                style="padding: 12px 20px; border-radius:10px; cursor:pointer; background:var(--accent-green); color:#000; border:none; font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-user-plus"></i> NOVO AUDITOR
        </button>
    </div>

    <div style="background: rgba(255,255,255,0.02); border-radius: 15px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; color: #ccc; text-align: left;">
            <thead>
                <tr style="background: rgba(255,255,255,0.03); color: #888; font-size: 0.75rem; text-transform: uppercase;">
                    <th style="padding: 20px;">Auditor</th>
                    <th style="padding: 20px;">Disponibilidade</th>
                    <th style="padding: 20px;">Cargo</th>
                    <th style="padding: 20px;">Status</th>
                    <th style="padding: 20px;">Data Registro</th>
                    <th style="padding: 20px; text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): 
                    $isSelf = ($row['id'] == $_SESSION['auth']['user_id']);
                    
                    // Cálculo de Online/Offline (Limiar de 5 minutos)
                    $isOnline = false;
                    $lastSeenText = "Offline";
                    
                    if (!empty($row['last_activity'])) {
                        $timeDiff = time() - $row['last_activity'];
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
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.02); transition: 0.3s;">
                    <td style="padding: 20px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="position:relative;">
                                <div style="width:35px; height:35px; border-radius:50%; background:rgba(0,255,136,0.1); display:flex; align-items:center; justify-content:center; color:var(--accent-green); font-weight:bold; border: 1px solid rgba(0,255,136,0.2);">
                                    <?= strtoupper(substr($row['nome'], 0, 1)) ?>
                                </div>
                                <div style="position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background: <?= $isOnline ? '#00ff88' : '#555' ?>; border: 2px solid #111;"></div>
                            </div>
                            <div>
                                <strong style="color:#fff; display:block;"><?= htmlspecialchars($row['nome']) ?> <?= $isSelf ? '<small style="color:var(--accent-green)">(Você)</small>' : '' ?></strong>
                                <small style="color:#555;"><?= htmlspecialchars($row['email_corporativo']) ?></small>
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
                        <span style="font-size:0.7rem; font-weight:800; padding:4px 10px; border-radius:20px; background: <?= $row['role'] == 'superadmin' ? 'rgba(255,204,0,0.1)' : 'rgba(0,162,255,0.1)' ?>; color: <?= $row['role'] == 'superadmin' ? '#ffcc00' : '#00a2ff' ?>; border: 1px solid currentColor;">
                            <?= strtoupper($row['role']) ?>
                        </span>
                    </td>
                    <td style="padding: 20px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div style="width:8px; height:8px; border-radius:50%; background: <?= $row['status'] == 'active' ? '#00ff88' : '#ff4d4d' ?>;"></div>
                            <span style="font-size:0.8rem;"><?= ucfirst($row['status']) ?></span>
                        </div>
                    </td>
                    <td style="padding: 20px; font-size:0.85rem; color:#666;">
                        <?= date('d/m/Y', strtotime($row['created_at'])) ?>
                    </td>
                    <td style="padding: 20px; text-align: right;">
                        <?php if(!$isSelf): ?>
                            <button title="Gerir Acesso" style="background:none; border:none; color:#444; cursor:pointer; font-size:1.1rem; transition:0.3s;" onmouseover="this.style.color='var(--accent-green)'" onmouseout="this.style.color='#444'">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
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
</style>