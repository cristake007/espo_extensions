<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security;

use InvalidArgumentException;

final readonly class FieldReadRequirement
{
    public function __construct(
        public string $scope,
        public string $field,
    ) {
        if (!self::isIdentifier($scope) || !self::isIdentifier($field)) {
            throw new InvalidArgumentException('An ACL field requirement contains an invalid identifier.');
        }
    }

    private static function isIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_]{0,99}$/D', $value) === 1;
    }
}
