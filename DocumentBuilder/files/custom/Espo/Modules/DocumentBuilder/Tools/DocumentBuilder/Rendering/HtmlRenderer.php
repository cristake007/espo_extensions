<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Html\ElementRendererRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Html\TypedStyleMapper;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedDocument;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedInline;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedNode;

final readonly class HtmlRenderer
{
    public function __construct(
        private ElementRendererRegistry $elements,
        private TypedStyleMapper $styles,
    ) {}

    public function render(ResolvedDocument $document): string
    {
        $body = implode('', array_map(fn (ResolvedNode $node): string => $this->node($node), $document->sections));
        $header = $this->chromeRegion('header', $document->header, $document->chrome['header'] ?? null);
        $footer = $this->chromeRegion('footer', $document->footer, $document->chrome['footer'] ?? null);
        $language = $this->language($document->defaults['locale'] ?? 'en_US');
        $pageCss = $this->pageCss($document->page);
        $baseCss = 'html,body{margin:0;padding:0;}body{font-family:DejaVu Sans,sans-serif;}' .
            '.db-page-break{height:0;}.db-divider{display:block;}.db-spacer{display:block;}' .
            '.db-page-number::after{content:counter(page);}';

        return '<!doctype html><html lang="' . $language . '"><head><meta charset="UTF-8">' .
            '<style>' . $pageCss . $baseCss . '</style></head><body>' . $header . $footer . $body .
            '</body></html>';
    }

    /** @param list<ResolvedNode> $nodes */
    private function chromeRegion(string $region, array $nodes, mixed $settings): string
    {
        if ($nodes === [] || !is_array($settings)) return '';
        $height = $settings['height']['value'] ?? 0;
        if (!is_int($height) && !is_float($height) || $height <= 0 || $height > 100) return '';
        $position = $region === 'header' ? 'top:-' . $this->decimal((float) $height) . 'mm;' :
            'bottom:-' . $this->decimal((float) $height) . 'mm;';
        $attributes = ' class="db-page-' . $region . '" style="position:fixed;' . $position .
            'left:0;right:0;height:' . $this->decimal((float) $height) . 'mm;overflow:hidden;"' .
            ' data-show-first-page="' . (($settings['showOnFirstPage'] ?? true) ? '1' : '0') . '"' .
            ' data-disable-on-full-page="' . (($settings['disableOnFullPage'] ?? true) ? '1' : '0') . '"';
        $content = implode('', array_map(fn (ResolvedNode $node): string => $this->node($node), $nodes));

        return "<$region$attributes>$content</$region>";
    }

    private function node(ResolvedNode $node): string
    {
        $definition = $this->elements->definition($node);
        $attributes = ' id="' . $this->escape($node->id) . '" class="' . $definition->className . '"';
        $style = $this->style($this->styles->node($node));
        if ($style !== '') $attributes .= ' style="' . $style . '"';
        if (in_array($node->type, ['spacer', 'page-break'], true)) $attributes .= ' aria-hidden="true"';
        if ($definition->void) return '<' . $definition->tag . $attributes . '>';
        $content = in_array($node->type, ['flow-section', 'flow-container'], true) ?
            implode('', array_map(fn (ResolvedNode $child): string => $this->node($child), $node->children)) :
            implode('', array_map(fn (ResolvedInline $item): string => $this->inline($item), $node->inline));

        return '<' . $definition->tag . $attributes . '>' . $content . '</' . $definition->tag . '>';
    }

    private function inline(ResolvedInline $item): string
    {
        if ($item->type === 'break') return '<br>';
        if ($item->type === 'page-number') return '<span class="db-page-number" aria-label="Page number"></span>';
        if ($item->type === 'list') {
            $tag = $item->listStyle === 'numbered' ? 'ol' : 'ul';
            $items = implode('', array_map(
                fn (array $listItem): string => '<li>' . implode('', array_map(
                    fn (ResolvedInline $inline): string => $this->inline($inline),
                    $listItem,
                )) . '</li>',
                $item->items,
            ));

            return "<$tag>$items</$tag>";
        }
        $text = $this->escape($item->text);
        foreach (['bold'=>'strong', 'italic'=>'em', 'underline'=>'u'] as $mark => $tag) {
            if (in_array($mark, $item->marks, true)) $text = "<$tag>$text</$tag>";
        }
        if ($item->color !== null && preg_match('/\A#[0-9A-Fa-f]{6}\z/D', $item->color) === 1) {
            $text = '<span style="color:' . strtolower($item->color) . '">' . $text . '</span>';
        }

        return $text;
    }

    /** @param array<string, string> $values */
    private function style(array $values): string
    {
        return implode('', array_map(
            static fn (string $property, string $value): string => "$property:$value;",
            array_keys($values),
            array_values($values),
        ));
    }

    /** @param array<string, mixed> $page */
    private function pageCss(array $page): string
    {
        $size = in_array($page['size'] ?? null, ['A4','Letter','Legal'], true) ? $page['size'] : null;
        $orientation = ($page['orientation'] ?? null) === 'landscape' ? 'landscape' : 'portrait';
        $margins = $page['margins'] ?? [];
        $parts = [];
        foreach (['top','right','bottom','left'] as $edge) {
            $value = $margins[$edge]['value'] ?? 0;
            $parts[] = (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0 && $value <= 2000 ?
                rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') . 'mm' : '0mm';
        }

        return '@page{' . ($size !== null ? 'size:' . $size . ' ' . $orientation . ';' : '') .
            'margin:' . implode(' ', $parts) . ';}';
    }

    private function language(mixed $locale): string
    {
        return is_string($locale) && preg_match('/\A[a-z]{2}_[A-Z]{2}\z/D', $locale) === 1 ?
            str_replace('_', '-', $locale) : 'en-US';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
