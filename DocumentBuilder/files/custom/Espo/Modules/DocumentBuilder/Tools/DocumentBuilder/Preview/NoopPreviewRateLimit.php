<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

final readonly class NoopPreviewRateLimit implements PreviewRateLimit
{
    public function consume(string $templateId, PreviewMode $mode): void
    {}
}
