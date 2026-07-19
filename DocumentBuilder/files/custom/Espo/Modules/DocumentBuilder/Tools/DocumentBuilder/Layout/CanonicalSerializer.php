<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

final class CanonicalSerializer
{
    /** @param array<string, mixed> $layout */
    public function serialize(array $layout): string
    {
        return json_encode(
            $this->sortObjects($layout),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /** @param array<string, mixed> $layout */
    public function hashInput(array $layout): string
    {
        return $this->serialize($layout);
    }

    private function sortObjects(mixed $value): mixed
    {
        if (
            is_float($value) &&
            is_finite($value) &&
            floor($value) === $value &&
            $value >= PHP_INT_MIN &&
            $value <= PHP_INT_MAX
        ) {
            return (int) $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortObjects($item), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortObjects($item);
        }

        return $value;
    }
}
