<?php
// =====================================================
// ERROR HANDLER CENTRALIZADO
// =====================================================

// Sanitiza o código de erro vindo por GET
$code = preg_replace('/[^a-z_]/', '', $_GET['e'] ?? 'unknown');

// Mapeamento de erros do sistema
$map = [
    'csrf' => [
        'title' => 'Sessão expirada',
        'msg'   => 'Por segurança, sua sessão expirou. Atualize a página e tente novamente.',
        'img'   => '/assets/img/errors/csrf.svg',
        'http'  => 403,
    ],
    'device' => [
        'title' => 'Dispositivo não permitido',
        'msg'   => 'Este tipo de dispositivo não pode acessar esta funcionalidade.',
        'img'   => '/assets/img/errors/device.svg',
        'http'  => 403,
    ],
    'rate' => [
        'title' => 'Muitas tentativas',
        'msg'   => 'Você realizou muitas tentativas em pouco tempo. Aguarde alguns minutos.',
        'img'   => '/assets/img/errors/rate.svg',
        'http'  => 429,
    ],
    'method' => [
        'title' => 'Acesso inválido',
        'msg'   => 'Esta página não pode ser acessada diretamente.',
        'img'   => '/assets/img/errors/method.svg',
        'http'  => 405,
    ],
    'flow' => [
        'title' => 'Fluxo inválido',
        'msg'   => 'Você tentou acessar uma etapa fora da ordem correta.',
        'img'   => '/assets/img/errors/flow.svg',
        'http'  => 403,
    ],
];

// Erro padrão (fallback)
$error = $map[$code] ?? [
    'title' => 'Erro inesperado',
    'msg'   => 'Algo deu errado. Por favor, tente novamente mais tarde.',
    'img'   => '/assets/img/errors/unknown.svg',
    'http'  => 500,
];

// Define o HTTP Status Code correto
http_response_code($error['http']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($error['title']) ?></title>

    <!-- CSS da página de erro -->
    <link rel="stylesheet" href="/assets/css/error.css">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>

<main class="error-container">

    <h1><?= htmlspecialchars($error['title']) ?></h1>

    <img
        src="<?= htmlspecialchars($error['img']) ?>"
        alt="<?= htmlspecialchars($error['title']) ?>"
        class="error-image"
    >

    <p><?= htmlspecialchars($error['msg']) ?></p>

    <div class="error-actions">
        <a href="" class="btn">Voltar para o início</a>
    </div>

</main>

</body>
</html>
