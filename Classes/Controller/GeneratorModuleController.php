<?php

declare(strict_types=1);

namespace In2code\Imager\Controller;

use In2code\Imager\Domain\Service\FolderImageService;
use In2code\Imager\Utility\ConfigurationUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class GeneratorModuleController
 * renders the backend module below "File" that lets editors generate AI images into a selected folder.
 */
#[AsController]
class GeneratorModuleController
{
    private const LLL_MODULE = 'LLL:EXT:imager/Resources/Private/Language/Backend/locallang_mod.xlf';
    private const PREVIEW_SIZE = 300;

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly PageRenderer $pageRenderer,
        private readonly FolderImageService $folderImageService,
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->getLanguageService()->sL(self::LLL_MODULE . ':mlang_tabs_tab'));
        $this->loadAssets();

        $combinedIdentifier = (string)($request->getQueryParams()['id'] ?? $request->getParsedBody()['id'] ?? '');
        $folder = $this->folderImageService->resolveFolder($combinedIdentifier);
        if ($folder === null) {
            $view->assign('folderSelected', false);
        } else {
            $view->assignMultiple([
                'folderSelected' => true,
                'folderIdentifier' => $folder->getCombinedIdentifier(),
                'folderName' => $folder->getReadablePath(),
                'writable' => $this->folderImageService->isWritable($folder),
                'images' => $this->prepareImages($folder),
                'defaultPrompt' => $this->resolveConfiguredText('promptValue'),
                'promptPlaceholder' => $this->resolveConfiguredText('promptPlaceholder'),
            ]);
        }
        return $view->renderResponse('GeneratorModule');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function prepareImages(Folder $folder): array
    {
        $images = [];
        foreach ($this->folderImageService->getImages($folder) as $file) {
            $preview = $file->process(
                ProcessedFile::CONTEXT_IMAGEPREVIEW,
                ['width' => self::PREVIEW_SIZE, 'height' => self::PREVIEW_SIZE]
            );
            $images[] = [
                'name' => $file->getName(),
                'thumbnail' => $preview->getPublicUrl(),
                'url' => $file->getPublicUrl(),
                'size' => GeneralUtility::formatSize($file->getSize()),
            ];
        }
        return $images;
    }

    private function resolveConfiguredText(string $key): string
    {
        return $this->getLanguageService()->sL(ConfigurationUtility::getConfigurationByKey($key));
    }

    private function loadAssets(): void
    {
        $this->pageRenderer->loadJavaScriptModule('@in2code/imager/Module.js');
        $this->pageRenderer->addCssFile('EXT:imager/Resources/Public/Css/Module.css');
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
