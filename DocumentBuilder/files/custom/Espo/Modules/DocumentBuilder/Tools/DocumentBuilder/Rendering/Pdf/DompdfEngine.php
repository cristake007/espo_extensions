<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf;

use Dompdf\Dompdf;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedDocument;

final readonly class DompdfEngine implements PdfEngine
{
    public function __construct(private Dompdf $dompdf, private ResolvedDocument $document)
    {}

    public function render(string $html): PdfRenderResult
    {
        $this->dompdf->loadHtml($html, 'UTF-8');
        $this->dompdf->render();
        $canvas = $this->dompdf->getCanvas();
        $this->hideFirstPageChrome($canvas);
        $pages = $canvas->get_page_count();
        $bytes = $this->dompdf->output();

        if (!is_string($bytes) || !str_starts_with($bytes, '%PDF-')) {
            throw new PdfRenderFailure('The PDF engine returned invalid output.');
        }

        return new PdfRenderResult($bytes, $pages);
    }

    private function hideFirstPageChrome(object $canvas): void
    {
        $chrome = $this->document->chrome;
        $margins = $this->document->page['margins'] ?? [];
        $hideHeader = ($chrome['header']['showOnFirstPage'] ?? true) === false && $this->document->header !== [];
        $hideFooter = ($chrome['footer']['showOnFirstPage'] ?? true) === false && $this->document->footer !== [];

        if (!$hideHeader && !$hideFooter) return;
        $pointsPerMillimetre = 72 / 25.4;
        $top = (float) ($margins['top']['value'] ?? 0) * $pointsPerMillimetre;
        $bottom = (float) ($margins['bottom']['value'] ?? 0) * $pointsPerMillimetre;
        $canvas->page_script(static function (int $pageNumber, int $pageCount, object $pageCanvas) use (
            $hideHeader,
            $hideFooter,
            $top,
            $bottom,
        ): void {
            if ($pageNumber !== 1) return;
            $width = $pageCanvas->get_width();
            $height = $pageCanvas->get_height();
            if ($hideHeader && $top > 0) $pageCanvas->filled_rectangle(0, 0, $width, $top, [1, 1, 1]);
            if ($hideFooter && $bottom > 0) {
                $pageCanvas->filled_rectangle(0, $height - $bottom, $width, $bottom, [1, 1, 1]);
            }
        });
    }
}
