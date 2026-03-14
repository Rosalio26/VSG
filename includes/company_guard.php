<?php
/**
 * company_guard.php — VSG Marketplace
 *
 * Incluir no TOPO de qualquer página restrita a contas 'company'.
 * Deve ser incluído APÓS session_start() e APÓS db.php.
 *
 * Uso (da raiz do projecto):
 *   require_once __DIR__ . '/includes/company_guard.php';
 *
 * Uso (de dentro de uma sub-pasta, ex: pages/person/):
 *   require_once __DIR__ . '/../../includes/company_guard.php';
 *
 * Comportamento:
 *   - Visitante (não autenticado)  → passa, sem restrição
 *   - Conta 'person' / 'admin'     → passa, sem restrição
 *   - Conta 'company'              → flash de erro + redirect para dashboard
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company') {

    $_SESSION['flash_message'] = '🚫 A tua conta empresarial não tem acesso a esta área. '
        . 'Usa o teu painel para gerir os teus produtos e encomendas.';
    $_SESSION['flash_type'] = 'error';

    /*
     * Calcular o caminho relativo para o dashboard.
     * Compara o directório do script actual com a raiz do projecto.
     * A raiz é o directório-pai deste ficheiro (includes/../ = raiz).
     */
    $guard_root   = realpath(__DIR__ . '/..');            // raiz do projecto
    $script_dir   = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
    $dashboard    = 'pages/business/dashboard_business.php';

    if ($guard_root && $script_dir) {
        // Calcular quantos níveis acima está a raiz
        $rel = str_replace($guard_root, '', $script_dir);
        $rel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        $rel = trim($rel, DIRECTORY_SEPARATOR);
        $depth = $rel === '' ? 0 : substr_count($rel, DIRECTORY_SEPARATOR) + 1;
        $prefix = $depth > 0 ? str_repeat('../', $depth) : '';
        $dashboard = $prefix . 'pages/business/dashboard_business.php';
    }

    header('Location: ' . $dashboard);
    exit;
}