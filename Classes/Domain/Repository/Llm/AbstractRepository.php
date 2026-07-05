<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Repository\Llm;

use In2code\Imager\Domain\Model\ImageCandidate;
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

    public function __construct(
        protected StorageRepository $storageRepository,
        protected ResourceFactory $resourceFactory,
        protected RequestFactory $requestFactory,
    ) {
    }

    protected function saveImageToStorage(ImageCandidate $candidate, string $prompt): File
    {
        $combinedIdentifier = ConfigurationUtility::getConfigurationByKey('combinedIdentifier');
        $storage = $this->storageRepository->findByCombinedIdentifier($combinedIdentifier);
        $tempFile = $this->createTempFile($candidate->getData());
        try {
            $this->ensureFolderExists($combinedIdentifier);
            $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
            $file = $storage->addFile($tempFile, $folder, $this->generateFileName($prompt, $candidate->getFileExtension()));
        } finally {
            $this->cleanupTempFile($tempFile);
        }
        return $file;
    }

    protected function generateFileName(string $prompt, string $extension): string
    {
        return sprintf('ai_generated_%d_%s.%s', time(), md5($prompt), $extension);
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
            GeneralUtility::unlink_tempfile($tempFile);
        }
    }

    protected function ensureFolderExists(string $combinedIdentifier): void
    {
        try {
            $this->resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
        } catch (FolderDoesNotExistException) {
            $storage = $this->storageRepository->findByCombinedIdentifier($combinedIdentifier);
            $basePath = $storage->getConfiguration()['basePath'];
            $parts = explode(':', $combinedIdentifier, 2);
            $path = $basePath . ltrim($parts[1], '/');
            GeneralUtility::mkdir_deep(GeneralUtility::getFileAbsFileName($path));
        }
    }
}
