<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Repository\Llm;

use In2code\Imager\Domain\Model\GenerationRequest;
use In2code\Imager\Domain\Model\ImageCandidate;
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
    private const DEFAULT_ASPECT_RATIO = '16:9';
    private const SEED_MIN = 1;
    private const SEED_MAX = 2147483647;

    private string $apiKey;
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private string $model;

    public function __construct(
        protected StorageRepository $storageRepository,
        protected ResourceFactory $resourceFactory,
        protected RequestFactory $requestFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($storageRepository, $resourceFactory, $requestFactory);
        $this->apiKey = getenv('GOOGLE_API_KEY') ?: ConfigurationUtility::getConfigurationByKey('apiKey') ?: '';
        $this->model = ConfigurationUtility::getModel();
    }

    public function checkApiKey(): void
    {
        if ($this->apiKey === '') {
            throw new ConfigurationException('Google API key not configured', 1764254036);
        }
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl . $this->model;
    }

    public function getImage(string $prompt): File
    {
        $this->checkApiKey();
        $candidate = $this->requestImage(new GenerationRequest($prompt));
        return $this->saveImageToStorage($candidate, $prompt);
    }

    /**
     * @return ImageCandidate[]
     */
    public function generateCandidates(GenerationRequest $request): array
    {
        $this->checkApiKey();
        $candidates = [];
        for ($index = 0; $index < $request->getCount(); $index++) {
            $candidates[] = $this->requestImage($request, random_int(self::SEED_MIN, self::SEED_MAX));
        }
        return $candidates;
    }

    /**
     * Request a single image from the Google Gemini API. Supports image-to-image editing when the
     * request carries a base image.
     *
     * A distinct seed per request enforces variation between candidates. This is essential for
     * image-to-image refinement, where identical requests would otherwise return identical results.
     *
     * @throws ApiException
     */
    protected function requestImage(GenerationRequest $request, ?int $seed = null): ImageCandidate
    {
        $additionalOptions = [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($this->buildPayload($request, $seed)),
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
        return $this->extractCandidate($responseData['candidates'][0]['content']['parts']);
    }

    /**
     * @param array<int, array<string, mixed>> $parts
     * @throws ApiException
     */
    protected function extractCandidate(array $parts): ImageCandidate
    {
        foreach ($parts as $part) {
            if (isset($part['inlineData']['data'])) {
                return new ImageCandidate(
                    base64_decode($part['inlineData']['data']),
                    $part['inlineData']['mimeType'] ?? 'image/png'
                );
            }
        }
        throw new ApiException('No image data found in response', 1764248403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(GenerationRequest $request, ?int $seed = null): array
    {
        $parts = [];
        if ($request->hasBaseImage()) {
            $baseImage = $request->getBaseImage();
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $baseImage->getMimeType(),
                    'data' => base64_encode($baseImage->getData()),
                ],
            ];
        }
        $parts[] = [
            'text' => ConfigurationUtility::getConfigurationByKey('promptPrefix') . PHP_EOL . $request->getPrompt(),
        ];
        $generationConfig = [
            'responseModalities' => ['image'],
            'imageConfig' => [
                'aspectRatio' => ConfigurationUtility::getConfigurationByKey('aspectRatio') ?: self::DEFAULT_ASPECT_RATIO,
            ],
        ];
        if ($seed !== null) {
            $generationConfig['seed'] = $seed;
        }
        return [
            'contents' => [
                [
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => $generationConfig,
        ];
    }
}
