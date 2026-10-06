<?php
// Copy to config.php for manual installation. Panel URLs include the /sub prefix.
return [
    'public_url' => 'https://sub.example.com',
    'mode' => 'dual',
    'panels' => ['marzban' => 'https://marzban.example.com/sub', 'pasarguard' => 'https://pasarguard.example.com/sub'],
    'order' => ['marzban', 'pasarguard'],
    'connect_timeout' => 5,
    'timeout' => 20,
    'max_bytes' => 8388608,
];
