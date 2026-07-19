<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

interface PdfPreviewConcurrency
{
    public function enter(): string;
    public function leave(string $leaseId): void;
}
