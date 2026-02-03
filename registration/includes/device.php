<?php

/**
 * Detecta o dispositivo, navegador e sistema operacional do usuário
 * Retorna array com informações detalhadas
 */
function detectDevice(): array
{
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

    // Detecção de Mobile e Tablet
    $isMobileUA = (bool)preg_match(
        '/(android|iphone|ipod|blackberry|windows phone|iemobile|opera mini|mobile)/',
        $ua
    );

    $isTabletUA = (bool)preg_match('/(ipad|tablet|playbook|silk)|(android(?!.*mobile))/', $ua);

    // Detecção de Navegador (ordem importa!)
    $browser = 'unknown';
    if (strpos($ua, 'edg/') !== false)       $browser = 'edge';
    elseif (strpos($ua, 'opr/') !== false)   $browser = 'opera';
    elseif (strpos($ua, 'chrome') !== false) $browser = 'chrome';
    elseif (strpos($ua, 'firefox') !== false)$browser = 'firefox';
    elseif (strpos($ua, 'safari') !== false) $browser = 'safari';

    // Sistema Operacional
    $os = 'unknown';
    if (strpos($ua, 'android') !== false)     $os = 'android';
    elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) $os = 'ios';
    elseif (strpos($ua, 'windows nt') !== false) $os = 'windows';
    elseif (strpos($ua, 'macintosh') !== false || strpos($ua, 'mac os x') !== false) $os = 'mac';
    elseif (strpos($ua, 'linux') !== false)   $os = 'linux';

    // Classificação final
    $isActuallyMobile = $isMobileUA && !$isTabletUA;
    $isDesktop = !$isActuallyMobile && !$isTabletUA;

    return [
        'isMobile'  => $isActuallyMobile,
        'isTablet'  => $isTabletUA,
        'isDesktop' => $isDesktop,
        'browser'   => $browser,
        'os'        => $os,
        'ua'        => $ua,
    ];
}

/**
 * Verifica se o dispositivo é permitido para cadastro business
 * Business requer: Desktop E largura mínima de 1080px
 * 
 * @return bool True se é permitido para business
 */
function isBusinessDeviceAllowed(): bool
{
    $device = detectDevice();
    
    // Regra 1: Deve ser desktop (não mobile, não tablet)
    if (!$device['isDesktop']) {
        return false;
    }
    
    // Regra 2: Sistemas permitidos
    $allowedOS = ['windows', 'mac', 'linux'];
    if (!in_array($device['os'], $allowedOS, true)) {
        return false;
    }
    
    return true;
}

/**
 * Retorna os tipos de cadastro permitidos baseado no dispositivo
 * 
 * @return array Lista de tipos permitidos: ['business', 'pessoal'] ou ['pessoal']
 */
function getTiposPermitidos(): array
{
    $device = detectDevice();
    
    // Mobile e Tablet: APENAS pessoa
    if ($device['isMobile'] || $device['isTablet']) {
        return ['pessoal'];
    }
    
    // Desktop: AMBOS (mas JS validará largura de tela)
    return ['business', 'pessoal'];
}