<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

final readonly class ValidationResult
{
    /** @param list<ValidationError> $errors */
    public function __construct(private array $errors)
    {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return list<ValidationError> */
    public function errors(): array
    {
        return $this->errors;
    }
}
