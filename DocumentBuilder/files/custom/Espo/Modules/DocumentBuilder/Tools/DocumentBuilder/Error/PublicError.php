<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error;

use InvalidArgumentException;

final readonly class PublicError
{
    public bool $retryable;

    public function __construct(
        public ErrorCategory $category,
        public ?string $elementId = null,
        public ?string $correlationId = null,
        bool $retryable = false,
    ) {
        if (!$this->isSafeOptionalIdentifier($elementId) || !$this->isSafeOptionalIdentifier($correlationId)) {
            throw new InvalidArgumentException('A public error identifier is invalid.');
        }

        $this->retryable = $retryable && $category->mayRetry();
    }

    public function httpStatus(): int
    {
        return $this->category->httpStatus();
    }

    /** @return array<string, bool|string> */
    public function toArray(): array
    {
        $data = [
            'category' => $this->category->value,
            'messageKey' => $this->category->messageKey(),
            'retryable' => $this->retryable,
        ];

        if ($this->elementId !== null) {
            $data['elementId'] = $this->elementId;
        }

        if ($this->correlationId !== null) {
            $data['correlationId'] = $this->correlationId;
        }

        return $data;
    }

    private function isSafeOptionalIdentifier(?string $value): bool
    {
        return $value === null || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $value) === 1;
    }
}
