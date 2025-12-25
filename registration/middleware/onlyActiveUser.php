<?php
require_once '../includes/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$stmt = $mysqli->prepare("
    SELECT status
    FROM users
    WHERE id = ?
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$status = $stmt->get_result()->fetch_assoc()['status'] ?? '';

if ($status !== 'active') {
    header('Location: /pending.php');
    exit;
}
