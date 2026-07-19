<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use InvalidArgumentException;

final readonly class DocumentValue
{
    /** @param array<string, scalar>|null $provenance */
    public function __construct(
        public VariableIdentity $identity,
        public VariableValue $value,
        public ?array $provenance = null,
    ) {
        if ($provenance !== null) {
            foreach ($provenance as $key => $item) {
                if (!is_string($key) || (!is_string($item) && !is_int($item) &&
                    !is_float($item) && !is_bool($item))) {
                    throw new InvalidArgumentException('Document value provenance must contain safe scalar data.');
                }
            }
        }
    }

    public function key(): string
    {
        return json_encode($this->identity->toArray(), JSON_THROW_ON_ERROR);
    }
}
