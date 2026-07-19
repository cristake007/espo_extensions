<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

use InvalidArgumentException;

final readonly class VariablePresentation
{
    public function __construct(
        public VariableFormat $format = new VariableFormat(),
        public MissingValuePolicy $missing = MissingValuePolicy::Empty,
    ) {
        if ($missing === MissingValuePolicy::Fallback && $format->fallback === null) {
            throw new InvalidArgumentException('The fallback policy requires fallback text.');
        }
    }

    /** @return array{format: array<string, mixed>, missing: string} */
    public function toArray(): array
    {
        return ['format' => $this->format->toArray(), 'missing' => $this->missing->value];
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        if (count($value) !== 2 ||
            array_diff(array_keys($value), ['format', 'missing']) !== [] ||
            array_diff(['format', 'missing'], array_keys($value)) !== [] ||
            !is_array($value['format']) || array_is_list($value['format']) ||
            !is_string($value['missing']) ||
            ($missing = MissingValuePolicy::tryFrom($value['missing'])) === null) {
            throw new InvalidArgumentException('A variable presentation has an invalid canonical structure.');
        }

        return new self(VariableFormat::fromArray($value['format']), $missing);
    }
}
