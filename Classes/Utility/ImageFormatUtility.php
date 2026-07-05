<?php

declare(strict_types=1);

namespace In2code\Imager\Utility;

/**
 * Class ImageFormatUtility
 * single source of truth for supported image formats and mime type handling
 */
class ImageFormatUtility
{
    public const ALLOWED_EXTENSIONS = [
        'png',
        'jpg',
        'jpeg',
        'webp',
    ];

    public static function extensionFromMimeType(string $mimeType): string
    {
        $extension = 'jpg';
        if (stripos($mimeType, 'png') !== false) {
            $extension = 'png';
        } elseif (stripos($mimeType, 'webp') !== false) {
            $extension = 'webp';
        }
        return $extension;
    }

    public static function mimeTypeFromExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
