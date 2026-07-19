<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Source\NoSourceDescriptor;
use InvalidArgumentException;

final class LayoutDefaults
{
    /** @return array<string, mixed> */
    public static function create(
        string $fontFamily = 'DejaVu Sans',
        string $locale = 'en_US',
        string $pageSize = 'A4',
    ): array {
        if (!in_array($pageSize, ['A4', 'Letter', 'Legal'], true)) {
            throw new InvalidArgumentException('The default page size is not canonical.');
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9 ._-]{0,99}$/D', $fontFamily) !== 1) {
            throw new InvalidArgumentException('The default font identifier is invalid.');
        }

        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/D', $locale) !== 1) {
            throw new InvalidArgumentException('The default locale is invalid.');
        }

        $margin = static fn (): array => (new Measurement(15, Unit::Millimetre))->toArray();

        return [
            'schemaVersion' => SchemaVersion::current()->value,
            'capabilities' => [],
            'document' => [
                'page' => [
                    'size' => $pageSize,
                    'orientation' => 'portrait',
                    'margins' => [
                        'top' => $margin(),
                        'right' => $margin(),
                        'bottom' => $margin(),
                        'left' => $margin(),
                    ],
                ],
                'defaults' => [
                    'fontFamily' => $fontFamily,
                    'fontSize' => (new Measurement(10, Unit::Point))->toArray(),
                    'color' => '#222222',
                    'lineHeight' => 1.2,
                    'locale' => $locale,
                    'timezone' => 'UTC',
                ],
                'titlePattern' => 'Document',
                'filenamePattern' => 'document.pdf',
            ],
            'dataSource' => (new NoSourceDescriptor())->toArray(),
            'header' => [],
            'sections' => [],
            'footer' => [],
        ];
    }
}
