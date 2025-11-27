<?php

declare(strict_types=1);

namespace In2code\Imager\Utility;

use Psr\Http\Message\ServerRequestInterface;

class RequestUtility
{
    public static function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}
