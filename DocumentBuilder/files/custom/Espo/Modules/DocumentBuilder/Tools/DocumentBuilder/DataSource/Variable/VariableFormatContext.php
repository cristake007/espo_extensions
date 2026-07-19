<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

use DateTimeZone;
use InvalidArgumentException;

final readonly class VariableFormatContext
{
    /** @param array<string, string> $enumLabels */
    public function __construct(
        public string $locale,
        public string $timezone,
        public array $enumLabels = [],
    ) {
        if (preg_match('/\A[a-z]{2}_[A-Z]{2}\z/D', $locale) !== 1) {
            throw new InvalidArgumentException('A formatting locale is invalid.');
        }

        try {
            new DateTimeZone($timezone);
        } catch (\Exception) {
            throw new InvalidArgumentException('A formatting timezone is invalid.');
        }

        foreach ($enumLabels as $key => $label) {
            if (!is_string($key) || !is_string($label) || mb_strlen($label) > 200 ||
                preg_match('/[\x00-\x1F\x7F]/', $label) === 1) {
                throw new InvalidArgumentException('An enum formatting label is invalid.');
            }
        }
    }
}
