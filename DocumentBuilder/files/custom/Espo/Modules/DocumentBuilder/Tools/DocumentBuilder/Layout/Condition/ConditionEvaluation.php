<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition;

final readonly class ConditionEvaluation
{
    public function __construct(
        public bool $visible,
        public ConditionTarget $target,
    ) {}
}
