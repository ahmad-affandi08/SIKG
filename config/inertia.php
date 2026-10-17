<?php

declare(strict_types=1);

return [
    // Tes memastikan komponen halaman yang dirender benar-benar ada di resources/js/Pages (berkas .tsx).
    'pages' => [
        'paths' => [
            resource_path('js/Pages'),
        ],
        'extensions' => ['tsx'],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],
];
