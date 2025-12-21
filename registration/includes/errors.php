<?php

/**
 * Redireciona para a página central de erro
 *
 * @param string $code Código do erro (csrf, device, rate, method, flow, etc)
 */
function errorRedirect(string $code): never
{
    // Limpa qualquer output anterior
    if (ob_get_length()) {
        ob_clean();
    }

    // Redireciona SEMPRE para o error handler
    header("Location: ../../public/error.php?e={$code}");
    exit;
}
