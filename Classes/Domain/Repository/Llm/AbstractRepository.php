<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Repository\Llm;

use In2code\Imager\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractRepository
{
    protected string $requestMethod = 'POST';
    protected string $mimeType = 'image/jpeg';

    public function __construct(
        protected StorageRepository $storageRepository,
        protected ResourceFactory $resourceFactory,
        protected RequestFactory $requestFactory,
    ) {
    }

    protected function saveImageToStorage(string $imageData, string $prompt): File
    {
        $combinedIdentifier = ConfigurationUtility::getConfigurationByKey('combinedIdentifier');
        $storage = $this->storageRepository->findByCombinedIdentifier($combinedIdentifier);
        $tempFile = $this->createTempFile($imageData);
        try {
            $this->ensureFolderExists($combinedIdentifier);
            $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
            $file = $storage->addFile($tempFile, $folder, $this->generateFileName($prompt));
        } finally {
            $this->cleanupTempFile($tempFile);
        }
        return $file;
    }

    protected function generateFileName(string $prompt): string
    {
        return sprintf('ai_generated_%d_%s.' . $this->getExtension(), time(), md5($prompt));
    }

    protected function getExtension(): string
    {
        if (stristr($this->mimeType, 'png') !== false) {
            return 'png';
        }
        return 'jpg';
    }

    protected function createTempFile(string $imageData): string
    {
        $tempFile = GeneralUtility::tempnam('imager_');
        file_put_contents($tempFile, $imageData);
        return $tempFile;
    }

    protected function cleanupTempFile(string $tempFile): void
    {
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }

    protected function ensureFolderExists(string $combinedIdentifier): void
    {
        try {
            $this->resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
        } catch (FolderDoesNotExistException $exception) {
            $storage = $this->storageRepository->findByCombinedIdentifier($combinedIdentifier);
            $basePath = $storage->getConfiguration()['basePath'];
            $parts = explode(':', $combinedIdentifier, 2);
            $path = $basePath . ltrim($parts[1], '/');
            GeneralUtility::mkdir_deep(GeneralUtility::getFileAbsFileName($path));
        }
    }
}
