<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Subject;

final readonly class WordPressSettings
{
    public function __construct(
        public bool $enabled,
        public string $baseUrl,
        public string $username,
        public string $applicationPassword,
        public int $connectTimeoutSeconds,
        public int $responseTimeoutSeconds,
    ) {}
}
