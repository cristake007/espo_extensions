<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Error;

use InvalidArgumentException;

final readonly class PublicWarning
{
    public function __construct(
        public WarningCode $code,
        public ?string $elementId = null,
    ) {
        if (
            $elementId !== null &&
            preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $elementId) !== 1
        ) {
            throw new InvalidArgumentException('A public warning identifier is invalid.');
        }
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        $data = [
            'code' => $this->code->value,
            'messageKey' => $this->code->messageKey(),
        ];

        if ($this->elementId !== null) {
            $data['elementId'] = $this->elementId;
        }

        return $data;
    }
}
