<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

final readonly class RichTextSanitizer
{
    private const MARK_ORDER = ['bold', 'italic', 'underline'];

    /** @param array<string, mixed> $layout @return array<string, mixed> */
    public function normalizeLayout(array $layout): array
    {
        foreach (['header', 'sections', 'footer'] as $region) {
            if (!is_array($layout[$region] ?? null) || !array_is_list($layout[$region])) {
                continue;
            }

            foreach ($layout[$region] as $index => $node) {
                $layout[$region][$index] = $this->normalizeNode($node);
            }
        }

        return $layout;
    }

    private function normalizeNode(mixed $node): mixed
    {
        if (!is_array($node) || (array_is_list($node) && $node !== [])) {
            return $node;
        }

        if (($node['type'] ?? null) === 'static-text' && is_string($node['text'] ?? null)) {
            $node['text'] = $this->normalizeText($node['text']);
        }

        if (in_array($node['type'] ?? null, ['heading', 'static-text', 'paragraph'], true) &&
            is_array($node['content'] ?? null) && array_is_list($node['content'])) {
            foreach ($node['content'] as $index => $item) {
                $node['content'][$index] = $this->normalizeInline($item);
            }
        }

        if (($node['type'] ?? null) === 'variable') {
            $node = $this->normalizeVariable($node);
        }

        if (is_array($node['children'] ?? null) && array_is_list($node['children'])) {
            foreach ($node['children'] as $index => $child) {
                $node['children'][$index] = $this->normalizeNode($child);
            }
        }

        return $node;
    }

    private function normalizeInline(mixed $item): mixed
    {
        if (!is_array($item) || (array_is_list($item) && $item !== [])) {
            return $item;
        }

        if (($item['type'] ?? null) === 'text' && is_string($item['text'] ?? null)) {
            $item['text'] = $this->normalizeText($item['text']);

            if (is_array($item['marks'] ?? null) &&
                array_is_list($item['marks']) &&
                array_diff($item['marks'], self::MARK_ORDER) === []) {
                $item['marks'] = array_values(array_filter(
                    self::MARK_ORDER,
                    static fn (string $mark): bool => in_array($mark, $item['marks'], true),
                ));
            }
        }

        if (($item['type'] ?? null) === 'list' &&
            is_array($item['items'] ?? null) && array_is_list($item['items'])) {
            foreach ($item['items'] as $listIndex => $listItem) {
                if (!is_array($listItem) || !array_is_list($listItem)) {
                    continue;
                }
                foreach ($listItem as $inlineIndex => $inline) {
                    $item['items'][$listIndex][$inlineIndex] = $this->normalizeInline($inline);
                }
            }
        }

        if (($item['type'] ?? null) === 'variable') {
            $item = $this->normalizeVariable($item);
        }

        return $item;
    }

    /** @param array<string, mixed> $variable @return array<string, mixed> */
    private function normalizeVariable(array $variable): array
    {
        if (is_string($variable['label'] ?? null)) {
            $variable['label'] = $this->normalizeText($variable['label']);
        }

        $format = $variable['presentation']['format'] ?? null;

        if (is_array($format) && !array_is_list($format)) {
            foreach (['trueLabel', 'falseLabel', 'separator', 'prefix', 'suffix', 'fallback'] as $key) {
                if (is_string($format[$key] ?? null)) {
                    $variable['presentation']['format'][$key] = $this->normalizeText($format[$key]);
                }
            }
        }

        return $variable;
    }

    private function normalizeText(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}
