<?php

declare(strict_types=1);

namespace In2code\Imager\EventListener;

use In2code\Imager\Domain\Service\AiAreaPreference;
use In2code\Imager\Domain\Service\FolderImageService;
use In2code\Imager\Utility\ConfigurationUtility;
use In2code\Imager\Utility\RequestUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\AbstractDropDownItem;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownDivider;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownToggle;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDownButton;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FilelistAiAreaEventListener
 * integrates the AI image generation into the core file list module: it adds a "Show AI area" toggle
 * to the existing "View" dropdown and - when that is the file list module - loads the JavaScript, CSS,
 * labels and settings that drive the AI area rendered on top of the file list.
 */
#[AsEventListener(
    identifier: 'imager/filelist-ai-area-event-listener',
    event: ModifyButtonBarEvent::class,
)]
class FilelistAiAreaEventListener
{
    private const MODULE_IDENTIFIER = 'media_management';
    private const LLL = 'LLL:EXT:imager/Resources/Private/Language/Backend/locallang.xlf:';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly IconFactory $iconFactory,
        private readonly AiAreaPreference $aiAreaPreference,
        private readonly FolderImageService $folderImageService,
    ) {
    }

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        $request = RequestUtility::getRequest();
        if ($this->isFileListModule($request) === false) {
            return;
        }
        $viewDropDown = $this->findViewDropDownButton($event->getButtons());
        if ($viewDropDown === null) {
            return;
        }
        $enabled = $this->isEnabled();
        $this->addToggle($viewDropDown, $enabled);
        $this->loadAssets($request, $enabled);
    }

    private function isFileListModule(?ServerRequestInterface $request): bool
    {
        return $request !== null
            && $request->getAttribute('module')?->getIdentifier() === self::MODULE_IDENTIFIER;
    }

    private function isEnabled(): bool
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        return $backendUser instanceof BackendUserAuthentication
            && $this->aiAreaPreference->isEnabled($backendUser);
    }

    private function addToggle(DropDownButton $viewDropDown, bool $enabled): void
    {
        $label = $this->getLanguageService()->sL(self::LLL . 'filelist.showAiArea');
        $viewDropDown->addItem(GeneralUtility::makeInstance(DropDownDivider::class));
        $viewDropDown->addItem(
            GeneralUtility::makeInstance(DropDownToggle::class)
                ->setActive($enabled)
                ->setHref('#')
                ->setLabel($label)
                ->setTitle($label)
                ->setIcon($this->iconFactory->getIcon('actions-image', IconSize::SMALL))
                ->setAttributes(['data-imager-ai-toggle' => '1'])
        );
    }

    private function loadAssets(ServerRequestInterface $request, bool $enabled): void
    {
        $this->pageRenderer->loadJavaScriptModule('@in2code/imager/Filelist.js');
        $this->pageRenderer->addCssFile('EXT:imager/Resources/Public/Css/Filelist.css');
        $this->pageRenderer->addInlineLanguageLabelFile(
            'EXT:imager/Resources/Private/Language/Backend/locallang.xlf'
        );
        $this->pageRenderer->addInlineSetting('imager', 'showAiArea', $enabled ? '1' : '0');
        $this->pageRenderer->addInlineSetting(
            'imager',
            'writable',
            $this->isCurrentFolderWritable($request) ? '1' : '0'
        );
        $this->pageRenderer->addInlineSetting('imager', 'defaultPrompt', $this->resolveText('promptValue'));
        $this->pageRenderer->addInlineSetting('imager', 'promptPlaceholder', $this->resolveText('promptPlaceholder'));
    }

    private function isCurrentFolderWritable(ServerRequestInterface $request): bool
    {
        $body = $request->getParsedBody();
        $bodyId = is_array($body) ? ($body['id'] ?? null) : null;
        $folderId = (string)($request->getQueryParams()['id'] ?? $bodyId ?? '');
        $folder = $this->folderImageService->resolveFolder($folderId);
        return $folder !== null && $this->folderImageService->isWritable($folder);
    }

    private function resolveText(string $key): string
    {
        return $this->getLanguageService()->sL(ConfigurationUtility::getConfigurationByKey($key));
    }

    /**
     * @param array<string, array<int, array<int, mixed>>> $buttons
     */
    private function findViewDropDownButton(array $buttons): ?DropDownButton
    {
        foreach ($buttons as $groups) {
            foreach ($groups as $groupButtons) {
                foreach ($groupButtons as $button) {
                    if ($button instanceof DropDownButton && $this->isViewDropDown($button)) {
                        return $button;
                    }
                }
            }
        }
        return null;
    }

    private function isViewDropDown(DropDownButton $button): bool
    {
        $isView = false;
        foreach ($button->getItems() as $item) {
            if ($item instanceof AbstractDropDownItem && $this->isViewModeHref((string)$item->getHref())) {
                $isView = true;
                break;
            }
        }
        return $isView;
    }

    private function isViewModeHref(string $href): bool
    {
        return str_contains($href, 'viewMode')
            || str_contains($href, 'displayThumbs')
            || str_contains($href, 'clipBoard');
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
