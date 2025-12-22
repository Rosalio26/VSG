<?php
require_once '../includes/db.php';
session_start();

if (empty($_SESSION['user_id'])) {
    die('Sessão inválida');
}

$userId = $_SESSION['user_id'];

$stmt = $mysqli->prepare("
    UPDATE users
    SET status = 'pending',
        registration_step = 'completed'
    WHERE id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();

/* encerra sessão UX */
session_destroy();

header('Location: ../../dashboard');
exit;
