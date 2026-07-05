<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Service;

use In2code\Imager\Domain\Model\ImageCandidate;
use In2code\Imager\Exception\FolderAccessException;
use In2code\Imager\Utility\ImageFormatUtility;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FolderImageService
 * provider independent handling of folders and images: resolving an accessible folder from a combined
 * identifier, listing the images inside a folder and persisting a picked candidate into that folder.
 */
class FolderImageService
{
    public function __construct(
        private readonly ResourceFactory $resourceFactory,
    ) {
    }

    public function resolveFolder(string $combinedIdentifier): ?Folder
    {
        $folder = null;
        if ($combinedIdentifier !== '') {
            try {
                $candidate = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
                if (
                    $candidate->getStorage()->isWithinFileMountBoundaries($candidate)
                    && $candidate->checkActionPermission('read')
                ) {
                    $folder = $candidate;
                }
            } catch (\Throwable) {
                $folder = null;
            }
        }
        return $folder;
    }

    /**
     * @return File[]
     */
    public function getImages(Folder $folder): array
    {
        $images = [];
        foreach ($folder->getFiles() as $file) {
            if (in_array(strtolower($file->getExtension()), ImageFormatUtility::ALLOWED_EXTENSIONS, true)) {
                $images[] = $file;
            }
        }
        return $images;
    }

    public function isWritable(Folder $folder): bool
    {
        return $folder->checkActionPermission('write');
    }

    public function saveCandidate(ImageCandidate $candidate, Folder $folder): File
    {
        if ($this->isWritable($folder) === false) {
            throw new FolderAccessException('No write access to the selected folder', 1764250001);
        }
        $tempFile = GeneralUtility::tempnam('imager_');
        GeneralUtility::writeFile($tempFile, $candidate->getData(), false);
        try {
            $file = $folder->getStorage()->addFile(
                $tempFile,
                $folder,
                $this->generateFileName($candidate),
                DuplicationBehavior::RENAME
            );
        } finally {
            GeneralUtility::unlink_tempfile($tempFile);
        }
        return $file;
    }

    private function generateFileName(ImageCandidate $candidate): string
    {
        $identifier = $candidate->getToken() !== '' ? substr($candidate->getToken(), 0, 16) : bin2hex(random_bytes(8));
        return sprintf('ai_generated_%d_%s.%s', time(), $identifier, $candidate->getFileExtension());
    }
}
