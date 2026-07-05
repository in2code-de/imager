<?php

declare(strict_types=1);

use In2code\Imager\Controller\GeneratorModuleController;

return [
    'file_imager' => [
        'parent' => 'file',
        'position' => ['after' => 'media_management'],
        'access' => 'user',
        'path' => '/module/file/imager',
        'icon' => 'EXT:imager/Resources/Public/Icons/Extension.svg',
        'labels' => 'LLL:EXT:imager/Resources/Private/Language/Backend/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => GeneratorModuleController::class . '::handleRequest',
            ],
        ],
    ],
];
