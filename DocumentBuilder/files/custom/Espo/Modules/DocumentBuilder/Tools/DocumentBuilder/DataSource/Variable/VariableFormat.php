<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

use InvalidArgumentException;

final readonly class VariableFormat
{
    public function __construct(
        public FormatType $type = FormatType::Auto,
        public int $decimals = 2,
        public string $dateStyle = 'medium',
        public string $timeStyle = 'short',
        public ?string $currency = null,
        public ?string $trueLabel = null,
        public ?string $falseLabel = null,
        public string $separator = ', ',
        public bool $trim = true,
        public TextCase $case = TextCase::None,
        public string $prefix = '',
        public string $suffix = '',
        public ?string $fallback = null,
    ) {
        if ($decimals < 0 || $decimals > 6 ||
            !in_array($dateStyle, ['short', 'medium', 'long'], true) ||
            !in_array($timeStyle, ['short', 'medium'], true) ||
            ($currency !== null && preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) ||
            !$this->isSafeText($trueLabel, 100, true) ||
            !$this->isSafeText($falseLabel, 100, true) ||
            !$this->isSafeText($separator, 10, false) ||
            !$this->isSafeText($prefix, 100, true) ||
            !$this->isSafeText($suffix, 100, true) ||
            !$this->isSafeText($fallback, 200, true)) {
            throw new InvalidArgumentException('A variable format contains an invalid bounded value.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'decimals' => $this->decimals,
            'dateStyle' => $this->dateStyle,
            'timeStyle' => $this->timeStyle,
            'currency' => $this->currency,
            'trueLabel' => $this->trueLabel,
            'falseLabel' => $this->falseLabel,
            'separator' => $this->separator,
            'trim' => $this->trim,
            'case' => $this->case->value,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'fallback' => $this->fallback,
        ];
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        $keys = array_keys((new self())->toArray());

        if (count($value) !== count($keys) ||
            array_diff(array_keys($value), $keys) !== [] ||
            array_diff($keys, array_keys($value)) !== [] ||
            !is_string($value['type']) || ($type = FormatType::tryFrom($value['type'])) === null ||
            !is_int($value['decimals']) || !is_string($value['dateStyle']) ||
            !is_string($value['timeStyle']) ||
            ($value['currency'] !== null && !is_string($value['currency'])) ||
            ($value['trueLabel'] !== null && !is_string($value['trueLabel'])) ||
            ($value['falseLabel'] !== null && !is_string($value['falseLabel'])) ||
            !is_string($value['separator']) || !is_bool($value['trim']) ||
            !is_string($value['case']) || ($case = TextCase::tryFrom($value['case'])) === null ||
            !is_string($value['prefix']) || !is_string($value['suffix']) ||
            ($value['fallback'] !== null && !is_string($value['fallback']))) {
            throw new InvalidArgumentException('A variable format has an invalid canonical structure.');
        }

        return new self(
            $type,
            $value['decimals'],
            $value['dateStyle'],
            $value['timeStyle'],
            $value['currency'],
            $value['trueLabel'],
            $value['falseLabel'],
            $value['separator'],
            $value['trim'],
            $case,
            $value['prefix'],
            $value['suffix'],
            $value['fallback'],
        );
    }

    private function isSafeText(?string $value, int $maximum, bool $emptyAllowed): bool
    {
        return $value === null || (
            ($emptyAllowed || $value !== '') &&
            mb_strlen($value) <= $maximum &&
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1
        );
    }
}
