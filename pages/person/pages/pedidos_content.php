<?php
/* =========================================
   MEUS PEDIDOS
   ========================================= */

// Filtro de status (se houver)
$status_filter = $_GET['status'] ?? 'all';

// Query de pedidos com filtro
$where_status = $status_filter !== 'all' ? "AND o.status = ?" : "";

$pedidos_query = "
    SELECT 
        o.id,
        o.order_number,
        o.order_date,
        o.status,
        o.payment_status,
        o.total,
        o.currency,
        u.nome as empresa_nome,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
    FROM orders o
    LEFT JOIN users u ON o.company_id = u.id
    WHERE o.customer_id = ?
    {$where_status}
    AND o.deleted_at IS NULL
    ORDER BY o.order_date DESC
";

$stmt = $mysqli->prepare($pedidos_query);
if ($status_filter !== 'all') {
    $stmt->bind_param("is", $userId, $status_filter);
} else {
    $stmt->bind_param("i", $userId);
}
$stmt->execute();
$pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="breadcrumb">
    <a href="index.php">Início</a>
    <i class="fa-solid fa-chevron-right"></i>
    <span>Meus Pedidos</span>
</div>

<div class="page-header">
    <div class="page-title-section">
        <h1><i class="fa-solid fa-box-archive"></i> Meus Pedidos</h1>
        <p class="page-subtitle">Histórico completo de compras</p>
    </div>
    <div class="page-actions">
        <select onchange="window.location.href='index.php?page=pedidos&status='+this.value" class="btn btn-secondary" style="padding: 10px 16px;">
            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Todos</option>
            <option value="pendente" <?= $status_filter === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
            <option value="enviado" <?= $status_filter === 'enviado' ? 'selected' : '' ?>>Em Trânsito</option>
            <option value="entregue" <?= $status_filter === 'entregue' ? 'selected' : '' ?>>Entregues</option>
        </select>
    </div>
</div>

<?php if (empty($pedidos)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-box-open"></i>
        <p>Nenhum pedido encontrado</p>
    </div>
<?php else: ?>
    <div class="card">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Fornecedor</th>
                    <th>Data</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $order): 
                    $status = getStatusInfo($order['status']);
                    $orderDate = date('d/m/Y', strtotime($order['order_date']));
                ?>
                    <tr>
                        <td><a href="#" class="order-id">#<?= htmlspecialchars($order['order_number']) ?></a></td>
                        <td><?= htmlspecialchars($order['empresa_nome']) ?></td>
                        <td><?= $orderDate ?></td>
                        <td>
                            <span class="status-badge <?= $status['class'] ?>">
                                <span class="status-indicator"></span>
                                <?= $status['label'] ?>
                            </span>
                        </td>
                        <td><strong><?= strtoupper($order['currency']) ?> <?= number_format($order['total'], 2, ',', '.') ?></strong></td>
                        <td>
                            <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 13px;">
                                Ver Detalhes
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>