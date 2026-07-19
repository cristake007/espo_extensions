<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Pdf;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedDocument;
use Espo\Tools\Pdf\Template;

final readonly class DocumentBuilderPdfTemplate implements Template
{
    /** @var array{widthMm: float, heightMm: float} */
    private array $dimensions;

    public function __construct(private ResolvedDocument $document, private Settings $settings)
    {
        $this->dimensions = $this->dimensions();
    }

    public function getFontFace(): ?string { return $this->settings->defaultFont(); }
    public function getBottomMargin(): float { return $this->margin('bottom'); }
    public function getTopMargin(): float { return $this->margin('top'); }
    public function getLeftMargin(): float { return $this->margin('left'); }
    public function getRightMargin(): float { return $this->margin('right'); }
    public function hasFooter(): bool { return false; }
    public function getFooter(): string { return ''; }
    public function getFooterPosition(): float { return 0.0; }
    public function hasHeader(): bool { return false; }
    public function getHeader(): string { return ''; }
    public function getHeaderPosition(): float { return 0.0; }
    public function getBody(): string { return ''; }
    public function getPageOrientation(): string
    {
        return ($this->document->page['orientation'] ?? null) === 'landscape' ?
            self::PAGE_ORIENTATION_LANDSCAPE : self::PAGE_ORIENTATION_PORTRAIT;
    }
    public function getPageFormat(): string
    {
        $size = $this->document->page['size'] ?? 'A4';
        return in_array($size, ['A4','Letter','Legal'], true) ? $size : self::PAGE_FORMAT_CUSTOM;
    }
    public function getPageWidth(): float { return $this->dimensions['widthMm']; }
    public function getPageHeight(): float { return $this->dimensions['heightMm']; }
    public function hasTitle(): bool { return false; }
    public function getTitle(): string { return ''; }
    public function getStyle(): ?string { return null; }

    private function margin(string $edge): float
    {
        $value = $this->document->page['margins'][$edge]['value'] ?? 0;
        return is_int($value) || is_float($value) ? (float) $value : 0.0;
    }

    /** @return array{widthMm: float, heightMm: float} */
    private function dimensions(): array
    {
        $size = $this->document->page['size'] ?? 'A4';
        $standard = [
            'A4'=>['widthMm'=>210.0,'heightMm'=>297.0],
            'Letter'=>['widthMm'=>215.9,'heightMm'=>279.4],
            'Legal'=>['widthMm'=>215.9,'heightMm'=>355.6],
        ];
        if (isset($standard[$size])) return $standard[$size];
        foreach ($this->settings->customPageSizeList() as $custom) {
            if ($custom['id'] === $size) return ['widthMm'=>(float)$custom['widthMm'],'heightMm'=>(float)$custom['heightMm']];
        }

        return $standard['A4'];
    }
}
