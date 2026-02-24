<?php
/**
 * api/featured-product.php
 * Retorna o produto mais vendido (total_sales DESC) para o hero card.
 *
 * Estrutura real confirmada pelo debug:
 *  - products : id, user_id, category_id, nome, preco, currency,
 *               imagem, eco_badges, total_sales, status, deleted_at
 *  - categories: id, name, icon
 *  - businesses : user_id, company_name   (nome da empresa)
 *  - customer_reviews: product_id, rating, id
 */

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('Access-Control-Allow-Origin: *');

$fallback = json_encode(['error' => true]);

/* ── Cache 5 min ── */
$cache = sys_get_temp_dir() . '/vsg_featured_v4.json';
if (file_exists($cache) && (time() - filemtime($cache)) < 300) {
    echo file_get_contents($cache);
    exit;
}

/* ── Bootstrap ── */
$bootstrap = dirname(__DIR__) . '/registration/bootstrap.php';
if (!file_exists($bootstrap)) { echo $fallback; exit; }
ob_start(); require_once $bootstrap; ob_get_clean();
if (!isset($mysqli) || $mysqli->connect_errno) { echo $fallback; exit; }

/* ── Query com nomes reais das colunas ── */
try {
    $sql = "
        SELECT
            p.id,
            p.nome,
            p.preco,
            p.currency,
            p.imagem,
            p.eco_badges,
            p.total_sales,
            COALESCE(c.name, '')                  AS category_name,
            COALESCE(c.icon, '')                  AS category_icon,
            COALESCE(u.nome, '')                  AS company_name,
            COALESCE(ROUND(AVG(r.rating), 1), 0)  AS avg_rating,
            COUNT(DISTINCT r.id)                  AS review_count
        FROM products p
        LEFT JOIN categories       c ON c.id        = p.category_id
        LEFT JOIN users            u ON u.id         = p.user_id
        LEFT JOIN customer_reviews r ON r.product_id = p.id
        WHERE p.status    = 'ativo'
          AND p.deleted_at IS NULL
        GROUP BY
            p.id, p.nome, p.preco, p.currency, p.imagem,
            p.eco_badges, p.total_sales, c.name, c.icon, u.nome
        ORDER BY p.total_sales DESC, p.id DESC
        LIMIT 1
    ";

    $res = $mysqli->query($sql);
    if (!$res) throw new Exception('Query: ' . $mysqli->error);

    $row = $res->fetch_assoc();
    $res->free();

    if (!$row) throw new Exception('Nenhum produto activo encontrado');

    $data = json_encode([
        'id'            => (int)    $row['id'],
        'nome'          => (string) $row['nome'],
        'preco'         => (float)  $row['preco'],
        'currency'      => (string) ($row['currency'] ?: 'MZN'),
        'total_sales'   => (int)    $row['total_sales'],
        'imagem'        => (string) $row['imagem'],
        'category_name' => (string) $row['category_name'],
        'category_icon' => (string) ($row['category_icon'] ?: '🌿'),
        'company_name'  => (string) $row['company_name'],
        'eco_badges'    => (string) $row['eco_badges'],
        'avg_rating'    => (float)  $row['avg_rating'],
        'review_count'  => (int)    $row['review_count'],
    ]);

    file_put_contents($cache, $data);
    echo $data;

} catch (Exception $e) {
    error_log('[VSG featured] ' . $e->getMessage());
    echo $fallback;
}