<?php

declare(strict_types=1);

namespace In2code\Imager\EventListener;

use In2code\Imager\Events\ButtonAllowedEvent;
use In2code\Imager\Utility\ConfigurationUtility;
use In2code\Imager\Utility\RequestUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Form\Event\CustomFileControlsEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

#[AsEventListener(
    identifier: 'imager/file-controls-event-listener',
    event: CustomFileControlsEvent::class,
)]
class FileControlsEventListener
{
    public function __construct(
        protected readonly PageRenderer $pageRenderer,
        protected readonly IconFactory $iconFactory,
        protected readonly ViewFactoryInterface $viewFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(CustomFileControlsEvent $event): void
    {
        if ($this->isActivated($event)) {
            $resultArray = $event->getResultArray();
            $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@in2code/imager/Backend.js');
            $event->setResultArray($resultArray);
            $event->addControl($this->getButtonHtml($event), $event->getFieldName() . '_imager');
        }
    }

    protected function getButtonHtml(CustomFileControlsEvent $event): string
    {
        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: ['EXT:imager/Resources/Private/Templates/Backend'],
            request: RequestUtility::getRequest(),
        );
        $view = $this->viewFactory->create($viewFactoryData);
        $view->assignMultiple([
            'event' => $event,
            'promptPlaceholder' => ConfigurationUtility::getConfigurationByKey('promptPlaceholder'),
            'promptValue' => ConfigurationUtility::getConfigurationByKey('promptValue'),
        ]);
        return $view->render('FileButton');
    }

    protected function isActivated(CustomFileControlsEvent $event): bool
    {
        /** @var ButtonAllowedEvent $eventButtonAllowed */
        $eventButtonAllowed = $this->eventDispatcher->dispatch(new ButtonAllowedEvent($event));
        return $eventButtonAllowed->isAllowed()
            && $this->isFileType($event)
            && $this->isFileReferenceField($event)
            && $this->isImageType($event);
    }

    protected function isFileType(CustomFileControlsEvent $event): bool
    {
        return ($event->getFieldConfig()['type'] ?? '') === 'file';
    }

    protected function isFileReferenceField(CustomFileControlsEvent $event): bool
    {
        return ($event->getFieldConfig()['foreign_table'] ?? '') === 'sys_file_reference';
    }

    protected function isImageType(CustomFileControlsEvent $event): bool
    {
        $allowed = [
            'png',
            'jpg',
            'jpeg',
            'webp',
        ];
        $formatList = $event->getFieldConfig()['allowed'] ?? $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];
        $intersection = array_intersect($allowed, GeneralUtility::trimExplode(',', $formatList, true));
        return $intersection !== [];
    }
}
