<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Source;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Capability;
use InvalidArgumentException;

final readonly class SpreadsheetSourceDescriptor implements SourceDescriptor
{
    public function __construct(private SpreadsheetFormat $format, private ?string $worksheet = null)
    {
        if ($format === SpreadsheetFormat::Csv && $worksheet !== null) {
            throw new InvalidArgumentException('A CSV source cannot select a worksheet.');
        }

        if ($worksheet !== null && (trim($worksheet) === '' || mb_strlen($worksheet) > 100)) {
            throw new InvalidArgumentException('A spreadsheet worksheet name is invalid.');
        }
    }

    public function type(): SourceType
    {
        return SourceType::Spreadsheet;
    }

    public function requiredCapability(): Capability
    {
        return Capability::SpreadsheetSource;
    }

    /** @return array{type: string, format: string, worksheet?: string} */
    public function toArray(): array
    {
        $value = ['type' => $this->type()->value, 'format' => $this->format->value];

        if ($this->worksheet !== null) {
            $value['worksheet'] = $this->worksheet;
        }

        return $value;
    }
}
