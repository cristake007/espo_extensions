<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf;

interface PdfEngine
{
    public function render(string $html): PdfRenderResult;
}
