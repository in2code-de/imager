<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Repository\Llm;

use In2code\Imager\Events\BeforeRequestEvent;
use In2code\Imager\Exception\ApiException;
use In2code\Imager\Exception\ConfigurationException;
use In2code\Imager\Utility\ConfigurationUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;

class GeminiRepository extends AbstractRepository implements RepositoryInterface
{
    private string $apiKey = '';
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private array $models = [
        '2_5_flash_image' => 'gemini-2.5-flash-image:generateContent',
        '3_pro_image_preview' => 'gemini-3-pro-image-preview:generateContent',
    ];

    public function __construct(
        protected StorageRepository $storageRepository,
        protected ResourceFactory $resourceFactory,
        protected RequestFactory $requestFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($storageRepository, $resourceFactory, $requestFactory);
        $this->apiKey = getenv('GOOGLE_API_KEY') ?: ConfigurationUtility::getConfigurationByKey('apiKey') ?: '';
    }

    public function checkApiKey(): void
    {
        if ($this->apiKey === '') {
            throw new ConfigurationException('Google API key not configured', 1764254036);
        }
    }

    public function getApiUrl(): string
    {
        $model = $this->models[ConfigurationUtility::getConfigurationByKey('model')] ?? $this->models['3_pro_image_preview'];
        return $this->apiUrl . $model;
    }

    public function getImage(string $prompt): File
    {
        $this->checkApiKey();
        $imageData = $this->generateImageContentWithGemini($prompt);
        return $this->saveImageToStorage($imageData, $prompt);
    }

    /**
     * Generate image content using Google Gemini API
     *
     * @param string $prompt
     * @return string Binary image data
     * @throws ApiException
     */
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
                'responseModalities' => ['image'],
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
            new BeforeRequestEvent($this->getApiUrl(), $additionalOptions)
        );
        $response = $this->requestFactory->request($event->getApiUrl(), $this->requestMethod, $event->getAdditionalOptions());
        if ($response->getStatusCode() !== 200) {
            throw new ApiException('Failed to generate image: ' . $response->getBody()->getContents(), 1764248401);
        }
        $responseData = json_decode($response->getBody()->getContents(), true);
        if (isset($responseData['candidates'][0]['content']['parts']) === false) {
            throw new ApiException('Invalid response from Gemini API: ' . json_encode($responseData), 1764248402);
        }
        foreach ($responseData['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['inlineData']['data'])) {
                $this->mimeType = $part['inlineData']['mimeType'] ?? 'image/png';
                return base64_decode($part['inlineData']['data']);
            }
        }
        throw new ApiException('No image data found in response', 1764248403);
    }
}
