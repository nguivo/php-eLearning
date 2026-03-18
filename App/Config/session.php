<?php

return [
    'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 120),
    'secure'   => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
];
