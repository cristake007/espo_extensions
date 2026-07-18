<?php

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use RuntimeException;

class CourseTitleHeaderException extends RuntimeException
{
    public const DUPLICATE_HEADER = 'duplicateHeader';
    public const CONFLICTING_VALUES = 'conflictingValues';

    public function __construct(
        private string $reason,
        private ?string $header = null,
        private ?int $sourceRow = null
    ) {
        parent::__construct($reason);
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getHeader(): ?string
    {
        return $this->header;
    }

    public function getSourceRow(): ?int
    {
        return $this->sourceRow;
    }
}

class CourseTitleHeaderResolver
{
    private const TITLE = 'title';
    private const LEGACY_TITLE = 'nume curs';
    private const COMPATIBILITY_TITLE = 'course name';
    private const TITLE_HEADERS = [
        self::TITLE,
        self::LEGACY_TITLE,
        self::COMPATIBILITY_TITLE,
    ];

    /** @var array<string, int> */
    private array $indexes = [];

    /** @param array<int, mixed> $header */
    public function __construct(array $header)
    {
        $seen = [];

        foreach ($header as $index => $value) {
            $normalized = self::normalizeHeader($value);

            if ($normalized === '') {
                continue;
            }

            if (isset($seen[$normalized])) {
                throw new CourseTitleHeaderException(
                    CourseTitleHeaderException::DUPLICATE_HEADER,
                    $normalized
                );
            }

            $seen[$normalized] = true;

            if (in_array($normalized, self::TITLE_HEADERS, true)) {
                $this->indexes[$normalized] = $index;
            }
        }
    }

    public function hasTitleHeader(): bool
    {
        return $this->indexes !== [];
    }

    /** @param array<int, mixed> $row */
    public function resolveTitle(array $row, int $sourceRow): string
    {
        $hasTitle = array_key_exists(self::TITLE, $this->indexes);
        $hasLegacyTitle = array_key_exists(self::LEGACY_TITLE, $this->indexes);

        if ($hasTitle && $hasLegacyTitle) {
            $title = $this->cell($row, $this->indexes[self::TITLE]);
            $legacyTitle = $this->cell($row, $this->indexes[self::LEGACY_TITLE]);

            if ($title === '') {
                return $legacyTitle;
            }

            if ($legacyTitle === '') {
                return $title;
            }

            if ($title !== $legacyTitle) {
                throw new CourseTitleHeaderException(
                    CourseTitleHeaderException::CONFLICTING_VALUES,
                    null,
                    $sourceRow
                );
            }

            return $title;
        }

        foreach (self::TITLE_HEADERS as $header) {
            if (array_key_exists($header, $this->indexes)) {
                return $this->cell($row, $this->indexes[$header]);
            }
        }

        return '';
    }

    public static function normalizeHeader(mixed $value): string
    {
        $text = trim((string) $value);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;

        return mb_strtolower(trim($text));
    }

    /** @param array<int, mixed> $row */
    private function cell(array $row, int $index): string
    {
        return array_key_exists($index, $row) ? trim((string) $row[$index]) : '';
    }
}
