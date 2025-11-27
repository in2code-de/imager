<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Repository;

use In2code\Imager\Events\BeforeRequestEvent;
use In2code\Imager\Exception\ApiException;
use In2code\Imager\Exception\ConfigurationException;
use In2code\Imager\Utility\ConfigurationUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LlmRepository
{
    private string $apiKey = '';
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private string $model = 'gemini-2.5-flash-image:generateContent';

    public function __construct(
        private readonly StorageRepository $storageRepository,
        private readonly ResourceFactory $resourceFactory,
        private readonly RequestFactory $requestFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        $this->apiKey = getenv('GOOGLE_API_KEY') ?: ConfigurationUtility::getConfigurationByKey('apiKey') ?: '';
    }

    public function getImage(string $prompt): File
    {
        $this->checkApiKey();
        $imageData = $this->generateImageContentWithGemini($prompt);
        return $this->saveImageToStorage($imageData, $prompt);
    }

    protected function generateImageContentWithGemini(string $prompt): string
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => ConfigurationUtility::getConfigurationByKey('promptPrefix') . PHP_EOL . $prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'imageConfig' => [
                    'aspectRatio' => ConfigurationUtility::getConfigurationByKey('aspectRatio') ?: '16:9',
                ],
            ],
        ];
        $additionalOptions = [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload),
        ];
        $event = $this->eventDispatcher->dispatch(
            new BeforeRequestEvent($this->apiUrl  . $this->model, $additionalOptions)
        );
        $response = $this->requestFactory->request($event->getApiUrl(), 'POST', $event->getAdditionalOptions());
        if ($response->getStatusCode() !== 200) {
            throw new ApiException('Failed to generate image: ' . $response->getBody()->getContents(), 1764248401);
        }
        $responseData = json_decode($response->getBody()->getContents(), true);
        if (isset($responseData['candidates'][0]['content']['parts']) === false) {
            throw new ApiException('Invalid response from Gemini API: ' . json_encode($responseData), 1764248402);
        }
        foreach ($responseData['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['inlineData']['data'])) {
                return base64_decode($part['inlineData']['data']);
            }
        }
        throw new ApiException('No image data found in response', 1764248403);
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
        return sprintf('ai_generated_%d_%s.png', time(), md5($prompt));
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

    protected function ensureFolderExists(string $combinedIdentifier): void {
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

    protected function checkApiKey(): void
    {
        if ($this->apiKey === '') {
            throw new ConfigurationException('Google API key not configured', 1764254036);
        }
    }
}
