<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Repository\Llm;

use In2code\Imager\Domain\Model\GenerationRequest;
use In2code\Imager\Domain\Model\ImageCandidate;
use TYPO3\CMS\Core\Resource\File;

interface RepositoryInterface
{
    public function checkApiKey(): void;

    public function getApiUrl(): string;

    /**
     * Generate a single image and store it directly in the configured storage.
     */
    public function getImage(string $prompt): File;

    /**
     * Generate one or more image candidates as raw data without persisting them into a storage.
     * Supports image-to-image refinement via GenerationRequest::getBaseImage().
     *
     * @return ImageCandidate[]
     */
    public function generateCandidates(GenerationRequest $request): array;
}
