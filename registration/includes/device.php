<?php

function detectDevice(): array
{
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

    $isMobileUA = preg_match(
        '/android|iphone|ipod|ipad|blackberry|windows phone|mobile/',
        $ua
    );

    $isTabletUA = preg_match('/ipad|tablet/', $ua);

    $isDesktopUA = !$isMobileUA;

    // Navegadores
    $browser = 'unknown';
    if (strpos($ua, 'chrome') !== false) $browser = 'chrome';
    elseif (strpos($ua, 'firefox') !== false) $browser = 'firefox';
    elseif (strpos($ua, 'safari') !== false) $browser = 'safari';
    elseif (strpos($ua, 'edge') !== false) $browser = 'edge';

    // Sistema operacional
    $os = 'unknown';
    if (strpos($ua, 'android') !== false) $os = 'android';
    elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) $os = 'ios';
    elseif (strpos($ua, 'windows') !== false) $os = 'windows';
    elseif (strpos($ua, 'mac os') !== false) $os = 'mac';
    elseif (strpos($ua, 'linux') !== false) $os = 'linux';

    return [
        'isMobile'  => (bool)$isMobileUA && !$isTabletUA,
        'isTablet'  => (bool)$isTabletUA,
        'isDesktop' => (bool)$isDesktopUA,
        'browser'   => $browser,
        'os'        => $os,
        'ua'        => $ua,
    ];
}
