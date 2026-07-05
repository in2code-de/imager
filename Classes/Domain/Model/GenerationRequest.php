<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Model;

/**
 * Class GenerationRequest
 * value object describing a single image generation request. Bundles all parameters into one
 * object to keep the repository method signatures slim and to support image-to-image refinement.
 */
class GenerationRequest
{
    public function __construct(
        private readonly string $prompt,
        private readonly int $count = 1,
        private readonly ?ImageCandidate $baseImage = null,
    ) {
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getBaseImage(): ?ImageCandidate
    {
        return $this->baseImage;
    }

    public function hasBaseImage(): bool
    {
        return $this->baseImage !== null;
    }
}
