<?php

declare(strict_types=1);

namespace In2code\Imager\Events;

final class BeforeRequestEvent
{
    public function __construct(private string $apiUrl, private array $additionalOptions)
    {
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function setApiUrl(string $apiUrl): self
    {
        $this->apiUrl = $apiUrl;
        return $this;
    }

    public function getAdditionalOptions(): array
    {
        return $this->additionalOptions;
    }

    public function setAdditionalOptions(array $additionalOptions): self
    {
        $this->additionalOptions = $additionalOptions;
        return $this;
    }
}
