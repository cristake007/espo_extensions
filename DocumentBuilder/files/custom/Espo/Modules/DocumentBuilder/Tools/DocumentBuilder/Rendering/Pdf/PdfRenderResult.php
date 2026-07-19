<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf;

final readonly class PdfRenderResult
{
    public function __construct(
        public string $bytes,
        public int $pageCount,
    ) {}
}
