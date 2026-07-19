<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf;

use Dompdf\Dompdf;

final readonly class DompdfEngine implements PdfEngine
{
    public function __construct(private Dompdf $dompdf)
    {}

    public function render(string $html): PdfRenderResult
    {
        $this->dompdf->loadHtml($html, 'UTF-8');
        $this->dompdf->render();
        $pages = $this->dompdf->getCanvas()->get_page_count();
        $bytes = $this->dompdf->output();

        if (!is_string($bytes) || !str_starts_with($bytes, '%PDF-')) {
            throw new PdfRenderFailure('The PDF engine returned invalid output.');
        }

        return new PdfRenderResult($bytes, $pages);
    }
}
