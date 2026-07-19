<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Audit;

final class AuditContextSanitizer
{
    private const STRING_KEYS = [
        'actorId',
        'templateId',
        'templateVersionId',
        'generatedDocumentId',
        'batchId',
        'importId',
        'mediaId',
        'sourceEntityType',
        'sourceRecordId',
        'elementId',
        'correlationId',
        'technicalCode',
    ];

    private const COUNT_KEYS = [
        'versionNumber',
        'recordCount',
        'successCount',
        'warningCount',
        'failureCount',
        'durationMilliseconds',
        'httpStatus',
    ];

    /** @param array<string, mixed> $context @return array<string, bool|int|string> */
    public function sanitize(array $context): array
    {
        $safe = [];
        $redactedCount = 0;

        foreach ($context as $key => $value) {
            if (in_array($key, self::STRING_KEYS, true) && $this->isSafeIdentifier($value)) {
                $safe[$key] = $value;
                continue;
            }

            if (in_array($key, self::COUNT_KEYS, true) && is_int($value) && $value >= 0) {
                $safe[$key] = $value;
                continue;
            }

            if ($key === 'retryable' && is_bool($value)) {
                $safe[$key] = $value;
                continue;
            }

            $redactedCount++;
        }

        if ($redactedCount > 0) {
            $safe['redactedFieldCount'] = $redactedCount;
        }

        return $safe;
    }

    private function isSafeIdentifier(mixed $value): bool
    {
        return is_string($value) &&
            preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $value) === 1;
    }
}
