<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\DocumentWarning;

final readonly class PdfPreviewResult
{
    /** @param list<DocumentWarning> $warnings */
    public function __construct(
        public string $bytes,
        public int $pageCount,
        public array $warnings,
    ) {}
}
