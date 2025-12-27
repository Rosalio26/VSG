<?php
/**
 * VisionGreen - Central de Tratamento de Erros
 * Gerencia redirecionamentos silenciosos e exibição de falhas críticas.
 */

// 1. Sanitização do código vindo pela URL
$code = preg_replace('/[^a-z_]/', '', $_GET['e'] ?? 'unknown');

/* ================= 1. REGRA DE REDIRECIONAMENTO SILENCIOSO ================= */
/**
 * Se o usuário tentou acessar uma página fora de ordem (flow), 
 * usou um método errado (method) ou dispositivo não autorizado (device),
 * ele é enviado ao index.php sem ver a página de erro.
 */
$silenciar = ['flow', 'method', 'device']; 

if (in_array($code, $silenciar)) {
    header("Location: ../index.php");
    exit;
}

/* ================= 2. MAPEAMENTO DE ERROS RELEVANTES ================= */
/**
 * Apenas estes erros mostrarão a interface visual ao usuário.
 */
$map = [
    'csrf' => [
        'title' => 'Sessão Expirada',
        'msg'   => 'Por segurança, sua sessão expirou ou o token é inválido. Por favor, atualize a página e tente novamente.',
        'img'   => '../assets/img/errors/csrf.svg',
        'http'  => 403,
    ],
    'rate' => [
        'title' => 'Muitas Tentativas',
        'msg'   => 'Bloqueio temporário: detectamos muitas requisições em curto período. Aguarde alguns minutos antes de tentar novamente.',
        'img'   => '../assets/img/errors/rate.svg',
        'http'  => 429,
    ],
    'unknown' => [
        'title' => 'Ops! Algo deu errado',
        'msg'   => 'Ocorreu um erro inesperado em nossos servidores. Nossa equipe técnica já foi notificada para resolver.',
        'img'   => '../assets/img/errors/unknown.svg',
        'http'  => 500,
    ]
];

// Fallback: se o código não existir no mapa, tratamos como erro desconhecido (500)
$error = $map[$code] ?? $map['unknown'];

// Define o código HTTP real na resposta do servidor
http_response_code($error['http']);

// 3. Log interno de erros graves para o administrador
if ($error['http'] >= 429) {
    error_log("VisionGreen Error: [$code] exibido para o IP [{$_SERVER['REMOTE_ADDR']}]");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($error['title']) ?> - VisionGreen</title>

    <link rel="stylesheet" href="../assets/style/error.css">
    <link rel="stylesheet" href="../assets/style/geral.css">
</head>
<body>

<main class="error-container">
    <div class="error-card">
        <h1><?= htmlspecialchars($error['title']) ?></h1>

        <div class="cnt-act-img">
            <div class="content-img">
                <img 
                    src="<?= htmlspecialchars($error['img']) ?>" 
                    alt="Ilustração de erro" 
                    class="error-image"
                    onerror="this.src='../assets/img/errors/unknown.svg'; this.onerror=null;"
                >
            </div>
        </div>

        <p class="error-msg"><?= htmlspecialchars($error['msg']) ?></p>

        <div class="error-actions">
            <a href="javascript:history.back()" class="btn btn-secondary">Voltar</a>
            <a href="../../index.php" class="btn btn-primary">Ir para o Início</a>
        </div>
    </div>
</main>

</body>
</html>