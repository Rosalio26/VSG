<?php
session_start();
require_once '../../registration/includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['messages' => [], 'alerts' => []]);
    exit;
}

$adminId = $_SESSION['auth']['user_id'];

// Busca mensagens não lidas (categoria 'chat')
$sql_msgs = "SELECT n.id, 
                    IFNULL(u.nome, 'Sistema') AS sender_name, 
                    n.subject, 
                    n.priority,
                    DATE_FORMAT(n.created_at, '%d/%m %H:%i') as created_at
             FROM notifications n 
             LEFT JOIN users u ON n.sender_id = u.id 
             WHERE n.receiver_id = ? 
               AND n.status = 'unread' 
               AND n.category = 'chat'
             ORDER BY n.created_at DESC 
             LIMIT 3";

$stmt_msgs = $mysqli->prepare($sql_msgs);
$stmt_msgs->bind_param("i", $adminId);
$stmt_msgs->execute();
$res_msgs = $stmt_msgs->get_result();

$messages = [];
while ($msg = $res_msgs->fetch_assoc()) {
    $messages[] = $msg;
}
$stmt_msgs->close();

// Busca alertas não lidos
$sql_alerts = "SELECT id, 
                      category, 
                      subject, 
                      priority,
                      DATE_FORMAT(created_at, '%d/%m %H:%i') as created_at 
               FROM notifications 
               WHERE receiver_id = ? 
                 AND category IN ('alert', 'security', 'system_error', 'audit') 
                 AND status = 'unread'
               ORDER BY 
                 FIELD(priority, 'critical', 'high', 'medium', 'low'),
                 created_at DESC 
               LIMIT 5";

$stmt_alerts = $mysqli->prepare($sql_alerts);
$stmt_alerts->bind_param("i", $adminId);
$stmt_alerts->execute();
$res_alerts = $stmt_alerts->get_result();

$alerts = [];
while ($alert = $res_alerts->fetch_assoc()) {
    $alerts[] = $alert;
}
$stmt_alerts->close();

echo json_encode([
    'messages' => $messages,
    'alerts' => $alerts
]);