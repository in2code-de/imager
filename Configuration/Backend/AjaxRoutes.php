<?php

declare(strict_types=1);

use In2code\Imager\Controller\ImageController;

return [
    'imager_getimage' => [
        'path' => '/imager/getimage',
        'target' => ImageController::class,
        'access' => 'user,group',
        'methods' => ['POST'],
    ],
];
