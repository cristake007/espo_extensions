<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use RuntimeException;
use Throwable;

final class ClientException extends RuntimeException
{
    public const INPUT = 'input';
    public const TRANSPORT = 'transport';
    public const REDIRECT = 'redirect';
    public const STATUS = 'status';
    public const RESPONSE_SIZE = 'response-size';
    public const JSON = 'json';
    public const SCHEMA = 'schema';

    public function __construct(
        private readonly string $category,
        string $safeMessage,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeMessage, previous: $previous);
    }

    public function getCategory(): string
    {
        return $this->category;
    }
}
