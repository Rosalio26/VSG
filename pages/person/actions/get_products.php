<?php
session_start();
require_once '../../../registration/includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categories = isset($_GET['categories']) && $_GET['categories'] !== '' ? explode(',', $_GET['categories']) : [];
$ecoCategories = isset($_GET['eco_categories']) && $_GET['eco_categories'] !== '' ? explode(',', $_GET['eco_categories']) : [];
$priceRange = isset($_GET['price_range']) && $_GET['price_range'] !== '' ? $_GET['price_range'] : null;
$ecoCertified = isset($_GET['eco_certified']) && $_GET['eco_certified'] === '1';

$sql = "SELECT p.*, u.nome as empresa_nome, b.logo_path as empresa_logo
        FROM products p
        INNER JOIN users u ON p.user_id = u.id
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE p.status = 'ativo' 
        AND p.deleted_at IS NULL";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (p.nome LIKE ? OR p.descricao LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

if (!empty($categories)) {
    $placeholders = implode(',', array_fill(0, count($categories), '?'));
    $sql .= " AND p.categoria IN ($placeholders)";
    foreach ($categories as $cat) {
        $params[] = $cat;
        $types .= 's';
    }
}

if (!empty($ecoCategories)) {
    $placeholders = implode(',', array_fill(0, count($ecoCategories), '?'));
    $sql .= " AND p.eco_category IN ($placeholders)";
    foreach ($ecoCategories as $eco) {
        $params[] = $eco;
        $types .= 's';
    }
}

if ($priceRange) {
    list($min, $max) = explode('-', $priceRange);
    $sql .= " AND p.preco BETWEEN ? AND ?";
    $params[] = (float)$min;
    $params[] = (float)$max;
    $types .= 'dd';
}

if ($ecoCertified) {
    $sql .= " AND p.eco_verified = 1";
}

$sql .= " ORDER BY p.created_at DESC LIMIT 100";

$stmt = $mysqli->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'products' => $products,
    'total' => count($products)
]);
?>