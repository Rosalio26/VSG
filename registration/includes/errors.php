<?php

/**
 * Redireciona para a página central de erro de forma segura.
 *
 * @param string $code Código do erro (csrf, device, rate, method, flow, etc)
 */
function errorRedirect(string $code): never
{
    // Limpa qualquer output anterior para evitar conflito com headers
    if (ob_get_length()) {
        ob_clean();
    }

    // Filtra o código para evitar injeção de cabeçalho (Header Injection)
    $code = preg_replace('/[^a-z_]/', '', $code);

    // Caminho para o seu handler de erro (ajuste se necessário)
    $url = "../../public/error.php?e={$code}";

    // Se os headers ainda não foram enviados, usa o redirecionamento padrão do PHP
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } 

    // Caso o PHP já tenha enviado algo para o navegador (fallback de segurança)
    echo "<script>window.location.href='$url';</script>";
    echo "<noscript><meta http-equiv='refresh' content='0;url=$url'></noscript>";
    exit;
}