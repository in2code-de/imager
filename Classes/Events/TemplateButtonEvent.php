<?php

declare(strict_types=1);

namespace In2code\Imager\Events;

use TYPO3\CMS\Backend\Form\Event\CustomFileControlsEvent;

final class TemplateButtonEvent
{
    private array $templates = [
        'EXT:imager/Resources/Private/Templates/Backend',
    ];
    private array $partials = [
        'EXT:imager/Resources/Private/Partials/Backend',
    ];
    private array $layouts = [
        'EXT:imager/Resources/Private/Layouts/Backend',
    ];
    private array $additionialAssignments = [];

    public function getTemplates(): array
    {
        return $this->templates;
    }

    public function setTemplates(array $templates): self
    {
        $this->templates = $templates;
        return $this;
    }

    public function addTemplate(string $path): self
    {
        $this->templates[] = $path;
        return $this;
    }

    public function getPartials(): array
    {
        return $this->partials;
    }

    public function setPartials(array $partials): self
    {
        $this->partials = $partials;
        return $this;
    }

    public function addPartial(string $path): self
    {
        $this->partials[] = $path;
        return $this;
    }

    public function getLayouts(): array
    {
        return $this->layouts;
    }

    public function setLayouts(array $layouts): self
    {
        $this->layouts = $layouts;
        return $this;
    }

    public function addLayout(string $path): self
    {
        $this->layouts[] = $path;
        return $this;
    }

    public function getAdditionialAssignments(): array
    {
        return $this->additionialAssignments;
    }

    public function setAdditionialAssignments(array $additionialAssignments): self
    {
        $this->additionialAssignments = $additionialAssignments;
        return $this;
    }
}
