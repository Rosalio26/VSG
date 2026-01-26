<!-- Modal de Notificações -->
<div id="notifications-modal" class="modal-overlay" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3>
                <i class="fa-solid fa-bell"></i>
                Notificações
                <?php if ($notif_count > 0): ?>
                    <span class="badge-count"><?= $notif_count ?></span>
                <?php endif; ?>
            </h3>
            <div class="modal-header-actions">
                <button class="btn-icon" id="mark-all-read" title="Marcar todas como lidas">
                    <i class="fa-solid fa-check-double"></i>
                </button>
                <button class="btn-icon" id="close-modal">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
        </div>

        <div class="modal-body">
            <!-- Abas de Filtro -->
            <div class="notification-tabs">
                <button class="tab-btn active" data-filter="all">
                    Todas (<?= $notif_count ?>)
                </button>
                <button class="tab-btn" data-filter="compra_pendente">
                    Compras
                </button>
                <button class="tab-btn" data-filter="pagamento">
                    Pagamentos
                </button>
                <button class="tab-btn" data-filter="estoque_baixo">
                    Estoque
                </button>
                <button class="tab-btn" data-filter="sistema">
                    Sistema
                </button>
            </div>

            <!-- Lista de Notificações -->
            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-bell-slash"></i>
                        <p>Nenhuma notificação</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <?php
                        $category_icons = [
                            'compra_pendente' => 'fa-solid fa-clock',
                            'compra_confirmada' => 'fa-solid fa-check-circle',
                            'pagamento_manual' => 'fa-solid fa-hand-holding-dollar',
                            'pagamento' => 'fa-solid fa-credit-card',
                            'estoque_baixo' => 'fa-solid fa-box-open',
                            'novo_pedido' => 'fa-solid fa-shopping-cart',
                            'sistema' => 'fa-solid fa-gear',
                            'alerta' => 'fa-solid fa-triangle-exclamation',
                            'importante' => 'fa-solid fa-star',
                        ];
                        
                        $category_classes = [
                            'compra_pendente' => 'icon-warning',
                            'compra_confirmada' => 'icon-success',
                            'pagamento_manual' => 'icon-info',
                            'pagamento' => 'icon-success',
                            'estoque_baixo' => 'icon-danger',
                            'novo_pedido' => 'icon-primary',
                            'sistema' => 'icon-secondary',
                            'alerta' => 'icon-danger',
                            'importante' => 'icon-critical',
                        ];
                        
                        $icon = $category_icons[$notif['category']] ?? 'fa-solid fa-bell';
                        $icon_class = $category_classes[$notif['category']] ?? 'icon-default';
                        ?>
                        
                        <div class="notification-item <?= $notif['status'] === 'nao_lida' ? 'unread' : '' ?>" 
                             data-id="<?= $notif['id'] ?>"
                             data-category="<?= htmlspecialchars($notif['category']) ?>"
                             data-has-reply="<?= ($notif['category'] === 'novo_pedido' || $notif['category'] === 'pagamento_manual') ? 'true' : 'false' ?>">
                            
                            <!-- Ícone por Categoria -->
                            <div class="notif-icon <?= $icon_class ?>">
                                <i class="<?= $icon ?>"></i>
                            </div>

                            <!-- Conteúdo -->
                            <div class="notif-content">
                                <div class="notif-header">
                                    <h4><?= htmlspecialchars($notif['subject']) ?></h4>
                                    <span class="notif-time"><?= htmlspecialchars($notif['formatted_date']) ?></span>
                                </div>
                                
                                <p class="notif-message">
                                    <?= htmlspecialchars(substr($notif['message'], 0, 120)) ?>
                                    <?= strlen($notif['message']) > 120 ? '...' : '' ?>
                                </p>

                                <!-- Badges de Prioridade -->
                                <?php if ($notif['priority'] === 'critica'): ?>
                                    <span class="badge badge-critical">Urgente</span>
                                <?php elseif ($notif['priority'] === 'alta'): ?>
                                    <span class="badge badge-high">Alta Prioridade</span>
                                <?php endif; ?>

                                <!-- Informações extras -->
                                <?php if (!empty($notif['order_number'])): ?>
                                    <span class="notif-meta">
                                        <i class="fa-solid fa-receipt"></i>
                                        Pedido #<?= htmlspecialchars($notif['order_number']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($notif['product_name'])): ?>
                                    <span class="notif-meta">
                                        <i class="fa-solid fa-box"></i>
                                        <?= htmlspecialchars($notif['product_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Ações -->
                            <div class="notif-actions">
                                <button class="btn-notif-action" onclick="viewNotification(<?= $notif['id'] ?>)">
                                    <i class="fa-solid fa-eye"></i>
                                    Ver
                                </button>
                                
                                <?php if ($notif['status'] === 'nao_lida'): ?>
                                    <button class="btn-notif-action" onclick="markAsRead(<?= $notif['id'] ?>)">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn-notif-action" onclick="deleteNotification(<?= $notif['id'] ?>)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>