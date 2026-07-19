<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

use InvalidArgumentException;

final readonly class VariableIdentity
{
    public function __construct(
        public VariableSource $source,
        public VariableType $type,
        public VariablePath $path,
        public ?string $entityType = null,
    ) {
        $length = count($path->segments());
        $entityIdentity = $source === VariableSource::Entity &&
            in_array($type, [VariableType::Direct, VariableType::Related, VariableType::Collection], true);
        $systemIdentity = $source === VariableSource::System && $type === VariableType::System;
        $spreadsheetIdentity = $source === VariableSource::Spreadsheet &&
            $type === VariableType::Spreadsheet;

        if (!$entityIdentity && !$systemIdentity && !$spreadsheetIdentity) {
            throw new InvalidArgumentException('A variable source and type are incompatible.');
        }

        if ($entityIdentity) {
            if ($entityType === null || preg_match('/\A[A-Za-z][A-Za-z0-9]{0,99}\z/D', $entityType) !== 1) {
                throw new InvalidArgumentException('An entity variable requires a safe root entity type.');
            }

            if (($type === VariableType::Direct && $length !== 1) ||
                ($type === VariableType::Related && $length < 2)) {
                throw new InvalidArgumentException('An entity variable type is incompatible with its path.');
            }
        } elseif ($entityType !== null || $length !== 1) {
            throw new InvalidArgumentException('A non-entity variable has incompatible identity properties.');
        }
    }

    public function usage(): VariableUsage
    {
        return $this->type === VariableType::Collection ?
            VariableUsage::Collection : VariableUsage::Scalar;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $value = [
            'source' => $this->source->value,
            'type' => $this->type->value,
        ];

        if ($this->entityType !== null) {
            $value['entityType'] = $this->entityType;
        }

        $value['path'] = $this->path->segments();

        return $value;
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        $source = is_string($value['source'] ?? null) ? VariableSource::tryFrom($value['source']) : null;
        $type = is_string($value['type'] ?? null) ? VariableType::tryFrom($value['type']) : null;
        $entity = $value['entityType'] ?? null;
        $allowed = $source === VariableSource::Entity ?
            ['source', 'type', 'entityType', 'path'] : ['source', 'type', 'path'];

        if ($source === null || $type === null ||
            array_diff(array_keys($value), $allowed) !== [] ||
            array_diff($allowed, array_keys($value)) !== [] ||
            !is_array($value['path'] ?? null) || !array_is_list($value['path']) ||
            ($entity !== null && !is_string($entity))) {
            throw new InvalidArgumentException('A variable identity has an invalid canonical structure.');
        }

        /** @var list<string> $path */
        $path = $value['path'];

        return new self($source, $type, new VariablePath($path), $entity);
    }
}
