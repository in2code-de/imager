<?php

declare(strict_types=1);

namespace In2code\Imager\Domain\Settings;

use In2code\Imager\Domain\Model\Setting;
use In2code\Imager\Utility\ConfigurationUtility;

/**
 * All settings that can be overwritten in the backend module "AI > Imager"
 */
class SettingsDefinition
{
    /**
     * @return Setting[]
     */
    public function getSettings(): array
    {
        return [
            new Setting(
                key: 'apiKey',
                type: Setting::TYPE_PASSWORD,
                environmentVariable: ConfigurationUtility::ENVIRONMENT_API_KEY,
                adminOnly: true,
            ),
            new Setting(
                key: 'geminiModel',
                default: ConfigurationUtility::DEFAULT_GEMINI_MODEL,
                environmentVariable: ConfigurationUtility::ENVIRONMENT_GEMINI_MODEL,
            ),
            new Setting(
                key: 'promptPrefix',
                type: Setting::TYPE_TEXTAREA,
            ),
            new Setting(
                key: 'promptPlaceholder',
            ),
            new Setting(
                key: 'promptValue',
            ),
            new Setting(
                key: 'combinedIdentifier',
                default: '1:/_imager/',
                adminOnly: true,
            ),
            new Setting(
                key: 'aspectRatio',
                type: Setting::TYPE_SELECT,
                default: '16:9',
                options: ['1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9'],
            ),
        ];
    }
}
