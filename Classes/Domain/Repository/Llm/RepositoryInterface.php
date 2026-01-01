<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Repository\Llm;

use TYPO3\CMS\Core\Resource\File;

interface RepositoryInterface
{
    public function checkApiKey(): void;
    public function getApiUrl(): string;
    public function getImage(string $prompt): File;
}
