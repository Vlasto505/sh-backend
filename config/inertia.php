<?php

return [

    /*
    | The Vue page components live in the sibling `frontend/` repository,
    | so point Inertia's test page resolver there.
    */
    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => [
            base_path('../frontend/resources/js/Pages'),
        ],

        'page_extensions' => [
            'js', 'jsx', 'ts', 'tsx', 'vue',
        ],

    ],

];
