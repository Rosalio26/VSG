<?php
require_once '../../../registration/includes/db.php';
session_start();

/* ================= PROTEÇÃO DE ACESSO DIRETO ================= */
if (!isset($_SESSION['auth']['role']) || !in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    die("<div style='color:red; padding:20px;'>Acesso negado. Autenticação administrativa necessária.</div>");
}

$q = $_GET['q'] ?? '';
$term = "%$q%"; // Alterado para buscar em qualquer parte do nome para ser mais abrangente

$stmt = $mysqli->prepare("
    (SELECT 'EMPRESA' as tipo, u.nome, b.tax_id as documento, b.status_documentos as status
    FROM businesses b 
    JOIN users u ON b.user_id = u.id 
    WHERE u.nome LIKE ? OR b.tax_id LIKE ? OR u.public_id LIKE ?)
    UNION
    (SELECT 'AUDITOR' as tipo, nome, email as documento, role as status
    FROM users 
    WHERE (nome LIKE ? OR email LIKE ?) AND role IN ('admin', 'superadmin'))
    LIMIT 15
");

$stmt->bind_param("sssss", $term, $term, $term, $term, $term);
$stmt->execute();
$result = $stmt->get_result();
?>

<div style="padding: 20px; animation: fadeIn 0.4s ease;">
    <h2 style="color: #fff; margin-bottom: 20px;">
        <i class="fa-solid fa-magnifying-glass" style="color: var(--accent-green);"></i> 
        Resultados para: <span style="color: var(--accent-green);"><?= htmlspecialchars($q) ?></span>
    </h2>

    <div class="data-table-container" style="background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
        <table style="width: 100%; border-collapse: collapse; color: #ccc;">
            <thead>
                <tr style="text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <th style="padding: 15px;">TIPO</th>
                    <th style="padding: 15px;">NOME/RAZÃO SOCIAL</th>
                    <th style="padding: 15px;">DOCUMENTO/INFO</th>
                    <th style="padding: 15px;">STATUS</th>
                    <th style="padding: 15px; text-align: right;">AÇÃO</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        // Lógica de cores para status
                        $status = strtolower($row['status']);
                        $color = "#888"; // Padrão
                        if(in_array($status, ['aprovado', 'superadmin', 'active'])) $color = "#00ff88";
                        if(in_array($status, ['pendente', 'admin'])) $color = "#ffcc00";
                        if(in_array($status, ['rejeitado', 'blocked'])) $color = "#ff4d4d";
                    ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.02); transition: 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.01)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 15px;">
                                <span class="logo-badge" style="font-size: 0.6rem; background: <?= $row['tipo'] == 'EMPRESA' ? 'rgba(0,255,136,0.1)' : 'rgba(0,162,255,0.1)' ?>; color: <?= $row['tipo'] == 'EMPRESA' ? '#00ff88' : '#00a2ff' ?>; padding: 4px 8px; border-radius: 4px; border: 1px solid currentColor;">
                                    <?= $row['tipo'] ?>
                                </span>
                            </td>
                            <td style="padding: 15px; color: #fff; font-weight: 600;"><?= htmlspecialchars($row['nome']) ?></td>
                            <td style="padding: 15px;"><code style="background: #000; padding: 3px 6px; border-radius: 4px; color: #aaa;"><?= htmlspecialchars($row['documento']) ?></code></td>
                            <td style="padding: 15px;">
                                <span style="display: flex; align-items: center; gap: 6px; color: <?= $color ?>; font-size: 0.75rem; font-weight: 800;">
                                    <i class="fa-solid fa-circle" style="font-size: 0.4rem;"></i>
                                    <?= strtoupper($row['status']) ?>
                                </span>
                            </td>
                            <td style="padding: 15px; text-align: right;">
                                <button class="btn-special" style="padding: 8px 15px; font-size: 0.7rem; cursor: pointer; border-radius: 6px; border: 1px solid var(--accent-green); background: transparent; color: var(--accent-green); transition: 0.3s;" 
                                        onclick="loadContent('modules/detalhes', {id: '<?= $row['documento'] ?>'})">
                                    <i class="fa-solid fa-eye"></i> DETALHES
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding: 60px; text-align: center; color: #666;">
                            <i class="fa-solid fa-face-frown" style="font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                            Nenhum dado encontrado para "<span style="color: #999;"><?= htmlspecialchars($q) ?></span>".
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.btn-special:hover {
    background: var(--accent-green) !important;
    color: #000 !important;
    box-shadow: 0 0 15px rgba(0, 255, 136, 0.3);
}
</style>