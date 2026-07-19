<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedDocument;

interface PdfEngineFactory
{
    public function create(ResolvedDocument $document, RenderWorkspace $workspace): PdfEngine;
}
