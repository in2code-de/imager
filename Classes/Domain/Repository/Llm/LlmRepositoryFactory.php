<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Repository\Llm;

use In2code\Imager\Exception\ConfigurationException;
use Psr\Container\ContainerInterface;

/**
 * Class LlmRepositoryFactory
 * to allow registering own Repositories to use other language models (e.g. DALL-E, Stable Diffusion, Midjourney, etc.) with
 * $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imager']['llmRepositoryClass'] = MyRepository::class;
 * (ensure that MyRepository implements RepositoryInterface class)
 */
class LlmRepositoryFactory
{
    protected string $defaultRepositoryClass = GeminiRepository::class;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function create(): RepositoryInterface
    {
        // Allow third-party extensions to override the LLM repository implementation
        $repositoryClass = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imager']['llmRepositoryClass']
            ?? $this->defaultRepositoryClass;

        if (is_a($repositoryClass, RepositoryInterface::class, true) === false) {
            throw new ConfigurationException(
                sprintf(
                    'LLM repository class "%s" must implement %s',
                    $repositoryClass,
                    RepositoryInterface::class
                ),
                1735646260
            );
        }

        return $this->container->get($repositoryClass);
    }
}
