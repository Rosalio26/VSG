<?php
session_start();
require_once '../../../registration/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

$userId = (int)$_SESSION['auth']['user_id'];
$uploadDir = "../../../registration/uploads/business/";

function uploadNovo($fileKey, $prefix, $dir) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    $newName = $prefix . "_RE_" . bin2hex(random_bytes(10)) . "." . $ext;
    return move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dir . $newName) ? $newName : null;
}

$novoAlvara = uploadNovo('licenca', 'lic', $uploadDir);
$novoTax = uploadNovo('tax_id_file', 'tax', $uploadDir);

if ($novoAlvara) {
    // Atualiza o alvará e volta o status para PENDENTE
    $sql = "UPDATE businesses SET license_path = ?, status_documentos = 'pendente', motivo_rejeicao = NULL WHERE user_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('si', $novoAlvara, $userId);
    $stmt->execute();
}

if ($novoTax) {
    // Se o utilizador também reenviou o Tax File
    $finalTax = "FILE:" . $novoTax;
    $stmt = $mysqli->prepare("UPDATE businesses SET tax_id = ? WHERE user_id = ?");
    $stmt->bind_param('si', $finalTax, $userId);
    $stmt->execute();
}

// Redireciona de volta para o Dashboard, que agora mostrará o banner de "Em análise"
header("Location: ../dashboard_business.php");
exit;