<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;

final readonly class LayoutNormalizer
{
    private RichTextSanitizer $richTextSanitizer;

    public function __construct(private Settings $settings, ?RichTextSanitizer $richTextSanitizer = null)
    {
        $this->richTextSanitizer = $richTextSanitizer ?? new RichTextSanitizer();
    }

    /**
     * @param array<string, mixed> $layout
     * @return array<string, mixed>
     */
    public function normalize(array $layout): array
    {
        $defaults = LayoutDefaults::create(
            $this->settings->defaultFont(),
            $this->settings->defaultLocale(),
            $this->settings->defaultPageSize(),
        );

        return $this->richTextSanitizer->normalizeLayout(
            $this->mergeKnownObjects($layout, $defaults),
        );
    }

    /**
     * Defaults only missing keys. Unknown keys and invalid values remain available to the validator.
     *
     * @param array<string, mixed> $value
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private function mergeKnownObjects(array $value, array $defaults): array
    {
        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $value)) {
                $value[$key] = $default;

                continue;
            }

            if ($value[$key] instanceof EmptyJsonObject && is_array($default) && !array_is_list($default)) {
                $value[$key] = $default;

                continue;
            }

            if (
                is_array($default) &&
                !array_is_list($default) &&
                is_array($value[$key]) &&
                !array_is_list($value[$key])
            ) {
                $value[$key] = $this->mergeKnownObjects($value[$key], $default);
            }
        }

        $ordered = [];

        foreach (array_keys($defaults) as $key) {
            $ordered[$key] = $value[$key];
            unset($value[$key]);
        }

        ksort($value, SORT_STRING);

        return array_replace($ordered, $value);
    }
}
