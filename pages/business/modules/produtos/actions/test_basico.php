<?php
/**
 * TESTE BÁSICO - Identificar problema
 * Salvar como: pages/business/modules/produtos/actions/test_basico.php
 */

// Mostrar TODOS os erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TESTE BÁSICO ===\n\n";

// 1. PHP funciona?
echo "✅ PHP funcionando\n";
echo "Versão PHP: " . PHP_VERSION . "\n\n";

// 2. Sessão funciona?
session_start();
echo "✅ Sessão iniciada\n\n";

// 3. Simular user_id
$_SESSION['auth']['user_id'] = 999;
echo "✅ User ID definido: " . $_SESSION['auth']['user_id'] . "\n\n";

// 4. Testar caminhos do DB
echo "=== TESTANDO CAMINHOS DB ===\n";

$db_paths = [
    __DIR__ . '/../../../../registration/includes/db.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/registration/includes/db.php',
    __DIR__ . '/../../../../../registration/includes/db.php',
];

foreach ($db_paths as $i => $path) {
    $exists = file_exists($path);
    echo ($i+1) . ". " . $path . "\n";
    echo "   Existe: " . ($exists ? 'SIM ✅' : 'NÃO ❌') . "\n";
    if ($exists) {
        echo "   Este é o caminho correto!\n";
    }
    echo "\n";
}

// 5. Tentar conectar
echo "=== TENTANDO CONECTAR AO BANCO ===\n";
$connected = false;

foreach ($db_paths as $path) {
    if (file_exists($path)) {
        echo "Tentando: $path\n";
        try {
            require_once $path;
            if (isset($mysqli)) {
                echo "✅ CONEXÃO ESTABELECIDA!\n";
                echo "Host: " . $mysqli->host_info . "\n";
                $connected = true;
                break;
            }
        } catch (Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "\n";
        }
    }
}

if (!$connected) {
    echo "❌ NÃO FOI POSSÍVEL CONECTAR\n";
}

echo "\n=== TESTANDO PASTA DE UPLOAD ===\n";

$upload_dir = __DIR__ . '/../../../../uploads/products/';
echo "Caminho: $upload_dir\n";
echo "Existe: " . (is_dir($upload_dir) ? 'SIM ✅' : 'NÃO ❌') . "\n";
echo "Permissões: " . (is_writable($upload_dir) ? 'ESCRITA OK ✅' : 'SEM PERMISSÃO ❌') . "\n";

echo "\n=== FIM DO TESTE ===\n";