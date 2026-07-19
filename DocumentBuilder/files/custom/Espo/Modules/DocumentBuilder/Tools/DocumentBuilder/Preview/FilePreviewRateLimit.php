<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\SettingsProvider;

final readonly class FilePreviewRateLimit implements PreviewRateLimit
{
    public function __construct(private SettingsProvider $settingsProvider)
    {}

    public function consume(string $templateId, PreviewMode $mode): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'document-builder-preview-limits';
        if (!is_dir($directory) && !mkdir($directory, 0700) && !is_dir($directory)) {
            throw new PreviewRateLimitExceeded();
        }
        $path = $directory . DIRECTORY_SEPARATOR . hash('sha256', "$templateId:{$mode->value}") . '.json';
        $handle = fopen($path, 'c+');
        if ($handle === false) throw new PreviewRateLimitExceeded();
        try {
            if (!flock($handle, LOCK_EX)) throw new PreviewRateLimitExceeded();
            $raw = stream_get_contents($handle);
            $values = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (!is_array($values)) $values = [];
            $cutoff = time() - 60;
            $values = array_values(array_filter($values, static fn (mixed $time): bool => is_int($time) && $time > $cutoff));
            if (count($values) >= $this->settingsProvider->get()->previewRequestsPerMinute()) {
                throw new PreviewRateLimitExceeded();
            }
            $values[] = time();
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($values, JSON_THROW_ON_ERROR));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
