<?php
// Copy this file as config.php next to index.php and replace example domains.
return [
    'public_url' => 'https://sub.example.com',
    'mode' => 'pasarguard',
    'panels' => ['marzban' => 'https://marzban.example.com/sub', 'pasarguard' => 'https://pasarguard.example.com/sub'],
    'order' => ['pasarguard'],
    'connect_timeout' => 5,
    'timeout' => 20,
    'max_bytes' => 8388608,
];
