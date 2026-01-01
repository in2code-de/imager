<?php

declare(strict_types=1);

namespace In2code\Imager\Controller;

use In2code\Imager\Domain\Repository\Llm\RepositoryInterface;
use In2code\Imager\Exception\ParameterException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\File;

/**
 * Class ImageController
 * returns a new generated image to the backend
 */
class ImageController
{
    protected string $table = '';
    protected int $uid = 0;
    protected string $field = '';
    protected int $pid = 0;
    protected string $prompt = '';

    public function __construct(
        protected readonly ConnectionPool $connectionPool,
        protected readonly RepositoryInterface $llmRepository,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->setProperties($request);
        try {
            $this->checkProperties();
            $file = $this->llmRepository->getImage($this->prompt);
            $response = new JsonResponse([
                'success' => true,
                'referenceUid' => $this->getReference($file),
                'fileUid' => $file->getUid(),
            ]);
        } catch (\Throwable $exception) {
            $response = new JsonResponse([
                'success' => false,
                'error' => $exception->getMessage() . ' (' . $exception->getCode() .')'],
                400
            );
        }
        return $response;
    }

    protected function getReference(File $file): int
    {
        $connection = $this->connectionPool->getConnectionForTable('sys_file_reference');
        $insert = [
            'pid' => $this->pid,
            'tstamp' => time(),
            'crdate' => time(),
            'uid_local' => $file->getUid(),
            'uid_foreign' => $this->uid,
            'tablenames' => $this->table,
            'fieldname' => $this->field,
            'sorting_foreign' => $this->getSorting(),
        ];
        $connection->insert('sys_file_reference', $insert);
        return (int)$connection->lastInsertId();
    }

    protected function setProperties(ServerRequestInterface $request): void
    {
        $body = $request->getParsedBody() ?? [];
        $this->table = ($body['table'] ?? '');
        $this->uid = (int)($body['uid'] ?? 0);
        $this->field = ($body['field'] ?? '');
        $this->prompt = ($body['prompt'] ?? '');
        $pid = (int)($body['pid'] ?? 0);
        if ($pid <= 0) {
            $pid = $this->getPageIdentifierFromRecord();
        }
        $this->pid = $pid;
    }

    protected function checkProperties(): void
    {
        if (
            $this->table === '' ||
            $this->uid <= 0 ||
            $this->field === ''
        ) {
            throw new ParameterException('Missing parameters', 1764248313);
        }
    }

    protected function getSorting(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $maxSorting = (int)$queryBuilder->selectLiteral('MAX(sorting_foreign)')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($this->table)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($this->field)),
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($this->uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
        return $maxSorting + 256;
    }

    protected function getPageIdentifierFromRecord(): int
    {
        $row = BackendUtility::getRecord($this->table, $this->uid);
        return $row['pid'] ?? 0;
    }
}
