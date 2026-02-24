<?php
/**
 * api/stats.php
 * ─────────────────────────────────────────────────────────────────
 * Endpoint chamado pelo index.html via fetch('api/stats.php').
 * Devolve SEMPRE JSON válido — nunca HTML de erro.
 *
 * ESTRUTURA esperada no servidor:
 *   /raiz-do-projecto/
 *       index.html          ← página rosto
 *       api/
 *           stats.php       ← este ficheiro
 *       registration/
 *           bootstrap.php   ← bootstrap com $mysqli
 * ─────────────────────────────────────────────────────────────────
 */

/* ── 1. Garantir que NENHUM erro PHP "vaze" para o output ─────── */
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

/* ── 2. Headers obrigatórios ─────────────────────────────────── */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');          // 5 min cache no browser
header('Access-Control-Allow-Origin: *');

/* ── 3. Resposta de fallback (usada se algo falhar) ──────────── */
$fallback = json_encode([
    'total_products'  => 0,
    'total_suppliers' => 0,
    'total_countries' => 0,
    'avg_rating'      => 0,
    'source'          => 'fallback',
]);

/* ── 4. Cache em ficheiro (5 minutos) ────────────────────────── */
$cache_file = sys_get_temp_dir() . '/vsg_stats_api_v2.json';
$cache_ttl  = 300;

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
    echo file_get_contents($cache_file);
    exit;
}

/* ── 5. Carregar bootstrap usando caminho ABSOLUTO ───────────── */
/*
 * __DIR__  →  /caminho/absoluto/raiz/api
 * dirname(__DIR__)  →  /caminho/absoluto/raiz
 * Assim funciona independente de como o PHP foi chamado.
 */
$bootstrap = dirname(__DIR__) . '/registration/bootstrap.php';

if (!file_exists($bootstrap)) {
    // Bootstrap não encontrado — devolve fallback com mensagem de debug
    error_log('[VSG stats] bootstrap não encontrado em: ' . $bootstrap);
    echo $fallback;
    exit;
}

/* Capturar qualquer output acidental do bootstrap (ex: BOM, espaços) */
ob_start();
    require_once $bootstrap;
$bootstrap_output = ob_get_clean();

/* Verificar se $mysqli existe e está conectado */
if (!isset($mysqli) || $mysqli->connect_errno) {
    error_log('[VSG stats] $mysqli não disponível. connect_error: ' . ($mysqli->connect_error ?? 'n/a'));
    echo $fallback;
    exit;
}

/* ── 6. Query de estatísticas ────────────────────────────────── */
try {
    $sql = "
        SELECT
            (SELECT COUNT(*)
             FROM products
             WHERE status = 'ativo' AND deleted_at IS NULL)                    AS total_products,

            (SELECT COUNT(*)
             FROM users
             WHERE type = 'company' AND status = 'active')                     AS total_suppliers,

            (SELECT COUNT(DISTINCT country)
             FROM users
             WHERE country IS NOT NULL AND country != '')                      AS total_countries,

            (SELECT COALESCE(ROUND(AVG(rating), 1), 0)
             FROM customer_reviews)                                            AS avg_rating
    ";

    $result = $mysqli->query($sql);

    if (!$result) {
        throw new Exception('Query falhou: ' . $mysqli->error);
    }

    $row = $result->fetch_assoc();
    $result->free();

    $data = json_encode([
        'total_products'  => (int)   ($row['total_products']  ?? 0),
        'total_suppliers' => (int)   ($row['total_suppliers'] ?? 0),
        'total_countries' => (int)   ($row['total_countries'] ?? 0),
        'avg_rating'      => (float) ($row['avg_rating']      ?? 0),
        'source'          => 'database',
        'cached_at'       => date('c'),
    ]);

    /* Salvar no cache */
    file_put_contents($cache_file, $data);

    echo $data;

} catch (Exception $e) {
    error_log('[VSG stats] Excepção: ' . $e->getMessage());
    echo $fallback;
}