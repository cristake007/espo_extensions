<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Html;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedNode;

final readonly class TypedStyleMapper
{
    /** @return array<string, string> */
    public function node(ResolvedNode $node): array
    {
        $css = [];
        $style = $node->style;
        $this->color($css, 'color', $style['color'] ?? null);
        $this->color($css, 'background-color', $style['backgroundColor'] ?? null);
        $this->fontFamily($css, $style['fontFamily'] ?? null);
        $this->measurement($css, 'font-size', $style['fontSize'] ?? null, ['pt']);
        $this->measurement($css, 'letter-spacing', $style['letterSpacing'] ?? null, ['pt'], -20.0, 100.0);
        $this->measurement($css, 'width', $style['width'] ?? null, ['mm', 'percent']);
        $this->measurement($css, 'height', $style['height'] ?? null, ['mm', 'percent']);
        $this->number($css, 'line-height', $style['lineHeight'] ?? null, 0.5, 5.0);
        $this->number($css, 'opacity', $style['opacity'] ?? null, 0.0, 1.0);
        $this->enum($css, 'font-weight', $style['fontWeight'] ?? null,
            ['normal','bold','100','200','300','400','500','600','700','800','900']);
        $this->enum($css, 'font-style', $style['fontStyle'] ?? null, ['normal','italic']);
        $this->enum($css, 'text-decoration', $style['textDecoration'] ?? null, ['none','underline']);
        $this->enum($css, 'text-transform', $style['textTransform'] ?? null,
            ['none','uppercase','lowercase','capitalize']);
        $this->mappedEnum($css, 'text-align', $style['horizontalAlignment'] ?? null,
            ['start'=>'left','center'=>'center','end'=>'right']);
        $this->mappedEnum($css, 'vertical-align', $style['verticalAlignment'] ?? null,
            ['start'=>'top','center'=>'middle','end'=>'bottom']);
        $this->box($css, 'margin', $node->attributes['margin'] ?? $style['margin'] ?? null);
        $this->box($css, 'padding', $node->attributes['padding'] ?? $style['padding'] ?? null);
        $this->measurement($css, 'min-height', $node->attributes['minHeight'] ?? null, ['mm']);
        $this->border($css, $style['border'] ?? null);

        if (($node->attributes['keepTogether'] ?? false) === true) $css['page-break-inside'] = 'avoid';
        if (($node->attributes['keepWithNext'] ?? false) === true) $css['page-break-after'] = 'avoid';
        if (($node->attributes['startNewPage'] ?? false) === true) $css['page-break-before'] = 'always';
        if ($node->type === 'page-break') $css['page-break-after'] = 'always';
        if ($node->type === 'spacer') {
            $this->measurement($css, 'height', $node->attributes['height'] ?? null, ['mm'], 0.1, 500.0);
        }
        if ($node->type === 'divider') {
            $this->divider($css, $node->attributes);
        }

        ksort($css, SORT_STRING);

        return $css;
    }

    /** @param array<string, string> $css */
    private function color(array &$css, string $property, mixed $value): void
    {
        if (is_string($value) && preg_match('/\A#[0-9A-Fa-f]{6}\z/D', $value) === 1) $css[$property] = strtolower($value);
    }

    /** @param array<string, string> $css */
    private function fontFamily(array &$css, mixed $value): void
    {
        if (is_string($value) && preg_match('/\A[A-Za-z][A-Za-z0-9 ._-]{0,99}\z/D', $value) === 1) {
            $css['font-family'] = "'" . str_replace("'", '', $value) . "'";
        }
    }

    /** @param array<string, string> $css @param list<string> $units */
    private function measurement(
        array &$css,
        string $property,
        mixed $value,
        array $units,
        float $minimum = 0.0,
        float $maximum = 2000.0,
    ): void {
        if (!is_array($value) || !is_int($value['value'] ?? null) && !is_float($value['value'] ?? null) ||
            !in_array($value['unit'] ?? null, $units, true)) return;
        $number = (float) $value['value'];
        if (!is_finite($number) || $number < $minimum || $number > $maximum) return;
        $unit = $value['unit'] === 'percent' ? '%' : $value['unit'];
        $css[$property] = $this->decimal($number) . $unit;
    }

    /** @param array<string, string> $css */
    private function number(array &$css, string $property, mixed $value, float $minimum, float $maximum): void
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) ||
            $value < $minimum || $value > $maximum) return;
        $css[$property] = $this->decimal((float) $value);
    }

    /** @param array<string, string> $css @param list<string> $values */
    private function enum(array &$css, string $property, mixed $value, array $values): void
    {
        if (is_string($value) && in_array($value, $values, true)) $css[$property] = $value;
    }

    /** @param array<string, string> $css @param array<string, string> $values */
    private function mappedEnum(array &$css, string $property, mixed $value, array $values): void
    {
        if (is_string($value) && isset($values[$value])) $css[$property] = $values[$value];
    }

    /** @param array<string, string> $css */
    private function box(array &$css, string $property, mixed $value): void
    {
        if (!is_array($value)) return;
        $parts = [];
        foreach (['top','right','bottom','left'] as $edge) {
            $measurement = $value[$edge] ?? null;
            if (!is_array($measurement) || $measurement['unit'] !== 'mm' ||
                (!is_int($measurement['value']) && !is_float($measurement['value'])) ||
                !is_finite((float) $measurement['value']) || $measurement['value'] < 0 || $measurement['value'] > 2000) return;
            $parts[] = $this->decimal((float) $measurement['value']) . 'mm';
        }
        $css[$property] = implode(' ', $parts);
    }

    /** @param array<string, string> $css */
    private function border(array &$css, mixed $value): void
    {
        if (!is_array($value) || !is_array($value['width'] ?? null) ||
            ($value['width']['unit'] ?? null) !== 'pt' ||
            !in_array($value['style'] ?? null, ['none','solid','dashed','dotted','double'], true) ||
            preg_match('/\A#[0-9A-Fa-f]{6}\z/D', (string) ($value['color'] ?? '')) !== 1) return;
        $width = $value['width']['value'] ?? null;
        if ((!is_int($width) && !is_float($width)) || $width < 0 || $width > 512) return;
        $css['border'] = $this->decimal((float) $width) . 'pt ' . $value['style'] . ' ' . strtolower($value['color']);
    }

    /** @param array<string, string> $css @param array<string, mixed> $attributes */
    private function divider(array &$css, array $attributes): void
    {
        $orientation = $attributes['orientation'] ?? 'horizontal';
        $style = in_array($attributes['lineStyle'] ?? null, ['solid','dashed','dotted','double'], true) ? $attributes['lineStyle'] : 'solid';
        $color = preg_match('/\A#[0-9A-Fa-f]{6}\z/D', (string) ($attributes['color'] ?? '')) === 1 ? strtolower($attributes['color']) : '#666666';
        $thickness = $attributes['thickness']['value'] ?? 0.5;
        if (!is_int($thickness) && !is_float($thickness) || $thickness < 0.1 || $thickness > 20) $thickness = 0.5;
        $css['border'] = '0';
        $css[$orientation === 'vertical' ? 'border-left' : 'border-top'] =
            $this->decimal((float) $thickness) . "mm $style $color";
        $this->measurement($css, $orientation === 'vertical' ? 'height' : 'width', $attributes['length'] ?? null, ['mm'], 1.0, 2000.0);
    }

    private function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
