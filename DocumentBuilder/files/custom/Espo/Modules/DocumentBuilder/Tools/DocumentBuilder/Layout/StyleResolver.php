<?php
declare(strict_types=1);
namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

final readonly class StyleResolver
{
    /** @param list<string> $allowedFonts */
    public function __construct(private array $allowedFonts, private string $fallbackFont = 'DejaVu Sans') {}

    /** @param array<string,mixed> $defaults @param array<string,mixed> ...$layers @return array<string,mixed> */
    public function resolve(array $defaults, array ...$layers): array
    {
        $resolved = [
            'fontFamily' => in_array($defaults['fontFamily'] ?? null, $this->allowedFonts, true) ? $defaults['fontFamily'] : $this->fallbackFont,
            'fontSize' => $defaults['fontSize'] ?? ['value' => 10, 'unit' => 'pt'],
            'color' => $defaults['color'] ?? '#222222',
            'lineHeight' => $defaults['lineHeight'] ?? 1.2,
        ];
        foreach ($layers as $layer) {
            foreach ($layer as $key => $value) $resolved[$key] = $value;
        }
        if (!in_array($resolved['fontFamily'] ?? null, $this->allowedFonts, true)) {
            $resolved['fontFamily'] = $this->fallbackFont;
        }
        ksort($resolved, SORT_STRING);
        return $resolved;
    }
}
