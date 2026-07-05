<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Service;

use In2code\Imager\Domain\Model\ImageCandidate;
use In2code\Imager\Utility\ImageFormatUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CandidateStorage
 * persists freshly generated image candidates as temporary files below the var/ path until an editor
 * decides to save one into a file storage or to use one as a base image for further refinement.
 * Candidates are scoped per backend user to avoid collisions between concurrent editors.
 */
class CandidateStorage
{
    private const SUB_PATH = 'imager/candidates/';
    private const TOKEN_BYTES = 20;

    public function store(int $scope, ImageCandidate $candidate): ImageCandidate
    {
        $directory = $this->getDirectory($scope);
        GeneralUtility::mkdir_deep($directory);
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $filePath = $directory . $token . '.' . $candidate->getFileExtension();
        GeneralUtility::writeFile($filePath, $candidate->getData(), false);
        return $candidate->withToken($token);
    }

    public function get(int $scope, string $token): ?ImageCandidate
    {
        $candidate = null;
        if ($this->isValidToken($token)) {
            $directory = $this->getDirectory($scope);
            foreach (ImageFormatUtility::ALLOWED_EXTENSIONS as $extension) {
                $filePath = $directory . $token . '.' . $extension;
                if (is_file($filePath)) {
                    $candidate = new ImageCandidate(
                        (string)file_get_contents($filePath),
                        ImageFormatUtility::mimeTypeFromExtension($extension),
                        $token
                    );
                    break;
                }
            }
        }
        return $candidate;
    }

    public function clear(int $scope): void
    {
        $directory = $this->getDirectory($scope);
        if (is_dir($directory)) {
            GeneralUtility::rmdir($directory, true);
        }
    }

    private function getDirectory(int $scope): string
    {
        return Environment::getVarPath() . '/' . self::SUB_PATH . $scope . '/';
    }

    private function isValidToken(string $token): bool
    {
        return preg_match('~^[a-f0-9]{' . (self::TOKEN_BYTES * 2) . '}$~', $token) === 1;
    }
}
