<?php
// includes/get_user_stats.php

function getUserStatsWithCache($mysqli, $userId) {
    $cacheKey = "user_stats_{$userId}";
    $cacheFile = sys_get_temp_dir() . "/{$cacheKey}.json";
    $cacheLifetime = 300;
    
    if (file_exists($cacheFile)) {
        $cacheAge = time() - filemtime($cacheFile);
        if ($cacheAge < $cacheLifetime) {
            $cachedData = @file_get_contents($cacheFile);
            if ($cachedData) {
                $decoded = json_decode($cachedData, true);
                if ($decoded && is_array($decoded)) {
                    return $decoded;
                }
            }
        }
    }
    
    $stats = getUserStats($mysqli, $userId);
    @file_put_contents($cacheFile, json_encode($stats));
    return $stats;
}

function getUserStats($mysqli, $userId) {
    $stmt = $mysqli->prepare("
        SELECT 
            -- Notificações não lidas
            (SELECT COUNT(*) 
             FROM notifications 
             WHERE receiver_id = ? 
             AND status = 'nao_lida' 
             AND deleted_at IS NULL) as mensagens_nao_lidas,
            
            -- Pedidos em andamento
            (SELECT COUNT(*) 
             FROM orders 
             WHERE customer_id = ? 
             AND status IN ('pendente', 'confirmado', 'processando')
             AND deleted_at IS NULL) as pedidos_em_andamento,
            
            -- Pedidos pendentes
            (SELECT COUNT(*) 
             FROM orders 
             WHERE customer_id = ? 
             AND status = 'pendente'
             AND deleted_at IS NULL) as pedidos_pendentes,
            
            -- Total de pedidos
            (SELECT COUNT(*) 
             FROM orders 
             WHERE customer_id = ? 
             AND deleted_at IS NULL) as total_pedidos,
            
            -- Pedidos entregues
            (SELECT COUNT(*) 
             FROM orders 
             WHERE customer_id = ? 
             AND status = 'entregue'
             AND deleted_at IS NULL) as pedidos_entregues,
            
            -- Total gasto
            (SELECT COALESCE(SUM(total), 0) 
             FROM orders 
             WHERE customer_id = ? 
             AND payment_status = 'pago'
             AND deleted_at IS NULL) as total_gasto,
            
            -- Total gasto 12 meses
            (SELECT COALESCE(SUM(total), 0) 
             FROM orders 
             WHERE customer_id = ? 
             AND payment_status = 'pago'
             AND deleted_at IS NULL
             AND order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)) as total_gasto_12m,
            
            -- Favoritos (SEM deleted_at)
            (SELECT COUNT(*) 
             FROM favorites 
             WHERE user_id = ?) as total_favoritos,
            
            -- Cotações
            (SELECT COUNT(*) 
             FROM quotations 
             WHERE customer_id = ? 
             AND deleted_at IS NULL) as total_cotacoes,
            
            -- Cotações pendentes
            (SELECT COUNT(*) 
             FROM quotations 
             WHERE customer_id = ? 
             AND status = 'pendente'
             AND deleted_at IS NULL) as cotacoes_pendentes,
            
            -- Cotações respondidas
            (SELECT COUNT(*) 
             FROM quotations 
             WHERE customer_id = ? 
             AND status = 'respondida'
             AND deleted_at IS NULL) as cotacoes_respondidas,
            
            -- Notificações (já consultado acima, reutilizar)
            (SELECT COUNT(*) 
             FROM notifications 
             WHERE receiver_id = ? 
             AND status = 'nao_lida'
             AND deleted_at IS NULL) as mensagens_inbox_nao_lidas,
            
            -- Total notificações
            (SELECT COUNT(*) 
             FROM notifications 
             WHERE receiver_id = ? 
             AND deleted_at IS NULL) as total_mensagens,
            
            -- Itens no carrinho
            (SELECT COALESCE(SUM(ci.quantity), 0) 
             FROM shopping_carts sc 
             LEFT JOIN cart_items ci ON sc.id = ci.cart_id 
             WHERE sc.user_id = ? 
             AND sc.status = 'active') as carrinho_items,
            
            -- Valor carrinho
            (SELECT COALESCE(SUM(ci.quantity * ci.price), 0) 
             FROM shopping_carts sc 
             LEFT JOIN cart_items ci ON sc.id = ci.cart_id 
             WHERE sc.user_id = ? 
             AND sc.status = 'active') as carrinho_total
    ");
    
    // Bind 15 parâmetros
    $stmt->bind_param(
        'iiiiiiiiiiiiiii',
        $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, 
        $userId, $userId, $userId, $userId, $userId, $userId, $userId
    );
    
    if (!$stmt->execute()) {
        $stmt->close();
        return getDefaultStats();
    }
    
    $result = $stmt->get_result();
    
    if (!$result) {
        $stmt->close();
        return getDefaultStats();
    }
    
    $data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$data) {
        return getDefaultStats();
    }
    
    return [
        'mensagens_nao_lidas' => (int)$data['mensagens_nao_lidas'],
        'mensagens_inbox_nao_lidas' => (int)$data['mensagens_inbox_nao_lidas'],
        'total_mensagens' => (int)$data['total_mensagens'],
        'notificacoes_nao_lidas' => (int)$data['mensagens_nao_lidas'],
        'pedidos_em_andamento' => (int)$data['pedidos_em_andamento'],
        'pedidos_pendentes' => (int)$data['pedidos_pendentes'],
        'pedidos_entregues' => (int)$data['pedidos_entregues'],
        'total_pedidos' => (int)$data['total_pedidos'],
        'pedidos' => [
            'total' => (int)$data['total_pedidos'],
            'pendentes' => (int)$data['pedidos_pendentes'],
            'em_andamento' => (int)$data['pedidos_em_andamento'],
            'entregues' => (int)$data['pedidos_entregues']
        ],
        'total_gasto' => (float)$data['total_gasto'],
        'total_gasto_12m' => (float)$data['total_gasto_12m'],
        'total_cotacoes' => (int)$data['total_cotacoes'],
        'cotacoes_pendentes' => (int)$data['cotacoes_pendentes'],
        'cotacoes_respondidas' => (int)$data['cotacoes_respondidas'],
        'cotacoes' => [
            'total' => (int)$data['total_cotacoes'],
            'pendentes' => (int)$data['cotacoes_pendentes'],
            'respondidas' => (int)$data['cotacoes_respondidas']
        ],
        'total_devolucoes' => 0,
        'devolucoes_pendentes' => 0,
        'devolucoes' => ['total' => 0, 'pendentes' => 0],
        'favoritos' => (int)$data['total_favoritos'],
        'total_favoritos' => (int)$data['total_favoritos'],
        'carrinho_items' => (int)$data['carrinho_items'],
        'carrinho_total' => (float)$data['carrinho_total'],
        'cache_timestamp' => time(),
        'cache_datetime' => date('Y-m-d H:i:s')
    ];
}

function getDefaultStats() {
    return [
        'mensagens_nao_lidas' => 0,
        'mensagens_inbox_nao_lidas' => 0,
        'total_mensagens' => 0,
        'notificacoes_nao_lidas' => 0,
        'pedidos_em_andamento' => 0,
        'pedidos_pendentes' => 0,
        'pedidos_entregues' => 0,
        'total_pedidos' => 0,
        'pedidos' => ['total' => 0, 'pendentes' => 0, 'em_andamento' => 0, 'entregues' => 0],
        'total_gasto' => 0.0,
        'total_gasto_12m' => 0.0,
        'total_cotacoes' => 0,
        'cotacoes_pendentes' => 0,
        'cotacoes_respondidas' => 0,
        'cotacoes' => ['total' => 0, 'pendentes' => 0, 'respondidas' => 0],
        'total_devolucoes' => 0,
        'devolucoes_pendentes' => 0,
        'devolucoes' => ['total' => 0, 'pendentes' => 0],
        'favoritos' => 0,
        'total_favoritos' => 0,
        'carrinho_items' => 0,
        'carrinho_total' => 0.0,
        'cache_timestamp' => time(),
        'cache_datetime' => date('Y-m-d H:i:s')
    ];
}

function clearUserStatsCache($userId) {
    $cacheFile = sys_get_temp_dir() . "/user_stats_{$userId}.json";
    return file_exists($cacheFile) ? @unlink($cacheFile) : true;
}

function refreshUserStats($mysqli, $userId) {
    clearUserStatsCache($userId);
    return getUserStatsWithCache($mysqli, $userId);
}