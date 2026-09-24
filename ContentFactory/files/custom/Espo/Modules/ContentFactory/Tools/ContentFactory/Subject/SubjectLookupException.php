<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Subject;

use RuntimeException;

final class SubjectLookupException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        private readonly int $httpStatus,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
