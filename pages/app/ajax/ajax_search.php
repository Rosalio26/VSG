<?php
// ==================== AJAX SEARCH ENDPOINT (VERSÃO FINAL 3.0) ====================
// Arquivo: ajax_search.php
// Busca instantânea com 1 letra - SEM restrições AJAX

require_once __DIR__ . '/../../../registration/bootstrap.php';
require_once __DIR__ . '/../../../registration/includes/security.php';
require_once __DIR__ . '/../../../registration/includes/db.php';

// Headers JSON com CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// ==================== VALIDAÇÃO DE PARÂMETROS ====================
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 12;

// Validação básica
if (empty($search_term)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Por favor, digite algo para buscar',
        'products' => [],
        'total' => 0
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== SANITIZAÇÃO ====================
$search_term = htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8');

// ==================== CACHE SIMPLES ====================
$cache_key = 'search_' . md5($search_term . '_' . $limit);
$cache_file = sys_get_temp_dir() . '/' . $cache_key . '.json';
$cache_duration = 300; // 5 minutos

// Verificar cache
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration) {
    // Retornar do cache
    header('X-Cache: HIT');
    readfile($cache_file);
    exit;
}

header('X-Cache: MISS');

// ==================== QUERY OTIMIZADA ====================
// Preparar termo para LIKE (proteger contra SQL injection)
$search_like = '%' . $mysqli->real_escape_string($search_term) . '%';

// Query simplificada e otimizada
$main_query = "
    SELECT 
        p.id, 
        p.nome, 
        p.descricao,
        p.preco, 
        p.currency, 
        p.imagem, 
        p.image_path1,
        p.stock,
        c.name as category_name,
        c.icon as category_icon,
        u.nome as company_name,
        CASE 
            WHEN p.nome LIKE '{$search_like}' THEN 1
            WHEN c.name LIKE '{$search_like}' THEN 2
            WHEN u.nome LIKE '{$search_like}' THEN 3
            WHEN p.descricao LIKE '{$search_like}' THEN 4
            ELSE 5
        END as relevance_score
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.status = 'ativo' 
      AND p.deleted_at IS NULL
      AND (
          p.nome LIKE '{$search_like}'
          OR p.descricao LIKE '{$search_like}'
          OR c.name LIKE '{$search_like}'
          OR u.nome LIKE '{$search_like}'
          OR CAST(p.preco AS CHAR) LIKE '{$search_like}'
      )
    ORDER BY relevance_score ASC, p.stock DESC, p.id DESC
    LIMIT {$limit}
";

// ==================== EXECUTAR QUERY ====================
$result = $mysqli->query($main_query);

if (!$result) {
    error_log("Erro AJAX Search: " . $mysqli->error);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar produtos',
        'products' => [],
        'total' => 0,
        'debug' => $mysqli->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$products = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

// ==================== PROCESSAR PRODUTOS ====================
$processed_products = [];

foreach ($products as $product) {
    // Determinar URL da imagem
    $image_url = '';
    if (!empty($product['imagem'])) {
        $image_url = 'pages/uploads/products/' . htmlspecialchars($product['imagem'], ENT_QUOTES, 'UTF-8');
    } elseif (!empty($product['image_path1'])) {
        $image_url = 'pages/uploads/products/' . htmlspecialchars($product['image_path1'], ENT_QUOTES, 'UTF-8');
    } else {
        $company = urlencode($product['company_name'] ?? 'Produto');
        $image_url = "https://ui-avatars.com/api/?name={$company}&size=200&background=00b96b&color=fff&bold=true&font-size=0.3";
    }
    
    // Truncar descrição
    $descricao = $product['descricao'] ?? '';
    $descricao_curta = mb_strlen($descricao) > 80 
        ? mb_substr($descricao, 0, 80) . '...' 
        : $descricao;
    
    $processed_products[] = [
        'id' => (int)$product['id'],
        'nome' => htmlspecialchars($product['nome'], ENT_QUOTES, 'UTF-8'),
        'descricao' => htmlspecialchars($descricao_curta, ENT_QUOTES, 'UTF-8'),
        'preco' => (float)$product['preco'],
        'preco_formatado' => number_format($product['preco'], 2, ',', '.'),
        'currency' => strtoupper($product['currency'] ?? 'MZN'),
        'imagem' => $image_url,
        'stock' => (int)$product['stock'],
        'category_name' => htmlspecialchars($product['category_name'] ?? 'Geral', ENT_QUOTES, 'UTF-8'),
        'category_icon' => htmlspecialchars($product['category_icon'] ?? 'box', ENT_QUOTES, 'UTF-8'),
        'company_name' => htmlspecialchars($product['company_name'] ?? 'Fornecedor', ENT_QUOTES, 'UTF-8'),
        'url' => 'marketplace.php?product=' . (int)$product['id']
    ];
}

// ==================== RESPOSTA JSON ====================
$response = [
    'success' => true,
    'message' => count($processed_products) > 0 
        ? sprintf('Encontrados %d %s', count($processed_products), count($processed_products) === 1 ? 'produto' : 'produtos')
        : 'Nenhum produto encontrado',
    'products' => $processed_products,
    'total' => count($processed_products),
    'search_term' => htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8')
];

$json_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

// Salvar no cache
@file_put_contents($cache_file, $json_response);

// Retornar resposta
echo $json_response;
?>