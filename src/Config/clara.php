<?php 

return [
    'app_store_url' => 'http://example.domain/',
    'global' => true,
    'routes' => false,
    'logs' => [
        'dir' => storage_path('/logs/clara/'),
        'days' => 10,
        'text' => true,
        'json' => true,
    ]
];