<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

final readonly class SystemVariableResult
{
    public function __construct(
        public SystemVariableKind $kind,
        public ?VariableValue $value,
        public ?string $placeholder,
    ) {}

    public static function value(VariableValue $value): self
    {
        return new self(SystemVariableKind::Value, $value, null);
    }

    public static function placeholder(string $placeholder): self
    {
        return new self(SystemVariableKind::RendererPlaceholder, null, $placeholder);
    }
}
