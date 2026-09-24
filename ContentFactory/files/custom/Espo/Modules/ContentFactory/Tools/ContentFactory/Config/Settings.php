<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\Config;

final readonly class Settings
{
    public function __construct(
        public bool $enabled,
        public string $webhookUrl,
        public string $authToken,
        public int $connectTimeoutSeconds,
        public int $responseTimeoutSeconds,
    ) {}
}
