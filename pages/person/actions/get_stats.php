<?php
/**
 * pages/person/actions/get_stats.php
 * Retorna estatísticas do utilizador autenticado.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../registration/includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['auth']['user_id']) || $_SESSION['auth']['type'] !== 'person') {
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

$st = $mysqli->prepare("SELECT COUNT(*) AS n FROM notifications WHERE receiver_id = ? AND status = 'nao_lida'");
$st->bind_param('i', $userId); $st->execute();
$noti = (int)$st->get_result()->fetch_assoc()['n']; $st->close();

$st = $mysqli->prepare("SELECT COUNT(*) AS n FROM orders WHERE customer_id = ? AND status IN ('pendente','confirmado','processando')");
$st->bind_param('i', $userId); $st->execute();
$pedidos = (int)$st->get_result()->fetch_assoc()['n']; $st->close();

$st = $mysqli->prepare("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE customer_id = ? AND payment_status = 'pago'");
$st->bind_param('i', $userId); $st->execute();
$gasto = (float)$st->get_result()->fetch_assoc()['n']; $st->close();

$st = $mysqli->prepare("SELECT COUNT(*) AS n FROM orders WHERE customer_id = ? AND status = 'entregue'");
$st->bind_param('i', $userId); $st->execute();
$entregues = (int)$st->get_result()->fetch_assoc()['n']; $st->close();

echo json_encode([
    'success' => true,
    'data'    => [
        'mensagens_nao_lidas'   => $noti,
        'pedidos_em_andamento'  => $pedidos,
        'total_gasto'           => $gasto,
        'entregues'             => $entregues,
    ]
], JSON_UNESCAPED_UNICODE);