<?php

return [
    'driver'   => $_ENV['MAIL_DRIVER']       ?? 'smtp',
    'host'     => $_ENV['MAIL_HOST']         ?? 'localhost',
    'port'     => (int) ($_ENV['MAIL_PORT']  ?? 25),
    'username' => $_ENV['MAIL_USERNAME']     ?? '',
    'password' => $_ENV['MAIL_PASSWORD']     ?? '',
    'from'     => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@elearning.com',
        'name'    => $_ENV['MAIL_FROM_NAME']    ?? 'eLearning Platform',
    ],
];
