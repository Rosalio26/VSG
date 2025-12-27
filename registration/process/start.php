<?php

// O bootstrap e o security já devem gerenciar a sessão e cabeçalhos básicos
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/rate_limit.php';

/* ================= RATE LIMIT ================= */
// Protege contra bots que tentam iniciar milhares de sessões
rateLimit('start_form', 5, 60); 

/* ================= MÉTODO ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorRedirect('method');
}

/* ================= ORIGEM (Anti-Spam) ================= */
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$expectedHost = $_SERVER['HTTP_HOST'] ?? '';
// Verifica se o formulário veio realmente do seu próprio domínio
if (empty($referer) || strpos($referer, $expectedHost) === false) {
    errorRedirect('flow');
}

/* ================= CSRF ================= */
// Valida o token gerado na página inicial ou botão de cadastro
if (!csrf_validate($_POST['csrf'] ?? '')) {
    errorRedirect('csrf');
}

/* ================= ESTADO DA SESSÃO ================= */
// Se o usuário já iniciou e não terminou, apenas redireciona para onde parou
if (isset($_SESSION['cadastro']['started'])) {
    header('Location: ../register/painel_cadastro.php');
    exit;
}

/* ================= INICIALIZAÇÃO DO FLUXO ================= */
// Limpa qualquer resquício de sessões anteriores para evitar conflitos
unset($_SESSION['auth']);
unset($_SESSION['user_id']);

$_SESSION['cadastro'] = [
    'started' => true,
    'at'      => time(),
    'step'    => 'initial'
];

// Opcional: Aqui você já pode injetar o Fingerprint inicial se desejar
// para que o middleware de dispositivo tenha uma base de comparação sólida.

/* ================= REDIRECIONAMENTO ================= */
// Garante que nenhum caractere extra quebre o redirecionamento
if (ob_get_length()) {
    ob_clean();
}

header('Location: ../register/painel_cadastro.php');
exit;