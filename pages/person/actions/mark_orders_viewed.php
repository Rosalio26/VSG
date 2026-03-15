<?php
/**
 * pages/person/actions/mark_orders_viewed.php
 * Marca pedidos como visualizados (reset do badge).
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../registration/includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['auth']['user_id']) || $_SESSION['auth']['type'] !== 'person') {
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

// O controlo real do badge é feito no localStorage do cliente.
// Este endpoint existe para futura persistência server-side se necessário.
echo json_encode(['success' => true]);