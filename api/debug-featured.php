<?php
/**
 * api/debug-featured.php
 * DIAGNÓSTICO TEMPORÁRIO — apagar após resolver o problema.
 * Aceder em: https://teu-site.com/api/debug-featured.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$out = [];

/* 1. Bootstrap */
$bootstrap = dirname(__DIR__) . '/registration/bootstrap.php';
$out['bootstrap_path']   = $bootstrap;
$out['bootstrap_exists'] = file_exists($bootstrap);

if (!$out['bootstrap_exists']) {
    echo json_encode($out, JSON_PRETTY_PRINT);
    exit;
}

ob_start(); require_once $bootstrap; ob_get_clean();

/* 2. $mysqli */
$out['mysqli_exists']  = isset($mysqli);
$out['connect_errno']  = $mysqli->connect_errno ?? null;
$out['connect_error']  = $mysqli->connect_error ?? null;

if (!isset($mysqli) || $mysqli->connect_errno) {
    echo json_encode($out, JSON_PRETTY_PRINT);
    exit;
}

/* 3. Listar tabelas */
$tabs = [];
$r = $mysqli->query("SHOW TABLES");
while ($row = $r->fetch_row()) $tabs[] = $row[0];
$out['tables'] = $tabs;

/* 4. Verificar tabela products */
$out['products_table_exists'] = in_array('products', $tabs);

if ($out['products_table_exists']) {
    /* Colunas da tabela products */
    $cols = [];
    $r2 = $mysqli->query("DESCRIBE products");
    while ($row = $r2->fetch_assoc()) $cols[] = $row['Field'];
    $out['products_columns'] = $cols;

    /* Contar produtos activos */
    $r3 = $mysqli->query("SELECT COUNT(*) as n FROM products WHERE status = 'ativo'");
    $out['products_ativo_count'] = $r3 ? (int)$r3->fetch_assoc()['n'] : 'query failed: ' . $mysqli->error;

    /* Tentar buscar 1 produto qualquer */
    $r4 = $mysqli->query("SELECT * FROM products LIMIT 1");
    $out['sample_product'] = $r4 ? $r4->fetch_assoc() : 'query failed: ' . $mysqli->error;
}

/* 5. Verificar tabelas relacionadas */
foreach (['categories', 'users', 'customer_reviews'] as $t) {
    $out['table_' . $t] = in_array($t, $tabs);
}

/* 6. Colunas de businesses */
if (in_array('businesses', $tabs)) {
    $cols = [];
    $r = $mysqli->query("DESCRIBE businesses");
    while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
    $out['businesses_columns'] = $cols;
    $sample = $mysqli->query("SELECT * FROM businesses LIMIT 1");
    $out['businesses_sample'] = $sample ? $sample->fetch_assoc() : null;
}

/* 7. Colunas de categories */
if (in_array('categories', $tabs)) {
    $cols = [];
    $r = $mysqli->query("DESCRIBE categories");
    while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
    $out['categories_columns'] = $cols;
}

/* 8. Produtos com deleted_at IS NULL e status ativo */
$r5 = $mysqli->query("SELECT COUNT(*) as n FROM products WHERE status = 'ativo' AND deleted_at IS NULL");
$out['products_ativo_not_deleted'] = $r5 ? (int)$r5->fetch_assoc()['n'] : 'erro';

/* 9. Sample de produto activo e não apagado */
$r6 = $mysqli->query("SELECT id, nome, status, deleted_at, total_sales FROM products WHERE status = 'ativo' AND deleted_at IS NULL ORDER BY total_sales DESC LIMIT 3");
$out['produtos_ativos_top3'] = [];
if ($r6) while ($row = $r6->fetch_assoc()) $out['produtos_ativos_top3'][] = $row;

/* 10. Colunas customer_reviews */
if (in_array('customer_reviews', $tabs)) {
    $cols = [];
    $r = $mysqli->query("DESCRIBE customer_reviews");
    while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
    $out['customer_reviews_columns'] = $cols;
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);