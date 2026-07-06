<?php

declare(strict_types=1);

namespace In2code\Imager\Controller;

use In2code\Imager\Domain\Model\GenerationRequest;
use In2code\Imager\Domain\Model\ImageCandidate;
use In2code\Imager\Domain\Repository\Llm\RepositoryInterface;
use In2code\Imager\Domain\Service\AiAreaPreference;
use In2code\Imager\Domain\Service\CandidateStorage;
use In2code\Imager\Domain\Service\FolderImageService;
use In2code\Imager\Exception\ParameterException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * Class GeneratorAjaxController
 * handles the asynchronous actions of the AI image area in the file list module: generating four
 * candidates (optionally refining a previously generated candidate), saving a picked candidate into
 * the current folder and persisting the "Show AI area" toggle state.
 */
#[AsController]
class GeneratorAjaxController
{
    private const CANDIDATE_COUNT = 4;

    public function __construct(
        private readonly RepositoryInterface $llmRepository,
        private readonly CandidateStorage $candidateStorage,
        private readonly FolderImageService $folderImageService,
        private readonly AiAreaPreference $aiAreaPreference,
    ) {
    }

    public function generate(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = $request->getParsedBody() ?? [];
            $prompt = trim((string)($body['prompt'] ?? ''));
            if ($prompt === '') {
                throw new ParameterException('Prompt must not be empty', 1764250100);
            }
            $scope = $this->getScope();
            $baseImage = $this->resolveBaseImage($scope, (string)($body['baseToken'] ?? ''));
            $this->candidateStorage->clear($scope);
            $generationRequest = new GenerationRequest($prompt, self::CANDIDATE_COUNT, $baseImage);
            $candidates = [];
            foreach ($this->llmRepository->generateCandidates($generationRequest) as $candidate) {
                $stored = $this->candidateStorage->store($scope, $candidate);
                $candidates[] = [
                    'token' => $stored->getToken(),
                    'dataUri' => $stored->getDataUri(),
                ];
            }
            $response = new JsonResponse(['success' => true, 'candidates' => $candidates]);
        } catch (\Throwable $exception) {
            $response = new JsonResponse(
                ['success' => false, 'error' => $exception->getMessage() . ' (' . $exception->getCode() . ')'],
                400
            );
        }
        return $response;
    }

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = $request->getParsedBody() ?? [];
            $scope = $this->getScope();
            $candidate = $this->candidateStorage->get($scope, (string)($body['candidate'] ?? ''));
            if ($candidate === null) {
                throw new ParameterException('Candidate not found', 1764250102);
            }
            $folder = $this->folderImageService->resolveFolder((string)($body['folder'] ?? ''));
            if ($folder === null) {
                throw new ParameterException('Folder not accessible', 1764250103);
            }
            $file = $this->folderImageService->saveCandidate($candidate, $folder);
            $this->candidateStorage->clear($scope);
            $response = new JsonResponse([
                'success' => true,
                'fileUid' => $file->getUid(),
                'fileName' => $file->getName(),
            ]);
        } catch (\Throwable $exception) {
            $response = new JsonResponse(
                ['success' => false, 'error' => $exception->getMessage() . ' (' . $exception->getCode() . ')'],
                400
            );
        }
        return $response;
    }

    public function toggle(ServerRequestInterface $request): ResponseInterface
    {
        $backendUser = $this->getBackendUser();
        if ($backendUser === null) {
            return new JsonResponse(['success' => false], 403);
        }
        $enabled = ($request->getParsedBody()['enabled'] ?? '') === '1';
        $this->aiAreaPreference->set($backendUser, $enabled);
        return new JsonResponse(['success' => true, 'enabled' => $enabled]);
    }

    private function resolveBaseImage(int $scope, string $baseToken): ?ImageCandidate
    {
        $baseImage = null;
        if ($baseToken !== '') {
            $baseImage = $this->candidateStorage->get($scope, $baseToken);
            if ($baseImage === null) {
                throw new ParameterException('Base image not found', 1764250101);
            }
        }
        return $baseImage;
    }

    private function getScope(): int
    {
        return (int)($this->getBackendUser()?->user['uid'] ?? 0);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
