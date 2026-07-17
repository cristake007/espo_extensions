<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

final readonly class HttpResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
        public ?string $location = null,
    ) {}
}
