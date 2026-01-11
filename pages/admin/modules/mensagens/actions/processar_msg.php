<?php
// Desativar exibição de erros na tela para não quebrar o JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../../../registration/includes/db.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
    $sender_id = $_SESSION['auth']['user_id'];
    $to_id = (int)$_POST['receiver_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (empty($to_id) || empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Campos obrigatórios vazios.']);
        exit;
    }

    // Tente preparar a query
    $stmt = $mysqli->prepare("INSERT INTO notifications (sender_id, receiver_id, subject, message, status, category) VALUES (?, ?, ?, ?, 'unread', 'chat')");
    
    if ($stmt) {
        $stmt->bind_param("iiss", $sender_id, $to_id, $subject, $message);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao executar: ' . $mysqli->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro na preparação: ' . $mysqli->error]);
    }
    exit;
}