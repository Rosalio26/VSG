<?php

/**
 * Detecta o dispositivo, navegador e sistema operacional do usuário
 * com base no User Agent.
 */
function detectDevice(): array
{
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

    // Detecção básica de Mobile e Tablet
    $isMobileUA = (bool)preg_match(
        '/(android|iphone|ipod|blackberry|windows phone|iemobile|opera mini|mobile)/',
        $ua
    );

    // Tablets costumam ter strings específicas ou 'android' sem 'mobile'
    $isTabletUA = (bool)preg_match('/(ipad|tablet|playbook|silk)|(android(?!.*mobile))/', $ua);

    // Lógica de Navegador (A ordem aqui importa muito!)
    $browser = 'unknown';
    if (strpos($ua, 'edg/') !== false)      $browser = 'edge';   // 'edg/' é a string do Edge moderno
    elseif (strpos($ua, 'opr/') !== false)  $browser = 'opera';
    elseif (strpos($ua, 'chrome') !== false) $browser = 'chrome';
    elseif (strpos($ua, 'firefox') !== false)$browser = 'firefox';
    elseif (strpos($ua, 'safari') !== false) $browser = 'safari';

    // Sistema operacional
    $os = 'unknown';
    if (strpos($ua, 'android') !== false)     $os = 'android';
    elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) $os = 'ios';
    elseif (strpos($ua, 'windows nt') !== false) $os = 'windows';
    elseif (strpos($ua, 'macintosh') !== false || strpos($ua, 'mac os x') !== false) $os = 'mac';
    elseif (strpos($ua, 'linux') !== false)   $os = 'linux';

    // Um dispositivo é desktop se não for mobile nem tablet
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