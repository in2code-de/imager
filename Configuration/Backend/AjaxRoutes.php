<?php

declare(strict_types=1);

use In2code\Imager\Controller\GeneratorAjaxController;
use In2code\Imager\Controller\ImageController;

return [
    'imager_getimage' => [
        'path' => '/imager/getimage',
        'target' => ImageController::class,
        'access' => 'user,group',
        'methods' => ['POST'],
    ],
    'imager_generate' => [
        'path' => '/imager/generate',
        'target' => GeneratorAjaxController::class . '::generate',
        'access' => 'user,group',
        'methods' => ['POST'],
    ],
    'imager_save' => [
        'path' => '/imager/save',
        'target' => GeneratorAjaxController::class . '::save',
        'access' => 'user,group',
        'methods' => ['POST'],
    ],
    'imager_toggle' => [
        'path' => '/imager/toggle',
        'target' => GeneratorAjaxController::class . '::toggle',
        'access' => 'user,group',
        'methods' => ['POST'],
    ],
];
