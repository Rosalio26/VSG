<?php

function rateLimit(string $key, int $max, int $seconds): void
{
    $now = time();

    if (!isset($_SESSION['rate'])) {
        $_SESSION['rate'] = [];
    }

    if (!isset($_SESSION['rate'][$key])) {
        $_SESSION['rate'][$key] = [
            'count' => 1,
            'time' => $now
        ];
        return;
    }

    if ($now - $_SESSION['rate'][$key]['time'] > $seconds) {
        $_SESSION['rate'][$key] = [
            'count' => 1,
            'time' => $now
        ];
        return;
    }

    if ($_SESSION['rate'][$key]['count'] >= $max) {
        http_response_code(429);
        exit('Muitas requisições');
    }

    $_SESSION['rate'][$key]['count']++;
}
