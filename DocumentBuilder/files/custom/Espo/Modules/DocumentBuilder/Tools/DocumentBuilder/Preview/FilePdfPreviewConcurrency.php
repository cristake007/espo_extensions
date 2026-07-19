<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;

final readonly class FilePdfPreviewConcurrency implements PdfPreviewConcurrency
{
    public function __construct(private Settings $settings)
    {}

    public function enter(): string
    {
        $leaseId = bin2hex(random_bytes(16));
        $this->update(function (array $leases) use ($leaseId): array {
            if (count($leases) >= $this->settings->maxConcurrentPreviews()) throw new PreviewRateLimitExceeded();
            $leases[$leaseId] = time() + $this->settings->renderTimeoutSeconds() + 5;
            return $leases;
        });

        return $leaseId;
    }

    public function leave(string $leaseId): void
    {
        $this->update(function (array $leases) use ($leaseId): array {
            unset($leases[$leaseId]);
            return $leases;
        });
    }

    /** @param callable(array<string, int>): array<string, int> $callback */
    private function update(callable $callback): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'document-builder-preview-concurrency.json';
        $handle = fopen($path, 'c+');
        if ($handle === false) throw new PreviewRateLimitExceeded();
        try {
            if (!flock($handle, LOCK_EX)) throw new PreviewRateLimitExceeded();
            $raw = stream_get_contents($handle);
            $leases = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (!is_array($leases)) $leases = [];
            $now = time();
            $leases = array_filter($leases, static fn (mixed $expiry): bool => is_int($expiry) && $expiry > $now);
            $leases = $callback($leases);
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($leases, JSON_THROW_ON_ERROR));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
