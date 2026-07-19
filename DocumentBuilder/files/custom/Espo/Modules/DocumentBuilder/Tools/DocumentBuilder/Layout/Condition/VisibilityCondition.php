<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition;

use InvalidArgumentException;

final readonly class VisibilityCondition
{
    private const MAX_RULES = 25;

    /** @param list<ConditionRule> $rules */
    public function __construct(
        public ConditionTarget $target,
        public ConditionMode $mode,
        public array $rules,
    ) {
        if ($rules === [] || !array_is_list($rules) || count($rules) > self::MAX_RULES) {
            throw new InvalidArgumentException('A condition group must contain between one and 25 rules.');
        }

        foreach ($rules as $rule) {
            if (!$rule instanceof ConditionRule) {
                throw new InvalidArgumentException('A condition group contains an invalid rule.');
            }
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        $keys = ['target', 'mode', 'rules'];

        if (array_is_list($value) || array_diff(array_keys($value), $keys) !== [] ||
            array_diff($keys, array_keys($value)) !== [] ||
            !is_array($value['rules']) || !array_is_list($value['rules'])) {
            throw new InvalidArgumentException('A visibility condition has an invalid structure.');
        }

        $target = is_string($value['target']) ? ConditionTarget::tryFrom($value['target']) : null;
        $mode = is_string($value['mode']) ? ConditionMode::tryFrom($value['mode']) : null;

        if ($target === null || $mode === null) {
            throw new InvalidArgumentException('A visibility condition target or mode is unsupported.');
        }

        $rules = array_map(static fn (mixed $rule): ConditionRule => is_array($rule) ?
            ConditionRule::fromArray($rule) :
            throw new InvalidArgumentException('A condition rule must be an object.'), $value['rules']);

        return new self($target, $mode, $rules);
    }
}
