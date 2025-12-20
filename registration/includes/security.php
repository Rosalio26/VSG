<?php

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

header(
    "Content-Security-Policy: default-src 'self'; " .
    "script-src 'self'; " .
    "style-src 'self';"
);
