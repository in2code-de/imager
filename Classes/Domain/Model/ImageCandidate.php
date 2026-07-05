<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Model;

use In2code\Imager\Utility\ImageFormatUtility;

/**
 * Class ImageCandidate
 * value object holding the raw binary data of a generated image that has not (yet) been saved into a
 * file storage. A candidate is only persisted into the selected folder once the editor picks it.
 */
class ImageCandidate
{
    public function __construct(
        private readonly string $data,
        private readonly string $mimeType,
        private readonly string $token = '',
    ) {
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getFileExtension(): string
    {
        return ImageFormatUtility::extensionFromMimeType($this->mimeType);
    }

    public function getDataUri(): string
    {
        return 'data:' . $this->mimeType . ';base64,' . base64_encode($this->data);
    }

    public function withToken(string $token): self
    {
        return new self($this->data, $this->mimeType, $token);
    }
}
