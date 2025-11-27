<?php

declare(strict_types=1);

namespace In2code\Imager\Events;

use TYPO3\CMS\Backend\Form\Event\CustomFileControlsEvent;

final class ButtonAllowedEvent
{
    private bool $allowed = true;

    public function __construct(private readonly CustomFileControlsEvent $event)
    {
    }

    public function getEvent(): CustomFileControlsEvent
    {
        return $this->event;
    }

    public function disallow(): self
    {
        $this->allowed = false;
        return $this;
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }
}
