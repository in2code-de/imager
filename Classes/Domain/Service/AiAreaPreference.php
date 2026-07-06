<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Class AiAreaPreference
 * stores whether the "Show AI area" toggle in the file list module is enabled, per backend user in
 * the user configuration (uc) - the same persistence mechanism the core view toggles use.
 */
class AiAreaPreference
{
    private const UC_KEY = 'imager_showAiArea';

    public function isEnabled(BackendUserAuthentication $backendUser): bool
    {
        return (bool)($backendUser->uc[self::UC_KEY] ?? false);
    }

    public function set(BackendUserAuthentication $backendUser, bool $enabled): void
    {
        $backendUser->uc[self::UC_KEY] = $enabled;
        $backendUser->writeUC();
    }
}
