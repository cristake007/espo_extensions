<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\LayoutTooLarge;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Error\MalformedLayout;
use JsonException;
use stdClass;

final readonly class LayoutParser
{
    public function __construct(private Settings $settings)
    {}

    /** @return array<string, mixed> */
    public function parse(string $json): array
    {
        if (strlen($json) > $this->settings->maxLayoutBytes()) {
            throw new LayoutTooLarge();
        }

        try {
            $value = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MalformedLayout();
        }

        if (!$value instanceof stdClass) {
            throw new MalformedLayout();
        }

        $layout = [];

        foreach (get_object_vars($value) as $key => $item) {
            $layout[$key] = $this->convert($item);
        }

        return $layout;
    }

    private function convert(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);

            if ($properties === []) {
                return new EmptyJsonObject();
            }

            $converted = [];

            foreach ($properties as $key => $item) {
                $converted[$key] = $this->convert($item);
            }

            return $converted;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->convert($item), $value);
        }

        return $value;
    }
}
